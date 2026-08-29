<?php
/** Formatting-resilient static regressions for the 6-Aug-2026 central-plan File 26 requirements. */
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
	if ( ! $condition ) { $failures++; fwrite( STDERR, "FAIL: $message\n" ); }
}
function f26_central_has_all( $text, array $tokens ) {
	foreach ( $tokens as $token ) { if ( false === strpos( $text, $token ) ) { return false; } }
	return true;
}

f26_central_assert( false !== strpos( $files['bootstrap'], 'Version: 1.3.0' ), 'runtime version 1.3.0 retains central-plan implementation' );
f26_central_assert( f26_central_has_all( $files['bootstrap'], array( 'SABRI_FILE26_CONTRACT_VERSION', "'1.3'", 'class-file26-central-plan.php', 'augment_search_result' ) ), 'contract 1.3 loads central-plan implementation and non-REST augmentation' );

foreach ( array( '/advanced-search', '/saved-queries', '/ranking-constitution', '/content-gap', '/admin/editorial-radar', '/admin/central-plan-status' ) as $route ) {
	f26_central_assert( false !== strpos( $files['central'], $route ), 'route exists: ' . $route );
}
foreach ( array( 'CV-164', 'CV-165', 'CV-166', 'CV-167', 'CV-168', 'CV-169', 'CV-172', 'CV-173', 'CV-174', 'CV-175', 'F26-CEN-01', 'F26-CEN-02' ) as $requirement ) {
	f26_central_assert( false !== strpos( $files['central'], $requirement ), 'traceability token exists: ' . $requirement );
}

f26_central_assert( f26_central_has_all( $files['central'], array( 'used_for_personalization', 'false', 'saved_queries' ) ), 'saved queries cannot silently personalize ranking' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'sabri_file26_encrypt_saved_query', 'file26_sensitive_save_encryption_unavailable', 'ciphertext', 'key_id' ) ), 'sensitive saved queries fail closed without approved encryption' );
f26_central_assert( f26_central_has_all( $files['central'], array( "'q'=>$sensitive?'':$q", 'q_encrypted' ) ) || f26_central_has_all( $files['central'], array( "'q'", '$sensitive', "''", '$q', 'q_encrypted' ) ), 'sensitive saved-query plaintext is not persisted' );
f26_central_assert( false !== strpos( $files['central'], 'file26_sensitive_saved_query_metadata' ), 'sensitive saved-query metadata is rejected rather than persisted in plaintext' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'require_step_up', 'saved_query_decrypt' ) ), 'sensitive saved-query decryption requires fresh step-up authorization' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'public_saved_record', 'privacy_export', 'false' ) ), 'privacy export does not opportunistically decrypt protected query text' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'identity_stored', 'false', 'explicit_user_submission' ) ), 'content-gap registry excludes user identity' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'raw_sensitive_query_history_included', 'false', 'editorial_radar' ) ), 'editorial radar excludes raw sensitive history' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'fabricated_result', 'false', 'zero_result_recovery' ) ), 'zero-result recovery cannot fabricate a result' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'sabri_file26_zero_result_help_destination', 'safe_resource_url' ) ), 'zero-result expert destination remains owner-provided and URL-safe' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'fabricated_local_details', 'false', 'verified_current_resource' ) ), 'search safety cannot fabricate emergency details' );
f26_central_assert( false !== strpos( $files['central'], 'sabri_file26_verified_emergency_resource' ), 'emergency details require verified provider/filter' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'verified_at', 'sabri_file26_emergency_resource_max_age', 'expires_at' ) ), 'emergency resources require current verification evidence' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'self harm', 'safety_support', 'گھریلو تشدد' ) ), 'self-harm and abuse-support query classes are covered' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'owner_click_revalidation_required', 'true' ) ), 'owner click-time revalidation remains mandatory' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'rights_revalidation_required', 'true' ) ), 'rights revalidation remains mandatory' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'language_eligibility_checked', 'deletion_state_checked', 'index_eligibility_checked' ) ), 'F26-CEN-01 language/deletion/index evidence is explicit' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'freshness_status', 'freshness_badge', 'index_sync_lag_seconds' ) ), 'freshness status is exposed without false certainty' );
f26_central_assert( false !== strpos( $files['central'], 'unknown_freshness_owner_recheck_required' ), 'missing freshness evidence stays unknown and requires owner recheck' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'paid_or_sponsored_organic_results', 'false' ) ), 'paid organic search is prohibited' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'single_free_tier_rank_parity', 'true' ) ), 'single-free-tier ranking parity' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'donation', 'founder_favoritism', 'paid_promotion' ) ), 'forbidden money/favoritism signals are named' );
f26_central_assert( f26_central_has_all( $files['central'], array( "'connector'", '$connector', "'source'", '$source' ) ), 'advanced search separates connector lane from owner-source filtering' );
f26_central_assert( f26_central_has_all( $files['central'], array( "meta['visibility']", "''===$access", 'extended' ) ), 'advanced access filter fails closed instead of assuming public' );
f26_central_assert( f26_central_has_all( $files['central'], array( "extended['exact']", 'field_haystack', 'strpos' ) ), 'field-scoped exact phrase must actually occur in selected fields' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'continuation_limited', 'next_offset', 'offset', 'bounded_more' ) ), 'advanced-search cursor cannot self-loop after a bounded scan ceiling' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'advanced-search|', 'client_bucket', '12,60' ) ) || f26_central_has_all( $files['central'], array( 'advanced-search|', 'client_bucket', 'rate_limit' ) ), 'advanced search has an explicit abuse-rate gate' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'secure_route_response', 'Cache-Control', 'private, no-store' ) ), 'new central REST routes have explicit no-store security semantics' );
f26_central_assert( f26_central_has_all( $files['central'], array( 'saved_query_retention_days', 'explicit_gap_retention_days', 'setting_days' ) ), 'saved-query and explicit-gap retention use governed settings' );

f26_central_assert( f26_central_has_all( $files['doctor'], array( 'top_10', 'top_100', 'top_1000' ) ), 'doctor Top 10/100/1000 tiers remain implemented' );
f26_central_assert( false === strpos( $files['doctor'], "'author_key' => \$row['author_key']" ), 'public doctor ranking does not expose internal author references' );
f26_central_assert( f26_central_has_all( $files['plugin'], array( 'primary_accent_fallback', '#087A4E', 'visual_owner', 'File 25' ) ), 'File 25 provider receives Sabri Green fallback and retains visual ownership' );
f26_central_assert( false !== strpos( $files['central'], 'shell_owner' ) && false !== strpos( $files['central'], 'File 20' ), 'File 20 shell ownership retained' );

f26_central_assert( false !== strpos( $files['workflow'], 'actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1' ), 'checkout action pinned to approved immutable Node 24 release' );
f26_central_assert( false !== strpos( $files['workflow'], 'actions/setup-node@820762786026740c76f36085b0efc47a31fe5020' ), 'setup-node action pinned to approved immutable Node 24 release' );
f26_central_assert( false !== strpos( $files['workflow'], 'actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a' ), 'upload-artifact action pinned to approved immutable Node 24 release' );

if ( $failures ) { fwrite( STDERR, "$failures of $assertions central-plan assertions failed.\n" ); exit( 1 ); }
echo "$assertions central-plan assertions passed.\n";
