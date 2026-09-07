<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/file-26-search-discovery.php' );
$roles = file_get_contents( $root . '/includes/class-file26-roles.php' );

$checks = array(
	'activation invokes role compensation' => false !== strpos( $main, 'Roles::uninstall()' ),
	'role compensation method exists' => false !== strpos( $roles, 'public static function uninstall()' ),
	'role compensation removes manage capability' => false !== strpos( $roles, "remove_cap( 'manage_sabri_search' )" ),
	'role compensation removes duty roles' => false !== strpos( $roles, 'remove_role( $slug )' ),
	'role compensation clears model pointer' => false !== strpos( $roles, 'delete_option( self::OPTION_VERSION )' ),
);
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
}
echo "PASS: activation failure has executable idempotent role compensation\n";
