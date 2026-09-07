<?php
/** Round 14 regression: public routes/REST must preserve truth, revocation safety and keyboard-safe UI. */
$root = dirname( __DIR__ );
$routes = file_get_contents( $root . '/includes/class-file26-routes.php' );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$js = file_get_contents( $root . '/assets/js/file26.js' );
$round12 = file_get_contents( $root . '/tests/review-round-12-privacy-retention.php' );
$compact = static function ( $source ) { return preg_replace( '/\s+/', '', (string) $source ); };
$checks = array(
	array( $routes, 'status_header(404)', 'unknown/missing public routes expose a real 404 path' ),
	array( $routes, "'file26_topic_read_failed'", 'topic DB failure is distinct from not-found' ),
	array( $routes, 'get_error_data()', 'topic HTTP status preserves the underlying failure class' ),
	array( $routes, 'nocache_headers()', 'dynamic public HTML does not replay revoked projections by default' ),
	array( $routes, 'run_search_contract', 'HTML search/topic uses the central governed search contract' ),
	array( $routes, 'ThisFile26presentationtemplateisunavailable.', 'missing templates fail visibly rather than blank-success' ),
	array( $rest, 'if(is_wp_error($results)){return$results;}', 'topic REST propagates search failure instead of false partial success' ),
	array( $rest, 'if(is_wp_error($appeals)){return$appeals;}', 'own-appeals REST does not wrap an error inside a 200 response' ),
	array( $rest, 'sabri_file26_revocation_cache_purge_ready', 'public REST caching requires explicit revocation-purge evidence' ),
	array( $rest, "Cache-Control','no-store,max-age=0", 'public REST is no-store by default' ),
	array( $plugin, 'enforce_rest_cache_safety', 'late REST cache safety gate overrides unsafe central-route caching' ),
	array( $js, 'safeSameOriginUrl', 'suggestion navigation is same-origin URL constrained' ),
	array( $js, "document.createElement('a')", 'suggestions are built with DOM text rather than injected HTML' ),
	array( $js, 'restoreCardFocus', 'undo restores keyboard focus to a valid target' ),
	array( $round12, "false===\$wpdb->query('STARTTRANSACTION')", 'round 12 static test preserves a literal DB transaction assertion' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $compact( $check[0] ), $compact( $check[1] ) ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 14 public surface, cache and accessibility regression passed.\n";
