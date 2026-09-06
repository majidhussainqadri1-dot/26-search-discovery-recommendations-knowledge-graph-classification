<?php
/** Round 18 regression: stale-worker recovery and retention DB failures must fail closed and remain observable. */
$root = dirname( __DIR__ );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$normalized = preg_replace( '/\s+/', '', $indexer );

$checks = array(
	array( $normalized, '$recovered=$wpdb->query', 'stale-worker recovery result is captured' ),
	array( $indexer, 'search_worker_recovery_failed', 'stale-worker recovery failure is audited' ),
	array( $indexer, 'file26_worker_recovery_failed', 'stale-worker recovery failure stops the queue cycle' ),
	array( $normalized, 'foreach($queriesas$class=>$sql)', 'retention validates every governed deletion class' ),
	array( $normalized, 'if(false===$result)', 'retention detects DB deletion failure' ),
	array( $indexer, 'search_retention_failed', 'retention failure is audited' ),
	array( $indexer, 'file26_retention_failed', 'retention failure is returned explicitly' ),
	array( $normalized, "returnarray('retention'=>true", 'retention success is explicit only after all deletion classes complete' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 18 operation durability regression passed.\n";
