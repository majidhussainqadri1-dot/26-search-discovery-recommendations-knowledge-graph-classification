<?php
/** Round 89 REST/security regression guards. */
$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/file-26-search-discovery.php' );
$security = file_get_contents( $root . '/includes/class-file26-security.php' );
$checks = 0;
function f26_r89_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r89_assert( false !== strpos( $bootstrap, 'sabri_file26_allowed_external_resource_url' ) && false !== strpos( $bootstrap, 'normalize_authorization' ), 'R89: external resource allowlist is strict-boolean normalized' );
f26_r89_assert( false !== strpos( $bootstrap, 'rest_post_dispatch' ) && false !== strpos( $bootstrap, '/sabri-search/v1/' ) && false !== strpos( $bootstrap, 'private, no-store' ), 'R89: File 26 REST responses are forced to fail-safe no-store' );
f26_r89_assert( false !== strpos( $bootstrap, 'remove_header' ) && false !== strpos( $bootstrap, 'ETag' ), 'R89: stale ETag validators are removed at the final REST boundary' );
f26_r89_assert( false !== strpos( $security, "'localhost'===$host" ) && false !== strpos( $security, 'FILTER_FLAG_NO_PRIV_RANGE' ) && false !== strpos( $security, 'FILTER_FLAG_NO_RES_RANGE' ), 'R89: external resources continue to reject localhost/private/reserved IP targets' );
echo "PASS: $checks Round 89 regression assertions\n";
