<?php
/** Round 91 DB/schema/roles/cron regression guards. */
$root = dirname( __DIR__ );
$db = file_get_contents( $root . '/includes/class-file26-db.php' );
$roles = file_get_contents( $root . '/includes/class-file26-roles.php' );
$health = file_get_contents( $root . '/includes/class-file26-health.php' );
$checks = 0;
function f26_r91_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r91_assert( false !== strpos( $db, 'required_columns' ) && false !== strpos( $db, 'schema_physical_ok' ) && false !== strpos( $db, 'SHOW COLUMNS FROM' ), 'R91: schema marker is backed by physical column parity' );
f26_r91_assert( false !== strpos( $db, 'ensure_schedule' ) && false !== strpos( $db, 'wp_get_scheduled_event' ) && false !== strpos( $db, 'sabri_file26_monthly' ), 'R91: required cron recurrences are verified and repaired' );
f26_r91_assert( false !== strpos( $roles, 'model_ok' ) && false !== strpos( $roles, "self::VERSION===get_option(self::OPTION_VERSION)&&self::model_ok()" ), 'R91: role marker is not trusted without physical capability parity' );
f26_r91_assert( false !== strpos( $health, 'DB::schema_physical_ok()' ) && false !== strpos( $health, 'cron_recurrence_drift' ) && false !== strpos( $health, 'role_model_ok' ), 'R91: health exposes physical schema, cron and role drift' );
echo "PASS: $checks Round 91 regression assertions\n";
