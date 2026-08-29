<?php
/** Round 80 regression guards. */
$root = dirname( __DIR__ );
$knowledge = file_get_contents( $root . '/includes/trait-file26-future-knowledge.php' );
$checks = 0;
function f26_r80_assert( $condition, $message ) {
    global $checks;
    $checks++;
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
f26_r80_assert( false !== strpos( $knowledge, '$snapshot_id = is_array' ) && false !== strpos( $knowledge, "'snapshot_available'" ), 'R80: research snapshot availability uses sanitized non-empty snapshot identity' );
f26_r80_assert( false !== strpos( $knowledge, 'file26_graph_endpoint_binding_failed' ), 'R80: graph path must contain both requested endpoints' );
f26_r80_assert( false !== strpos( $knowledge, 'file26_graph_depth_exceeded' ) && false !== strpos( $knowledge, 'maximum depth of six edges' ), 'R80: graph path depth is enforced by File 26' );
f26_r80_assert( false !== strpos( $knowledge, 'retain provenance after sanitization' ), 'R80: graph edge provenance is revalidated after sanitization' );
f26_r80_assert( false !== strpos( $knowledge, "'historical_snapshot_unavailable'" ) && false !== strpos( $knowledge, "'snapshot_id' => $snapshot_id" ) === false, 'R80: historical snapshots require a sanitized identity before success' );
echo "PASS: $checks Round 80 regression assertions\n";
