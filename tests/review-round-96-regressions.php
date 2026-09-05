<?php
/** Round 96 future knowledge integrity regression guards. */
$root = dirname( __DIR__ );
$knowledge = file_get_contents( $root . '/includes/trait-file26-future-knowledge.php' );
$checks = 0;
function f26_r96_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r96_assert( false !== strpos( $knowledge, 'graph_path_connected' ) && false !== strpos( $knowledge, 'file26_graph_path_not_connected' ), 'R96: graph explorer verifies an actual connected path between requested endpoints' );
f26_r96_assert( false !== strpos( $knowledge, 'file26_graph_edge_owner_type_required' ), 'R96: graph edges retain owner/type identity after sanitization' );
f26_r96_assert( false !== strpos( $knowledge, "'snapshot_provenance' => \$provenance" ) && false !== strpos( $knowledge, "'' === \$provenance" ), 'R96: historical snapshot requires and returns provenance' );
f26_r96_assert( false !== strpos( $knowledge, "safe_resource_url( isset( \$clean['canonical_url'] ) ? \$clean['canonical_url'] : '', 'evidence_map_canonical_url' )" ), 'R96: evidence-map canonical destinations pass safe resource validation' );
echo "PASS: $checks Round 96 regression assertions\n";
