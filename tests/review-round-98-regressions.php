<?php
/** Round 98 recommendation-transparency and geo-discovery regression guards. */
$root = dirname( __DIR__ );
$discovery = file_get_contents( $root . '/includes/trait-file26-future-user-discovery.php' );
$checks = 0;
function f26_r98_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r98_assert( false !== strpos( $discovery, "\$item['why_this'] = \$this->recommendations->explain( \$item, false, false )" ) && false !== strpos( $discovery, "'why_this_available' => true" ), 'R98: less-personalization keeps non-personal why-this explanations available' );
f26_r98_assert( false !== strpos( $discovery, "'native_recommendation_controls' => \$native_controls" ) && false !== strpos( $discovery, "'interests' => \$interests" ), 'R98: transparency center retains native consent/opt-out/interests control state' );
f26_r98_assert( false !== strpos( $discovery, "'less_personalization_effective' => ! empty( \$controls['less_personalization'] )" ) && false === strpos( $discovery, "'personalization_disabled_by_breadth'" ), 'R98: discovery-breadth response reports the real less-personalization cause' );
f26_r98_assert( false !== strpos( $discovery, "'file26_geo_entity_type_invalid'" ) && false !== strpos( $discovery, "'file26_geo_radius_invalid'" ), 'R98: geo discovery rejects invalid entity type and malformed/out-of-range radius' );
echo "PASS: $checks Round 98 regression assertions\n";
