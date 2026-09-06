<?php
/** Round 100 CI/package/release-evidence regression guards. */
$root = dirname( __DIR__ );
$qa = file_get_contents( $root . '/qa/run-tests.sh' );
$workflow = file_get_contents( $root . '/.github/workflows/qa.yml' );
$report = file_get_contents( $root . '/docs/QA-REPORT.md' );
$checks = 0;
function f26_r100_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r100_assert( false !== strpos( $qa, 'run_php_test()' ) && false !== strpos( $qa, 'PHP test emitted warning/notice/deprecation output' ), 'R100: PHP behavioral/regression tests fail on warning/notice/deprecation stderr' );
f26_r100_assert( false !== strpos( $qa, '82 83 84 85 87 88 89 90 91 92 93 94 95 96 97 98 99 100' ) && false !== strpos( $qa, 'current R82-R100 regression evidence missing' ), 'R100: current defect-round regression files have an explicit presence gate' );
f26_r100_assert( false !== strpos( $workflow, 'release/CHECKSUMS.sha256' ) && false !== strpos( $workflow, 'Upload deterministic package and checksum' ), 'R100: deterministic package artifact includes checksum sidecar' );
f26_r100_assert( false !== strpos( $report, 'review/file26-v1.3.0-r82-r101-2026-08-29' ) && false !== strpos( $report, 'R82–R101' ) && false === strpos( $report, 'review/file26-v1.3.0-second-forty-round-2026-08-29' ), 'R100: QA report identifies the current review branch/cycle rather than stale branch evidence' );
echo "PASS: $checks Round 100 regression assertions\n";
