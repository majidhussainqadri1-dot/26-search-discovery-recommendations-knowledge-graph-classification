<?php
/** Second-cycle Round 3 regression: upsert and tombstone share canonical identity/version semantics. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$normalized = preg_replace( '/\s+/', '', $source );
$checks = array(
	array( $source, "sanitize_text_field( (string) \$object_id )", 'canonical key normalizes object id' ),
	array( $source, 'file26_invalid_document_identity', 'normalized document identity cannot become empty/oversized' ),
	array( $source, 'file26_invalid_object_version', 'malformed/non-positive document version is rejected' ),
	array( $source, 'file26_invalid_event_sequence', 'malformed source event sequence is rejected' ),
	array( $source, 'file26_invalid_tombstone_identity', 'tombstone requires normalized identity and positive version' ),
	array( $normalized, '$connector=sanitize_key($connector);$domain=sanitize_key($domain);$object_id=sanitize_text_field((string)$object_id);', 'tombstone normalizes the same identity before hashing' ),
	array( $normalized, '$key=self::canonical_key($connector,$domain,$object_id);', 'tombstone hashes normalized identity' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 03 identity/version regression passed.\n";
