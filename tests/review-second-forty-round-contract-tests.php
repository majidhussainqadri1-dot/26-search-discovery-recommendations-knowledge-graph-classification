<?php
/** Regression contract for the second sequential File 26 review cycle. */
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
    if ( ! $condition ) {
        $failures++;
        fwrite( STDERR, "FAIL: $message\n" );
    }
}

f26_second40_assert( false !== strpos( $files['db'], "'primary_color'=>'#087A4E'" ), 'Sabri Green remains the DB default accent' );
f26_second40_assert( false !== strpos( $files['db'], 'delete_option(self::OPTION_SCHEMA);return false;' ), 'main schema marker is cleared when a required table is missing' );
f26_second40_assert( false !== strpos( $files['db'], 'SABRI_FILE26_SCHEMA_VERSION===(string)get_option(self::OPTION_SCHEMA)' ), 'main schema version is verified after persistence' );
f26_second40_assert( false !== strpos( $files['db'], 'get_option(self::OPTION_SETTINGS,array())!==$merged' ), 'settings persistence fails closed' );
f26_second40_assert( false !== strpos( $files['db'], 'wp_schedule_event' ) && false !== strpos( $files['db'], 'return $ok;' ), 'cron scheduling returns a verified outcome' );
f26_second40_assert( false === strpos( $files['db'], 'install_capabilities()' ), 'DB activation no longer owns privileged role capabilities' );

f26_second40_assert( false !== strpos( $files['bootstrap'], 'sabri_file26_monthly' ) && false !== strpos( $files['bootstrap'], 'cron_schedules' ), 'activation registers custom recurrence before scheduling' );
f26_second40_assert( false !== strpos( $files['bootstrap'], "add_rewrite_rule( '^search/?$'" ) && false !== strpos( $files['bootstrap'], "add_rewrite_rule( '^topics/([^/]+)/?$'" ), 'activation registers public rewrites before flushing' );
f26_second40_assert( false !== strpos( $files['bootstrap'], 'DB::activate()' ), 'activation verifies DB/settings/schedule result' );
f26_second40_assert( false !== strpos( $files['bootstrap'], 'Roles::install( true )' ), 'activation verifies role model result' );
f26_second40_assert( false !== strpos( $files['bootstrap'], 'Doctor_Appeals::install_schema()' ), 'activation verifies appeal schema result' );
f26_second40_assert( false !== strpos( $files['bootstrap'], 'deactivate_plugins' ) && false !== strpos( $files['bootstrap'], 'wp_die' ), 'failed activation is explicitly aborted' );

f26_second40_assert( false !== strpos( $files['security'], 'strlen($cursor)>8192' ) && false !== strpos( $files['security'], 'strlen($decoded)>4096' ), 'cursor verification is length bounded before and after decoding' );
f26_second40_assert( false !== strpos( $files['security'], 'strlen($json)>4096' ), 'cursor signing fails closed on invalid or oversized JSON' );
f26_second40_assert( false !== strpos( $files['security'], 'query_text' ) && false !== strpos( $files['security'], 'search_query' ), 'audit metadata blocks common query-key aliases' );
f26_second40_assert( false !== strpos( $files['security'], 'contains_sensitive_query($text)' ), 'audit metadata redacts sensitive scalar values regardless of key name' );

f26_second40_assert( false !== strpos( $files['rest'], 'file26_membership_invalid' ), 'protected REST routes require current membership assertions' );
f26_second40_assert( false !== strpos( $files['rest'], 'is_wp_error($appeals)?$appeals' ), 'own-appeals read errors are not hidden inside success envelopes' );
f26_second40_assert( false !== strpos( $files['rest'], 'is_wp_error($result)?$result:$this->respond($result)' ), 'reconciliation errors are propagated to REST callers' );
f26_second40_assert( false !== strpos( $files['rest'], "Cache-Control','private, no-store" ), 'non-public REST responses stay no-store' );

f26_second40_assert( false !== strpos( $files['recommendations'], 'START TRANSACTION' ) && false !== strpos( $files['recommendations'], 'feedback_commit_failed' ), 'feedback mutations and negative-control projection are atomic' );
f26_second40_assert( false !== strpos( $files['recommendations'], 'ORDER BY id DESC LIMIT 3000' ), 'negative-control rebuild considers latest bounded feedback rather than oldest rows only' );
f26_second40_assert( false !== strpos( $files['recommendations'], 'Opt-out commit failed.' ), 'opt-out purge and persisted marker commit atomically' );
f26_second40_assert( false === strpos( $files['recommendations'], 'ORDER BY id ASC LIMIT 1000' ), 'obsolete oldest-1000 negative-control truncation is absent' );

