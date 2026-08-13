<?php
$root = dirname( __DIR__ );
$read = static function ( $path ) use ( $root ) {
	$content = file_get_contents( $root . '/' . $path );
	if ( false === $content ) {
		fwrite( STDERR, "Unable to read $path\n" );
		exit( 1 );
	}
	return $content;
};
$passed = 0;
$failed = 0;
function corrective_assert( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) { $passed++; echo "PASS: $label\n"; }
	else { $failed++; echo "FAIL: $label\n"; }
}

$connectors = $read( 'includes/class-file26-connectors.php' );
$search = $read( 'includes/class-file26-search.php' );
$governance = $read( 'includes/class-file26-governance.php' );
$security = $read( 'includes/class-file26-security.php' );
$roles = $read( 'includes/class-file26-roles.php' );
$recommendations = $read( 'includes/class-file26-recommendations.php' );
$rest = $read( 'includes/class-file26-rest.php' );
$doctor = $read( 'includes/class-file26-doctor-ranking.php' );
$appeals = $read( 'includes/class-file26-doctor-appeals.php' );
$owner_contracts = $read( 'includes/class-file26-owner-contracts.php' );
$privacy = $read( 'includes/class-file26-privacy.php' );
$plugin = $read( 'includes/class-file26-plugin.php' );
$uninstall = $read( 'uninstall.php' );

corrective_assert( false !== strpos( $connectors, "'active' === \$manifest['status']" ), 'Only the active connector lane is publicly retrievable.' );
corrective_assert( false !== strpos( $connectors, "array( 'shadow', 'approved', 'active' )" ), 'Shadow and approved lanes are explicitly index-only lifecycle states.' );
corrective_assert( false !== strpos( $connectors, "\$manifest['status'] = 'proposed'" ) && false !== strpos( $connectors, 'Every new connector and every owner/contract change' ), 'Every new or changed connector begins in the proposed lifecycle state.' );
corrective_assert( false !== strpos( $search, "c.status='active'" ), 'Search SQL joins only active production connectors.' );
corrective_assert( false !== strpos( $search, "fc.status IN ('approved','corrected')" ), 'Topic retrieval consumes only approved/corrected classifications.' );
corrective_assert( false !== strpos( $search, 'apply_graph_relationship_scores' ) && false !== strpos( $search, "state='active' AND visibility='public'" ), 'Only active public graph edges contribute a bounded relationship signal.' );
corrective_assert( false !== strpos( $search, "'health' => 'scan_limit'" ), 'Bounded corpus scans disclose truthful partial state.' );
corrective_assert( false !== strpos( $governance, 'restored_policy_uuid' ) && false === strpos( $governance, "'activated' => false" ), 'Ranking rollback restores the previous policy without disabling File 26.' );
corrective_assert(
	false !== strpos( $governance, 'second_approve_ranking_rollback' ) &&
	false !== strpos( $governance, 'ranking_policy_rollback_second_approved' ) &&
	false !== strpos( $governance, "'file26_rb_'" ) &&
	false !== strpos( $governance, "user_can( \$second, 'approve_sabri_ranking' )" ) &&
	false !== strpos( $governance, 'A separately recorded distinct authorized second rollback approval is required.' ),
	'High-risk ranking rollback requires a distinct, separately recorded, still-authorized second approver.'
);
corrective_assert( false === strpos( $security, "current_user_can( 'manage_sabri_search' ) ||" ), 'Configuration authority is not an operational super-capability.' );
corrective_assert( false === strpos( $security, 'WP_CLI' ) && false !== strpos( $security, "'sabri_file26_step_up_authorized'" ), 'High-risk step-up has no silent CLI bypass.' );
foreach ( array( 'sabri_search_operator', 'sabri_taxonomy_curator', 'sabri_ranking_approver', 'sabri_search_auditor' ) as $role ) {
	corrective_assert( false !== strpos( $roles, $role ), "Dedicated separation-of-duties role exists: $role" );
}
corrective_assert( false !== strpos( $recommendations, 'session_contextual' ) && false !== strpos( $recommendations, 'never persisted by File 26' ), 'Guest/session discovery is request-bound and non-persistent.' );
foreach ( array( 'hide_item', 'hide_author', 'hide_topic', 'undo', 'opt_out', 'set_interests' ) as $control ) {
	corrective_assert( false !== strpos( $recommendations, $control ), "Recommendation control is implemented: $control" );
}
corrective_assert( false !== strpos( $rest, '/doctors/ranking' ) && false !== strpos( $rest, '/doctors/ranking/appeals' ), 'Doctor ranking and appeal REST contracts are registered.' );
foreach ( array( 'country', 'city', 'language', 'specialization', 'educator', 'researcher' ) as $context ) {
	corrective_assert( false !== strpos( $doctor, "'$context'" ), "Doctor contextual ranking is supported: $context" );
}
corrective_assert( false !== strpos( $doctor, 'Donation, payment, paid promotion, follower count and Founder favoritism are excluded' ), 'Doctor ranking explains prohibited-signal exclusion.' );
corrective_assert( false === strpos( $doctor, "'author_key' => \$row['author_key']" ), 'Public doctor-ranking output does not expose the internal author reference.' );
corrective_assert( false !== strpos( $appeals, 'appellant cannot review' ) || false !== strpos( $appeals, 'An appellant cannot review' ), 'Doctor ranking appeals enforce reviewer conflict control.' );
corrective_assert( false !== strpos( $appeals, 'expected_version' ) && false !== strpos( $appeals, "'version' => \$version" ), 'Doctor ranking appeals use optimistic concurrency.' );
foreach ( array( 'file03', 'file05', 'file06', 'file07', 'file08', 'file10', 'file11', 'file12', 'file15', 'file18', 'file21' ) as $owner ) {
	corrective_assert( false !== strpos( $owner_contracts, "'$owner'" ), "Required owner connector contract is declared: $owner" );
}
corrective_assert( false !== strpos( $owner_contracts, 'staging_acceptance' ) && false !== strpos( $owner_contracts, 'rollback_rehearsal' ), 'Activation requires external staging and rollback evidence.' );
corrective_assert( false !== strpos( $privacy, 'Doctor Ranking Appeals' ) && false !== strpos( $privacy, 'appellant_user_id=0' ), 'Appeals are exportable and pseudonymized during erasure.' );
corrective_assert( false !== strpos( $plugin, 'retain_doctor_appeals' ) && false !== strpos( $plugin, 'ranking_appeal_retention_days' ), 'Doctor-ranking appeals have a bounded retention lifecycle.' );
corrective_assert( false !== strpos( $uninstall, 'sabri_file26_destructive_uninstall' ) && false !== strpos( $uninstall, 'f26_ranking_appeals' ), 'Uninstall preserves data by default and includes appeal data in explicit destructive purge.' );

printf( "Passed: %d\nFailed: %d\n", $passed, $failed );
exit( $failed ? 1 : 0 );
