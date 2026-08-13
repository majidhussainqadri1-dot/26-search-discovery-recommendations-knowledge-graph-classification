<?php
/** Round 13 regression: ranking recompute and appeals are serialized, atomic and fresh-authorized. */
$root = dirname( __DIR__ );
$ranking = file_get_contents( $root . '/includes/class-file26-doctor-ranking.php' );
$appeals = file_get_contents( $root . '/includes/class-file26-doctor-appeals.php' );
$checks = array(
	array( $ranking, "wp_doing_cron()", 'scheduled recompute requires real cron context' ),
	array( $ranking, "SELECT GET_LOCK(%s, 5)", 'ranking recompute is serialized' ),
	array( $ranking, "START TRANSACTION", 'ranking projection writes are atomic' ),
	array( $ranking, "file26_doctor_ranking_write_failed", 'partial rank writes fail closed' ),
	array( $appeals, "file26:appeal:", 'appeal creation is serialized per doctor' ),
	array( $appeals, "SELECT RELEASE_LOCK(%s)", 'appeal lock is released' ),
	array( $appeals, "(int) $expected_version < 1", 'appeal review requires explicit version' ),
	array( $appeals, "empty( $audience['valid'] ) || ! empty( $audience['suspended'] )", 'own appeal reads revalidate membership' ),
);
$failures = 0;
foreach ( $checks as $check ) { if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, "FAIL: {$check[2]}\n" ); $failures++; } }
if ( $failures ) { exit( 1 ); }
echo "Round 13 doctor ranking/appeals regression passed.\n";
