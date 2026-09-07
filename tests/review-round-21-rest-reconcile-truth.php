<?php
$root = dirname( __DIR__ );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$compact = static function ( $source ) { return preg_replace( '/\s+/', '', (string) $source ); };
$rest_compact = $compact( $rest );
$indexer_compact = $compact( $indexer );

$fail = static function ( $message ) {
    fwrite( STDERR, "FAIL: {$message}\n" );
    exit( 1 );
};

if ( false === strpos( $indexer_compact, $compact( "return new \\WP_Error( 'file26_reconcile_failed'" ) ) ) {
    $fail( 'Indexer reconciliation must expose an explicit failure object.' );
}
if ( false === strpos( $rest_compact, $compact( '$this->indexer->reconcile()' ) ) ) {
    $fail( 'REST reconciliation must return the actual Indexer result.' );
}
if ( false !== strpos( $rest_compact, $compact( '$this->indexer->reconcile(); return $this->respond( array( \'reconciled\' => true ) );' ) ) ) {
    $fail( 'REST reconciliation must not discard a proven Indexer failure.' );
}

echo "PASS: round 21 reconciliation truth preserved\n";
