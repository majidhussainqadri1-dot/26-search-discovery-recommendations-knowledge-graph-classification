<?php
/** Regression contract for the second sequential File 26 review cycle. */
$root = dirname( __DIR__ );
$files = array(
    'db' => file_get_contents( $root . '/includes/class-file26-db.php' ),
    'rest' => file_get_contents( $root . '/includes/class-file26-rest.php' ),
    'owner' => file_get_contents( $root . '/includes/class-file26-owner-contracts.php' ),
    'roles' => file_get_contents( $root . '/includes/class-file26-roles.php' ),
    'plugin' => file_get_contents( $root . '/includes/class-file26-plugin.php' ),
    'central' => file_get_contents( $root . '/includes/class-file26-central-plan.php' ),
    'qa' => file_get_contents( $root . '/qa/run-tests.sh' ),
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
f26_second40_assert( false !== strpos( $files['db'], "delete_option(self::OPTION_SCHEMA);return false;" ), 'main schema marker is cleared when a required table is missing' );
f26_second40_assert( false !== strpos( $files['db'], "return $schema_saved||SABRI_FILE26_SCHEMA_VERSION===(string)get_option(self::OPTION_SCHEMA)" ), 'main schema version is verified after persistence' );
f26_second40_assert( false !== strpos( $files['db'], "if(!$saved&&get_option(self::OPTION_SETTINGS,array())!==$merged){return false;}" ), 'settings persistence fails closed' );
f26_second40_assert( false !== strpos( $files['db'], "return $ok;" ) && false !== strpos( $files['db'], "wp_schedule_event" ), 'cron scheduling returns a verified outcome' );

f26_second40_assert( false !== strpos( $files['rest'], "file26_membership_invalid" ), 'protected REST routes require current membership assertions' );
f26_second40_assert( false !== strpos( $files['rest'], "is_wp_error($appeals)?$appeals" ), 'own-appeals read errors are not hidden inside success envelopes' );
f26_second40_assert( false !== strpos( $files['rest'], "is_wp_error($result)?$result:$this->respond($result)" ), 'reconciliation errors are propagated to REST callers' );
f26_second40_assert( false !== strpos( $files['rest'], "Cache-Control','private, no-store" ), 'non-public REST responses stay no-store' );

f26_second40_assert( false !== strpos( $files['owner'], "if ( ! $approved ) { return false; }" ), 'cross-file activation requires explicit upstream approval' );
f26_second40_assert( false !== strpos( $files['owner'], "'ready'=>$callbacks&&isset($connector['status'])&&'active'===$connector['status']" ), 'owner readiness explicitly distinguishes active-ready connectors' );
f26_second40_assert( false !== strpos( $files['owner'], "matching_connector_count" ), 'owner readiness exposes duplicate/multiple connector evidence' );

f26_second40_assert( false !== strpos( $files['roles'], "delete_option(self::OPTION_VERSION);return false;" ), 'role migration does not retain a false success marker' );
f26_second40_assert( false !== strpos( $files['roles'], "!$role->has_cap($cap)" ), 'required role capabilities are verified after mutation' );

f26_second40_assert( false !== strpos( $files['plugin'], "if(!Roles::install())" ), 'plugin boot fails closed when separation-of-duties roles cannot be verified' );
f26_second40_assert( false !== strpos( $files['plugin'], "if(!DB::schedule())" ), 'plugin boot surfaces background scheduling failure' );
f26_second40_assert( false !== strpos( $files['plugin'], "Appeal retention commit failed." ), 'appeal retention verifies COMMIT' );
f26_second40_assert( false !== strpos( $files['plugin'], "file26_schema_marker_incomplete" ), 'plugin validates schema markers after table migration' );

f26_second40_assert( false !== strpos( $files['central'], "file26_membership_invalid" ), 'central-plan account routes require current membership assertions' );
f26_second40_assert( false !== strpos( $files['central'], "private, no-store" ), 'central-plan sensitive/account responses remain no-store' );

f26_second40_assert( false !== strpos( $files['qa'], "FAIL: node is required for JavaScript syntax verification" ), 'JavaScript syntax runtime is a hard QA requirement' );
f26_second40_assert( false !== strpos( $files['qa'], "node --check \"$ROOT/assets/js/file26-future.js\"" ), 'Future JavaScript receives syntax verification' );

if ( $failures ) {
    fwrite( STDERR, "$failures of $checks second-cycle assertions failed.\n" );
    exit( 1 );
}
echo "PASS: $checks second-cycle review regression assertions\n";
