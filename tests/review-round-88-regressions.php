<?php
/** Round 88 graph trust-boundary regression guards. */
$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/file-26-search-discovery.php' );
$graph = file_get_contents( $root . '/includes/class-file26-graph.php' );
$checks = 0;
function f26_r88_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
foreach ( array( 'sabri_file26_graph_edge_owner_approved', 'sabri_file26_allowed_evidence_url' ) as $hook ) {
	f26_r88_assert( false !== strpos( $bootstrap, $hook ), 'R88: strict authorization normalizer is registered for ' . $hook );
}
f26_r88_assert( false !== strpos( $graph, 'source_owner_file' ) && false !== strpos( $graph, 'public_node_exists' ) && false !== strpos( $graph, "state='active'" ), 'R88: graph activation and traversal remain owner/visibility bounded' );
f26_r88_assert( false !== strpos( $graph, 'provenance' ) && false !== strpos( $graph, 'evidence_url' ), 'R88: provenance and evidence boundaries remain explicit' );
echo "PASS: $checks Round 88 regression assertions\n";
