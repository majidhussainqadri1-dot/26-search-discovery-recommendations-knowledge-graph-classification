<?php
/** Static regression checks for the fresh forty-round review. */
$root = dirname( __DIR__ );
$files = array(
    'search' => file_get_contents( $root . '/includes/trait-file26-future-search-core.php' ),
    'media' => file_get_contents( $root . '/includes/trait-file26-future-multimodal.php' ),
    'rest' => file_get_contents( $root . '/includes/trait-file26-future-rest-trait.php' ),
    'account' => file_get_contents( $root . '/includes/trait-file26-future-user-data.php' ),
    'discovery' => file_get_contents( $root . '/includes/trait-file26-future-user-discovery.php' ),
    'knowledge' => file_get_contents( $root . '/includes/trait-file26-future-knowledge.php' ),
);
$checks = 0;
function f26_r40_assert( $condition, $message ) {
    global $checks;
    $checks++;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: $message\n" );
        exit( 1 );
    }
}
f26_r40_assert( false !== strpos( $files['search'], 'provider_bypassed_for_sensitive_or_clinical' ), 'provider bypass marker present' );
f26_r40_assert( false !== strpos( $files['media'], 'provider_returned_empty_query' ), 'empty derived query guard present' );
f26_r40_assert( false !== strpos( $files['media'], 'seed_provider_returned_empty_query' ), 'empty similarity seed guard present' );
f26_r40_assert( false !== strpos( $files['rest'], 'file26_external_consent_required' ), 'consent preflight present' );
f26_r40_assert( false !== strpos( $files['rest'], 'eligible_baseline_keys_only' ), 'candidate scope guard present' );
f26_r40_assert( false !== strpos( $files['discovery'], 'save_user_meta_cas' ), 'conflict-safe preference write present' );
f26_r40_assert( false !== strpos( $files['discovery'], 'owner_revalidated_for_request' ), 'owner revalidation marker present' );
f26_r40_assert( false !== strpos( $files['knowledge'], 'owner_revalidated_special_constraints_eligible_keys_only' ), 'research eligibility guard present' );
f26_r40_assert( false !== strpos( $files['knowledge'], 'file26_graph_referential_integrity' ), 'graph integrity guard present' );
f26_r40_assert( false !== strpos( $files['knowledge'], '64 !== strlen' ) && false !== strpos( $files['knowledge'], 'source_key' ), 'canonical source-key check present' );
f26_r40_assert( false !== strpos( $files['knowledge'], 'checkdate(' ), 'calendar validation present' );
f26_r40_assert( false !== strpos( $files['account'], 'file26_alert_filters_not_allowed' ), 'saved-alert filter guard present' );
echo "PASS: $checks forty-round review regression assertions\n";
