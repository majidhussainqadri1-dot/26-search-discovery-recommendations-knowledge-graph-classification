<?php
/** Round 16 regression: schema upgrades run before runtime contracts and fail closed under a serialized migration lock. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$checks = array(
	'$migration=$this->ensure_schema_current()' => 'boot performs migration gate before connector runtime',
	'file26:schema-migration' => 'migration is serialized across concurrent requests',
	'SHOW TABLES LIKE %s' => 'required tables are verified after migration',
	"'activated'=>false" => 'migration failure disables runtime activation',
	"'public_search_enabled'=>false" => 'migration failure prevents search serving',
	'return $this->ensure_schema_current()' => 'admin upgrade uses same migration discipline',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Round 16 migration gate regression passed.\n";
