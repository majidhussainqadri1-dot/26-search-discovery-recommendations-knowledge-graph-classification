<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Checked queue worker that preserves the Indexer contract while surfacing job-state failures. */
final class Queue_Truth {
	private $indexer;
	private $connectors;
	private $security;

	public function __construct( Indexer $indexer, Connectors $connectors, Security $security ) {
		$this->indexer = $indexer;
		$this->connectors = $connectors;
		$this->security = $security;
	}

	public function run() {
		global $wpdb;
		$table = DB::table( 'jobs' );
		$timeout = max( 300, min( DAY_IN_SECONDS, (int) DB::setting( 'job_lock_timeout_seconds', 1800 ) ) );
		$stale_before = gmdate( 'Y-m-d H:i:s', time() - $timeout );
		$now = DB::now();
		$recovered = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET status=IF(attempts>=8,'dead_letter','retry'),error_code='worker_timeout',lock_token=NULL,available_at=%s,finished_at=IF(attempts>=8,%s,NULL),updated_at=%s WHERE status='running' AND started_at<%s",
				$now, $now, $now, $stale_before
			)
		);
		if ( false === $recovered ) {
			$this->security->audit( 'search_worker_recovery_failed', array( 'object_type' => 'job', 'object_key' => 'stale-recovery', 'reason' => 'db_update_failed' ) );
			return new \WP_Error( 'file26_worker_recovery_failed', 'Stale search workers could not be recovered safely.' );
		}

		$wpdb->last_error = '';
		$job = $wpdb->get_row( "SELECT * FROM $table WHERE status IN ('pending','retry') AND available_at <= UTC_TIMESTAMP() ORDER BY id ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( null === $job && ! empty( $wpdb->last_error ) ) {
			$this->security->audit( 'search_queue_read_failed', array( 'object_type' => 'job', 'object_key' => 'queue-head', 'reason' => 'db_read_failed' ) );
			return new \WP_Error( 'file26_queue_read_failed', 'The search job queue could not be read safely.' );
		}
		if ( ! $job ) { return true; }

		$token = hash( 'sha256', $job['job_uuid'] . '|' . microtime( true ) );
		$locked = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET status='running',lock_token=%s,started_at=%s,attempts=attempts+1,updated_at=%s WHERE id=%d AND status IN ('pending','retry')",
				$token, DB::now(), DB::now(), $job['id']
			)
		);
		if ( false === $locked ) {
			return new \WP_Error( 'file26_job_claim_failed', 'The search job could not be claimed because its queue state write failed.' );
		}
		if ( 1 !== (int) $locked ) { return true; }

		$attempts = (int) $job['attempts'] + 1;
		$scope = json_decode( $job['scope_json'], true );
		$connector = is_array( $scope ) && isset( $scope['connector'] ) ? $this->connectors->get( $scope['connector'] ) : null;
		if ( ! $connector || empty( $connector['list_batch'] ) || ! is_callable( $connector['list_batch'] ) ) {
			return $this->fail_job( $job, $token, 'connector_unavailable', $attempts );
		}

		try {
			$batch = call_user_func( $connector['list_batch'], $job['cursor_value'], 100, $scope );
			if ( ! is_array( $batch ) || ! isset( $batch['items'] ) || ! is_array( $batch['items'] ) ) {
				throw new \RuntimeException( 'Invalid connector batch.' );
			}
			$counts = json_decode( $job['counts_json'], true );
			$counts = is_array( $counts ) ? $counts : array( 'processed' => 0, 'failed' => 0 );
			foreach ( $batch['items'] as $document ) {
				$result = $this->indexer->upsert( $document );
				if ( is_wp_error( $result ) ) { $counts['failed']++; } else { $counts['processed']++; }
			}
			$done = ! empty( $batch['done'] );
			$saved = $wpdb->update(
				$table,
				array(
					'status' => $done ? 'completed' : 'pending',
					'cursor_value' => isset( $batch['next_cursor'] ) ? sanitize_text_field( $batch['next_cursor'] ) : '',
					'counts_json' => wp_json_encode( $counts ),
					'lock_token' => null,
					'available_at' => DB::now(),
					'finished_at' => $done ? DB::now() : null,
					'updated_at' => DB::now(),
				),
				array( 'id' => $job['id'], 'lock_token' => $token )
			);
			if ( false === $saved ) {
				return new \WP_Error( 'file26_job_completion_failed', 'The search job completion state could not be stored.' );
			}
			if ( 1 !== (int) $saved ) {
				$this->security->audit( 'search_job_lock_lost', array( 'object_type' => 'job', 'object_key' => $job['job_uuid'], 'reason' => 'completion_cas_failed' ) );
				return new \WP_Error( 'file26_job_lock_lost', 'The search job lock changed before completion could be recorded.' );
			}
			return array( 'job_uuid' => $job['job_uuid'], 'completed' => $done, 'counts' => $counts );
		} catch ( \Throwable $e ) {
			return $this->fail_job( $job, $token, 'job_exception', $attempts );
		}
	}

	private function fail_job( array $job, $token, $code, $attempts ) {
		global $wpdb;
		$dead = $attempts >= 8;
		$retry = min( DAY_IN_SECONDS, (int) pow( 2, min( 10, $attempts ) ) * 60 );
		$updated = $wpdb->update(
			DB::table( 'jobs' ),
			array(
				'status' => $dead ? 'dead_letter' : 'retry',
				'error_code' => sanitize_key( $code ),
				'lock_token' => null,
				'available_at' => gmdate( 'Y-m-d H:i:s', time() + $retry ),
				'finished_at' => $dead ? DB::now() : null,
				'updated_at' => DB::now(),
			),
			array( 'id' => (int) $job['id'], 'lock_token' => $token )
		);
		if ( false === $updated ) {
			return new \WP_Error( 'file26_job_failure_transition_failed', 'The failed search job could not be moved to retry/dead-letter state.' );
		}
		if ( 1 !== (int) $updated ) {
			return new \WP_Error( 'file26_job_lock_lost', 'The failed search job lock changed before failure state could be recorded.' );
		}
		return new \WP_Error( $dead ? 'file26_job_dead_lettered' : 'file26_job_retry_scheduled', $dead ? 'The search job exhausted retries and was dead-lettered.' : 'The search job failed and a retry was scheduled.' );
	}
}
