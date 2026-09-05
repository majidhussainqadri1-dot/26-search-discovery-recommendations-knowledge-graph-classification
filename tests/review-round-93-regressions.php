<?php
/** Round 93 routes/front-end/accessibility regression guards. */
$root = dirname( __DIR__ );
$routes = file_get_contents( $root . '/includes/class-file26-routes.php' );
$search = file_get_contents( $root . '/templates/search.php' );
$js = file_get_contents( $root . '/assets/js/file26.js' );
$css = file_get_contents( $root . '/assets/css/file26.css' );
$checks = 0;
function f26_r93_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r93_assert( false !== strpos( $routes, 'nocache_headers();' ) && false === strpos( $routes, 'Cache-Control: public, max-age=300' ), 'R93: routed topic HTML cannot use stale shared-public cache without mutation-aware invalidation' );
f26_r93_assert( false !== strpos( $search, 'role="combobox"' ) && false !== strpos( $search, 'aria-autocomplete="list"' ), 'R93: autocomplete input exposes combobox semantics' );
f26_r93_assert( false !== strpos( $js, 'data-url=' ) && false !== strpos( $js, 'role="option"' ) && false === strpos( $js, '<a href=' ), 'R93: listbox options have no nested interactive link' );
f26_r93_assert( false !== strpos( $css, '.sabri-f26__suggestions [role="option"]' ), 'R93: suggestion styling follows option markup' );
echo "PASS: $checks Round 93 regression assertions\n";
