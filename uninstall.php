<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * File 26 uninstall law:
 * - runtime jobs and plugin-specific institutional roles are always removed;
 * - indexed/user/audit data is retained by default for safe reinstall and rollback;
 * - data tables/options/account metadata are purged only after an explicit destructive-uninstall opt-in.
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
	if ( ! $role ) { continue; }
	foreach ( $capabilities as $capability ) { $role->remove_cap( $capability ); }
}
foreach ( array( 'sabri_search_operator', 'sabri_taxonomy_curator', 'sabri_ranking_approver', 'sabri_search_auditor' ) as $role_name ) { remove_role( $role_name ); }
delete_option( 'sabri_file26_role_model_version' );

$destructive_raw = get_option( 'sabri_file26_destructive_uninstall', false );
$destructive = true === $destructive_raw || 1 === $destructive_raw || '1' === $destructive_raw;
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
/**
 * FR-005 generations are strictly owned by File 26. Destructive uninstall
 * must not abandon snapshots containing indexed content or graph projections.
 * Validate every name before DROP; no user-controlled table identifiers.
 */
$generation_like = $wpdb->esc_like( $wpdb->prefix . 'f26_' ) . '%';
$generation_tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $generation_like ) );
$generation_regex = '/^' . preg_quote( $wpdb->prefix, '/' ) . 'f26_[spf][dnec]_[0-9a-f]{32}$/D';
foreach ( (array) $generation_tables as $generation_table ) {
	if ( preg_match( $generation_regex, (string) $generation_table ) ) {
		$wpdb->query( "DROP TABLE IF EXISTS `$generation_table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
$generation_option_like = $wpdb->esc_like( 'sabri_f26_shadow_' ) . '%';
$generation_options = $wpdb->get_col( $wpdb->prepare(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
	$generation_option_like
) );
foreach ( (array) $generation_options as $generation_option ) {
	if ( preg_match( '/^sabri_f26_shadow_[0-9a-f]{32}$/D', (string) $generation_option ) ) {
		delete_option( $generation_option );
	}
}

$appeals_table = $wpdb->prefix . 'f26_ranking_appeals';
$wpdb->query( "DROP TABLE IF EXISTS `$appeals_table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

foreach ( array(
	'sabri_file26_saved_queries_v1',
	'sabri_file26_research_trails_v1',
	'sabri_file26_saved_search_alerts_v1',
	'sabri_file26_search_history_sync_v1',
	'sabri_file26_search_history_sync_opt_in_v1',
	'sabri_file26_discovery_controls_v1',
) as $meta_key ) {
	delete_metadata( 'user', 0, $meta_key, '', true );
}

foreach ( array(
	'sabri_file26_settings',
	'sabri_file26_schema_version',
	'sabri_file26_doctor_appeals_schema',
	'sabri_file26_explicit_content_gaps_v1',
	'sabri_file26_central_plan_migration',
	'sabri_file26_destructive_uninstall',
) as $option_key ) {
	delete_option( $option_key );
}

$like_transient = $wpdb->esc_like( '_transient_file26_rb_' ) . '%';
$like_timeout = $wpdb->esc_like( '_transient_timeout_file26_rb_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like_transient, $like_timeout ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
