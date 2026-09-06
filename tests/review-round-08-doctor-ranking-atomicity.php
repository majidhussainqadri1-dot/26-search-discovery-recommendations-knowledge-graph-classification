<?php
/** Current audit Round 08 regression: doctor ranking recompute must fail closed across transaction and metadata boundaries. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-doctor-ranking.php' );
$checks = array(
    "false === $wpdb->query( 'START TRANSACTION' )" => 'doctor ranking verifies transaction start',
    "update_option( DB::OPTION_SETTINGS, $settings, false )" => 'doctor ranking metadata write is explicit and verifiable',
    "false === $settings_written && $settings !== DB::settings()" => 'metadata write failure cannot be silently accepted',
    "false === $wpdb->query( 'COMMIT' )" => 'doctor ranking verifies transaction commit',
    "file26_doctor_ranking_write_failed" => 'atomic recompute failure is surfaced',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
    if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Round 08 doctor ranking atomicity regression passed.\n";
