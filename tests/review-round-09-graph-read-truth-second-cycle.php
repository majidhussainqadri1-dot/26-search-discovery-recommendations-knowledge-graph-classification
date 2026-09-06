<?php
/** Second-cycle Round 9 regression: graph traversal distinguishes database failure from empty/revoked state. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-graph.php' );
$checks = array(
	"$wpdb->last_error = '';" => 'graph reads clear last_error before checked reads',
	'null === $rows && ! empty( $wpdb->last_error )' => 'edge traversal read failure is detected',
	'null === $nodes && ! empty( $wpdb->last_error )' => 'node projection read failure is detected',
	'file26_graph_read_failed' => 'graph read failures use an explicit safe error',
	"'status' => 503" => 'graph backend read failure is a service error, not a false 404',
	'graph_traversal_read_failed' => 'edge read failure is audited',
	'graph_node_projection_read_failed' => 'node read failure is audited',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 09 graph read-truth regression passed.\n";
