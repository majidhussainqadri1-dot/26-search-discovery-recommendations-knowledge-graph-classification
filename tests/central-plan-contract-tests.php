<?php
/** Static regressions for the 6-Aug-2026 central-plan File 26 requirements. */
$root = dirname( __DIR__ );
$files = array(
	'bootstrap' => file_get_contents( $root . '/file-26-search-discovery.php' ),
	'plugin' => file_get_contents( $root . '/includes/class-file26-plugin.php' ),
	'central' => file_get_contents( $root . '/includes/class-file26-central-plan.php' ),
	'doctor' => file_get_contents( $root . '/includes/class-file26-doctor-ranking.php' ),
	'css' => file_get_contents( $root . '/assets/css/file26.css' ),
	'workflow' => file_get_contents( $root . '/.github/workflows/qa.yml' ),
);
$assertions = 0;
$failures = 0;
function f26_central_assert( $condition, $message ) {
	global $assertions, $failures;
	$assertions++;
	if ( ! $condition ) {
		$failures++;
		fwrite( STDERR, "FAIL: $message\n" );
	}
}

f26_central_assert( false !== strpos( $files['bootstrap'], 'Version: 1.2.0' ), 'runtime version 1.2.0' );
f26_central_assert( false !== strpos( $files['bootstrap'], "SABRI_FILE26_CONTRACT_VERSION', '1.2'" ), 'contract version 1.2' );
f26_central_assert( false !== strpos( $files['bootstrap'], 'class-file26-central-plan.php' ), 'central-plan implementation loaded' );
f26_central_assert( false !== strpos( $files['bootstrap'], 'augment_search_result' ), 'non-REST search wrapper receives central contracts' );

foreach ( array( '/advanced-search', '/saved-queries', '/ranking-constitution', '/content-gap', '/admin/editorial-radar', '/admin/central-plan-status' ) as $route ) {
	f26_central_assert( false !== strpos( $files['central'], $route ), 'route exists: ' . $route );
}
foreach ( array( 'CV-164', 'CV-165', 'CV-166', 'CV-167', 'CV-168', 'CV-169', 'CV-172', 'CV-173', 'CV-174', 'CV-175', 'F26-CEN-01', 'F26-CEN-02' ) as $requirement ) {
	f26_central_assert( false !== strpos( $files['central'], $requirement ), 'traceability token exists: ' . $requirement );
}

