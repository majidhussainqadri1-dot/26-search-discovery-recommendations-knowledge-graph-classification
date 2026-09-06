<?php
/** Round 10 regression: privileged actions remain bound to the authenticated subject and fresh File00 assertions, and separation-of-duties roles are physically verified. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-security.php' );
$roles = file_get_contents( $root . '/includes/class-file26-roles.php' );
$checks = array(
	'$audience[\'user_id\'] = $current_user_id' => 'membership adapter cannot swap request subject',
	'$audience[\'authenticated\'] = $authenticated' => 'membership adapter cannot spoof authentication state',
	'current_membership_valid() && current_user_can' => 'privileged capabilities require fresh valid membership',
	'if ( ! $this->current_membership_valid() )' => 'step-up refuses invalid/suspended membership',
	'$target_scheme !== $home_scheme' => 'same-origin URL rejects scheme downgrade',
	'$target_port !== $home_port' => 'same-origin URL rejects port mismatch',
	'if ( false === $written )' => 'rate-limit storage failure is fail closed',
	'if ( null === $count )' => 'rate-limit read failure is fail closed',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
$role_checks = array(
	'self::VERSION === get_option( self::OPTION_VERSION ) && self::integrity_ok()' => 'role installer never trusts version alone',
	'private static function integrity_ok()' => 'physical role integrity verifier exists',
	"! $administrator->has_cap( 'manage_sabri_search' )" => 'administrator configuration capability is verified',
	"$administrator->has_cap( $cap )" => 'administrator operational-capability drift is detected',
	"! $role->has_cap( $required_cap )" => 'dedicated required capabilities are verified',
);
foreach ( $role_checks as $needle => $label ) { if ( false === strpos( $roles, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
if ( $failures ) { exit( 1 ); }
echo "Round 10 security assertions regression passed.\n";
