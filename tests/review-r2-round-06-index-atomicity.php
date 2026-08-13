<?php
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$checks = array(
	'Search document and graph projection could not be indexed atomically.' => 'document/node/tombstone projection writes share an atomic failure boundary',
	'Superseded tombstone cleanup failed.' => 'upsert checks tombstone cleanup persistence',
	'Search graph-node projection failed.' => 'upsert checks graph-node persistence',
	'Derivative purge verification failed.' => 'revocation verifies all derivative remnants are gone',
	'Document revocation could not be completed atomically.' => 'revocation reports atomic failure',
	'Batch item failed; cursor must not advance.' => 'failed reindex item prevents cursor advancement and is retried',
	'file26_invalid_freshness_time' => 'invalid owner freshness timestamp fails closed',
	'file26_tombstone_read_failed' => 'deletion precedence read failure fails closed',
	'file26_index_state_read_failed' => 'current index-state read failure fails closed',
);
$failed = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failed++; }
	else { echo "PASS: $label\n"; }
}
exit( $failed ? 1 : 0 );
