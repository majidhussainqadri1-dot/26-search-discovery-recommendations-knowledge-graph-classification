<?php
$root = dirname( __DIR__ );
$code = file_get_contents( $root . '/includes/class-file26-taxonomy.php' );
$fail = static function ( $m ) { fwrite( STDERR, "FAIL: {$m}\n" ); exit( 1 ); };
foreach ( array(
    'Taxonomy term and aliases could not be created atomically.',
    'private function insert_term_record',
    'Parent term must exist and remain current before approval.',
    'file26_merge_preview_failed',
    'Source alias cleanup failed.',
    'file26_split_preview_failed',
    'file26_classification_target_read_failed',
    "SELECT term_uuid FROM ' . DB::table( 'term_aliases' )",
) as $needle ) {
    if ( false === strpos( $code, $needle ) ) { $fail( 'Missing taxonomy safeguard: ' . $needle ); }
}
if ( false !== strpos( $code, 'INSERT IGNORE INTO ' . "' . DB::table( 'term_aliases' )" ) ) {
    $fail( 'Alias collisions must not be silently ignored.' );
}
if ( false !== strpos( $code, '$result = $this->create( $target );' ) ) {
    $fail( 'Split must not start nested transactions through public create().' );
}
echo "PASS: round 26 taxonomy atomicity and alias collision safety\n";
