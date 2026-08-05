<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Health {
	private $connectors;

	public function __construct( Connectors $connectors ) {
		$this->connectors = $connectors;
	}

	public function snapshot() {
		global $wpdb;
		$tables = array();
		foreach ( array(
			'connectors', 'documents', 'tombstones', 'terms', 'term_aliases',
			'classifications', 'nodes', 'edges', 'ranking_policies', 'feedback',
			'profiles', 'jobs', 'audit', 'metrics', 'rate_limits',
		) as $name ) {
			$table = DB::table( $name );
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
			$tables[ $name ] = $exists ? 'present' : 'missing';
		}
		$counts = array();
		foreach ( array( 'documents', 'tombstones', 'terms', 'edges', 'jobs' ) as $name ) {
			if ( 'present' === $tables[ $name ] ) {
				$counts[ $name ] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . DB::table( $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
		$dead_letter = 'present' === $tables['jobs'] ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . DB::table( 'jobs' ) . " WHERE status='dead_letter'" ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$unknown = in_array( 'missing', $tables, true );
		$connector_health = $this->connectors->health_snapshot();
		$degraded = false;
		foreach ( $connector_health as $connector ) {
			if ( in_array( $connector['state'], array( 'degraded', 'unavailable' ), true ) ) {
				$degraded = true;
			}
		}
		$activated = (bool) DB::setting( 'activated', false );
		$status = $unknown ? 'unavailable' : ( $degraded || $dead_letter ? 'degraded' : ( $activated ? 'healthy' : 'inactive' ) );
		return array(
			'status' => $status,
			'plugin_version' => SABRI_FILE26_VERSION,
			'schema_version' => get_option( DB::OPTION_SCHEMA ),
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
			'activated' => $activated,
			'tables' => $tables,
			'counts' => $counts,
			'dead_letter_jobs' => $dead_letter,
			'connectors' => $connector_health,
			'cron' => array(
				'queue' => wp_next_scheduled( DB::CRON_QUEUE ) ?: null,
				'reconcile' => wp_next_scheduled( DB::CRON_RECONCILE ) ?: null,
				'retention' => wp_next_scheduled( DB::CRON_RETENTION ) ?: null,
				'doctor_ranking' => wp_next_scheduled( DB::CRON_DOCTOR_RANKING ) ?: null,
			),
			'doctor_ranking' => array( 'policy_version' => DB::setting( 'doctor_ranking_policy_version', 'doctor-global-1.0' ), 'last_run' => DB::setting( 'doctor_ranking_last_run', '' ) ),
			'claims' => array(
				'staging_accepted' => false,
				'live_deployed' => false,
				'operational' => false,
			),
			'timestamp_utc' => DB::now(),
		);
	}
}
