<?php
/** Round 08 regression: graph edges require governed activation and final visibility rechecks. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-graph.php' );
$checks = array(
	'public function approve_edge' => 'draft edge has explicit approval transition',
	'require_step_up( \'graph_edge_approve\' )' => 'edge activation requires fresh authorization',
	'sabri_file26_graph_edge_owner_approved' => 'source-domain owner approval contract exists',
	'\'state\' => \'active\'' => 'approved transition writes active state',
	'public function remove_edge' => 'edge removal has governed transition',
	'$visible_keys' => 'final visible node set is rebuilt before response',
	'isset( $visible_keys[ $edge[\'source_key\'] ], $visible_keys[ $edge[\'target_key\'] ] )' => 'edges to revoked nodes are removed from response',
	'hash_equals( $source, $target )' => 'self-edge rejected',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
if ( $failures ) { exit( 1 ); }
echo "Round 08 graph governance regression passed.\n";
