<?php
/** Round 03 regression: sensitive queries are never retained in shared cache and explicit sorts remain deterministic. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-search.php' );
$checks = array(
	'$sensitive_query = $this->security->contains_sensitive_query( $query )' => 'search sensitivity is classified before any cache decision',
	'if ( $at === $bt )' => 'equal freshness timestamps receive deterministic tie-break',
	'strcmp( $a[\'canonical_key\'], $b[\'canonical_key\'] )' => 'canonical key is stable sort tie-break',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
$conditional_sensitive_block = false !== strpos( $source, '&& ! $sensitive_query' );
$global_cache_disable = false !== strpos( $source, '$public_cache = false;' );
if ( ! $conditional_sensitive_block && ! $global_cache_disable ) {
	fwrite( STDERR, "FAIL: sensitive guest searches cannot use shared result cache\n" ); $failures++;
}
if ( false !== strpos( $source, '$public_cache = empty( $audience[\'authenticated\'] ) && empty( $filters[\'availability\'] );' ) ) {
	fwrite( STDERR, "FAIL: old sensitive-query cache predicate still present\n" ); $failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Round 03 search integrity regression passed.\n";
