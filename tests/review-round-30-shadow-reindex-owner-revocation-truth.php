<?php
/** Round 30 regression: shadow rebuild preserves owner scope, source monotonicity and revocation-version truth. */
$root = dirname( __DIR__ );
$shadow = file_get_contents( $root . '/includes/class-file26-shadow-reindex.php' );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$compact = static function ( $source ) { return preg_replace( '/\s+/', '', (string) $source ); };
$s = $compact( $shadow );
$failures = 0;
$fail = static function ( $m ) use ( &$failures ) { fwrite( STDERR, 'FAIL: ' . $m . "\n" ); $failures++; };
$checks = array(
	"\$this->stage(\$shadow,\$item,\$scope)" => 'staging receives the governed job scope',
	'file26_shadow_scope_violation' => 'out-of-connector/out-of-scope batches fail closed',
	'privatefunctionmatches_scope(array$d,array$scope)' => 'scope matcher is executable',
	"\$d['connector_slug']!==sanitize_key(\$scope['connector'])" => 'candidate connector must equal job connector',
	'file26_shadow_active_version_read_failed' => 'active version read failures are explicit',
	'file26_shadow_source_version_stale' => 'stale snapshot versions fail closed',
	'file26_shadow_tombstone_precedence' => 'equal-version tombstones retain precedence over searchable candidates',
	"shadow_version" => 'cutover carries staged restrictive source versions into removal evidence',
	"\$version=max((int)\$r['object_version'],\$shadow_version)" => 'tombstone version preserves the highest active/restrictive source version',
	"\$where=\"stateIN('published','active','corrected','retracted')ANDvisibility<>'restricted'\"" => 'parity defines an explicit searchable-only predicate',
	"COUNT(*)FROM`\$shadow`WHERE\$where" => 'parity count applies the searchable-only predicate',
	"canonical_key,checksumFROM`\$shadow`WHERE\$where" => 'parity checksum applies the same searchable-only predicate',
	"SELECT\$colsFROM`\$shadow`WHERE\$promotable" => 'restrictive markers are never promoted into active search documents',
	"FROM`\$shadow`WHERE\$promotableONDUPLICATEKEYUPDATE" => 'restrictive markers are never promoted into graph nodes',
);
foreach ( $checks as $needle => $label ) { if ( false === strpos( $s, $compact( $needle ) ) ) { $fail( 'Missing shadow safeguard: ' . $label ); } }
$old_skip = "if(in_array(\$d['state'],array('deleted','suspended','restricted','rejected','private'),true)||'restricted'==\$d['visibility']){returntrue;}";
if ( false !== strpos( $s, $compact( $old_skip ) ) ) { $fail( 'Restrictive source rows must not be discarded before cutover can preserve their version evidence.' ); }

// Lock payload projection parity so rebuild and incremental lanes cannot silently drift again.
$extract_allowed = static function ( $code, $method ) {
	if ( ! preg_match( '/(?:private|public) function ' . preg_quote( $method, '/' ) . '\s*\([^)]*\)\s*\{.*?\$allowed\s*=\s*array\((.*?)\);/s', $code, $m ) ) { return null; }
	preg_match_all( "/'([^']+)'/", $m[1], $keys );
	return $keys[1];
};
$index_allowed = $extract_allowed( $indexer, 'sanitize_payload' );
$shadow_allowed = $extract_allowed( $shadow, 'safe_payload' );
if ( ! is_array( $index_allowed ) || ! is_array( $shadow_allowed ) || $index_allowed !== $shadow_allowed ) {
	$fail( 'Shadow and incremental payload allowlists must remain identical and ordered.' );
}
$doctor_score = "/elseif\s*\(\s*'doctor_rank_score'\s*={2,3}\s*\\\$k\s*\)\s*\{\s*\\\$c\s*\[\s*\\\$k\s*\]\s*=\s*min\s*\(\s*100\s*,\s*max\s*\(\s*0\s*,\s*\(float\)\s*\\\$p\s*\[\s*\\\$k\s*\]\s*\)\s*\)\s*;/";
$ordinary_score = "/elseif\s*\(\s*preg_match\s*\(\s*'\/_score\\\$\/'\s*,\s*\\\$k\s*\)\s*\)\s*\{\s*\\\$c\s*\[\s*\\\$k\s*\]\s*=\s*min\s*\(\s*1\s*,\s*max\s*\(\s*0\s*,\s*\(float\)\s*\\\$p\s*\[\s*\\\$k\s*\]\s*\)\s*\)\s*;/";
if ( ! preg_match( $doctor_score, $shadow ) || ! preg_match( $ordinary_score, $shadow ) ) {
	$fail( 'Shadow score bounds must match incremental projection semantics.' );
}
if ( $failures ) { exit( 1 ); }
echo "PASS: round 30 shadow rebuild owner, revocation and projection truth\n";
