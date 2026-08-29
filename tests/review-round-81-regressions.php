<?php
/** Round 81 release-evidence regression guards. */
$root = dirname( __DIR__ );
$qa = file_get_contents( $root . '/qa/run-tests.sh' );
$report = file_get_contents( $root . '/docs/QA-REPORT.md' );
$ledger = file_get_contents( $root . '/docs/FILE26-R62-R81-SEQUENTIAL-REVIEW-2026-08-29.md' );
$builder = file_get_contents( $root . '/tools/build-package.py' );
$checks = 0;
function f26_r81_assert( $condition, $message ) {
    global $checks;
    $checks++;
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
f26_r81_assert( false !== strpos( $report, 'File 26 v1.3.0' ) && false !== strpos( $report, 'Contract version: `1.3`' ) && false !== strpos( $report, 'R62–R81' ), 'R81: QA report is aligned with current v1.3 review identity' );
f26_r81_assert( false !== strpos( $ledger, '**Clean rounds:** R62' ) && false !== strpos( $ledger, '**Defect rounds:** R63' ) && false !== strpos( $ledger, 'R81' ) && false !== strpos( $ledger, '19 defect rounds + 1 clean round' ), 'R81: dedicated R62-R81 sequential ledger records final classification' );
f26_r81_assert( false !== strpos( $qa, 'tests/review-round-77-regressions.php' ) && false !== strpos( $qa, 'tests/review-round-81-regressions.php' ) && false !== strpos( $qa, 'FILE26-R62-R81-SEQUENTIAL-REVIEW-2026-08-29.md' ), 'R81: current round regressions and ledger are explicit required release evidence' );
f26_r81_assert( false !== strpos( $qa, 'ls-files --error-unmatch MANIFEST.sha256' ) && false !== strpos( $builder, 'write_manifest(root)' ) && false !== strpos( $builder, 'EXCLUDED_NAMES = {"CHECKSUMS.sha256", "MANIFEST.sha256"}' ), 'R81: manifest is generated from the exact build tree and stale tracking is rejected' );
echo "PASS: $checks Round 81 release-evidence assertions\n";
