<?php
/** File 26 review: dynamic revocation must not be hidden by public caches or false REST success. */
$root = dirname( __DIR__ );
$search = file_get_contents( $root . '/includes/class-file26-search.php' );
$rest   = file_get_contents( $root . '/includes/class-file26-rest.php' );
$routes = file_get_contents( $root . '/includes/class-file26-routes.php' );
$checks = array(
    array( false === strpos( $search, 'wp_cache_get(' ) && false === strpos( $search, 'wp_cache_set(' ), 'A revoked document must never be returned by a shared search-result object cache.' ),
    array( false === strpos( $rest, "'public, max-age=" ), 'REST projections must not be publicly cacheable without verified owner revocation invalidation.' ),
    array( false !== strpos( $rest, "'private, no-store'" ), 'REST projections must explicitly prevent shared caching.' ),
    array( false !== strpos( $routes, 'nocache_headers();' ) && false === strpos( $routes, 'Cache-Control: public' ), 'Search, discovery and topic HTML routes must suppress public caching.' ),
    array( false !== strpos( $rest, 'return $this->respond( $this->indexer->reconcile() );' ), 'Reconciliation errors must propagate to the REST caller.' ),
    array( false === strpos( $rest, "array( 'reconciled' => true )" ), 'Reconciliation must not fabricate success.' ),
);
$failed = 0;
foreach ( $checks as $check ) {
    if ( ! $check[0] ) { fwrite( STDERR, 'FAIL: ' . $check[1] . "\n" ); $failed++; }
}
if ( $failed ) { exit( 1 ); }
echo "File 26 revocation/reconciliation regressions passed.\n";
