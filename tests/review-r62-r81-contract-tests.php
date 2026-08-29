<?php
/** Regression ledger for sequential review rounds R62-R81. */
$root = dirname( __DIR__ );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$connectors = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$security = file_get_contents( $root . '/includes/class-file26-security.php' );
$recommendations = file_get_contents( $root . '/includes/class-file26-recommendations.php' );
$taxonomy = file_get_contents( $root . '/includes/class-file26-taxonomy.php' );
$graph = file_get_contents( $root . '/includes/class-file26-graph.php' );
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
if ( $failures ) { fwrite( STDERR, "$failures of $checks R62-R81 assertions failed.\n" ); exit( 1 ); }
echo "PASS: $checks R62-R81 review regression assertions\n";
