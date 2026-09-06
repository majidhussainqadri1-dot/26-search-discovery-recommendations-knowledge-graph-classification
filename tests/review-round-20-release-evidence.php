<?php
/** Round 20 regression: final corrective evidence must bind to the current audit head, not historical provenance. */
$root = dirname( __DIR__ );
$workflow = file_get_contents( $root . '/.github/workflows/qa.yml' );
$runner = file_get_contents( $root . '/qa/run-tests.sh' );
$qa_report = file_get_contents( $root . '/docs/QA-REPORT.md' );
$ledger_path = $root . '/docs/FILE26-20-ROUND-CORRECTIVE-AUDIT-2026-09-06.md';
$ledger = file_exists( $ledger_path ) ? file_get_contents( $ledger_path ) : '';

$checks = array(
	array( $workflow, 'actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1', 'checkout must remain on the reviewed immutable Node 24 release' ),
	array( $workflow, 'actions/setup-node@820762786026740c76f36085b0efc47a31fe5020', 'setup-node must remain on the reviewed immutable Node 24 release' ),
	array( $workflow, 'actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a', 'upload-artifact must remain on the reviewed immutable Node 24 release' ),
	array( $runner, 'ROUND_TESTS=("$ROOT"/tests/review-round-*.php)', 'source QA must execute every sequential review regression' ),
	array( $runner, '"$PACKAGE"/tests/review-round-*.php', 'clean-package QA must execute every sequential review regression' ),
	array( $runner, 'docs/FILE26-20-ROUND-CORRECTIVE-AUDIT-2026-09-06.md', 'QA must require the current corrective ledger' ),
	array( $qa_report, 'Current 20-round corrective ledger: `docs/FILE26-20-ROUND-CORRECTIVE-AUDIT-2026-09-06.md`', 'QA report must name the current ledger' ),
	array( $qa_report, 'historical evidence from the prior corrective cycle only', 'prior 2026-08-13 evidence must remain explicitly historical' ),
	array( $ledger, 'Review/correction count: **20/20 rounds completed**.', 'current ledger must explicitly complete all twenty review/correction rounds' ),
	array( $ledger, 'Defect rounds: **1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 14, 15, 16, 17, 18, 19, 20**', 'current defect-round set must be explicit' ),
	array( $ledger, 'Clean rounds: **7, 13**', 'current clean-round set must be explicit' ),
	array( $ledger, 'Staging/live deployment, deployed database/schema, migration completion, real connectors and operational behavior require separate evidence.', 'repository/live truth boundary must remain explicit' ),
);

$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}

$required_current_regressions = array(
	'review-round-01-index-lifecycle.php',
	'review-round-02-connector-lifecycle.php',
	'review-round-03-search-integrity.php',
	'review-round-06-recommendation-privacy.php',
	'review-round-08-doctor-ranking-atomicity.php',
	'review-round-09-rest-cache-governance.php',
	'review-round-10-security-assertions.php',
	'review-round-11-privacy-lifecycle.php',
	'review-round-12-governance-transitions.php',
	'review-round-14-evidence-truth.php',
	'review-round-15-physical-schema-integrity.php',
	'review-round-16-route-cache-privacy.php',
	'review-round-17-operation-atomicity.php',
	'review-round-18-operation-durability.php',
	'review-round-19-autocomplete-accessibility.php',
);
foreach ( $required_current_regressions as $regression ) {
	if ( ! is_file( $root . '/tests/' . $regression ) ) {
		fwrite( STDERR, 'FAIL: missing current corrective regression: ' . $regression . "\n" );
		$failures++;
	}
}

$tests = glob( $root . '/tests/review-round-*.php' );
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
echo "Round 20 current-ledger release evidence regression passed.\n";