f26_central_assert( false !== strpos( $files['central'], "'used_for_personalization' => false" ), 'saved queries cannot silently personalize ranking' );
f26_central_assert( false !== strpos( $files['central'], 'sabri_file26_encrypt_saved_query' ) && false !== strpos( $files['central'], 'file26_sensitive_save_encryption_unavailable' ), 'sensitive saved queries fail closed without approved encryption' );
f26_central_assert( false !== strpos( $files['central'], "'q' => \$sensitive ? '' : \$q" ), 'sensitive saved-query plaintext is not persisted' );
f26_central_assert( false !== strpos( $files['central'], 'file26_sensitive_saved_query_metadata' ), 'sensitive saved-query metadata is rejected rather than persisted in plaintext' );
f26_central_assert( false !== strpos( $files['central'], "require_step_up( 'saved_query_decrypt' )" ), 'sensitive saved-query decryption requires fresh step-up authorization' );
f26_central_assert( false !== strpos( $files['central'], "public_saved_record( \$record, \$user->ID, false )" ), 'privacy export does not opportunistically decrypt protected query text' );
f26_central_assert( false !== strpos( $files['central'], "'identity_stored' => false" ), 'content-gap registry excludes user identity' );
f26_central_assert( false !== strpos( $files['central'], "'raw_sensitive_query_history_included' => false" ), 'editorial radar excludes raw sensitive history' );
f26_central_assert( false !== strpos( $files['central'], "'fabricated_result' => false" ), 'zero-result recovery cannot fabricate a result' );
f26_central_assert( false !== strpos( $files['central'], 'sabri_file26_zero_result_help_destination' ) && false !== strpos( $files['central'], 'safe_resource_url' ), 'zero-result expert destination remains owner-provided and URL-safe' );
f26_central_assert( false !== strpos( $files['central'], "'fabricated_local_details' => false" ), 'search safety cannot fabricate emergency details' );
f26_central_assert( false !== strpos( $files['central'], 'sabri_file26_verified_emergency_resource' ), 'emergency details require verified provider/filter' );
f26_central_assert( false !== strpos( $files['central'], "empty( \$resource['verified_at'] )" ) && false !== strpos( $files['central'], 'sabri_file26_emergency_resource_max_age' ), 'emergency resources require current verification evidence' );
f26_central_assert( false !== strpos( $files['central'], "'self harm'" ) && false !== strpos( $files['central'], "'safety_support'" ) && false !== strpos( $files['central'], 'گھریلو تشدد' ), 'self-harm and abuse-support query classes are covered' );
f26_central_assert( false !== strpos( $files['central'], "'owner_click_revalidation_required' => true" ), 'owner click-time revalidation remains mandatory' );
f26_central_assert( false !== strpos( $files['central'], "'rights_revalidation_required' => true" ), 'rights revalidation remains mandatory' );
f26_central_assert( false !== strpos( $files['central'], "'language_eligibility_checked' => true" ) && false !== strpos( $files['central'], "'deletion_state_checked' => true" ), 'F26-CEN-01 language/deletion evidence is explicit' );
f26_central_assert( false !== strpos( $files['central'], "'freshness_status' => \$status" ), 'freshness status exposed without false certainty' );
f26_central_assert( false !== strpos( $files['central'], "'unknown_freshness_owner_recheck_required'" ), 'missing freshness evidence stays unknown and requires owner recheck' );
f26_central_assert( false !== strpos( $files['central'], "'paid_or_sponsored_organic_results' => false" ), 'paid organic search is prohibited' );
f26_central_assert( false !== strpos( $files['central'], "'single_free_tier_rank_parity' => true" ), 'single-free-tier ranking parity' );
f26_central_assert( false !== strpos( $files['central'], "'donation'" ) && false !== strpos( $files['central'], "'founder_favoritism'" ), 'forbidden money/favoritism signals are named' );
f26_central_assert( false !== strpos( $files['central'], "'connector' => \$connector" ) && false !== strpos( $files['central'], "'source' => \$source" ), 'advanced search separates connector lane from owner-source filtering' );
f26_central_assert( false !== strpos( $files['central'], "isset( \$meta['visibility'] ) ? \$meta['visibility'] : ''" ), 'advanced access filter fails closed instead of assuming public' );
f26_central_assert( false !== strpos( $files['central'], "\$extended['exact'] && false === \$this->strpos( \$field_haystack" ), 'field-scoped exact phrase must actually occur in selected fields' );
f26_central_assert( false !== strpos( $files['central'], "'continuation_limited' => \$continuation_limited" ) && false !== strpos( $files['central'], "\$next_offset > \$offset" ), 'advanced-search cursor cannot self-loop after a bounded scan ceiling' );
f26_central_assert( false !== strpos( $files['central'], 'advanced-search|' ) && false !== strpos( $files['central'], '12, 60' ), 'advanced search has an explicit abuse-rate gate' );
f26_central_assert( false !== strpos( $files['central'], 'secure_route_response' ) && false !== strpos( $files['central'], "'Cache-Control', 'private, no-store'" ), 'new central REST routes have explicit no-store security semantics' );
f26_central_assert( false !== strpos( $files['central'], "setting_days( 'saved_query_retention_days'" ) && false !== strpos( $files['central'], "setting_days( 'explicit_gap_retention_days'" ), 'saved-query and explicit-gap retention use governed settings' );

f26_central_assert( false !== strpos( $files['doctor'], "'top_10'" ) && false !== strpos( $files['doctor'], "'top_100'" ) && false !== strpos( $files['doctor'], "'top_1000'" ), 'doctor Top 10/100/1000 tiers remain implemented' );
f26_central_assert( false === strpos( $files['doctor'], "'author_key' => \$row['author_key']" ), 'public doctor ranking does not expose internal author references' );
f26_central_assert( false !== strpos( $files['plugin'], "'primary_accent_fallback' => '#087A4E'" ), 'File 25 provider receives Sabri Green fallback only' );
f26_central_assert( false !== strpos( $files['plugin'], "'visual_owner' => 'File 25'" ), 'File 26 does not claim visual ownership' );
f26_central_assert( false !== strpos( $files['plugin'], "'shell_owner'" ) || false !== strpos( $files['central'], "'shell_owner' => 'File 20'" ), 'File 20 shell ownership retained' );

f26_central_assert( false !== strpos( $files['workflow'], 'actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1' ), 'checkout action pinned to approved immutable Node 24 release' );
f26_central_assert( false !== strpos( $files['workflow'], 'actions/setup-node@820762786026740c76f36085b0efc47a31fe5020' ), 'setup-node action pinned to approved immutable Node 24 release' );
f26_central_assert( false !== strpos( $files['workflow'], 'actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a' ), 'upload-artifact action pinned to approved immutable Node 24 release' );

if ( $failures ) {
	fwrite( STDERR, "$failures of $assertions central-plan assertions failed.\n" );
	exit( 1 );
}
echo "$assertions central-plan assertions passed.\n";
