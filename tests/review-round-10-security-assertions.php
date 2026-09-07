<?php
/** Round 10 regression: privileged actions remain bound to the authenticated subject and fresh File00 assertions. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-security.php' );
$compact = preg_replace( '/\s+/', '', $source );
$checks = array(
	array( '$audience[\'user_id\']=$current_user_id', 'membership adapter cannot swap request subject' ),
	array( '$audience[\'authenticated\']=$authenticated', 'membership adapter cannot spoof authentication state' ),
	array( 'current_membership_valid()&&current_user_can', 'privileged capabilities require fresh valid membership' ),
	array( 'if(!$this->current_membership_valid())', 'step-up refuses invalid/suspended membership' ),
	array( '$target_scheme!==$home_scheme', 'same-origin URL rejects scheme downgrade' ),
	array( '$target_port!==$home_port', 'same-origin URL rejects port mismatch' ),
	array( 'if(false===$written){returnfalse;}', 'rate-limit storage failure is fail closed' ),
	array( 'null===$count', 'rate-limit null read failure is fail closed' ),
	array( '!empty($wpdb->last_error)', 'rate-limit database read error is fail closed' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $compact, $check[0] ) ) {
		fwrite( STDERR, "FAIL: {$check[1]}\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 10 security assertions regression passed.\n";
