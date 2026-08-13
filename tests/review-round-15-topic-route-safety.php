<?php
/** Round 15 regression: merged public topics cannot redirect to stale/non-active concepts. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-routes.php' );
$checks = array(
	"! $target || 'active' !== $target['status']" => 'merged topic target must remain active',
	"wp_safe_redirect( home_url( '/topics/' . $target['slug'] . '/' ), 301 )" => 'canonical redirect remains same-origin',
	"This topic is unavailable." => 'stale topic fails closed without content leak',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
if ( $failures ) { exit( 1 ); }
echo "Round 15 topic route safety regression passed.\n";
