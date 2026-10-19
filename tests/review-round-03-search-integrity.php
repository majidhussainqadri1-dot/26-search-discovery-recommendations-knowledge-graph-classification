<?php
/** Round 03 regression: no File 26 shared result cache can outlive an owner revocation; sorting remains deterministic. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-search.php' );
$checks = array(
	'if ( $at === $bt )' => 'equal freshness timestamps receive deterministic tie-break',
	'strcmp( $a[\'canonical_key\'], $b[\'canonical_key\'] )' => 'canonical key is stable sort tie-break',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
if ( preg_match( '/wp_cache_(?:get|set)\s*\(/', $source ) ) {
	fwrite( STDERR, "FAIL: a shared result cache may serve a deleted or suspended owner record\\n" ); $failures++;
}

if ( $failures ) { exit( 1 ); }
echo "Round 03 search integrity regression passed.\n";
