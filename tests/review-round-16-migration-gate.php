<?php
/** Historical migration-gate regression, kept semantic after the stronger 2026-09-06 physical-shape verifier. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$integrity = file_get_contents( $root . '/includes/class-file26-schema-integrity.php' );
$checks = array(
	array( $source, '$migration = $this->ensure_schema_current()', 'boot performs migration gate before connector runtime' ),
	array( $source, 'file26:schema-migration', 'migration is serialized across concurrent requests' ),
	array( $source, 'Schema_Integrity::snapshot()', 'version parity is bound to physical schema verification' ),
	array( $source, "empty( \$shape['main_complete'] )", 'main physical drift forces repair' ),
	array( $source, "empty( \$shape['appeals_complete'] )", 'appeals physical drift forces repair' ),
	array( $source, 'delete_option( Doctor_Appeals::OPTION_SCHEMA )', 'appeals repair cannot be skipped by a stale version option' ),
	array( $source, "empty( \$shape['complete'] )", 'post-migration shape is reverified before runtime exposure' ),
	array( $source, "'activated' => false", 'migration failure disables runtime activation' ),
	array( $source, "'public_search_enabled' => false", 'migration failure prevents search serving' ),
	array( $source, 'return $this->ensure_schema_current()', 'admin upgrade uses same migration discipline' ),
	array( $integrity, 'information_schema.COLUMNS', 'required columns are physically verified' ),
	array( $integrity, 'information_schema.STATISTICS', 'required indexes are physically verified' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Historical migration gate regression passed against structural schema verification.\n";
