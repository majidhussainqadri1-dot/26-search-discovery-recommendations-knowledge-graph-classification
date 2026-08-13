<?php
/** Static regression contract for File 26 Future Search & Knowledge Intelligence Superset 24. */
$root = dirname( __DIR__ );
$future_paths = array_merge( array( $root . '/includes/class-file26-future-intelligence.php' ), glob( $root . '/includes/trait-file26-future-*.php' ) );
$future = '';
foreach ( $future_paths as $future_path ) { $future .= "\n" . file_get_contents( $future_path ); }
$main = file_get_contents( $root . '/file-26-search-discovery.php' );
$js = file_get_contents( $root . '/assets/js/file26-future.js' );
$doc = file_get_contents( $root . '/docs/FUTURE-SEARCH-KNOWLEDGE-INTELLIGENCE-SUPERSET-24-1.3.0.md' );
$checks = 0;
function f26_future_assert( $condition, $message ) {
    global $checks;
    $checks++;
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}

f26_future_assert( false !== strpos( $main, 'Version: 1.3.0' ), 'runtime version 1.3.0' );
f26_future_assert( false !== strpos( $main, "SABRI_FILE26_CONTRACT_VERSION', '1.3'" ), 'contract version 1.3' );
f26_future_assert( false !== strpos( $main, 'class-file26-future-intelligence.php' ), 'future runtime required' );
f26_future_assert( false !== strpos( $main, 'sabri_file26_future_capabilities' ), 'public future registry wrapper present' );

for ( $i = 1; $i <= 24; $i++ ) {
    $id = sprintf( 'F26-FUT-%02d', $i );
    f26_future_assert( 1 === substr_count( $future, "'id' => '$id'" ), "$id appears exactly once in capability manifest" );
    f26_future_assert( false !== strpos( $doc, $id ), "$id documented in Future Superset plan" );
}

$slugs = array(
'conversational-grounded-search','query-planner','cross-language-search','semantic-rerank','multimodal-search','voice-search','segment-search','find-similar','research-search','result-clusters','graph-path','evidence-map','disambiguate','historical-search','research-trails','saved-search-alerts','search-history','recommendation-transparency','discovery-breadth','geo-availability','search-modes','private-search-vault','external-evidence','relevance-lab'
);
foreach ( $slugs as $slug ) { f26_future_assert( false !== strpos( $future, "'$slug' => array(" ), "route manifest contains $slug" ); }

$hooks = array(
'sabri_file26_grounded_answer_provider','sabri_file26_cross_language_variants','sabri_file26_semantic_reranker','sabri_file26_multimodal_query_adapter','sabri_file26_voice_transcription_adapter','sabri_file26_segment_search_provider','sabri_file26_similarity_seed','sabri_file26_research_snapshot_provider','sabri_file26_graph_path_provider','sabri_file26_evidence_map_provider','sabri_file26_historical_snapshot_search','sabri_file26_saved_search_alert_changed','sabri_file26_geo_availability_constraints','sabri_file26_private_vault_provider','sabri_file26_external_evidence_connector_approved','sabri_file26_external_evidence_provider','sabri_file26_relevance_lab_candidate','sabri_file26_future_step_up_verified'
);
foreach ( $hooks as $hook ) { f26_future_assert( false !== strpos( $future, $hook ), "provider/integration contract $hook present" ); }

$required = array(
"'autonomous_diagnosis' => false" => 'conversational search forbids autonomous diagnosis',
"'autonomous_prescription' => false" => 'conversational search forbids autonomous prescription',
'file26_multimodal_clinical_diagnosis_prohibited' => 'patient-image diagnosis blocked',
"'current_results_substituted' => false" => 'historical search forbids current-result substitution',
"'public_index_used' => false" => 'private vault declares public-index isolation',
"'delivery_owner' => 'File 19'" => 'saved-search notification delivery owned by File 19',
"'doctor_truth_owner' => 'File 07'" => 'doctor truth remains File 07',
"'clinic_and_appointment_truth_owner' => 'File 08'" => 'clinic/appointment truth remains File 08',
"'availability_claims_suppressed'" => 'availability claims suppressed without owner provider',
"'merged_into_organic_ranking' => false" => 'external evidence never silently merges into organic ranking',
'external_connector_not_approved' => 'external connector approval is fail closed',
"'production_mutation' => false" => 'relevance lab cannot mutate production',
'paid_donor_founder_signal_prohibited' => 'relevance lab retains paid/donor/founder prohibition',
"'policy' => 'local_first'" => 'server search history advertises local-first policy',
"'default_network_sync' => false" => 'server history default network sync false',
'wp_privacy_personal_data_exporters' => 'future account data registered for privacy export',
'wp_privacy_personal_data_erasers' => 'future account data registered for privacy erasure',
'private, no-store' => 'private future routes receive no-store policy',
"'result_schema'] = 'sabri.file26.result.v1.3'" => 'File 25 metadata upgraded to result schema v1.3',
"future_capability_count'] = 24" => 'File 24 assurance advertises 24 future capabilities'
);
foreach ( $required as $needle => $label ) { f26_future_assert( false !== strpos( $future, $needle ), $label ); }

f26_future_assert( false !== strpos( $js, "policy: 'local_first'" ), 'browser history local-first client implemented' );
f26_future_assert( false !== strpos( $js, 'Merely loading this script never sends history to the network.' ), 'client documents no automatic history network transfer' );
f26_future_assert( false !== strpos( $js, 'syncOptIn' ), 'client server sync requires explicit method call' );

