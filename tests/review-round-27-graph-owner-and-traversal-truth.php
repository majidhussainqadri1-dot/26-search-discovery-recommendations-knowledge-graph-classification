<?php
/** Round 27 regression: graph ownership, duplicate prevention and traversal failures remain fail-closed. */
$root = dirname( __DIR__ );
$code = file_get_contents( $root . '/includes/class-file26-graph.php' );
$compact = static function ( $source ) { return preg_replace( '/\s+/', '', (string) $source ); };
$code_compact = $compact( $code );
$failures = 0;
$fail = static function ( $m ) use ( &$failures ) { fwrite( STDERR, 'FAIL: ' . $m . "\n" ); $failures++; };
foreach ( array(
	'$owner_file = $this->source_owner_file( $source );' => 'edge ownership is derived from source-owner truth',
	'file26_duplicate_edge' => 'duplicate edge writes are rejected',
	'acquire_edge_lock' => 'edge mutation is serialized',
	'file26_graph_owner_stale' => 'stale owner evidence is rejected',
	'file26_graph_read_failed' => 'graph traversal read failure is explicit',
	'file26_graph_owner_read_failed' => 'graph owner read failure is explicit',
	'if ( false === $updated ) { return new \\WP_Error( \'file26_edge_write_failed\'' => 'edge persistence failure is explicit',
) as $needle => $label ) {
	if ( false === strpos( $code_compact, $compact( $needle ) ) ) { $fail( 'Missing graph safeguard: ' . $label ); }
}
$forbidden_owner_input = "'owner_file' => isset( \$input['owner_file'] )";
if ( false !== strpos( $code_compact, $compact( $forbidden_owner_input ) ) ) {
	$fail( 'Graph edge ownership must not be accepted from curator input.' );
}
if ( $failures ) { exit( 1 ); }
echo "PASS: round 27 graph ownership, duplicate prevention and traversal failure truth\n";
