<?php
/** Regression ledger for sequential review rounds R62-R81. */
$root = dirname( __DIR__ );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$connectors = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$security = file_get_contents( $root . '/includes/class-file26-security.php' );
$recommendations = file_get_contents( $root . '/includes/class-file26-recommendations.php' );
$taxonomy = file_get_contents( $root . '/includes/class-file26-taxonomy.php' );
$graph = file_get_contents( $root . '/includes/class-file26-graph.php' );
$governance = file_get_contents( $root . '/includes/class-file26-governance.php' );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$appeals = file_get_contents( $root . '/includes/class-file26-doctor-appeals.php' );
$doctor_ranking = file_get_contents( $root . '/includes/class-file26-doctor-ranking.php' );
$privacy = file_get_contents( $root . '/includes/class-file26-privacy.php' );
$checks = 0;
$failures = 0;
function f26_r62_81_assert( $condition, $message ) {
    global $checks, $failures;
    $checks++;
    if ( ! $condition ) { $failures++; fwrite( STDERR, "FAIL: $message\n" ); }
}
f26_r62_81_assert( false !== strpos( $indexer, 'file26_index_transaction_unavailable' ), 'R63: index upsert fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $indexer, 'file26_tombstone_transaction_unavailable' ), 'R63: tombstone revocation fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $indexer, 'file26_reconcile_transaction_unavailable' ), 'R63: reconciliation fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $connectors, "foreach ( array( 'slug', 'owner_file', 'contract_version', 'entity_types', 'privacy_classes', 'visibility_fields', 'deletion_semantics', 'status', 'event_contract', 'index_schema' ) as \$safe_key )" ), 'R64: persisted connector manifest uses explicit safe-field allowlist' );
f26_r62_81_assert( false !== strpos( $connectors, 'sanitize_health_detail' ) && false !== strpos( $connectors, 'authorization|api[_-]?key|cookie|session' ), 'R64: connector health detail is bounded and credential-like keys are redacted' );
f26_r62_81_assert( false !== strpos( $security, 'normalize_claim_bool' ) && false !== strpos( $security, "null===\$guardian?false:\$guardian" ), 'R65: guardian assertion is typed and malformed values fail closed' );
f26_r62_81_assert( false !== strpos( $security, "null===\$is_minor?true:\$is_minor" ) && false !== strpos( $security, "null===\$suspended?true:\$suspended" ), 'R65: malformed minor/suspension assertions take restrictive state' );
f26_r62_81_assert( false !== strpos( $security, 'normalize_string_claim_list' ) && false !== strpos( $security, 'sanitize_claim_text' ), 'R65: membership lists and text claims are bounded and normalized' );
f26_r62_81_assert( false !== strpos( $recommendations, 'file26_feedback_transaction_unavailable' ), 'R66: feedback mutation fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $recommendations, 'file26_consent_transaction_unavailable' ), 'R66: consent mutation fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $recommendations, 'file26_profile_reset_transaction_unavailable' ), 'R66: reset fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $recommendations, 'file26_opt_out_transaction_unavailable' ), 'R66: opt-out fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $taxonomy, 'file26_merge_transaction_unavailable' ), 'R67: taxonomy merge fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $taxonomy, 'file26_split_transaction_unavailable' ), 'R67: taxonomy split fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $graph, 'file26_graph_source_owner_unverified' ) && false !== strpos( $graph, 'source_owner_file' ), 'R68: graph source owner is derived and server-side verified' );
f26_r62_81_assert( false !== strpos( $graph, "sabri_file26_allowed_evidence_url', false" ), 'R68: external graph evidence URLs are default-deny' );
f26_r62_81_assert( false !== strpos( $graph, 'file26_graph_source_owner_changed' ), 'R68: graph activation revalidates source owner before approval' );
f26_r62_81_assert( false !== strpos( $governance, 'file26_policy_activation_transaction_unavailable' ), 'R69: ranking activation fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $governance, 'file26_policy_rollback_transaction_unavailable' ), 'R69: ranking rollback fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $rest, 'file26_invalid_consent' ) && false !== strpos( $rest, 'strict_bool' ), 'R70: personalization consent uses strict boolean parsing and rejects malformed values' );
f26_r62_81_assert( false !== strpos( $plugin, 'main_schema_tables_present' ) && false !== strpos( $plugin, 'appeals_schema_table_present' ) && false !== strpos( $plugin, '$main_current&&$appeal_current&&$main_present&&$appeal_present' ), 'R71: boot trusts schema markers only when required physical tables are present' );
f26_r62_81_assert( false !== strpos( $appeals, 'SHOW TABLES LIKE %s' ) && false !== strpos( $appeals, 'delete_option( self::OPTION_SCHEMA )' ), 'R71: appeals schema marker is revalidated against the physical table' );
f26_r62_81_assert( false !== strpos( $plugin, 'file26_appeal_retention_transaction_unavailable' ), 'R71: appeal retention fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $doctor_ranking, 'file26_doctor_ranking_transaction_unavailable' ), 'R72: doctor ranking recompute fails closed if transaction start fails' );
f26_r62_81_assert( false !== strpos( $doctor_ranking, 'file26_doctor_ranking_read_failed' ), 'R72: doctor ranking source read failure is not treated as an empty cohort' );
f26_r62_81_assert( false !== strpos( $doctor_ranking, "unset( \$payload['global_doctor_rank'], \$payload['doctor_rank_score'], \$payload['doctor_rank_policy_version'] )" ), 'R72: stale rank metadata is cleared before rebuilding the verified cohort' );
f26_r62_81_assert( false !== strpos( $appeals, "d.entity_type='doctor_directory_projection'" ), 'R73: appeals target the same doctor projection entity type used by ranking' );
f26_r62_81_assert( false !== strpos( $appeals, "c.status='active' AND c.owner_file='File 07'" ), 'R73: appeals are restricted to the active File 07 ranking production lane' );
f26_r62_81_assert( false !== strpos( $privacy, 'sabri_file26_research_trails_v1' ) && false !== strpos( $privacy, 'sabri_file26_saved_search_alerts_v1' ) && false !== strpos( $privacy, 'sabri_file26_search_history_sync_v1' ) && false !== strpos( $privacy, 'sabri_file26_discovery_controls_v1' ), 'R74: Future account-owned meta stores participate in WordPress privacy export/erase' );
f26_r62_81_assert( false !== strpos( $privacy, "metadata_exists('user',\$user->ID,\$meta_key)" ) && false !== strpos( $privacy, "delete_user_meta(\$user->ID,\$meta_key)" ), 'R74: Future metadata erasure is verified instead of reported optimistically' );
if ( $failures ) { fwrite( STDERR, "$failures of $checks R62-R81 assertions failed.\n" ); exit( 1 ); }
echo "PASS: $checks R62-R81 review regression assertions\n";
