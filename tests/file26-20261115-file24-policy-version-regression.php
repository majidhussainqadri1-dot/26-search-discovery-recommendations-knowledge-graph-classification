<?php
/**
 * File26 <-> File24 contract regression (source-only).
 * No WordPress connection, production data or database mutation occurs.
 * Reproduces issue #40 without interpreting CI as deployed assurance.
 */
namespace Sabri\File26 {
	final class DB {
		public static $configured = '1.0.0';
		public static function setting( $key, $fallback = null ) { return self::$configured; }
		public static function table( $name ) { return 'wp_f26_' . $name; }
		public static function uuid() { return '12345678-1234-1234-1234-123456789012'; }
		public static function now() { return '2026-11-15 06:00:00'; }
	}
	final class Security {
		public function can_approve_ranking() { return true; }
		public function require_step_up( $purpose ) { return true; }
		public function audit( $action, $details ) { return true; }
	}
}
namespace {
	define( 'ABSPATH', '/' );
	define( 'ARRAY_A', 'ARRAY_A' );
	function sanitize_text_field( $value ) { return trim( (string) $value ); }
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
	function is_wp_error( $value ) { return $value instanceof \WP_Error; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function get_current_user_id() { return 1; }
	function absint( $value ) { return abs( (int) $value ); }
	function user_can( $user_id, $capability ) { return true; }
	function apply_filters( $name, $value ) { return $value; }
	function get_transient( $key ) { return array( 'user_id' => 3, 'expires_at' => time() + 60 ); }
	class WP_Error {
		private $code;
		public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; }
		public function get_error_code() { return $this->code; }
	}
	$wpdb = new class {
		public $active = null;
		public $last_error = '';
		public $failRead = false;
		public $previous = null;
		public $writes = 0;
		public $transactions = 0;
		public function get_row( $sql, $type ) {
			if ( $this->failRead ) { $this->last_error = 'mock owner table unavailable'; return null; }
			if ( false !== strpos( $sql, "status='rolled_back'" ) ) { return $this->previous; }
			return $this->active;
		}
		public function prepare( $sql ) { return $sql; }
		public function insert( $table, $record ) { $this->writes++; return 1; }
		public function query( $sql ) {
			if ( 0 === strpos( $sql, 'START TRANSACTION' ) ) { $this->transactions++; }
			return 1;
		}
	};
	$GLOBALS['wpdb'] = $wpdb;
	require dirname( __DIR__ ) . '/includes/class-file26-doctor-ranking.php';

	function check( $ok, $label ) {
		if ( ! $ok ) { fwrite( STDERR, 'FAIL: ' . $label . "\n" ); exit( 1 ); }
	}

	$valid = array( '1', '1.0', '1.0.0', '1.2.3.4', '2.0.0-rc.1' );
	$invalid = array( 'doctor-global-1.0', 'safe-fallback-1.0', 'v1.0.0', '', '1.2.3.4.5', '1.0.0+build', '1.0.0<script>', '1.0.0' . str_repeat( 'a', 41 ), 123, array( '1.0.0' ), null );
	foreach ( $valid as $version ) { check( \Sabri\File26\Doctor_Ranking::is_assurance_policy_version( $version ), 'valid File24 version rejected ' . $version ); }
	foreach ( $invalid as $version ) { check( ! \Sabri\File26\Doctor_Ranking::is_assurance_policy_version( $version ), 'incompatible version admitted' ); }

	$reflection = new \ReflectionClass( '\\Sabri\\File26\\Doctor_Ranking' );
	$ranking = $reflection->newInstanceWithoutConstructor();
	$default = $ranking->policy();
	check( '1.0.0' === $default['version'] && empty( $default['policy_read_failed'] ), 'new-install fallback must remain an explicit semantic version' );

	\Sabri\File26\DB::$configured = 'doctor-global-1.0';
	$legacy = $ranking->policy();
	check( ! empty( $legacy['policy_read_failed'] ) && ! empty( $legacy['safe_fallback'] ) && 'policy-unavailable' === $legacy['version'], 'legacy option must not be silently approved or rewritten' );

