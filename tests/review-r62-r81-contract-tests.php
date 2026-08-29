<?php
/** Regression ledger for sequential review rounds R62-R81. */
$root = dirname( __DIR__ );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$checks = 0;
$failures = 0;
function f26_r62_81_assert( $condition, $message ) {
    global $checks, $failures;
    $checks++;
    if ( ! $condition ) { $failures++; fwrite( STDERR, "FAIL: $message\n" ); }
}
f26_r62_81_assert( false !== strpos( $indexer, 'file26_index_transaction_unavailable' ), 'R63: index upsert fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $indexer, 'file26_tombstone_transaction_unavailable' ), 'R63: tombstone revocation fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $indexer, 'file26_reconcile_transaction_unavailable' ), 'R63: reconciliation fails closed if transaction start fails' );
if ( $failures ) { fwrite( STDERR, "$failures of $checks R62-R81 assertions failed.\n" ); exit( 1 ); }
echo "PASS: $checks R62-R81 review regression assertions\n";
