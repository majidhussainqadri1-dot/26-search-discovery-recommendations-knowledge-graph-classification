<?php
/** Round 15 regression: merged public topics cannot redirect to stale/non-active concepts. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-routes.php' );
$compact = preg_replace( '/\s+/', '', $source );
$checks = array(
	'!$target||\'active\'!==$target[\'status\']' => 'merged topic target must remain active',
	'wp_safe_redirect(' => 'canonical redirect uses WordPress safe redirect handling',
	'home_url(' => 'canonical redirect remains same-origin',
	'rawurlencode($resolved[\'redirect_to\'][\'slug\'])' => 'canonical topic slug is safely encoded',
	'Thistopicisunavailable.' => 'stale topic fails closed without content leak',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $compact, $needle ) ) {
		fwrite( STDERR, "FAIL: $label\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 15 topic route safety regression passed.\n";
