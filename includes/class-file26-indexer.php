<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Indexer {
	private $connectors;
	private $normalizer;
	private $security;

	public function __construct( Connectors $connectors, Normalizer $normalizer, Security $security ) {
		$this->connectors = $connectors;
		$this->normalizer = $normalizer;
		$this->security = $security;
	}

	public static function canonical_key( $connector, $domain, $object_id ) {
		return hash( 'sha256', sanitize_key( $connector ) . '|' . sanitize_key( $domain ) . '|' . (string) $object_id );
	}

	public function upsert( array $document ) {
		global $wpdb;
		$validation = $this->connectors->validate_document( $document );
		if ( is_wp_error( $validation ) ) { return $validation; }
		$document = $this->sanitize_document( $document );
		if ( is_wp_error( $document ) ) { return $document; }
		if ( in_array( $document['state'], array( 'deleted', 'suspended', 'restricted', 'rejected', 'private' ), true ) || 'restricted' === $document['visibility'] ) {
			return $this->tombstone( $document['connector_slug'], $document['domain_name'], $document['object_id'], $document['object_version'], $document['state'] );
		}

		$lock = $this->acquire_object_lock( $document['canonical_key'] );
		if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$tombstone_version = $wpdb->get_var( $wpdb->prepare( 'SELECT object_version FROM ' . DB::table( 'tombstones' ) . ' WHERE canonical_key=%s', $document['canonical_key'] ) );
			if ( ! empty( $wpdb->last_error ) ) { return new \WP_Error( 'file26_index_read_failed', 'Search document revocation state could not be read safely.' ); }
			$tombstone_version = null === $tombstone_version ? 0 : (int) $tombstone_version;
			if ( $tombstone_version >= (int) $document['object_version'] ) {
				$this->security->audit( 'index_event_ignored', array( 'object_type' => 'document', 'object_key' => $document['canonical_key'], 'reason' => 'tombstone_precedence', 'metadata' => array( 'tombstone_version' => $tombstone_version, 'incoming_version' => (int) $document['object_version'] ) ) );
				return true;
			}

			$table = DB::table( 'documents' );
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT object_version,source_event_id,source_event_sequence,checksum FROM $table WHERE canonical_key=%s", $document['canonical_key'] ), ARRAY_A );
			if ( null === $existing && ! empty( $wpdb->last_error ) ) { return new \WP_Error( 'file26_index_read_failed', 'Existing search document state could not be read safely.' ); }
			if ( $existing ) {
				$incoming_version = (int) $document['object_version'];
				$current_version = (int) $existing['object_version'];
				$incoming_sequence = (int) $document['source_event_sequence'];
				$current_sequence = (int) $existing['source_event_sequence'];
				if ( $incoming_version < $current_version || ( $incoming_version === $current_version && $incoming_sequence < $current_sequence ) ) {
					$this->security->audit( 'index_event_ignored', array( 'object_type' => 'document', 'object_key' => $document['canonical_key'], 'reason' => 'stale_source_version' ) );
					return true;
				}
				if ( $incoming_version === $current_version && $incoming_sequence === $current_sequence && ! hash_equals( (string) $existing['checksum'], (string) $document['checksum'] ) ) {
					return new \WP_Error( 'file26_event_identity_conflict', 'The same source version and event sequence arrived with different searchable content.', array( 'status' => 409 ) );
				}
				if ( $incoming_version === $current_version && $incoming_sequence === $current_sequence && hash_equals( (string) $existing['checksum'], (string) $document['checksum'] ) ) {
					return true;
				}
			}

			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return new \WP_Error( 'file26_index_transaction_failed', 'Search indexing transaction could not start.', array( 'status' => 500 ) ); }
			try {
				$sql = $wpdb->prepare(
					"INSERT INTO $table (canonical_key,connector_slug,domain_name,object_id,object_version,entity_type,locale,state,visibility,title,excerpt,normalized_title,normalized_body,canonical_url,author_key,topic_ids,country,location,availability,quality_score,authority_score,popularity_score,freshness_at,safety_class,payload,source_event_id,source_event_sequence,checksum,indexed_at,updated_at) VALUES (" . implode( ',', array_fill( 0, 30, '%s' ) ) . ") ON DUPLICATE KEY UPDATE object_version=VALUES(object_version),entity_type=VALUES(entity_type),locale=VALUES(locale),state=VALUES(state),visibility=VALUES(visibility),title=VALUES(title),excerpt=VALUES(excerpt),normalized_title=VALUES(normalized_title),normalized_body=VALUES(normalized_body),canonical_url=VALUES(canonical_url),author_key=VALUES(author_key),topic_ids=VALUES(topic_ids),country=VALUES(country),location=VALUES(location),availability=VALUES(availability),quality_score=VALUES(quality_score),authority_score=VALUES(authority_score),popularity_score=VALUES(popularity_score),freshness_at=VALUES(freshness_at),safety_class=VALUES(safety_class),payload=VALUES(payload),source_event_id=VALUES(source_event_id),source_event_sequence=VALUES(source_event_sequence),checksum=VALUES(checksum),indexed_at=VALUES(indexed_at),updated_at=VALUES(updated_at)",
					$document['canonical_key'], $document['connector_slug'], $document['domain_name'], $document['object_id'], $document['object_version'], $document['entity_type'], $document['locale'], $document['state'], $document['visibility'], $document['title'], $document['excerpt'], $document['normalized_title'], $document['normalized_body'], $document['canonical_url'], $document['author_key'], $document['topic_ids'], $document['country'], $document['location'], $document['availability'], $document['quality_score'], $document['authority_score'], $document['popularity_score'], $document['freshness_at'], $document['safety_class'], $document['payload'], $document['source_event_id'], $document['source_event_sequence'], $document['checksum'], $document['indexed_at'], $document['updated_at']
				);
				if ( false === $wpdb->query( $sql ) ) { throw new \RuntimeException( 'Document write failed.' ); }
				if ( false === $wpdb->delete( DB::table( 'tombstones' ), array( 'canonical_key' => $document['canonical_key'] ), array( '%s' ) ) ) { throw new \RuntimeException( 'Stale tombstone cleanup failed.' ); }
				$node = $this->upsert_node( $document );
				if ( is_wp_error( $node ) ) { throw new \RuntimeException( 'Node projection write failed.' ); }
				if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'Indexing commit failed.' ); }
			} catch ( \Throwable $e ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error( 'file26_index_write_failed', 'Search document and its graph projection could not be indexed atomically.', array( 'status' => 500 ) );
			}
			$this->flush_derivative_cache();
			$this->security->audit( 'search_document_indexed', array( 'object_type' => 'document', 'object_key' => $document['canonical_key'], 'metadata' => array( 'connector' => $document['connector_slug'], 'entity_type' => $document['entity_type'], 'version' => $document['object_version'] ) ) );
			do_action( 'sabri_file26_event', 'SearchDocumentIndexed', array( 'contract_version' => SABRI_FILE26_CONTRACT_VERSION, 'canonical_key' => $document['canonical_key'], 'object_version' => $document['object_version'] ) );
			return $document['canonical_key'];
		} finally {
			$this->release_object_lock( $lock );
		}
	}

	private function sanitize_document( array $document ) {
		$state = sanitize_key( $document['state'] );
		$visibility = sanitize_key( $document['visibility'] );
		$allowed_states = array( 'published', 'active', 'corrected', 'retracted', 'restricted', 'suspended', 'deleted', 'private', 'rejected' );
		$allowed_visibility = array( 'public', 'members', 'entitled', 'minor_guarded', 'restricted' );
		if ( ! in_array( $state, $allowed_states, true ) || ! in_array( $visibility, $allowed_visibility, true ) ) { return new \WP_Error( 'file26_invalid_visibility_state', 'Unknown state or visibility; fail closed.' ); }
		$url = $this->security->safe_url( $document['canonical_url'] );
		if ( ! $url ) { return new \WP_Error( 'file26_invalid_canonical_url', 'Canonical URL must be a safe same-origin route.' ); }
		$connector = sanitize_key( $document['connector_slug'] );
		$domain = sanitize_key( $document['domain'] );
		$object_id = substr( sanitize_text_field( (string) $document['object_id'] ), 0, 191 );
		$title = sanitize_text_field( $document['title'] );
		$excerpt = isset( $document['excerpt'] ) ? wp_strip_all_tags( $document['excerpt'], true ) : '';
		$body = isset( $document['search_text'] ) ? wp_strip_all_tags( $document['search_text'], true ) : $excerpt;
		$payload = isset( $document['payload'] ) && is_array( $document['payload'] ) ? $this->sanitize_payload( $document['payload'] ) : array();
		$version = max( 1, (int) $document['object_version'] );
		$sequence = isset( $document['source_event_sequence'] ) ? max( 0, (int) $document['source_event_sequence'] ) : $version;
		$event_id = isset( $document['source_event_id'] ) ? substr( sanitize_text_field( $document['source_event_id'] ), 0, 191 ) : '';
		$now = DB::now();
		$freshness = null;
		if ( ! empty( $document['freshness_at'] ) ) {
			$ts = strtotime( (string) $document['freshness_at'] );
			if ( false === $ts ) { return new \WP_Error( 'file26_invalid_freshness', 'Source freshness timestamp is invalid.', array( 'status' => 400 ) ); }
			$freshness = gmdate( 'Y-m-d H:i:s', $ts );
		}
		$data = array(
			'canonical_key' => self::canonical_key( $connector, $domain, $object_id ), 'connector_slug' => $connector, 'domain_name' => $domain,
			'object_id' => $object_id, 'object_version' => $version, 'entity_type' => sanitize_key( $document['entity_type'] ),
			'locale' => substr( sanitize_text_field( $document['locale'] ), 0, 20 ), 'state' => $state, 'visibility' => $visibility,
			'title' => $title, 'excerpt' => $excerpt, 'normalized_title' => $this->normalizer->normalize( $title ), 'normalized_body' => $this->normalizer->normalize( $body ),
			'canonical_url' => $url, 'author_key' => isset( $document['author_key'] ) ? substr( sanitize_text_field( $document['author_key'] ), 0, 191 ) : '',
			'topic_ids' => wp_json_encode( array_values( array_unique( array_map( 'sanitize_text_field', isset( $document['topic_ids'] ) ? (array) $document['topic_ids'] : array() ) ) ) ),
			'country' => isset( $document['country'] ) ? substr( sanitize_text_field( $document['country'] ), 0, 64 ) : '', 'location' => isset( $document['location'] ) ? substr( sanitize_text_field( $document['location'] ), 0, 191 ) : '',
			'availability' => isset( $document['availability'] ) ? substr( sanitize_key( $document['availability'] ), 0, 32 ) : '', 'quality_score' => isset( $document['quality_score'] ) ? min( 1, max( 0, (float) $document['quality_score'] ) ) : 0,
			'authority_score' => isset( $document['authority_score'] ) ? min( 1, max( 0, (float) $document['authority_score'] ) ) : 0, 'popularity_score' => isset( $document['popularity_score'] ) ? max( 0, (float) $document['popularity_score'] ) : 0,
			'freshness_at' => $freshness, 'safety_class' => isset( $document['safety_class'] ) ? substr( sanitize_key( $document['safety_class'] ), 0, 32 ) : 'general',
			'payload' => wp_json_encode( $payload ), 'source_event_id' => $event_id, 'source_event_sequence' => $sequence, 'indexed_at' => $now, 'updated_at' => $now,
		);
		$semantic = $data;
		unset( $semantic['object_version'], $semantic['source_event_id'], $semantic['source_event_sequence'], $semantic['indexed_at'], $semantic['updated_at'] );
		$data['checksum'] = hash( 'sha256', wp_json_encode( $semantic ) );
		return $data;
	}

	private function sanitize_payload( array $payload ) {
		$allowed = array( 'image_url','duration','verified_author','verified_doctor','global_doctor_rank','correction_label','retraction_label','required_entitlement','download_allowed','download_label','download_reason','source_count','language','content_type_label','ai_generated','ai_provider_label','institutional_account_class','qualification_score','experience_score','patient_verified_review_score','ethical_conduct_score','knowledge_contribution_score','responsiveness_score','profile_completeness_score','complaint_appeal_outcome_score','manipulation_resistant_engagement_score','doctor_rank_score','doctor_rank_policy_version' );
		$clean = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $payload ) ) { continue; }
			if ( 'image_url' === $key ) { $clean[ $key ] = $this->security->safe_resource_url( $payload[ $key ], $key ); }
			elseif ( in_array( $key, array( 'verified_author','verified_doctor','download_allowed','ai_generated' ), true ) ) { $clean[ $key ] = (bool) $payload[ $key ]; }
			elseif ( 'global_doctor_rank' === $key || 'source_count' === $key ) { $clean[ $key ] = max( 0, (int) $payload[ $key ] ); }
			elseif ( 'doctor_rank_score' === $key ) { $clean[ $key ] = min( 100, max( 0, (float) $payload[ $key ] ) ); }
			elseif ( preg_match( '/_score$/', $key ) ) { $clean[ $key ] = min( 1, max( 0, (float) $payload[ $key ] ) ); }
			else { $clean[ $key ] = sanitize_text_field( $payload[ $key ] ); }
		}
		return $clean;
	}

	public function restrict( $connector, $domain, $object_id, $object_version, $reason = 'restricted' ) { return $this->tombstone( $connector, $domain, $object_id, $object_version, $reason ); }

	public function tombstone( $connector, $domain, $object_id, $object_version, $reason = 'deleted' ) {
		global $wpdb;
		$key = self::canonical_key( $connector, $domain, $object_id );
		$lock = $this->acquire_object_lock( $key );
		if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$document_table = DB::table( 'documents' );
			$existing_raw = $wpdb->get_var( $wpdb->prepare( "SELECT object_version FROM $document_table WHERE canonical_key=%s", $key ) );
			if ( ! empty( $wpdb->last_error ) ) { return new \WP_Error( 'file26_tombstone_read_failed', 'Current indexed version could not be read safely.' ); }
			$existing_version = null === $existing_raw ? 0 : (int) $existing_raw;
			if ( $existing_version > (int) $object_version ) { return true; }
			$table = DB::table( 'tombstones' ); $now = DB::now();
			$days = max( 90, min( 180, (int) DB::setting( 'tombstone_retention_days', 180 ) ) );
			$expires = gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return new \WP_Error( 'file26_tombstone_transaction_failed', 'Revocation transaction could not start.', array( 'status' => 500 ) ); }
			try {
				$sql = $wpdb->prepare( "INSERT INTO $table (canonical_key,connector_slug,domain_name,object_id,object_version,reason_class,received_at,purged_at,expires_at) VALUES (%s,%s,%s,%s,%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE reason_class=IF(VALUES(object_version)>=object_version,VALUES(reason_class),reason_class),received_at=IF(VALUES(object_version)>=object_version,VALUES(received_at),received_at),purged_at=IF(VALUES(object_version)>=object_version,VALUES(purged_at),purged_at),expires_at=IF(VALUES(object_version)>=object_version,VALUES(expires_at),expires_at),object_version=GREATEST(object_version,VALUES(object_version))", $key, sanitize_key( $connector ), sanitize_key( $domain ), substr( sanitize_text_field( $object_id ), 0, 191 ), max( 1, (int) $object_version ), sanitize_key( $reason ), $now, $now, $expires );
				if ( false === $wpdb->query( $sql ) ) { throw new \RuntimeException( 'Tombstone write failed.' ); }
				if ( false === $wpdb->delete( $document_table, array( 'canonical_key' => $key ), array( '%s' ) ) ) { throw new \RuntimeException( 'Document purge failed.' ); }
				if ( false === $wpdb->delete( DB::table( 'nodes' ), array( 'node_key' => $key ), array( '%s' ) ) ) { throw new \RuntimeException( 'Node purge failed.' ); }
				if ( false === $wpdb->delete( DB::table( 'classifications' ), array( 'object_key' => $key ), array( '%s' ) ) ) { throw new \RuntimeException( 'Classification purge failed.' ); }
				if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . DB::table( 'edges' ) . ' WHERE source_key=%s OR target_key=%s', $key, $key ) ) ) { throw new \RuntimeException( 'Edge purge failed.' ); }
				if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'Revocation commit failed.' ); }
			} catch ( \Throwable $e ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error( 'file26_tombstone_failed', 'Document revocation and all derivatives could not be completed atomically.', array( 'status' => 500 ) );
			}
			$this->flush_derivative_cache();
			$this->security->audit( 'search_document_tombstoned', array( 'object_type' => 'document', 'object_key' => $key, 'reason' => $reason, 'metadata' => array( 'version' => (int) $object_version ) ) );
			do_action( 'sabri_file26_event', 'SearchDocumentTombstoned', array( 'contract_version' => SABRI_FILE26_CONTRACT_VERSION, 'canonical_key' => $key, 'object_version' => (int) $object_version, 'reason_class' => sanitize_key( $reason ) ) );
			return true;
		} finally { $this->release_object_lock( $lock ); }
	}

	private function upsert_node( array $document ) {
		global $wpdb;
		$table = DB::table( 'nodes' );
		$sql = $wpdb->prepare( "INSERT INTO $table (node_key,node_type,canonical_url,visibility,state,locale,version,title,payload,updated_at) VALUES (%s,%s,%s,%s,%s,%s,%d,%s,%s,%s) ON DUPLICATE KEY UPDATE node_type=VALUES(node_type),canonical_url=VALUES(canonical_url),visibility=VALUES(visibility),state=VALUES(state),locale=VALUES(locale),version=VALUES(version),title=VALUES(title),payload=VALUES(payload),updated_at=VALUES(updated_at)", $document['canonical_key'], $document['entity_type'], $document['canonical_url'], $document['visibility'], $document['state'], $document['locale'], $document['object_version'], $document['title'], $document['payload'], $document['updated_at'] );
		if ( false === $wpdb->query( $sql ) ) { return new \WP_Error( 'file26_node_write_failed', 'Knowledge-graph node projection could not be written.' ); }
		return true;
	}

	public function enqueue_reindex( $connector_slug, array $scope = array() ) {
		global $wpdb;
		$connector_slug = sanitize_key( $connector_slug );
		$connector = $this->connectors->get( $connector_slug );
		if ( ! $connector || ! $this->connectors->is_index_eligible( $connector_slug ) || empty( $connector['list_batch'] ) || ! is_callable( $connector['list_batch'] ) ) { return new \WP_Error( 'file26_connector_not_reindexable', 'Connector is not currently eligible for governed reindexing.', array( 'status' => 409 ) ); }
		$scope = $this->sanitize_reindex_scope( $scope );
		$scope['connector'] = $connector_slug;
		$scope_json = wp_json_encode( $scope );
		if ( false === $scope_json || strlen( $scope_json ) > 8192 ) { return new \WP_Error( 'file26_invalid_reindex_scope', 'Reindex scope is invalid or too large.', array( 'status' => 400 ) ); }
		$table = DB::table( 'jobs' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT job_uuid FROM $table WHERE job_type='shadow_reindex' AND status IN ('pending','retry','running') AND scope_json=%s LIMIT 1", $scope_json ) );
		if ( ! empty( $wpdb->last_error ) ) { return new \WP_Error( 'file26_job_read_failed', 'Current reindex job state could not be read safely.', array( 'status' => 500 ) ); }
		if ( $existing ) { return new \WP_Error( 'file26_reindex_already_queued', 'An equivalent reindex job is already pending or running.', array( 'status' => 409, 'job_uuid' => $existing ) ); }
		$uuid = DB::uuid();
		$inserted = $wpdb->insert( $table, array( 'job_uuid' => $uuid, 'job_type' => 'shadow_reindex', 'status' => 'pending', 'scope_json' => $scope_json, 'cursor_value' => '', 'counts_json' => wp_json_encode( array( 'processed' => 0, 'failed' => 0 ) ), 'attempts' => 0, 'available_at' => DB::now(), 'created_at' => DB::now(), 'updated_at' => DB::now() ) );
		if ( false === $inserted ) { return new \WP_Error( 'file26_job_enqueue_failed', 'Reindex job could not be queued.', array( 'status' => 500 ) ); }
		return $uuid;
	}

	private function sanitize_reindex_scope( array $scope ) {
		$allowed = array( 'domain', 'entity_type', 'locale', 'mode', 'reason', 'from_version', 'to_version' );
		$clean = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $scope ) || is_array( $scope[ $key ] ) || is_object( $scope[ $key ] ) ) { continue; }
			if ( in_array( $key, array( 'from_version', 'to_version' ), true ) ) { $clean[ $key ] = max( 0, (int) $scope[ $key ] ); }
			elseif ( in_array( $key, array( 'domain', 'entity_type', 'mode' ), true ) ) { $clean[ $key ] = substr( sanitize_key( $scope[ $key ] ), 0, 64 ); }
			else { $clean[ $key ] = substr( sanitize_text_field( $scope[ $key ] ), 0, 191 ); }
		}
		return $clean;
	}

	public function process_queue() {
		global $wpdb;
		$table = DB::table( 'jobs' );
		$timeout = max( 300, min( DAY_IN_SECONDS, (int) DB::setting( 'job_lock_timeout_seconds', 1800 ) ) );
		$stale_before = gmdate( 'Y-m-d H:i:s', time() - $timeout );
		$now = DB::now();
		$recovered = $wpdb->query( $wpdb->prepare( "UPDATE $table SET status=IF(attempts>=8,'dead_letter','retry'),error_code='worker_timeout',lock_token=NULL,available_at=%s,finished_at=IF(attempts>=8,%s,NULL),updated_at=%s WHERE status='running' AND started_at<%s", $now, $now, $now, $stale_before ) );
		if ( false === $recovered ) { return new \WP_Error( 'file26_job_recovery_failed', 'Stale reindex workers could not be recovered safely.' ); }
		$job = $wpdb->get_row( "SELECT * FROM $table WHERE status IN ('pending','retry') AND available_at <= UTC_TIMESTAMP() ORDER BY id ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( null === $job && ! empty( $wpdb->last_error ) ) { return new \WP_Error( 'file26_job_read_failed', 'Runnable reindex job state could not be read safely.' ); }
		if ( ! $job ) { return true; }
		$token = hash( 'sha256', $job['job_uuid'] . '|' . microtime( true ) . '|' . wp_rand() );
		$locked = $wpdb->query( $wpdb->prepare( "UPDATE $table SET status='running',lock_token=%s,started_at=%s,attempts=attempts+1,updated_at=%s WHERE id=%d AND status IN ('pending','retry')", $token, DB::now(), DB::now(), $job['id'] ) );
		if ( false === $locked ) { return new \WP_Error( 'file26_job_claim_failed', 'Reindex job could not be claimed because the job store failed.' ); }
		if ( 1 !== (int) $locked ) { return true; }

		$scope = json_decode( $job['scope_json'], true );
		$scope = is_array( $scope ) ? $scope : array();
		$connector = isset( $scope['connector'] ) ? $this->connectors->get( $scope['connector'] ) : null;
		$attempt = (int) $job['attempts'] + 1;
		if ( ! $connector || ! $this->connectors->is_index_eligible( $scope['connector'] ) || empty( $connector['list_batch'] ) || ! is_callable( $connector['list_batch'] ) ) {
			return $this->fail_job( $job['id'], $token, 'connector_unavailable', $attempt );
		}
		try {
			$old_cursor = (string) $job['cursor_value'];
			$batch = call_user_func( $connector['list_batch'], $old_cursor, 100, $scope );
			if ( ! is_array( $batch ) || ! isset( $batch['items'] ) || ! is_array( $batch['items'] ) || count( $batch['items'] ) > 100 ) { throw new \RuntimeException( 'Invalid connector batch.' ); }
			$done = ! empty( $batch['done'] );
			$next_cursor = isset( $batch['next_cursor'] ) && is_scalar( $batch['next_cursor'] ) ? substr( sanitize_text_field( (string) $batch['next_cursor'] ), 0, 512 ) : '';
			if ( ! $done && empty( $batch['items'] ) && ( '' === $next_cursor || hash_equals( $old_cursor, $next_cursor ) ) ) { throw new \RuntimeException( 'Connector batch made no progress.' ); }
			if ( ! $done && '' === $next_cursor ) { throw new \RuntimeException( 'Connector batch omitted a continuation cursor.' ); }
			$counts = json_decode( $job['counts_json'], true );
			$counts = is_array( $counts ) ? $counts : array( 'processed' => 0, 'failed' => 0 );
			$batch_failed = 0;
			foreach ( $batch['items'] as $document ) {
				$result = is_array( $document ) ? $this->upsert( $document ) : new \WP_Error( 'file26_invalid_document', 'Connector returned a non-document item.' );
				if ( is_wp_error( $result ) ) { $counts['failed']++; $batch_failed++; }
				else { $counts['processed']++; }
			}
			if ( $batch_failed ) {
				$wpdb->update( $table, array( 'counts_json' => wp_json_encode( $counts ), 'updated_at' => DB::now() ), array( 'id' => (int) $job['id'], 'lock_token' => $token ), array( '%s', '%s' ), array( '%d', '%s' ) );
				return $this->fail_job( $job['id'], $token, 'document_index_failed', $attempt );
			}
			$saved = $wpdb->update( $table, array( 'status' => $done ? 'completed' : 'pending', 'cursor_value' => $done ? '' : $next_cursor, 'counts_json' => wp_json_encode( $counts ), 'lock_token' => null, 'available_at' => DB::now(), 'finished_at' => $done ? DB::now() : null, 'updated_at' => DB::now() ), array( 'id' => $job['id'], 'lock_token' => $token, 'status' => 'running' ), array( '%s','%s','%s','%s','%s','%s','%s' ), array( '%d','%s','%s' ) );
			if ( false === $saved ) { return new \WP_Error( 'file26_job_state_write_failed', 'Reindex progress could not be persisted.' ); }
			if ( 1 !== (int) $saved ) { $this->security->audit( 'search_job_lock_lost', array( 'object_type' => 'job', 'object_key' => $job['job_uuid'], 'reason' => 'completion_cas_failed' ) ); return new \WP_Error( 'file26_job_lock_lost', 'Reindex worker lost ownership before progress could be committed.' ); }
			return true;
		} catch ( \Throwable $e ) {
			return $this->fail_job( $job['id'], $token, 'job_exception', $attempt );
		}
	}

	private function fail_job( $id, $token, $code, $attempts ) {
		global $wpdb;
		$retry = min( DAY_IN_SECONDS, (int) pow( 2, min( 10, $attempts ) ) * 60 );
		$status = $attempts >= 8 ? 'dead_letter' : 'retry';
		$updated = $wpdb->update( DB::table( 'jobs' ), array( 'status' => $status, 'error_code' => sanitize_key( $code ), 'lock_token' => null, 'available_at' => gmdate( 'Y-m-d H:i:s', time() + $retry ), 'finished_at' => 'dead_letter' === $status ? DB::now() : null, 'updated_at' => DB::now() ), array( 'id' => (int) $id, 'lock_token' => $token, 'status' => 'running' ) );
		if ( false === $updated ) { return new \WP_Error( 'file26_job_failure_write_failed', 'Reindex failure state could not be persisted.' ); }
		if ( 1 !== (int) $updated ) { return new \WP_Error( 'file26_job_lock_lost', 'Reindex worker lost ownership while recording failure.' ); }
		return new \WP_Error( 'file26_reindex_batch_failed', 'Reindex batch failed and was moved to governed retry or dead-letter state.', array( 'status' => 503, 'error_code' => sanitize_key( $code ), 'job_status' => $status ) );
	}

	private function acquire_object_lock( $canonical_key ) {
		global $wpdb;
		$lock_name = 'file26:' . substr( hash( 'sha256', (string) $canonical_key ), 0, 48 );
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
		if ( '1' !== (string) $acquired ) { return new \WP_Error( 'file26_object_busy', 'Search object is busy; retry safely.' ); }
		return $lock_name;
	}

	private function release_object_lock( $lock_name ) { global $wpdb; if ( is_string( $lock_name ) && '' !== $lock_name ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); } }

	private function flush_derivative_cache() {
		if ( function_exists( 'wp_cache_flush_group' ) ) { wp_cache_flush_group( 'sabri_file26' ); }
		else { wp_cache_flush(); }
	}

	public function reconcile() {
		global $wpdb;
		$documents = DB::table( 'documents' ); $tombstones = DB::table( 'tombstones' ); $nodes = DB::table( 'nodes' ); $classes = DB::table( 'classifications' ); $edges = DB::table( 'edges' );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return new \WP_Error( 'file26_reconcile_transaction_failed', 'Reconciliation transaction could not start.' ); }
		try {
			$queries = array(
				"DELETE c FROM $classes c INNER JOIN $tombstones t ON c.object_key=t.canonical_key LEFT JOIN $documents d ON d.canonical_key=c.object_key WHERE d.canonical_key IS NULL OR t.object_version >= d.object_version",
				"DELETE n FROM $nodes n INNER JOIN $tombstones t ON n.node_key=t.canonical_key WHERE t.object_version >= n.version",
				"DELETE d FROM $documents d INNER JOIN $tombstones t ON d.canonical_key=t.canonical_key WHERE t.object_version >= d.object_version",
				"DELETE e FROM $edges e LEFT JOIN $nodes s ON e.source_key=s.node_key LEFT JOIN $nodes t ON e.target_key=t.node_key WHERE s.node_key IS NULL OR t.node_key IS NULL",
				"UPDATE $documents SET payload=JSON_REMOVE(payload,'$.download_url') WHERE JSON_VALID(payload) AND JSON_EXTRACT(payload,'$.download_url') IS NOT NULL",
				"UPDATE $nodes SET payload=JSON_REMOVE(payload,'$.download_url') WHERE JSON_VALID(payload) AND JSON_EXTRACT(payload,'$.download_url') IS NOT NULL",
			);
			foreach ( $queries as $sql ) { if ( false === $wpdb->query( $sql ) ) { throw new \RuntimeException( 'Reconciliation query failed.' ); } }
			if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'Reconciliation commit failed.' ); }
			$this->flush_derivative_cache();
			return array( 'reconciled' => true, 'timestamp_utc' => DB::now() );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_reconcile_failed', 'Deletion, graph and unsafe-payload reconciliation failed atomically.' );
		}
	}

	public function retention() {
		global $wpdb;
		$metrics_days = max( 30, min( 365, (int) apply_filters( 'sabri_file26_metrics_retention_days', 120 ) ) );
		$audit_days = max( 365, (int) DB::setting( 'audit_retention_days', 760 ) );
		$queries = array(
			'tombstones' => 'DELETE FROM ' . DB::table( 'tombstones' ) . ' WHERE expires_at < UTC_TIMESTAMP()',
			'feedback' => 'DELETE FROM ' . DB::table( 'feedback' ) . ' WHERE expires_at < UTC_TIMESTAMP()',
			'rate_limits' => 'DELETE FROM ' . DB::table( 'rate_limits' ) . ' WHERE expires_at < UTC_TIMESTAMP()',
			'metrics' => $wpdb->prepare( 'DELETE FROM ' . DB::table( 'metrics' ) . ' WHERE metric_date < %s', gmdate( 'Y-m-d', time() - ( $metrics_days * DAY_IN_SECONDS ) ) ),
			'audit' => $wpdb->prepare( 'DELETE FROM ' . DB::table( 'audit' ) . ' WHERE created_at < %s', gmdate( 'Y-m-d H:i:s', time() - ( $audit_days * DAY_IN_SECONDS ) ) ),
		);
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { update_option( 'sabri_file26_last_retention_failure', array( 'at' => DB::now(), 'stage' => 'start' ), false ); return new \WP_Error( 'file26_retention_failed', 'File 26 retention transaction could not start.' ); }
		$counts = array();
		try {
			foreach ( $queries as $name => $sql ) { $affected = $wpdb->query( $sql ); if ( false === $affected ) { throw new \RuntimeException( 'Retention purge failed: ' . $name ); } $counts[ $name ] = (int) $affected; }
			if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'Retention commit failed.' ); }
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); update_option( 'sabri_file26_last_retention_failure', array( 'at' => DB::now(), 'stage' => 'purge' ), false ); return new \WP_Error( 'file26_retention_failed', 'File 26 retention purge failed atomically.' );
		}
		delete_option( 'sabri_file26_last_retention_failure' );
		return array( 'purged' => $counts, 'metrics_retention_days' => $metrics_days, 'audit_retention_days' => $audit_days, 'completed_at' => DB::now() );
	}
}
