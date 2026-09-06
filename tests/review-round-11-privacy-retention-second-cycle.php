<?php
/** Second-cycle Round 11 regression: export, erasure, retention and destructive purge remain truthful. */
$root = dirname( __DIR__ );
$privacy = file_get_contents( $root . '/includes/class-file26-privacy.php' );
$truth = file_get_contents( $root . '/includes/class-file26-privacy-truth.php' );
$operation = file_get_contents( $root . '/includes/class-file26-operation-truth.php' );
$bootstrap = file_get_contents( $root . '/file-26-search-discovery.php' );
$uninstall = file_get_contents( $root . '/uninstall.php' );

$checks = array(
	array( $privacy, 'private function export_read_failure', 'privacy exporter has a retryable DB-read failure state' ),
	array( $privacy, 'null === $feedback && ! empty( $wpdb->last_error )', 'feedback export read failure is checked' ),
	array( $privacy, 'null === $appeals && ! empty( $wpdb->last_error )', 'appeal export read failure is checked' ),
	array( $privacy, 'appeal_count_raw', 'erasure keeps the appeal count uncast until read truth is known' ),
	array( $privacy, 'no success is reported', 'erasure explicitly refuses false completion' ),
	array( $truth, 'override_saved_query_eraser', 'saved-query eraser is replaced by a post-state-verifying eraser' ),
	array( $truth, 'Saved-query erasure could not be verified', 'saved-query deletion failure remains retryable' ),
	array( $truth, 'user_id>%d', 'saved-query retention walks user meta through a bounded cursor' ),
	array( $truth, 'file26_saved_query_retention_write_failed', 'saved-query expiry write failure is explicit' ),
	array( $truth, 'file26_content_gap_retention_write_failed', 'content-gap expiry write failure is explicit' ),
	array( $operation, 'file26_saved_query_retention_failed', 'saved-query GET cannot hide failed expiry persistence' ),
	array( $bootstrap, 'class-file26-privacy-truth.php', 'privacy truth enforcement is loaded' ),
	array( $bootstrap, 'Privacy_Truth::boot()', 'privacy truth enforcement is booted' ),
	array( $uninstall, "delete_metadata( 'user', 0, 'sabri_file26_saved_queries_v1', '', true )", 'destructive uninstall removes saved-query user meta across accounts' ),
	array( $uninstall, 'sabri_file26_explicit_content_gaps_v1', 'destructive uninstall removes explicit content gaps' ),
	array( $uninstall, 'sabri_file26_central_plan_migration', 'destructive uninstall removes central-plan migration state' ),
	array( $uninstall, 'sabri_file26_privacy_retention_cursor', 'destructive uninstall removes privacy-retention cursor state' ),
);

$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 11 privacy-retention regression passed.\n";
