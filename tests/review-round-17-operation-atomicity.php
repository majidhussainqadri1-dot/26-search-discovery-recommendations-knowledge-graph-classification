<?php
/** Round 17 regression: reconciliation and settings success claims require verified persistence boundaries. */
$root = dirname( __DIR__ );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$admin = file_get_contents( $root . '/includes/class-file26-admin.php' );

$checks = array(
	array( $indexer, 'if(false===$wpdb->query(\'START TRANSACTION\'))', 'reconciliation verifies transaction start before destructive queries' ),
	array( $indexer, 'Deletion and graph reconciliation transaction could not start.', 'transaction-start failure is explicit' ),
	array( $indexer, 'if(false===$wpdb->query(\'COMMIT\'))', 'reconciliation verifies commit' ),
	array( $indexer, '$wpdb->query(\'ROLLBACK\')', 'reconciliation failure rolls back' ),
	array( $admin, '$requested = array(', 'settings success is bound to an explicit requested state' ),
	array( $admin, '$persisted = DB::settings()', 'settings are read back after persistence' ),
	array( $admin, '! array_key_exists( $key, $persisted ) || $persisted[ $key ] !== $value', 'every requested setting is verified exactly' ),
	array( $admin, 'No success state is reported.', 'persistence mismatch fails without success redirect' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 17 operation atomicity/truth regression passed.\n";
