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
function f26_future_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
function f26_future_has( $haystack, $needle ) {
    if ( false !== strpos( $haystack, $needle ) ) { return true; }
    $h = preg_replace( '/\s+/', '', $haystack );
    $n = preg_replace( '/\s+/', '', $needle );
    return false !== strpos( $h, $n );
}

f26_future_assert( f26_future_has( $main, 'Version: 1.3.0' ), 'runtime version 1.3.0' );
f26_future_assert( f26_future_has( $main, "SABRI_FILE26_CONTRACT_VERSION', '1.3'" ), 'contract version 1.3' );
f26_future_assert( f26_future_has( $main, 'class-file26-future-intelligence.php' ), 'future runtime required' );
f26_future_assert( f26_future_has( $main, 'sabri_file26_future_capabilities' ), 'public future registry wrapper present' );
for ( $i = 1; $i <= 24; $i++ ) {
    $id = sprintf( 'F26-FUT-%02d', $i );
    f26_future_assert( 1 === substr_count( $future, "'id' => '$id'" ), "$id appears exactly once in capability manifest" );
    f26_future_assert( false !== strpos( $doc, $id ), "$id documented in Future Superset plan" );
}
$slugs = array( 'conversational-grounded-search','query-planner','cross-language-search','semantic-rerank','multimodal-search','voice-search','segment-search','find-similar','research-search','result-clusters','graph-path','evidence-map','disambiguate','historical-search','research-trails','saved-search-alerts','search-history','recommendation-transparency','discovery-breadth','geo-availability','search-modes','private-search-vault','external-evidence','relevance-lab' );
foreach ( $slugs as $slug ) { f26_future_assert( f26_future_has( $future, "'$slug' => array(" ), "route manifest contains $slug" ); }
$hooks = array( 'sabri_file26_grounded_answer_provider','sabri_file26_cross_language_variants','sabri_file26_semantic_reranker','sabri_file26_multimodal_query_adapter','sabri_file26_voice_transcription_adapter','sabri_file26_segment_search_provider','sabri_file26_similarity_seed','sabri_file26_research_snapshot_provider','sabri_file26_graph_path_provider','sabri_file26_evidence_map_provider','sabri_file26_historical_snapshot_search','sabri_file26_saved_search_alert_changed','sabri_file26_geo_availability_constraints','sabri_file26_private_vault_provider','sabri_file26_external_evidence_connector_approved','sabri_file26_external_evidence_provider','sabri_file26_relevance_lab_candidate','sabri_file26_future_step_up_verified' );
foreach ( $hooks as $hook ) { f26_future_assert( f26_future_has( $future, $hook ), "provider/integration contract $hook present" ); }
$required = array(
"'autonomous_diagnosis' => false"=>'conversational search forbids autonomous diagnosis',
"'autonomous_prescription' => false"=>'conversational search forbids autonomous prescription',
'file26_multimodal_clinical_diagnosis_prohibited'=>'patient-image diagnosis blocked',
"'current_results_substituted' => false"=>'historical search forbids current-result substitution',
"'public_index_used' => false"=>'private vault declares public-index isolation',
"'delivery_owner' => 'File 19'"=>'saved-search notification delivery owned by File 19',
"'doctor_truth_owner' => 'File 07'"=>'doctor truth remains File 07',
"'clinic_and_appointment_truth_owner' => 'File 08'"=>'clinic/appointment truth remains File 08',
"'availability_claims_suppressed'"=>'availability claims suppressed without owner provider',
"'merged_into_organic_ranking' => false"=>'external evidence never silently merges into organic ranking',
'external_connector_not_approved'=>'external connector approval is fail closed',
"'production_mutation' => false"=>'relevance lab cannot mutate production',
'paid_donor_founder_signal_prohibited'=>'relevance lab retains paid/donor/founder prohibition',
"'policy' => 'local_first'"=>'server search history advertises local-first policy',
"'default_network_sync' => false"=>'server history default network sync false',
'wp_privacy_personal_data_exporters'=>'future account data registered for privacy export',
'wp_privacy_personal_data_erasers'=>'future account data registered for privacy erasure',
'private, no-store'=>'private future routes receive no-store policy',
"'result_schema'] = 'sabri.file26.result.v1.3'"=>'File 25 metadata upgraded to result schema v1.3',
"future_capability_count'] = 24"=>'File 24 assurance advertises 24 future capabilities'
);
foreach ( $required as $needle=>$label ) { f26_future_assert( f26_future_has( $future, $needle ), $label ); }
f26_future_assert( f26_future_has( $js, "policy: 'local_first'" ), 'browser history local-first client implemented' );
f26_future_assert( f26_future_has( $js, 'Merely loading this script never sends history to the network.' ), 'client documents no automatic history network transfer' );
f26_future_assert( f26_future_has( $js, 'syncOptIn' ), 'client server sync requires explicit method call' );
foreach ( array( 'donation'.'_score','payment'.'_score','paid_rank'.'_score','founder_favoritism'.'_score','sponsor'.'_score' ) as $signal ) { f26_future_assert( false === strpos( $future, $signal ), "forbidden organic ranking signal absent: $signal" ); }
f26_future_assert( 0 === preg_match( '/(?:SELECT|UPDATE|DELETE|INSERT)\s+.*(?:clinical_|message_body|payment_card|smc_)/i', $future ), 'no direct sensitive foreign-table access' );
f26_future_assert( false !== strpos( $doc, 'Specified' ) && false !== strpos( $doc, 'Coded' ) && false !== strpos( $doc, 'Staging-Accepted' ) && false !== strpos( $doc, 'Live-Deployed' ), 'status/evidence ladder preserved' );

