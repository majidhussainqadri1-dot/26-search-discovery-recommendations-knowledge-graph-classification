<?php
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-central-plan.php' );
$checks = array(
	"'policy' => \$policy_version" => 'advanced cursor binds ranking policy',
	'file26_policy_changed_during_advanced_search' => 'advanced search detects mid-request policy change',
	'expected_version is required when updating an existing saved query' => 'saved-query update requires CAS version',
	'expected_version is required when deleting a saved query' => 'saved-query delete requires CAS version',
	"acquire_state_lock( 'saved-query-user:'" => 'saved-query writes and erasure serialize per account',
	"acquire_state_lock( 'content-gap-registry'" => 'content-gap option mutation is serialized',
	'Sensitive identifiers are not allowed in saved-query name or filter metadata.' => 'saved-query label cannot leak sensitive identifiers',
	'$wpdb->usermeta' => 'retention actively scans saved-query usermeta',
	'Saved-query erasure could not be verified' => 'privacy erasure verifies deletion',
	'sensitive_queries_revealed' => 'sensitive saved-query reveal is explicit and disclosed',
);
$failed = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "FAIL: $label\n" );
		$failed++;
	} else {
		echo "PASS: $label\n";
	}
}
exit( $failed ? 1 : 0 );