foreach ( array( 'donation' . '_score', 'payment' . '_score', 'paid_rank' . '_score', 'founder_favoritism' . '_score', 'sponsor' . '_score' ) as $signal ) {
    f26_future_assert( false === strpos( $future, $signal ), "forbidden organic ranking signal absent: $signal" );
}
f26_future_assert( 0 === preg_match( '/(?:SELECT|UPDATE|DELETE|INSERT)\s+.*(?:clinical_|message_body|payment_card|smc_)/i', $future ), 'no direct sensitive foreign-table access' );
f26_future_assert( false !== strpos( $doc, 'Specified' ) && false !== strpos( $doc, 'Coded' ) && false !== strpos( $doc, 'Staging-Accepted' ) && false !== strpos( $doc, 'Live-Deployed' ), 'status/evidence ladder preserved' );

// Round 1 regression locks.
f26_future_assert( substr_count( $future, 'optional_provider_bypassed_for_sensitive_query' ) >= 3, 'sensitive queries bypass grounded/cross-language/rerank optional providers' );
f26_future_assert( false !== strpos( $future, 'file26_external_evidence_consent_required' ) && false !== strpos( $future, 'file26_external_sensitive_query_prohibited' ), 'external evidence requires consent and rejects sensitive query disclosure' );
f26_future_assert( false !== strpos( $future, 'file26_research_constraint_injected_result' ), 'research constraint provider cannot inject non-eligible results' );
f26_future_assert( false !== strpos( $future, 'file26_relevance_candidate_injected_result' ), 'relevance candidate cannot inject non-baseline results' );
f26_future_assert( false !== strpos( $future, 'file26_graph_edge_integrity_invalid' ) && false !== strpos( $future, 'file26_graph_node_integrity_invalid' ), 'graph node/edge integrity enforced' );
f26_future_assert( false !== strpos( $future, 'file26_evidence_relation_integrity_invalid' ), 'evidence relation integrity enforced' );
f26_future_assert( false !== strpos( $future, 'owner_envelope' ) && false !== strpos( $future, 'owner_filters' ), 'geo provider attestation envelope and entity type protection present' );
f26_future_assert( false !== strpos( $future, 'file26_alert_sensitive_filter_not_allowed' ), 'saved alert sensitive filter metadata rejected' );
f26_future_assert( substr_count( $future, 'save_user_meta_cas( $user_id, self::META_DISCOVERY' ) >= 2, 'recommendation/discovery preferences use CAS' );
f26_future_assert( false !== strpos( $future, 'save_user_meta_cas( $user_id, self::META_HISTORY_OPT_IN' ), 'history opt-in uses CAS' );
f26_future_assert( false !== strpos( $future, 'sabri_file26_saved_search_alert_changed' ) && false !== strpos( $future, 'self::META_ALERTS === $meta_key' ), 'privacy erasure reconciles actually removed File 19 saved-alert registrations' );
f26_future_assert( false !== strpos( $future, 'provider_query_empty_after_sanitization' ) && false !== strpos( $future, 'seed_query_empty_after_sanitization' ), 'provider-derived empty queries cannot broaden search' );
f26_future_assert( false !== strpos( $future, 'snapshot_provider_bypassed_sensitive_query' ), 'research snapshots bypass optional provider for sensitive queries' );
f26_future_assert( false !== strpos( $future, 'valid_historical_as_of' ), 'historical as_of calendar/time validation present' );

// Round 2 regression locks.
f26_future_assert( false !== strpos( $future, 'file26_execute_flag_invalid' ), 'query planner execute flag uses strict boolean semantics' );
f26_future_assert( false !== strpos( $future, 'file26_less_personalization_flag_invalid' ), 'less_personalization uses strict boolean semantics' );
f26_future_assert( false !== strpos( $future, 'file26_alert_enabled_flag_invalid' ), 'saved alert enabled uses strict boolean semantics' );
f26_future_assert( false !== strpos( $future, 'explicit true opt-in' ), 'search history accepts only explicit true sync opt-in' );
f26_future_assert( substr_count( $future, '$provider_context = array(' ) >= 2, 'segment and historical providers receive minimized contexts' );
f26_future_assert( false !== strpos( $future, "'purpose' => 'geo_availability_discovery'" ), 'geo provider receives bounded purpose context instead of raw params' );
f26_future_assert( false !== strpos( $future, 'file26_semantic_query_required' ), 'semantic reranking requires a real query' );

// Round 3/5 truthfulness locks.
f26_future_assert( false !== strpos( $future, 'file26_recommendation_reset_flag_invalid' ), 'recommendation reset uses strict boolean semantics' );
f26_future_assert( false !== strpos( $future, 'file26_history_disable_sync_flag_invalid' ), 'history disable_sync uses strict boolean semantics' );
f26_future_assert( false !== strpos( $future, 'file26_multimodal_diagnose_flag_invalid' ), 'multimodal diagnose uses strict boolean semantics' );
f26_future_assert( false !== strpos( $future, 'file26_future_erasure_incomplete' ) && false !== strpos( $future, 'metadata_exists' ), 'privacy erasure cannot falsely claim completeness when deletion fails' );

echo "PASS: $checks Future Search Intelligence contract assertions\n";
