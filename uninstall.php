<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * File 26 uninstall law:
 * - runtime jobs and plugin-specific institutional roles are always removed;
 * - indexed/user/audit data is retained by default for safe reinstall and rollback;
 * - data tables/options are purged only after an explicit destructive-uninstall opt-in.
 */
wp_clear_scheduled_hook( 'sabri_file26_process_queue' );
wp_clear_scheduled_hook( 'sabri_file26_reconcile' );
wp_clear_scheduled_hook( 'sabri_file26_retention' );
wp_clear_scheduled_hook( 'sabri_file26_doctor_ranking' );

$capabilities = array(
	'manage_sabri_search',
	'operate_sabri_search',
	'curate_sabri_taxonomy',
	'approve_sabri_ranking',
	'audit_sabri_search',
);
foreach ( array( 'administrator', 'sabri_search_operator', 'sabri_taxonomy_curator', 'sabri_ranking_approver', 'sabri_search_auditor' ) as $role_name ) {
	$role = get_role( $role_name );
	if ( ! $role ) {
		continue;
	}
	foreach ( $capabilities as $capability ) {
		$role->remove_cap( $capability );
	}
}
foreach ( array( 'sabri_search_operator', 'sabri_taxonomy_curator', 'sabri_ranking_approver', 'sabri_search_auditor' ) as $role_name ) {
	remove_role( $role_name );
}
delete_option( 'sabri_file26_role_model_version' );

$destructive = (bool) get_option( 'sabri_file26_destructive_uninstall', false );
if ( ! $destructive ) {
	return;
}

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

delete_option( 'sabri_file26_settings' );
delete_option( 'sabri_file26_schema_version' );
delete_option( 'sabri_file26_doctor_appeals_schema' );
delete_option( 'sabri_file26_destructive_uninstall' );
