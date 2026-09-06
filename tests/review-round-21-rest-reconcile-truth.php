<?php
$root = dirname( __DIR__ );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );

$fail = static function ( $message ) {
    fwrite( STDERR, "FAIL: {$message}\n" );
    exit( 1 );
};

if ( false === strpos( $indexer, "return new \\WP_Error('file26_reconcile_failed'" ) ) {
    $fail( 'Indexer reconciliation must expose an explicit failure object.' );
}
if ( false === strpos( $rest, 'public function reconcile() { $result = $this->indexer->reconcile(); return $this->respond( $result ); }' ) ) {
    $fail( 'REST reconciliation must return the actual Indexer result instead of unconditional success.' );
}
if ( false !== strpos( $rest, '$this->indexer->reconcile(); return $this->respond( array( \'reconciled\' => true ) );' ) ) {
    $fail( 'REST reconciliation must not discard a proven Indexer failure.' );
}

echo "PASS: round 21 reconciliation truth preserved\n";
