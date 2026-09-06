<?php
/** Round 101 final traceability/release-closure regression guards. */
$root = dirname( __DIR__ );
$trace = file_get_contents( $root . '/docs/FUTURE-REQUIREMENTS-TRACEABILITY-1.3.md' );
$ledger = file_get_contents( $root . '/docs/FILE26-R82-R101-SEQUENTIAL-REVIEW-2026-09-06.md' );
$qa = file_get_contents( $root . '/qa/run-tests.sh' );
$report = file_get_contents( $root . '/docs/QA-REPORT.md' );
$checks = 0;
function f26_r101_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r101_assert( false !== strpos( $trace, 'proven to connect requested endpoints' ) && false !== strpos( $trace, 'snapshot ID + provenance' ) && false !== strpos( $trace, 'safe canonical resource URL' ), 'R101: Future traceability records final graph/evidence/historical provenance guarantees' );
f26_r101_assert( false !== strpos( $trace, 'bounded/deduplicated browser history' ) && false !== strpos( $trace, 'explicit cadence restricted to hourly/daily/weekly' ) && false !== strpos( $trace, 'non-personal why-this remains available' ) && false !== strpos( $trace, 'whole-number radius 1–500 km' ), 'R101: Future traceability records final user-data/transparency/geo guarantees' );
f26_r101_assert( false !== strpos( $ledger, '20/20 reviews completed' ) && false !== strpos( $ledger, '19 defect-bearing rounds' ) && false !== strpos( $ledger, '1 clean round' ) && false !== strpos( $ledger, 'R86' ), 'R101: final sequential ledger records both ten-round checkpoints and overall defect result' );
f26_r101_assert( false !== strpos( $qa, 'docs/FILE26-R82-R101-SEQUENTIAL-REVIEW-2026-09-06.md' ) && false !== strpos( $qa, '82 83 84 85 87 88 89 90 91 92 93 94 95 96 97 98 99 100 101' ), 'R101: QA explicitly requires final ledger and every defect-round regression through R101' );
f26_r101_assert( false !== strpos( $report, '20/20 rounds completed' ) && false !== strpos( $report, 'R92, R93, R94, R95, R96, R97, R98, R99, R100, R101' ) && false !== strpos( $report, '19 defect-bearing rounds' ), 'R101: QA report closes the full R82-R101 cycle truthfully' );
echo "PASS: $checks Round 101 regression assertions\n";
