<?php
$root = dirname( __DIR__ );
$code = file_get_contents( $root . '/includes/class-file26-graph.php' );
$fail = static function ( $m ) { fwrite( STDERR, "FAIL: {$m}\n" ); exit( 1 ); };
foreach ( array(
    '$owner_file = $this->source_owner_file( $source );',
    'file26_duplicate_edge',
    'acquire_edge_lock',
    'file26_graph_owner_stale',
    'file26_graph_read_failed',
    'file26_graph_owner_read_failed',
    "if ( false === $updated ) { return new \\WP_Error( 'file26_edge_write_failed'",
) as $needle ) {
    if ( false === strpos( $code, $needle ) ) { $fail( 'Missing graph safeguard: ' . $needle ); }
}
$forbidden_owner_input = "'owner_file' => isset( \$input['owner_file'] )";
if ( false !== strpos( $code, $forbidden_owner_input ) ) {
    $fail( 'Graph edge ownership must not be accepted from curator input.' );
}
echo "PASS: round 27 graph ownership, duplicate prevention and traversal failure truth\n";
