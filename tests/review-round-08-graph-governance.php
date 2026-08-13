<?php
/** Round 08 regression: graph edges require governed activation and final visibility rechecks. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-graph.php' );
$compact = preg_replace( '/\s+/', '', $source );
$checks = array(
	array( false !== strpos( $source, 'public function approve_edge' ), 'draft edge has explicit approval transition' ),
	array( false !== strpos( $compact, "require_step_up('graph_edge_approve')" ), 'edge activation requires fresh authorization' ),
	array( false !== strpos( $source, 'sabri_file26_graph_edge_owner_approved' ), 'source-domain owner approval contract exists' ),
	array( false !== strpos( $compact, "'state'=>'active'" ), 'approved transition writes active state' ),
	array( false !== strpos( $source, 'public function remove_edge' ), 'edge removal has governed transition' ),
	array( false !== strpos( $source, '$visible_keys' ), 'final visible node set is rebuilt before response' ),
	array( false !== strpos( $compact, 'isset($visible_keys[$edge[\'source_key\']],$visible_keys[$edge[\'target_key\']])' ), 'edges to revoked nodes are removed from response' ),
	array( false !== strpos( $compact, 'hash_equals($source,$target)' ), 'self-edge rejected' ),
);
$failures = 0;
foreach ( $checks as $check ) { if ( ! $check[0] ) { fwrite( STDERR, "FAIL: {$check[1]}\n" ); $failures++; } }
if ( $failures ) { exit( 1 ); }
echo "Round 08 graph governance regression passed.\n";
