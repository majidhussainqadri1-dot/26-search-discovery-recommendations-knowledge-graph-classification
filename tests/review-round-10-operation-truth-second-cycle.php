<?php
/** Second-cycle Round 10 regression: REST and health operation truth must fail closed. */
$root = dirname( __DIR__ );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$health = file_get_contents( $root . '/includes/class-file26-health.php' );
$truth = file_get_contents( $root . '/includes/class-file26-operation-truth.php' );
$plugin = file_get_contents( $root . '/file-26-search-discovery.php' );

$checks = array(
	array( $rest, '$appeals = $this->doctor_appeals->own();', 'own-appeal backend result is captured' ),
	array( $rest, 'is_wp_error( $appeals ) ? $appeals', 'own-appeal backend errors are propagated' ),
	array( $rest, 'file26_reports_read_failed', 'governance report read failure is explicit' ),
	array( $rest, "array( 'connector_health', 'jobs', 'zero_results', 'active_policies' )", 'all governance report collections are validated' ),
	array( $health, 'database_read_failed', 'health exposes database read-failure state' ),
	array( $health, 'private function table_state', 'health table probes distinguish DB error from missing table' ),
	array( $health, 'private function count_value', 'health count reads are checked' ),
	array( $health, "$database_read_failed || $unknown || $schema_drift ? 'unavailable'", 'health fails closed on database read failure' ),
	array( $truth, "add_filter( 'rest_pre_dispatch'", 'operation-truth guard captures pre-state' ),
	array( $truth, "add_filter( 'rest_post_dispatch'", 'operation-truth guard verifies post-state' ),
	array( $truth, 'file26_saved_query_persistence_failed', 'saved-query write truth is enforced' ),
	array( $truth, 'file26_saved_query_delete_failed', 'saved-query delete truth is enforced' ),
	array( $truth, 'file26_content_gap_persistence_failed', 'content-gap write truth is enforced' ),
	array( $truth, 'file26_editorial_radar_read_failed', 'editorial-radar read truth is enforced' ),
	array( $plugin, "require_once SABRI_FILE26_DIR . 'includes/class-file26-operation-truth.php';", 'operation-truth guard is loaded' ),
	array( $plugin, '\\Sabri\\File26\\Operation_Truth::boot();', 'operation-truth guard is booted' ),
);

$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 10 operation-truth regression passed.\n";
