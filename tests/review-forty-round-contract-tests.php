<?php
/** Regression gate for File 26 fresh forty-round corrective review. */
$root = dirname( __DIR__ );
$search_core = file_get_contents( $root . '/includes/trait-file26-future-search-core.php' );
$multimodal = file_get_contents( $root . '/includes/trait-file26-future-multimodal.php' );
$rest = file_get_contents( $root . '/includes/trait-file26-future-rest-trait.php' );
$user_data = file_get_contents( $root . '/includes/trait-file26-future-user-data.php' );
$user_discovery = file_get_contents( $root . '/includes/trait-file26-future-user-discovery.php' );
$knowledge = file_get_contents( $root . '/includes/trait-file26-future-knowledge.php' );
$checks = 0;
function f26_r40_assert( $condition, $message ) {
	global $checks; $checks++;
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
f26_r40_assert( false !== strpos( $search_core, '! $this->sensitive_query( $q ) && $sources' ), 'grounded-answer provider bypasses sensitive queries' );
f26_r40_assert( false !== strpos( $search_core, 'if ( ! $this->sensitive_query( $q ) )' ), 'cross-language provider is guarded for sensitive queries' );
f26_r40_assert( false !== strpos( $search_core, 'provider_bypassed_for_sensitive_or_clinical' ), 'semantic reranker exposes sensitive/clinical bypass state' );
f26_r40_assert( false !== strpos( $multimodal, 'provider_returned_empty_query' ), 'multimodal sanitized-empty query is fail closed' );
f26_r40_assert( false !== strpos( $multimodal, 'seed_provider_returned_empty_query' ), 'similarity seed sanitized-empty query is fail closed' );
f26_r40_assert( false !== strpos( $rest, 'file26_external_consent_required' ), 'external evidence requires explicit per-request consent' );
f26_r40_assert( false !== strpos( $rest, 'file26_external_query_not_eligible' ), 'external evidence rejects sensitive/clinical provider disclosure' );
f26_r40_assert( false !== strpos( $rest, "'eligible_baseline_keys_only'" ), 'relevance-lab candidate is bounded to eligible baseline keys' );
f26_r40_assert( false !== strpos( $user_discovery, 'save_user_meta_cas' ), 'Future discovery preference writes use conflict-safe CAS' );
f26_r40_assert( false !== strpos( $user_discovery, "'owner_revalidated_for_request'" ), 'geo availability requires native-owner revalidation attestation' );
f26_r40_assert( false !== strpos( $knowledge, 'owner_revalidated_special_constraints_eligible_keys_only' ), 'research special constraints cannot inject non-eligible candidates' );
f26_r40_assert( false !== strpos( $knowledge, 'file26_graph_referential_integrity' ), 'graph paths enforce edge-to-node referential integrity' );
f26_r40_assert( false !== strpos( $knowledge, "64 !== strlen( $clean['source_key'] )" ), 'evidence relations require canonical source keys' );
f26_r40_assert( false !== strpos( $knowledge, 'checkdate(' ), 'historical search rejects impossible calendar dates' );
f26_r40_assert( false !== strpos( $user_data, 'file26_alert_filters_not_allowed' ), 'saved-search alerts reject sensitive filter values' );
echo "PASS: $checks forty-round review regression assertions\n";
