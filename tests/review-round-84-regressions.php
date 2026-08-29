<?php
/** Round 84 search/cursor/cache truth regression guards. */
$root = dirname( __DIR__ );
$search = file_get_contents( $root . '/includes/class-file26-search.php' );
$checks = 0;
function f26_r84_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r84_assert( false !== strpos( $search, "array_key_exists( 'o', \$cursor )" ) && false !== strpos( $search, "is_numeric( \$cursor['o'] )" ), 'R84: signed search cursors require an explicit numeric offset' );
f26_r84_assert( false !== strpos( $search, "file26_search_read_failed" ) && false !== strpos( $search, "null === \$rows" ) && false !== strpos( $search, "\$wpdb->last_error" ), 'R84: candidate DB read failure is not reported as an empty result set' );
f26_r84_assert( false !== strpos( $search, '$public_cache = false;' ) && false !== strpos( $search, 'mutation is bound to a cache epoch' ), 'R84: stale public result caching is fail-safe disabled until mutation-bound invalidation exists' );
echo "PASS: $checks Round 84 regression assertions\n";
