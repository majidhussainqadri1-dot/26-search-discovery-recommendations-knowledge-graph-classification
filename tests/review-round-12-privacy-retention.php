<?php
/** Round 12 regression: export, erasure and retention never hide DB failure. */
$root = dirname( __DIR__ );
$privacy = file_get_contents( $root . '/includes/class-file26-privacy.php' );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$checks = array(
	array( $privacy, 'export_failure', 'export has an explicit failure path' ),
	array( $privacy, 'sabri_file26_last_privacy_failure', 'privacy failures are surfaced operationally' ),
	array( $privacy, 'appeal_count_raw', 'erasure verifies appeal scope before destructive work' ),
	array( $privacy, "false===$wpdb->query('START TRANSACTION')", 'erasure verifies transaction start' ),
	array( $privacy, "false===$wpdb->query('COMMIT')", 'erasure verifies transaction commit' ),
	array( $indexer, 'sabri_file26_metrics_retention_days', 'aggregated search metrics have bounded retention' ),
	array( $indexer, 'sabri_file26_last_retention_failure', 'general retention failure is observable' ),
	array( $indexer, 'file26_retention_failed', 'retention failure returns an explicit error' ),
	array( $plugin, 'sabri_file26_last_appeal_retention_failure', 'appeal-retention failure is observable' ),
	array( $plugin, 'file26_appeal_retention_failed', 'appeal retention fails closed atomically' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Round 12 privacy and retention regression passed.\n";
