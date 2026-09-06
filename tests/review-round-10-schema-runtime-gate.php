<?php
/** Round 10 regression: schema option pointers are not proof of deployed DB reality. */
$root = dirname( __DIR__ );
$db = file_get_contents( $root . '/includes/class-file26-db.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$entry = file_get_contents( $root . '/file-26-search-discovery.php' );
$checks = array(
	array( $db, 'public static function verify_schema', 'DB exposes actual-table verification' ),
	array( $db, 'file26_schema_columns_incomplete', 'critical columns are deep-verified' ),
	array( $db, "'primary_color' => '#087A4E'", 'default accent matches governing green' ),
	array( $db, "'search_scan_batch' => 250", 'runtime search scan setting is declared and configurable' ),
	array( $db, "'ranking_appeal_retention_days' => 1095", 'appeal retention setting is declared' ),
	array( $plugin, 'option pointers are never treated as proof', 'runtime documents the truth boundary' ),
	array( $plugin, 'DB::verify_schema( false )', 'runtime verifies actual tables before early success' ),
	array( $plugin, 'verify_appeal_schema', 'appeal schema is independently verified' ),
	array( $plugin, 'private $admin; private $privacy; private $booted = false; private $schema_ready = false;', 'runtime readiness is explicit' ),
	array( $entry, 'function sabri_file26_runtime()', 'global compatibility wrappers share one readiness gate' ),
	array( $entry, 'File 26 activation failed safely', 'activation fails closed on schema failure' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Round 10 schema/runtime gate regression passed.\n";
