<?php
/** Round 15 regression: indexing, queue ownership, retries and caches are failure-truthful. */
$root = dirname( __DIR__ );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$search = file_get_contents( $root . '/includes/class-file26-search.php' );
$checks = array(
	array( $indexer, 'file26_index_transaction_failed', 'index transaction start is verified' ),
	array( $indexer, 'Indexing commit failed.', 'index transaction commit is verified' ),
	array( $indexer, 'file26_tombstone_transaction_failed', 'revocation transaction start is verified' ),
	array( $indexer, 'Revocation commit failed.', 'revocation commit is verified' ),
	array( $indexer, 'file26_reconcile_transaction_failed', 'reconciliation transaction start is verified' ),
	array( $indexer, 'Reconciliation commit failed.', 'reconciliation commit is verified' ),
	array( $indexer, 'file26_event_identity_conflict', 'same source event identity cannot change content silently' ),
	array( $indexer, "unset( $semantic['object_version']", 'semantic checksum excludes delivery metadata and runtime timestamps' ),
	array( $indexer, 'sanitize_reindex_scope', 'reindex scope is allowlisted and bounded' ),
	array( $indexer, "scope['connector'] = $connector_slug", 'validated connector cannot be overwritten by caller scope' ),
	array( $indexer, 'file26_reindex_already_queued', 'equivalent active reindex jobs are deduplicated' ),
	array( $indexer, 'count( $batch[\'items\'] ) > 100', 'connector batch cardinality is bounded' ),
	array( $indexer, 'Connector batch made no progress.', 'worker rejects non-progressing cursor loops' ),
	array( $indexer, 'document_index_failed', 'batch document failures cannot be reported as completed success' ),
	array( $indexer, 'file26_job_recovery_failed', 'stale worker recovery DB failure is explicit' ),
	array( $indexer, 'file26_job_claim_failed', 'job claim DB failure is distinct from CAS contention' ),
	array( $indexer, 'file26_job_failure_write_failed', 'failure-state persistence itself is verified' ),
	array( $search, 'sabri_file26_object_cache_allowed', 'search object cache is explicit opt-in only' ),
	array( $search, 'sabri_file26_revocation_cache_purge_ready', 'object cache requires revocation-purge readiness' ),
	array( $search, 'file26_graph_signal_read_failed', 'ranking graph-signal DB failures fail closed' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 15 jobs, concurrency and cache regression passed.\n";
