<?php
/** Round 24 regression: search eligibility/cursors and index revocation remain fail-closed and atomic. */
$root = dirname( __DIR__ );
$search = file_get_contents( $root . '/includes/class-file26-search.php' );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$compact = static function ( $source ) { return preg_replace( '/\s+/', '', (string) $source ); };
$search_compact = $compact( $search );
$indexer_compact = $compact( $indexer );
$rest_compact = $compact( $rest );
$failures = 0;
$fail = static function ( $m ) use ( &$failures ) { fwrite( STDERR, 'FAIL: ' . $m . "\n" ); $failures++; };

foreach ( array(
	'\'audience\'=>$audience_fingerprint' => 'cursor is bound to audience eligibility fingerprint',
	"d.safety_classNOTIN('blocked','restricted')" => 'blocked/restricted search documents are excluded',
	'file26_search_read_failed' => 'search DB read failure is explicit',
	'file26_suggest_read_failed' => 'suggest DB read failure is explicit',
	'unset($payload[\'download_url\'])' => 'capability-bearing download URL is stripped from result payload',
) as $needle => $label ) {
	if ( false === strpos( $search_compact, $needle ) ) { $fail( 'Missing search safeguard: ' . $label ); }
}
if ( false === strpos( $rest_compact, 'if(is_wp_error($suggestions)){return$suggestions;}' ) ) {
	$fail( 'Suggestion read failures must propagate through REST.' );
}
foreach ( array(
	'Capability-bearingsigneddeliveryURLsareintentionallyexcluded' => 'signed-delivery exclusion is documented at the index boundary',
	'STARTTRANSACTION' => 'index/revocation mutation has a transaction boundary',
	'file26_node_write_failed' => 'graph-node write failure is explicit',
	'Staletombstonecleanupfailed.' => 'stale tombstone cleanup failure aborts mutation',
	'Documentrevocationandallderivativescouldnotbecompletedatomically.' => 'revocation failure is surfaced atomically',
	"JSON_REMOVE(payload,'$.download_url')" => 'revocation removes any legacy download URL projection',
) as $needle => $label ) {
	if ( false === strpos( $indexer_compact, $needle ) ) { $fail( 'Missing index/revocation safeguard: ' . $label ); }
}
if ( false !== strpos( $indexer, "'download_url'" ) ) {
	$fail( 'Capability-bearing download_url must not remain in the indexed payload allowlist.' );
}
if ( $failures ) { exit( 1 ); }
echo "PASS: round 24 search eligibility, cursor and index atomicity\n";
