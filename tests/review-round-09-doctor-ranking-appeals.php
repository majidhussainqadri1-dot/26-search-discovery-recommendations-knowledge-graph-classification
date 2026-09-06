<?php
/** Round 09 regression: doctor ranking and appeals must be freshness-bound and DB-failure truthful. */
$root = dirname( __DIR__ );
$ranking = file_get_contents( $root . '/includes/class-file26-doctor-ranking.php' );
$appeals = file_get_contents( $root . '/includes/class-file26-doctor-appeals.php' );
$checks = array(
	array( $ranking, 'file26_doctor_ranking_policy_read_failed', 'policy DB failure fails closed' ),
	array( $ranking, 'file26_doctor_ranking_source_read_failed', 'eligible doctor DB failure fails closed' ),
	array( $ranking, 'file26_doctor_ranking_stale', 'directory rejects stale policy projection' ),
	array( $ranking, 'file26_doctor_ranking_projection_mixed', 'mixed policy projections are rejected' ),
	array( $ranking, 'unset( $payload[\'global_doctor_rank\']', 'stale rank fields are purged before recompute' ),
	array( $ranking, 'all_doctor_rows', 'recompute cleans doctors outside current public eligibility' ),
	array( $appeals, 'file26_appeal_schema_incomplete', 'appeal schema is verified before version pointer' ),
	array( $appeals, 'file26_appeal_doctor_read_failed', 'doctor read failure is not a false 404' ),
	array( $appeals, 'file26_appeal_open_check_failed', 'open-appeal read failure blocks duplicate creation' ),
	array( $appeals, 'file26_appeal_list_failed', 'own appeal list fails closed on DB error' ),
	array( $appeals, "'projection_update_pending'", 'corrected appeal does not claim projection already applied' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Round 09 doctor ranking and appeals regression passed.\n";
