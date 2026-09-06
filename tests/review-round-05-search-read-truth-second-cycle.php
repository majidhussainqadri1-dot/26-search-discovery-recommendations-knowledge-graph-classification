<?php
/** Second-cycle Round 5 regression: a failed federated DB read cannot masquerade as zero results. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-search.php' );
$checks = array(
	array( $source, 'if ( ! is_array( $rows ) )', 'candidate read distinguishes failure from an empty array' ),
	array( $source, 'file26_search_read_failed', 'candidate DB failure has an explicit safe error code' ),
	array( $source, "'status' => 503, 'trace_id' => \$trace", 'candidate DB failure carries unavailable status and trace id' ),
	array( $source, 'search_candidate_read_failed', 'candidate DB failure is audited without raw query text' ),
	array( $source, 'if ( empty( $rows ) )', 'a valid empty result remains a normal terminal batch' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 05 search read truth regression passed.\n";
