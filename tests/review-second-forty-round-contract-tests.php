<?php
/** Robust static regression contract for the second sequential File 26 review cycle. */
$root = dirname( __DIR__ );
$files = array(
    'bootstrap' => file_get_contents( $root . '/file-26-search-discovery.php' ),
    'db' => file_get_contents( $root . '/includes/class-file26-db.php' ),
    'security' => file_get_contents( $root . '/includes/class-file26-security.php' ),
    'rest' => file_get_contents( $root . '/includes/class-file26-rest.php' ),
    'recommendations' => file_get_contents( $root . '/includes/class-file26-recommendations.php' ),
    'owner' => file_get_contents( $root . '/includes/class-file26-owner-contracts.php' ),
    'roles' => file_get_contents( $root . '/includes/class-file26-roles.php' ),
    'plugin' => file_get_contents( $root . '/includes/class-file26-plugin.php' ),
    'central' => file_get_contents( $root . '/includes/class-file26-central-plan.php' ),
    'future_advanced' => file_get_contents( $root . '/includes/trait-file26-future-advanced.php' ),
    'future_infra' => file_get_contents( $root . '/includes/trait-file26-future-infra-trait.php' ),
    'future_utility' => file_get_contents( $root . '/includes/trait-file26-future-utility-trait.php' ),
    'future_class' => file_get_contents( $root . '/includes/class-file26-future-intelligence.php' ),
    'qa' => file_get_contents( $root . '/qa/run-tests.sh' ),
    'builder' => file_get_contents( $root . '/tools/build-package.py' ),
    'workflow' => file_get_contents( $root . '/.github/workflows/qa.yml' ),
);
$checks = 0;
$failures = 0;
function f26_second40_assert( $condition, $message ) {
    global $checks, $failures;
    $checks++;
    if ( ! $condition ) { $failures++; fwrite( STDERR, "FAIL: $message\n" ); }
}
function f26_has_all( $text, array $tokens ) {
    foreach ( $tokens as $token ) { if ( false === strpos( $text, $token ) ) { return false; } }
    return true;
}

f26_second40_assert( f26_has_all( $files['db'], array( 'primary_color', '#087A4E' ) ), 'Sabri Green remains the DB default accent' );
f26_second40_assert( f26_has_all( $files['db'], array( 'delete_option', 'OPTION_SCHEMA', 'SHOW TABLES LIKE' ) ), 'main schema marker is cleared/verified against required tables' );
f26_second40_assert( f26_has_all( $files['db'], array( 'SABRI_FILE26_SCHEMA_VERSION', 'get_option', 'OPTION_SCHEMA' ) ), 'main schema version is verified after persistence' );
f26_second40_assert( f26_has_all( $files['db'], array( 'OPTION_SETTINGS', 'update_option', 'return false' ) ), 'settings persistence has a fail-closed path' );
f26_second40_assert( f26_has_all( $files['db'], array( 'wp_schedule_event', 'CRON_QUEUE', 'CRON_RECONCILE', 'CRON_RETENTION', 'CRON_DOCTOR_RANKING' ) ), 'all File 26 schedules remain explicit' );
f26_second40_assert( false === strpos( $files['db'], 'install_capabilities()' ), 'DB activation no longer owns privileged role capabilities' );

f26_second40_assert( f26_has_all( $files['bootstrap'], array( 'sabri_file26_monthly', 'cron_schedules', 'DB::activate()', 'Roles::install( true )', 'Doctor_Appeals::install_schema()', 'deactivate_plugins', 'wp_die' ) ), 'activation verifies recurrence, DB, roles, appeals schema and aborts failure' );
f26_second40_assert( f26_has_all( $files['bootstrap'], array( "^search/?$", "^discover/?$", "^topics/([^/]+)/?$" ) ), 'activation registers public rewrites before flush' );

f26_second40_assert( f26_has_all( $files['security'], array( '8192', '4096', 'verify_cursor', 'sign_cursor' ) ), 'cursor signing/verification remains size bounded' );
f26_second40_assert( f26_has_all( $files['security'], array( 'query_text', 'search_query', 'contains_sensitive_query' ) ), 'audit metadata protects query aliases and sensitive values' );

