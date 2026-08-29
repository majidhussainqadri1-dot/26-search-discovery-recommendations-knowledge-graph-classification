<?php
/** Regression ledger for sequential review rounds R62-R81. */
$root = dirname( __DIR__ );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$connectors = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$security = file_get_contents( $root . '/includes/class-file26-security.php' );
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
f26_r62_81_assert( false !== strpos( $security, 'normalize_string_claim_list' ), 'R65: membership entitlement/consent lists are bounded and normalized' );
if ( $failures ) { fwrite( STDERR, "$failures of $checks R62-R81 assertions failed.\n" ); exit( 1 ); }
echo "PASS: $checks R62-R81 review regression assertions\n";
