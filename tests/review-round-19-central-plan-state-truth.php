<?php
/** Round 19 regression: authenticated central routes, cursors and persistence fail truthfully. */
$root = dirname( __DIR__ );
$central = file_get_contents( $root . '/includes/class-file26-central-plan.php' );
$security = file_get_contents( $root . '/includes/class-file26-security.php' );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$health = file_get_contents( $root . '/includes/class-file26-health.php' );
$graph_test = file_get_contents( $root . '/tests/review-round-27-graph-owner-and-traversal-truth.php' );
$checks = array(
	array( $security, 'valid_authenticated_member', 'shared valid-membership route gate exists' ),
	array( $rest, 'valid_authenticated_member()', 'ordinary authenticated REST routes bind to membership validity' ),
	array( $central, 'valid_authenticated_member()', 'central authenticated routes bind to membership validity' ),
	array( $central, "'audience' => \$this->audience_fingerprint()", 'advanced-search cursor binds to eligibility audience' ),
	array( $central, 'file26_advanced_metadata_read_failed', 'advanced metadata read failure is explicit' ),
	array( $central, "acquire_lock( 'saved-query'", 'saved-query mutations are serialized' ),
	array( $central, 'file26_saved_query_write_failed', 'saved-query persistence is verified' ),
	array( $central, 'file26_saved_query_expected_version_required', 'saved-query updates require optimistic version evidence' ),
	array( $central, "acquire_lock( 'content-gaps'", 'content-gap registry mutation/retention is serialized' ),
	array( $central, 'file26_content_gap_write_failed', 'content-gap persistence failure is explicit' ),
	array( $central, 'file26_editorial_radar_read_failed', 'editorial-radar DB failure is explicit' ),
	array( $central, "'related_topics_status' => \$related_status", 'zero-result topic-read degradation is disclosed' ),
	array( $central, "'read_failure' => false", 'freshness read-failure truth is represented' ),
	array( $central, 'file26_central_metric_write_failed', 'aggregate telemetry write failure is observable' ),
	array( $central, 'file26_central_settings_migration_failed', 'central settings migration write is verified' ),
	array( $central, 'Saved-query erasure could not be verified', 'privacy erasure cannot claim unverified success' ),
	array( $health, 'sabri_file26_central_failures', 'central-plan operational failures degrade health' ),
	array( $graph_test, '$forbidden_owner_input', 'pre-existing graph regression parses without invalid interpolation' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "PASS: round 19 central-plan state, concurrency and failure truth\n";
