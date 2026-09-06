<?php
/** Round 15 regression: schema-version parity cannot hide physical column/index drift. */
$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/file-26-search-discovery.php' );
$integrity = file_get_contents( $root . '/includes/class-file26-schema-integrity.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$health = file_get_contents( $root . '/includes/class-file26-health.php' );

$checks = array(
	array( $bootstrap, 'class-file26-schema-integrity.php', 'physical schema verifier is loaded' ),
	array( $integrity, 'information_schema.COLUMNS', 'column shape is verified from physical metadata' ),
	array( $integrity, 'information_schema.STATISTICS', 'index shape is verified from physical metadata' ),
	array( $integrity, "'ranking_appeals'", 'appeals table shape is included' ),
	array( $integrity, "'missing_columns'", 'missing columns are surfaced' ),
	array( $integrity, "'missing_indexes'", 'missing indexes are surfaced' ),
	array( $plugin, 'Schema_Integrity::snapshot()', 'runtime migration uses structural schema snapshot' ),
	array( $plugin, "empty( \$shape['main_complete'] )", 'main schema drift forces repair' ),
	array( $plugin, "empty( \$shape['appeals_complete'] )", 'appeals schema drift forces repair' ),
	array( $plugin, 'delete_option( Doctor_Appeals::OPTION_SCHEMA )', 'appeals dbDelta fast-path is bypassed for physical drift repair' ),
	array( $plugin, "empty( \$shape['complete'] )", 'runtime remains fail-closed if repair is incomplete' ),
	array( $health, 'empty( $integrity[\'complete\'] )', 'health reports structural schema drift' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 15 physical schema integrity regression passed.\n";
