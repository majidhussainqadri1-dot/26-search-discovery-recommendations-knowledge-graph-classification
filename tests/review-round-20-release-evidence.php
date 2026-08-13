<?php
/** Round 20 regression: corrective tests, CI runtimes and evidence ledger must remain executable and current. */
$root = dirname( __DIR__ );
$workflow = file_get_contents( $root . '/.github/workflows/qa.yml' );
$runner = file_get_contents( $root . '/qa/run-tests.sh' );
$ledger_path = $root . '/docs/FILE26-20-ROUND-CORRECTIVE-AUDIT-2026-08-13.md';
$ledger = file_exists( $ledger_path ) ? file_get_contents( $ledger_path ) : '';
$checks = array(
	array( $workflow, 'actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1', 'checkout must remain on the reviewed immutable Node 24 release' ),
	array( $workflow, 'actions/setup-node@820762786026740c76f36085b0efc47a31fe5020', 'setup-node must remain on the reviewed immutable Node 24 release' ),
	array( $workflow, 'actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a', 'upload-artifact must remain on the reviewed immutable Node 24 release' ),
	array( $runner, 'ROUND_TESTS=("$ROOT"/tests/review-round-*.php)', 'source QA must execute every sequential review regression' ),
	array( $runner, '"$PACKAGE"/tests/review-round-*.php', 'clean-package QA must execute every sequential review regression' ),
	array( $ledger, 'Total: **20/20 rounds**; **17 defect rounds**, **3 clean rounds**.', '20-round ledger summary must remain explicit' ),
	array( $ledger, 'Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔', 'live/repository truth boundary must remain explicit' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, "FAIL: {$check[2]}\n" ); $failures++; }
}
$tests = glob( $root . '/tests/review-round-*.php' );
if ( count( $tests ) < 17 ) {
	fwrite( STDERR, "FAIL: expected regression evidence for all defect rounds\n" );
	$failures++;
}
$dangerous_vars = '(?:this|wpdb|audience|query|document|manifest|target|source|expected_version|migration|job|result|classes|nodes|edges|visible_keys)';
foreach ( $tests as $test ) {
	if ( basename( $test ) === basename( __FILE__ ) ) { continue; }
	$content = file_get_contents( $test );
	if ( preg_match( '/"[^"\n]*\$' . $dangerous_vars . '(?:->|\[|\b)[^"\n]*"/', $content, $match ) ) {
		fwrite( STDERR, 'FAIL: interpolation-prone regression literal in ' . basename( $test ) . ': ' . $match[0] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 20 release evidence regression passed.\n";
