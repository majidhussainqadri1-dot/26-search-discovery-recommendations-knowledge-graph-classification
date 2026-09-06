<?php
/** Round 03 regression: sensitive queries are never retained in shared cache, mutations invalidate shared results and explicit sorts remain deterministic. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-search.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$checks = array(
	'$sensitive_query = $this->security->contains_sensitive_query( $query )' => 'search sensitivity is classified before cache use',
	'&& ! $sensitive_query' => 'sensitive guest searches cannot use shared result cache',
	'if ( $at === $bt )' => 'equal freshness timestamps receive deterministic tie-break',
	'strcmp( $a[\'canonical_key\'], $b[\'canonical_key\'] )' => 'canonical key is stable sort tie-break',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
$plugin_checks = array(
	'add_action( \'sabri_file26_event\', array( $this, \'invalidate_public_search_cache\' ), 1, 2 )' => 'governed File 26 mutations invalidate shared anonymous search cache',
	"wp_cache_flush_group( 'sabri_file26' )" => 'File 26 cache group is invalidated when supported',
	'wp_cache_flush();' => 'older WordPress/object-cache implementations still fail safe by invalidating cache',
);
foreach ( $plugin_checks as $needle => $label ) { if ( false === strpos( $plugin, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
if ( false !== strpos( $source, '$public_cache = empty( $audience[\'authenticated\'] ) && empty( $filters[\'availability\'] );' ) ) {
	fwrite( STDERR, "FAIL: old sensitive-query cache predicate still present\n" ); $failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Round 03 search integrity regression passed.\n";
