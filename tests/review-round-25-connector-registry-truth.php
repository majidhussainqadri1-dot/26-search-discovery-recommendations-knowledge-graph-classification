<?php
/** Round 25 regression: connector registry persistence remains public-safe and failure-truthful. */
$root = dirname( __DIR__ );
$code = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$compact = preg_replace( '/\s+/', '', $code );
$failures = 0;
$fail = static function ( $m ) use ( &$failures ) { fwrite( STDERR, 'FAIL: ' . $m . "\n" ); $failures++; };
foreach ( array(
	'array_intersect_key($manifest,array_flip($public_keys))' => 'connector persistence uses a public metadata allowlist',
	'file26_connector_registry_read_failed' => 'connector registry read failure is explicit',
	'file26_connector_registry_write_failed' => 'connector registry write failure is explicit',
	'if(is_wp_error($persisted))' => 'persist failure propagates before runtime registration',
	'health_state_write_failed' => 'health-state persistence failure is explicit',
	'registry_read_failed' => 'degraded-domain registry read failure is disclosed',
) as $needle => $label ) {
	if ( false === strpos( $compact, $needle ) ) { $fail( 'Missing connector safeguard: ' . $label ); }
}
$forbidden_secret_blacklist = 'foreach(array(\'list_batch\',\'can_view\',\'health\',\'fetch_object\',\'secret\',\'token\',\'credentials\')as$private_key';
if ( false !== strpos( $compact, $forbidden_secret_blacklist ) ) {
	$fail( 'Connector persistence must use a public metadata allowlist, not a secret-name blacklist.' );
}
if ( $failures ) { exit( 1 ); }
echo "PASS: round 25 connector registry durability and public-safe persistence\n";