	\Sabri\File26\DB::$configured = '1.0.0';
	$wpdb->active = array( 'version' => 'doctor-global-1.0', 'features_json' => '{}' );
	$activeLegacy = $ranking->policy();
	check( ! empty( $activeLegacy['policy_read_failed'] ), 'legacy active row must fail closed' );

	$wpdb->active = array( 'version' => '1.2.3', 'features_json' => '{}' );
	$activeCurrent = $ranking->policy();
	check( '1.2.3' === $activeCurrent['version'] && empty( $activeCurrent['policy_read_failed'] ), 'approved-format row must remain usable' );

	$wpdb->active = array( 'version' => '1.2.3', 'features_json' => json_encode( array( 'weights' => array_fill_keys( array_keys( $activeCurrent['weights'] ), 0 ) ) ) );
	$zeroWeights = $ranking->policy();
	check( ! empty( $zeroWeights['policy_read_failed'] ), 'zero-weight doctor policy must not claim valid assurance' );

	$wpdb->active = null;
	$wpdb->failRead = true;
	$dbFailure = $ranking->policy();
	check( ! empty( $dbFailure['policy_read_failed'] ), 'DB error must fail closed' );

	$wpdb->failRead = false;
	require dirname( __DIR__ ) . '/includes/class-file26-governance.php';
	$gr = new \ReflectionClass( '\\Sabri\\File26\\Governance' );
	$governor = $gr->newInstanceWithoutConstructor();
	$property = $gr->getProperty( 'security' );
	$property->setAccessible( true );
	$property->setValue( $governor, new \Sabri\File26\Security() );

	$wpdb->writes = 0;
	$wpdb->transactions = 0;
	$invalidStage = $governor->stage_ranking_policy( array(
		'context' => 'doctor_global',
		'audience' => 'public',
		'version' => 'doctor-global-1.0',
		'features' => array( 'weights' => array( 'qualification_score' => 1 ) ),
	) );
	check( is_wp_error( $invalidStage ) && 'file26_invalid_doctor_policy_version' === $invalidStage->get_error_code(), 'legacy stage must be explicitly rejected' );
	check( 0 === $wpdb->writes, 'invalid stage must not write a policy row' );

	$validStage = $governor->stage_ranking_policy( array(
		'context' => 'doctor_global',
		'audience' => 'public',
		'version' => '1.0.1',
		'features' => array( 'weights' => array( 'qualification_score' => 1 ) ),
	) );
	check( ! is_wp_error( $validStage ) && 1 === $wpdb->writes, 'valid staged policy must still be supported' );

	$wpdb->active = array(
		'policy_uuid' => 'legacy-staged',
		'status' => 'staged',
		'context_name' => 'doctor_global',
		'audience' => 'public',
		'version' => 'doctor-global-1.0',
		'approval_one' => 2,
		'approval_two' => 3,
	);
	$invalidActivation = $governor->activate_ranking_policy( 'legacy-staged', 3, 'test-only' );
	check( is_wp_error( $invalidActivation ) && 'file26_invalid_doctor_policy_version' === $invalidActivation->get_error_code(), 'legacy activation must fail closed' );
	check( 0 === $wpdb->transactions, 'invalid activation must not start any transaction' );

	$wpdb->active['status'] = 'active';
	$wpdb->previous = array( 'policy_uuid' => 'legacy-rolled-back', 'version' => 'doctor-global-1.0' );
	$invalidRollback = $governor->rollback_ranking_policy( 'legacy-staged', 'test-only', 3 );
	check( is_wp_error( $invalidRollback ) && 'file26_invalid_doctor_policy_version' === $invalidRollback->get_error_code(), 'historical invalid version rollback must fail closed' );
	check( 0 === $wpdb->transactions, 'invalid rollback must not start any transaction' );

	$governance = file_get_contents( dirname( __DIR__ ) . '/includes/class-file26-governance.php' );
	check( substr_count( $governance, 'Doctor_Ranking::is_assurance_policy_version' ) >= 3, 'stage, activate and rollback must enforce File24 version' );
	$database = file_get_contents( dirname( __DIR__ ) . '/includes/class-file26-db.php' );
	check( false !== strpos( $database, "'doctor_ranking_policy_version'=>'1.0.0'" ), 'new install configuration must use semver' );
	echo "PASS: File26/File24 doctor ranking policy version gates (fail-closed; no deployment claim).\n";
}
