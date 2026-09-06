<?php
/** Round 11 regression: auth claims, cursors, external evidence and duties fail closed. */
$root = dirname( __DIR__ );
$security = file_get_contents( $root . '/includes/class-file26-security.php' );
$roles = file_get_contents( $root . '/includes/class-file26-roles.php' );
$db = file_get_contents( $root . '/includes/class-file26-db.php' );
$graph = file_get_contents( $root . '/includes/class-file26-graph.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$checks = array(
	array( $security, "'exp'", 'signed cursor has an expiry claim' ),
	array( $security, 'sabri_file26_cursor_ttl', 'cursor lifetime is bounded and configurable' ),
	array( $security, 'esc_url_raw( $url, array( \'https\' ) )', 'external resources require HTTPS' ),
	array( $security, 'file26_audit_write_failed', 'audit persistence failure is explicit' ),
	array( $security, 'access\\s*token', 'sensitive query detection covers access tokens' ),
	array( $roles, 'public static function verify()', 'role model is verifiable' ),
	array( $roles, 'file26_role_model_privilege_overlap', 'duty overlap fails closed' ),
	array( $db, 'Bootstrap only configuration authority', 'DB bootstrap does not grant operational super-capabilities' ),
	array( $graph, "array('https')", 'graph external evidence is HTTPS-only' ),
	array( $graph, "apply_filters('sabri_file26_allowed_evidence_url',false", 'external evidence requires explicit allowlisting' ),
	array( $plugin, 'separation-of-duties role model could not be verified', 'runtime fails closed if role verification fails' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Round 11 security boundaries regression passed.\n";