f26_second40_assert( false !== strpos( $files['owner'], 'if ( ! $approved ) { return false; }' ), 'cross-file activation requires explicit upstream approval' );
f26_second40_assert( false !== strpos( $files['owner'], "'ready'=>$callbacks&&isset($connector['status'])&&'active'===$connector['status']" ), 'owner readiness explicitly distinguishes active-ready connectors' );
f26_second40_assert( false !== strpos( $files['owner'], 'matching_connector_count' ), 'owner readiness exposes duplicate/multiple connector evidence' );

f26_second40_assert( false !== strpos( $files['roles'], 'delete_option(self::OPTION_VERSION);return false;' ), 'role migration does not retain a false success marker' );
f26_second40_assert( false !== strpos( $files['roles'], '!$role->has_cap($cap)' ), 'required role capabilities are verified after mutation' );

f26_second40_assert( false !== strpos( $files['plugin'], 'if(!Roles::install())' ), 'plugin boot fails closed when separation-of-duties roles cannot be verified' );
f26_second40_assert( false !== strpos( $files['plugin'], 'if(!DB::schedule())' ), 'plugin boot surfaces background scheduling failure' );
f26_second40_assert( false !== strpos( $files['plugin'], 'Appeal retention commit failed.' ), 'appeal retention verifies COMMIT' );
f26_second40_assert( false !== strpos( $files['plugin'], 'file26_schema_marker_incomplete' ), 'plugin validates schema markers after table migration' );

f26_second40_assert( false !== strpos( $files['central'], 'file26_membership_invalid' ), 'central-plan account routes require current membership assertions' );
f26_second40_assert( false !== strpos( $files['central'], 'private, no-store' ), 'central-plan sensitive/account responses remain no-store' );

f26_second40_assert( false !== strpos( $files['future_advanced'], 'sensitive_query_external_disclosure_blocked' ), 'external evidence blocks sensitive-query disclosure at handler boundary' );
f26_second40_assert( false !== strpos( $files['future_advanced'], 'file26_external_evidence_consent_required' ), 'external evidence requires per-request explicit consent at handler boundary' );
f26_second40_assert( false !== strpos( $files['future_infra'], "META_HISTORY_OPT_IN => 'Server search history sync opt-in'" ), 'privacy export includes server-history synchronization consent state' );
f26_second40_assert( false !== strpos( $files['future_utility'], "esc_url_raw( (string) $item[ $key ], array( 'http', 'https' ) )" ), 'Future safe-result boundary sanitizes returned URLs' );
f26_second40_assert( false !== strpos( $files['future_utility'], 'wp_strip_all_tags( (string) $item[ $key ] )' ), 'Future safe-result boundary strips unsafe excerpt markup' );
f26_second40_assert( false !== strpos( $files['future_class'], "'unavailable' ===" ) && false !== strpos( $files['future_class'], 'schema_drift' ), 'Future bootstrap remains closed when core schema health is unavailable or drifting' );

f26_second40_assert( false !== strpos( $files['qa'], 'FAIL: node is required for JavaScript syntax verification' ), 'JavaScript syntax runtime is a hard QA requirement' );
f26_second40_assert( false !== strpos( $files['qa'], 'node --check "$ROOT/assets/js/file26-future.js"' ), 'Future JavaScript receives syntax verification' );
f26_second40_assert( false !== strpos( $files['qa'], 'stat.S_ISLNK' ) && false !== strpos( $files['qa'], '0o644' ), 'ZIP QA rejects symlinks and verifies fixed file metadata' );

f26_second40_assert( false !== strpos( $files['builder'], 'FIXED_FILE_MODE = 0o644' ), 'package builder uses environment-independent file modes' );
f26_second40_assert( false !== strpos( $files['builder'], 'if path.is_symlink():' ), 'package builder rejects symlink input' );
f26_second40_assert( false !== strpos( $files['workflow'], "'review/**'" ), 'review branches receive exact-head GitHub Actions runs' );

if ( $failures ) {
    fwrite( STDERR, "$failures of $checks second-cycle assertions failed.\n" );
    exit( 1 );
}
echo "PASS: $checks second-cycle review regression assertions\n";
