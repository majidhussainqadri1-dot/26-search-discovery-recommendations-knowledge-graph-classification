<?php
$root = dirname( __DIR__ );
$code = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$fail = static function ( $m ) { fwrite( STDERR, "FAIL: {$m}\n" ); exit( 1 ); };
foreach ( array(
    'array_intersect_key( $manifest, array_flip( $public_keys ) )',
    "file26_connector_registry_read_failed",
    "file26_connector_registry_write_failed",
    "if ( is_wp_error( $persisted ) )",
    "health_state_write_failed",
    "registry_read_failed",
) as $needle ) {
    if ( false === strpos( $code, $needle ) ) { $fail( 'Missing connector safeguard: ' . $needle ); }
}
if ( false !== strpos( $code, "foreach ( array( 'list_batch', 'can_view', 'health', 'fetch_object', 'secret', 'token', 'credentials' ) as $private_key" ) ) {
    $fail( 'Connector persistence must use a public metadata allowlist, not a secret-name blacklist.' );
}
echo "PASS: round 25 connector registry durability and public-safe persistence\n";
