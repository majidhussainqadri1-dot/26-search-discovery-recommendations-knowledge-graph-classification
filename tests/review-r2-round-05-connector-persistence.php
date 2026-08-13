<?php
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$checks = array(
	'file26_connector_registry_read_failed' => 'registry read failure prevents connector registration',
	'file26_connector_registry_write_failed' => 'registry write failure prevents in-memory availability',
	'if ( is_wp_error( $persisted ) )' => 'register propagates persistence error before registry mutation',
	'health_persistence_failed' => 'health persistence failure cannot remain false-green',
	'safe_health_detail' => 'provider health detail is minimized',
	'connector_registration_failed' => 'boot records safe connector-registration failure evidence',
);
$failed = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failed++; }
	else { echo "PASS: $label\n"; }
}
exit( $failed ? 1 : 0 );
