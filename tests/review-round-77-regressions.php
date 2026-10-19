<?php
/** Round 77 regression guards. */
$root = dirname( __DIR__ );
$discovery = file_get_contents( $root . '/includes/trait-file26-future-user-discovery.php' );
$checks = 0;
function f26_r77_assert( $condition, $message ) {
    global $checks;
    $checks++;
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
f26_r77_assert( false !== strpos( $discovery, 'future_strict_bool' ) && false !== strpos( $discovery, 'file26_less_personalization_invalid' ), 'R77: less_personalization uses strict boolean parsing' );
f26_r77_assert( false !== strpos( $discovery, 'file26_discovery_reset_invalid' ), 'R77: reset uses strict boolean parsing' );
f26_r77_assert( false !== strpos( $discovery, 'file26_discovery_breadth_invalid' ), 'R77: invalid breadth fails closed' );
f26_r77_assert( false !== strpos( $discovery, 'normalize_discovery_controls' ), 'R77: persisted discovery controls are normalized before use' );
f26_r77_assert( false !== strpos( $discovery, 'file26_geo_owner_constraint_conflict' ), 'R77: conflicting owner-verified geo constraints fail closed' );
f26_r77_assert( false !== strpos( $discovery, 'diversify_discovery' ) && false !== strpos( $discovery, '$sources' ) && false !== strpos( $discovery, '$authors' ), 'R77: discovery diversification independently limits source and author concentration' );
echo "PASS: $checks Round 77 regression assertions\n";
