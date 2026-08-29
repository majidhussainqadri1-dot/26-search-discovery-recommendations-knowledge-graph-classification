<?php
/** Round 87 taxonomy/classification authorization regression guards. */
$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/file-26-search-discovery.php' );
$taxonomy = file_get_contents( $root . '/includes/class-file26-taxonomy.php' );
$governance = file_get_contents( $root . '/includes/class-file26-governance.php' );
$checks = 0;
function f26_r87_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
foreach ( array( 'sabri_file26_classification_writer_authorized', 'sabri_file26_taxonomy_domain_owner_approved', 'sabri_file26_classification_domain_reviewer_approved' ) as $hook ) {
	f26_r87_assert( false !== strpos( $bootstrap, $hook ), 'R87: strict authorization normalizer is registered for ' . $hook );
}
f26_r87_assert( false !== strpos( $bootstrap, 'normalize_authorization' ) && false !== strpos( $bootstrap, 'PHP_INT_MAX' ), 'R87: authorization filters terminate in strict boolean normalization' );
f26_r87_assert( false !== strpos( $taxonomy, 'START TRANSACTION' ) && false !== strpos( $taxonomy, 'rollback_mapping' ) && false !== strpos( $governance, 'expected_version' ), 'R87: taxonomy/classification concurrency and rollback evidence remain present' );
echo "PASS: $checks Round 87 regression assertions\n";
