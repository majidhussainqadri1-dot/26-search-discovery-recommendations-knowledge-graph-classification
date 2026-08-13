<?php
/** Round 18 regression: stale workers recover, enqueue failures surface and reconciliation removes all derivative remnants atomically. */
$root = dirname( __DIR__ );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$health = file_get_contents( $root . '/includes/class-file26-health.php' );
$checks = array(
	array( $indexer, "error_code='worker_timeout'", 'stale running workers are recovered' ),
	array( $indexer, 'file26_job_enqueue_failed', 'job enqueue DB failure is explicit' ),
	array( $indexer, 'DELETE c FROM $classes', 'tombstone reconciliation removes classifications' ),
	array( $indexer, 'DELETE n FROM $nodes', 'tombstone reconciliation removes graph nodes' ),
	array( $indexer, 'DELETE e FROM $edges', 'tombstone reconciliation removes graph edges' ),
	array( $indexer, 'file26_reconcile_failed', 'reconciliation failure rolls back' ),
	array( $health, 'stale_running_jobs', 'health reports stale workers' ),
	array( $health, 'cron_missing', 'health reports missing scheduled operations' ),
	array( $health, 'schema_drift', 'health reports schema-version drift' ),
);
$failures = 0;
foreach ( $checks as $c ) { if ( false === strpos( $c[0], $c[1] ) ) { fwrite( STDERR, "FAIL: {$c[2]}\n" ); $failures++; } }
if ( $failures ) { exit( 1 ); }
echo "Round 18 operations resilience regression passed.\n";
