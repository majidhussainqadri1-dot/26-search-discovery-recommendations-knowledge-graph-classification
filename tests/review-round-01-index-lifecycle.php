<?php
/** Round 01 regression: index/tombstone lifecycle must be serialized and derivative transitions atomic. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$checks = array(
	'SELECT GET_LOCK(%s, 5)' => 'per-object advisory lock acquisition',
	'SELECT RELEASE_LOCK(%s)' => 'per-object advisory lock release',
	'acquire_object_lock($document[\'canonical_key\'])' => 'normal upsert acquires object lock',
	'acquire_object_lock($key)' => 'tombstone path acquires same object lock',
	'finally' => 'lock release is protected by finally',
	'reason_class=IF(VALUES(object_version) >= object_version' => 'older tombstone cannot overwrite newer tombstone metadata',
	'Stale tombstone removal failed.' => 'higher-version resurrection verifies tombstone removal',
	'Node projection write failed.' => 'higher-version resurrection verifies derivative node projection',
	'Document revocation could not be completed atomically.' => 'tombstone cleanup failures roll back as one revocation',
	'Classification purge failed.' => 'classification deletion failure is checked',
	'Edge purge failed.' => 'edge deletion failure is checked',
);
$normalized = preg_replace( '/\s+/', '', $source );
$failures = 0;
foreach ( $checks as $needle => $label ) {
	$haystack = false !== strpos( $needle, 'acquire_object_lock' ) ? $normalized : $source;
	if ( false === strpos( $haystack, $needle ) ) {
		fwrite( STDERR, "FAIL: $label\n" );
		$failures++;
	}
}
if ( substr_count( $source, "START TRANSACTION" ) < 3 ) {
	fwrite( STDERR, "FAIL: upsert, tombstone and reconciliation must each start an explicit transaction\n" );
	$failures++;
}
if ( false !== strpos( $source, '$format = array(' ) ) {
	fwrite( STDERR, "FAIL: obsolete unused format array remains in index writer\n" );
	$failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Round 01 index lifecycle regression passed.\n";
