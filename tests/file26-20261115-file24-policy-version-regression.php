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
	}
}
namespace {
	define( 'ABSPATH', '/' );
	define( 'ARRAY_A', 'ARRAY_A' );
	function sanitize_text_field( $value ) { return trim( (string) $value ); }
	$wpdb = new class {
		public $active = null;
		public $last_error = '';
		public function get_row( $sql, $type ) { return $this->active; }
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
	$wpdb->last_error = 'owner table unavailable';
	$dbFailure = $ranking->policy();
	check( ! empty( $dbFailure['policy_read_failed'] ), 'DB error must fail closed' );

	$governance = file_get_contents( dirname( __DIR__ ) . '/includes/class-file26-governance.php' );
	check( substr_count( $governance, 'Doctor_Ranking::is_assurance_policy_version' ) >= 3, 'stage, activate and rollback must enforce File24 version' );
	$database = file_get_contents( dirname( __DIR__ ) . '/includes/class-file26-db.php' );
	check( false !== strpos( $database, "'doctor_ranking_policy_version'=>'1.0.0'" ), 'new install configuration must use semver' );
	echo "PASS: File26/File24 doctor ranking policy version gates (fail-closed; no deployment claim).\n";
}
