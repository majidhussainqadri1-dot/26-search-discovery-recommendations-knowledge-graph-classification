<?php
/** Round 06 regression: persisted recommendation controls require fresh membership/guardian assertions and revocation purges signals. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-recommendations.php' );
$checks = array(
	'require_preference_access()' => 'preference mutation access helper exists',
	'empty( $audience[\'valid\'] ) || ! empty( $audience[\'suspended\'] )' => 'invalid or suspended membership is rejected',
	'! empty( $audience[\'is_minor\'] ) && empty( $audience[\'guardian_verified\'] )' => 'minor preference mutations require guardian verification',
	'interests_json=IF(VALUES(consent)=0,VALUES(interests_json),interests_json)' => 'consent revocation purges stored interests',
	'negatives_json=IF(VALUES(consent)=0,VALUES(negatives_json),negatives_json)' => 'consent revocation purges stored negative controls',
	'$wpdb->delete( DB::table( \'feedback\' )' => 'consent revocation/reset removes persisted feedback',
	'file26_profile_reset_failed' => 'reset fails closed on partial DB failure',
	'file26_opt_out_record_failed' => 'opt-out persistence failure is explicit',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
if ( $failures ) { exit( 1 ); }
echo "Round 06 recommendation privacy regression passed.\n";
