<?php
/** Round 92 doctor ranking/appeal regression guards. */
$root = dirname( __DIR__ );
$ranking = file_get_contents( $root . '/includes/class-file26-doctor-ranking.php' );
$appeals = file_get_contents( $root . '/includes/class-file26-doctor-appeals.php' );
$bootstrap = file_get_contents( $root . '/file-26-search-discovery.php' );
$health = file_get_contents( $root . '/includes/class-file26-health.php' );
$checks = 0;
function f26_r92_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r92_assert( false !== strpos( $ranking, "array_key_exists( 'o', \$cursor )" ) && false !== strpos( $ranking, "is_numeric( \$cursor['o'] )" ), 'R92: doctor-ranking cursor requires a numeric offset' );
f26_r92_assert( false !== strpos( $ranking, 'policy_read_failed' ) && false !== strpos( $ranking, 'file26_doctor_policy_read_failed' ), 'R92: doctor policy DB read failures fail closed' );
f26_r92_assert( false !== strpos( $bootstrap, 'sabri_file26_can_appeal_doctor_ranking' ) && false !== strpos( $bootstrap, 'normalize_authorization' ), 'R92: doctor-appeal authorization is strict-boolean normalized' );
f26_r92_assert( false !== strpos( $appeals, 'required_columns' ) && false !== strpos( $appeals, 'schema_physical_ok' ) && false !== strpos( $health, 'appeal_schema_ok' ), 'R92: ranking-appeal schema has physical column parity and health coverage' );
f26_r92_assert( false !== strpos( $appeals, 'file26_appeal_open_state_read_failed' ) && false !== strpos( $appeals, 'file26_doctor_projection_read_failed' ) && false !== strpos( $appeals, 'file26_appeal_review_read_failed' ), 'R92: appeal source/open/review DB read failures cannot masquerade as normal absence' );
echo "PASS: $checks Round 92 regression assertions\n";