$round_markers = array(
'optional_provider_bypassed_for_sensitive_query'=>'sensitive optional-provider bypass',
'file26_external_evidence_consent_required'=>'external evidence consent',
'file26_external_sensitive_query_prohibited'=>'external sensitive query block',
'file26_research_constraint_injected_result'=>'research candidate integrity',
'file26_relevance_candidate_injected_result'=>'lab candidate integrity',
'file26_graph_edge_integrity_invalid'=>'graph edge integrity',
'file26_graph_node_integrity_invalid'=>'graph node integrity',
'file26_evidence_relation_integrity_invalid'=>'evidence relation integrity',
'owner_envelope'=>'geo owner attestation envelope',
'file26_alert_sensitive_filter_not_allowed'=>'alert sensitive filter rejection',
'file26_history_delete_conflict'=>'history compare-and-delete conflict',
'file26_history_optin_delete_conflict'=>'history opt-in compare-and-delete conflict',
'provider_query_empty_after_sanitization'=>'multimodal empty-query block',
'seed_query_empty_after_sanitization'=>'similarity empty-query block',
'snapshot_provider_bypassed_sensitive_query'=>'sensitive snapshot bypass',
'valid_historical_as_of'=>'historical calendar validation',
'file26_execute_flag_invalid'=>'planner strict boolean',
'file26_less_personalization_flag_invalid'=>'personalization strict boolean',
'file26_alert_enabled_flag_invalid'=>'alert enabled strict boolean',
'explicit true opt-in'=>'history explicit true opt-in',
"'purpose' => 'geo_availability_discovery'"=>'geo minimized context',
'file26_semantic_query_required'=>'semantic query required',
'file26_recommendation_reset_flag_invalid'=>'reset strict boolean',
'file26_history_disable_sync_flag_invalid'=>'disable_sync strict boolean',
'file26_multimodal_diagnose_flag_invalid'=>'diagnose strict boolean',
'file26_future_erasure_incomplete'=>'truthful erasure incomplete state',
'guard_future_metadata_mutation'=>'cross-request erasure write barrier',
'privacy-erasure-write-barrier'=>'assurance erasure barrier marker'
);
foreach ( $round_markers as $needle=>$label ) { f26_future_assert( f26_future_has( $future, $needle ), $label ); }
f26_future_assert( substr_count( preg_replace('/\s+/','',$future), preg_replace('/\s+/','','save_user_meta_cas( $user_id, self::META_DISCOVERY') ) >= 2, 'recommendation/discovery preferences use CAS' );
f26_future_assert( f26_future_has( $future, 'save_user_meta_cas( $user_id, self::META_HISTORY_OPT_IN' ), 'history opt-in uses CAS' );
f26_future_assert( f26_future_has( $future, 'sabri_file26_saved_search_alert_changed' ) && f26_future_has( $future, 'self::META_ALERTS === $meta_key' ), 'privacy erasure reconciles removed File 19 saved-alert registrations' );
f26_future_assert( substr_count( $future, '$provider_context = array(' ) >= 2, 'segment and historical providers receive minimized contexts' );
f26_future_assert( f26_future_has( $future, 'metadata_exists' ), 'privacy erasure checks retained metadata truthfully' );

echo "PASS: $checks Future Search Intelligence contract assertions\n";
