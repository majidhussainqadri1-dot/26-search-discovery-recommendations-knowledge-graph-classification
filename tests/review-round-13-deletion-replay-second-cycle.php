<?php
/** Second-cycle Round 13 regression: deletion/replay precedence reads fail closed and stale tombstones cannot purge higher-version derivatives. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$checks = array(
	array( $source, 'private function checked_scalar', 'scalar precedence reads use a checked DB helper' ),
	array( $source, 'private function checked_row', 'row precedence reads use a checked DB helper' ),
	array( $source, 'file26_index_precedence_read_failed', 'tombstone-precedence DB failure is explicit' ),
	array( $source, 'file26_index_existing_read_failed', 'existing-document DB failure is explicit' ),
	array( $source, 'file26_tombstone_precedence_read_failed', 'revocation precedence DB failure is explicit' ),
	array( $source, 'live.object_version>t.object_version', 'reconciliation detects a higher-version live resurrection' ),
	array( $source, '$valid_tombstone', 'derivative reconciliation shares a version-safe tombstone predicate' ),
	array( $source, 'DELETE c FROM $classes', 'classification reconciliation remains covered' ),
	array( $source, 'DELETE n FROM $nodes', 'node reconciliation remains covered' ),
	array( $source, 'DELETE e FROM $edges', 'edge reconciliation remains covered' ),
	array( $source, 't.object_version >= d.object_version', 'document reconciliation retains monotonic version guard' ),
	array( $source, 'Deletion and graph reconciliation failed atomically.', 'reconciliation remains transactional and fail closed' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( substr_count( $source, '$valid_tombstone' ) < 4 ) {
	fwrite( STDERR, "FAIL: version-safe tombstone predicate must govern edges, classifications and nodes as well as its declaration\n" );
	$failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 13 deletion/replay regression passed.\n";
