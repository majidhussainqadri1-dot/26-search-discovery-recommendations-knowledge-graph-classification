<?php
/** Regression: version options never substitute for deployed schema-shape verification. */
$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$appeals = file_get_contents( $root . '/includes/class-file26-doctor-appeals.php' );
$checks = array(
	'private function verify_schema_shape()' => 'runtime has an explicit schema-shape verifier',
	'file26_schema_column_missing' => 'missing runtime columns fail closed',
	"'documents' => array( 'canonical_key', 'connector_slug', 'object_id', 'object_version'" => 'document contract columns are verified',
	"'ranking_policies' => array( 'policy_uuid', 'context_name', 'audience', 'version', 'status'" => 'ranking governance columns are verified',
	"'jobs' => array( 'job_uuid', 'job_type', 'status', 'cursor_value', 'counts_json', 'attempts', 'lock_token'" => 'worker recovery columns are verified',
	'$shape = $this->verify_schema_shape();' => 'schema shape is checked even when version options already match',
	'$verified = $this->verify_schema_shape();' => 'schema shape is rechecked after dbDelta repair',
	'Doctor_Appeals::install_schema( true )' => 'appeal schema can be forcibly reconciled after shape drift',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $plugin, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; }
}
if ( false === strpos( $appeals, 'public static function install_schema( $force = false )' ) ) {
	fwrite( STDERR, "FAIL: appeal schema installer lacks forced reconciliation mode\n" );
	$failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Schema-shape parity regression passed.\n";