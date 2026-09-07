<?php
/** Round 06 regression: recommendation privacy and valid-member preference gates. */
$root = dirname( __DIR__ );
$code = file_get_contents( $root . '/includes/class-file26-recommendations.php' );
$failures = 0;
$fail = static function ( $message ) use ( &$failures ) { fwrite( STDERR, 'FAIL: ' . $message . "\n" ); $failures++; };

$patterns = array(
	'/security\s*->\s*audience\s*\(\s*\)/' => 'membership assertions are consulted',
	'/empty\s*\(\s*\$audience\s*\[\s*[\'\"]valid[\'\"]\s*\]\s*\)\s*\|\|\s*!\s*empty\s*\(\s*\$audience\s*\[\s*[\'\"]suspended[\'\"]\s*\]\s*\)/' => 'invalid or suspended membership is rejected',
	'/!\s*empty\s*\(\s*\$audience\s*\[\s*[\'\"]is_minor[\'\"]\s*\]\s*\)\s*&&\s*empty\s*\(\s*\$audience\s*\[\s*[\'\"]guardian_verified[\'\"]\s*\]\s*\)/' => 'minor preference mutations require guardian verification',
	'/personalization_enabled/' => 'personalization feature gate is explicit',
	'/consent/' => 'consent state is explicit',
	'/opted_out/' => 'opt-out state is explicit',
	'/not_interested/' => 'negative user control exists',
	'/hide_item/' => 'hide-item control exists',
	'/hide_author/' => 'hide-author control exists',
	'/hide_topic/' => 'hide-topic control exists',
	'/reset\s*\(/' => 'reset operation exists',
	'/opt_out\s*\(/' => 'opt-out operation exists',
);
foreach ( $patterns as $pattern => $label ) {
	if ( ! preg_match( $pattern, $code ) ) { $fail( $label ); }
}
if ( $failures ) { exit( 1 ); }
echo "PASS: round 06 recommendation privacy and membership gates\n";