f26_second40_assert( f26_has_all( $files['rest'], array( 'file26_membership_invalid', 'audience', 'suspended' ) ), 'protected REST routes require current membership assertions' );
f26_second40_assert( f26_has_all( $files['rest'], array( 'own_doctor_appeals', 'is_wp_error', 'reconcile' ) ), 'REST propagates own-appeal/reconciliation errors' );
f26_second40_assert( f26_has_all( $files['rest'], array( 'Cache-Control', 'private, no-store' ) ), 'non-public REST responses stay no-store' );

f26_second40_assert( f26_has_all( $files['recommendations'], array( 'START TRANSACTION', 'COMMIT', 'ROLLBACK', 'feedback_commit_failed' ) ), 'feedback/control mutations remain atomic' );
f26_second40_assert( f26_has_all( $files['recommendations'], array( 'ORDER BY id DESC LIMIT 3000', 'Opt-out commit failed.' ) ), 'negative-control rebuild and opt-out hardening remain present' );
f26_second40_assert( false === strpos( $files['recommendations'], 'ORDER BY id ASC LIMIT 1000' ), 'obsolete oldest-1000 truncation is absent' );

f26_second40_assert( f26_has_all( $files['owner'], array( 'matching_connector_count', 'production_ready', 'active', 'callbacks_complete' ) ), 'owner readiness prioritizes and exposes active-ready connector evidence' );
f26_second40_assert( f26_has_all( $files['owner'], array( 'activation_gate', 'if ( ! $approved )', 'staging_acceptance', 'migration_rehearsal', 'rollback_rehearsal' ) ), 'cross-file activation requires explicit approval and evidence' );

f26_second40_assert( f26_has_all( $files['roles'], array( 'OPTION_VERSION', 'delete_option', 'has_cap', 'return false' ) ), 'role migration verifies capabilities and clears false-success marker' );

f26_second40_assert( f26_has_all( $files['plugin'], array( 'Roles::install()', 'DB::schedule()', 'file26_schema_marker_incomplete', 'Appeal retention commit failed.' ) ), 'plugin boot/retention remains fail closed' );
f26_second40_assert( f26_has_all( $files['central'], array( 'file26_membership_invalid', 'private, no-store' ) ), 'central-plan account routes require membership and no-store responses' );

f26_second40_assert( f26_has_all( $files['future_advanced'], array( 'sensitive_query_external_disclosure_blocked', 'file26_external_evidence_consent_required', 'approved_external_public' ) ), 'external evidence blocks sensitive disclosure and requires consent/attestation' );
f26_second40_assert( f26_has_all( $files['future_infra'], array( 'META_HISTORY_OPT_IN', 'Server search history sync opt-in', 'privacy_export', 'privacy_erase' ) ), 'privacy lifecycle includes server-history synchronization consent state' );
f26_second40_assert( f26_has_all( $files['future_utility'], array( 'safe_result', 'esc_url_raw', "array( 'http', 'https' )", 'wp_strip_all_tags', 'sanitize_key' ) ), 'Future result boundary performs value-level sanitization' );
f26_second40_assert( f26_has_all( $files['future_class'], array( 'health()->snapshot()', "'unavailable'", 'schema_drift', 'return;' ) ), 'Future bootstrap remains closed when core schema health is unavailable/drifting' );

f26_second40_assert( f26_has_all( $files['qa'], array( 'FAIL: node is required for JavaScript syntax verification', 'node --check', 'file26-future.js', 'review-second-forty-round-contract-tests.php', 'stat.S_ISLNK', '0o644' ) ), 'QA requires Node, Future JS, second-cycle regressions and ZIP metadata safety' );
f26_second40_assert( f26_has_all( $files['builder'], array( 'FIXED_FILE_MODE = 0o644', 'path.is_symlink()', 'MANIFEST.sha256', 'ZIP_DEFLATED' ) ), 'package builder remains deterministic and rejects symlink input' );
f26_second40_assert( f26_has_all( $files['workflow'], array( "'review/**'", "php: ['7.4', '8.3']", 'actions/setup-node', 'upload-artifact' ) ), 'review branches receive PHP-matrix exact-head CI and package artifact step' );

if ( $failures ) { fwrite( STDERR, "$failures of $checks second-cycle assertions failed.\n" ); exit( 1 ); }
echo "PASS: $checks second-cycle review regression assertions\n";
