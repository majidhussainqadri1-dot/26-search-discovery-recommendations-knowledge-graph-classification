<?php
/** Round 10 regression: schema option pointers are not proof of deployed DB reality. */
$root = dirname( __DIR__ );
$db = file_get_contents( $root . '/includes/class-file26-db.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$entry = file_get_contents( $root . '/file-26-search-discovery.php' );
$compact = static function ( $value ) { return preg_replace( '/\s+/', '', (string) $value ); };
$db_compact = $compact( $db );
$plugin_compact = $compact( $plugin );
$entry_compact = $compact( $entry );
$checks = array(
	array( $db_compact, $compact( 'public static function verify_schema' ), 'DB exposes actual-table verification' ),
	array( $db_compact, $compact( 'file26_schema_columns_incomplete' ), 'critical columns are deep-verified' ),
	array( $db_compact, $compact( "'primary_color' => '#087A4E'" ), 'default accent matches governing green' ),
	array( $db_compact, $compact( "'search_scan_batch' => 250" ), 'runtime search scan setting is declared and configurable' ),
	array( $db_compact, $compact( "'ranking_appeal_retention_days' => 1095" ), 'appeal retention setting is declared' ),
	array( $plugin_compact, $compact( '$main_verify=DB::verify_schema(false)' ), 'runtime verifies actual primary tables before accepting schema pointers' ),
	array( $plugin_compact, $compact( '$appeal_verify=$this->verify_appeal_schema(false)' ), 'runtime verifies actual appeal table before accepting schema pointers' ),
	array( $plugin_compact, $compact( 'verify_appeal_schema' ), 'appeal schema is independently verified' ),
	array( $plugin_compact, $compact( 'private $admin; private $privacy; private $booted=false; private $schema_ready=false;' ), 'runtime readiness is explicit' ),
	array( $entry_compact, $compact( 'function sabri_file26_runtime()' ), 'global compatibility wrappers share one readiness gate' ),
	array( $entry_compact, $compact( 'File 26 activation failed safely' ), 'activation fails closed on schema failure' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" ); $failures++; }
}
if ( false === strpos( $plugin_compact, $compact( '$main_current&&$appeal_current&&!is_wp_error($main_verify)&&!is_wp_error($appeal_verify)' ) ) ) {
	fwrite( STDERR, "FAIL: option pointers cannot short-circuit actual schema verification\n" );
	$failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Round 10 schema/runtime gate regression passed.\n";
