<?php
/** Round 16 regression: human routes validate route identity and never shared-cache authenticated topic HTML. */
$root = dirname( __DIR__ );
$routes = file_get_contents( $root . '/includes/class-file26-routes.php' );
$search = file_get_contents( $root . '/includes/class-file26-search.php' );

$checks = array(
	array( $routes, "in_array( \$route, array( 'search', 'discover', 'topic' ), true )", 'only canonical File 26 route identities are intercepted' ),
	array( $routes, "'topic' === \$route && ! is_user_logged_in()", 'topic HTML is shared-cacheable only for anonymous users' ),
	array( $routes, "header( 'Cache-Control: public, max-age=300, stale-while-revalidate=600' )", 'anonymous topic cache remains explicitly bounded' ),
	array( $routes, 'nocache_headers()', 'authenticated/non-topic routes are non-shared-cache responses' ),
	array( $search, "d.visibility IN ('public','members','entitled','minor_guarded')", 'authenticated search can contain non-public visibility classes, making cache isolation material' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 16 route/cache privacy regression passed.\n";
