<?php
/** Round 14 regression: current QA evidence must identify the current corrective cycle and must not masquerade historical provenance as current truth. */
$root = dirname( __DIR__ );
$report = file_get_contents( $root . '/docs/QA-REPORT.md' );
$runner = file_get_contents( $root . '/qa/run-tests.sh' );

$checks = array(
	array( $report, 'codex/file26-20-round-review-20260906', true, 'current corrective branch is named' ),
	array( $report, '89fd57bb9408dddf5f6390982ba4c55d87752286', true, 'current frozen main baseline is named' ),
	array( $report, 'docs/FILE26-20-ROUND-CORRECTIVE-AUDIT-2026-09-06.md', true, 'current corrective ledger is authoritative' ),
	array( $report, 'historical evidence from the prior corrective cycle only', true, 'historical evidence boundary is explicit' ),
	array( $report, 'review/file26-next-20-round-2026-08-13', false, 'old review branch is not presented in the current report' ),
	array( $report, 'e5f0dc21db57889b9df9f724edfd5652fc1675fb', false, 'old baseline is not presented as current evidence' ),
	array( $runner, 'docs/FILE26-20-ROUND-CORRECTIVE-AUDIT-2026-09-06.md', true, 'QA requires the current corrective ledger' ),
);

$failures = 0;
foreach ( $checks as $check ) {
	$found = false !== strpos( $check[0], $check[1] );
	if ( $found !== $check[2] ) {
		fwrite( STDERR, 'FAIL: ' . $check[3] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 14 evidence-truth regression passed.\n";
