<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * File 26 uninstall law:
 * - runtime jobs and plugin-specific institutional roles are always removed;
 * - indexed/user/audit data is retained by default for safe reinstall and rollback;
 * - all File-26-owned data is purged only after an explicit destructive-uninstall opt-in.
 */
wp_clear_scheduled_hook( 'sabri_file26_process_queue' );
wp_clear_scheduled_hook( 'sabri_file26_reconcile' );
wp_clear_scheduled_hook( 'sabri_file26_retention' );
wp_clear_scheduled_hook( 'sabri_file26_doctor_ranking' );

$capabilities = array(
	'manage_sabri_search', 'operate_sabri_search', 'curate_sabri_taxonomy',
	'approve_sabri_ranking', 'audit_sabri_search',
);
foreach ( array( 'administrator', 'sabri_search_operator', 'sabri_taxonomy_curator', 'sabri_ranking_approver', 'sabri_search_auditor' ) as $role_name ) {
	$role = get_role( $role_name );
	if ( ! $role ) { continue; }
	foreach ( $capabilities as $capability ) { $role->remove_cap( $capability ); }
}
foreach ( array( 'sabri_search_operator', 'sabri_taxonomy_curator', 'sabri_ranking_approver', 'sabri_search_auditor' ) as $role_name ) { remove_role( $role_name ); }
delete_option( 'sabri_file26_role_model_version' );

$destructive = (bool) get_option( 'sabri_file26_destructive_uninstall', false );
if ( ! $destructive ) { return; }

global $wpdb;
$tables = array(
	'connectors', 'documents', 'tombstones', 'terms', 'term_aliases',
	'classifications', 'nodes', 'edges', 'ranking_policies', 'feedback',
	'profiles', 'jobs', 'audit', 'metrics', 'rate_limits',
);
foreach ( $tables as $name ) {
	$table = $wpdb->prefix . 'f26_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS `$table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
$appeals_table = $wpdb->prefix . 'f26_ranking_appeals';
$wpdb->query( "DROP TABLE IF EXISTS `$appeals_table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Account-owned saved searches are File-26 data even though WordPress owns the user table.
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'sabri_file26_saved_queries_v1' ), array( '%s' ) );

$options = array(
	'sabri_file26_settings', 'sabri_file26_schema_version', 'sabri_file26_doctor_appeals_schema',
	'sabri_file26_central_plan_migration', 'sabri_file26_explicit_content_gaps_v1', 'sabri_file26_central_failures',
	'sabri_file26_relevance_evaluations_v1', 'sabri_file26_search_experiments_v1', 'sabri_file26_evaluation_failure',
	'sabri_file26_last_activation_failure', 'sabri_file26_last_audit_failure', 'sabri_file26_last_privacy_failure',
	'sabri_file26_last_retention_failure', 'sabri_file26_last_appeal_retention_failure',
	'sabri_file26_last_connector_boot_failure', 'sabri_file26_last_connector_health_failure',
	'sabri_file26_last_schedule_failure', 'sabri_file26_last_search_metric_failure',
	'sabri_file26_destructive_uninstall',
);
foreach ( $options as $option ) { delete_option( $option ); }

// Remove transient rollback receipts without touching unrelated application transients.
$like = $wpdb->esc_like( '_transient_file26_rb_' ) . '%';
$timeout_like = $wpdb->esc_like( '_transient_timeout_file26_rb_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout_like ) );
