<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Consent-first personalized, session and guest discovery orchestration. */
final class Recommendations {
	private $search;
	private $security;

	public function __construct( Search $search, Security $security ) {
		$this->search = $search;
		$this->security = $security;
	}

	public function get( array $request = array() ) {
		$user_id = get_current_user_id();
		$audience = $this->security->audience();
		$profile = $user_id ? $this->profile( $user_id ) : null;
		$personalized = false;
		$session_contextual = false;
		$context = isset( $request['context'] ) ? sanitize_key( $request['context'] ) : 'discover';
		$filters = isset( $request['filters'] ) && is_array( $request['filters'] ) ? $request['filters'] : array();
		$requested_limit = isset( $request['limit'] ) ? max( 1, min( 30, (int) $request['limit'] ) ) : 12;
		$candidate_limit = min( 30, max( 12, $requested_limit * 3 ) );
		$session_topics = isset( $request['session_topics'] ) ? $this->sanitize_topics( (array) $request['session_topics'], 10 ) : array();

		if (
			$user_id && ! empty( $audience['valid'] ) &&
			( empty( $audience['is_minor'] ) || ! empty( $audience['guardian_verified'] ) ) &&
			DB::setting( 'personalization_enabled', false ) && $profile &&
			! empty( $profile['consent'] ) && empty( $profile['opted_out'] )
		) {
			$personalized = true;
		} elseif ( $session_topics ) {
			$session_contextual = true;
		}

		$response = $this->search->run(
			array(
				'q' => '',
				'locale' => isset( $request['locale'] ) && $request['locale'] ? $request['locale'] : determine_locale(),
				'filters' => $filters,
				'cursor' => isset( $request['cursor'] ) ? $request['cursor'] : '',
				'limit' => $candidate_limit,
			)
		);
		if ( is_wp_error( $response ) ) { return $response; }

		$interests = array();
		$negatives = array( 'items' => array(), 'authors' => array(), 'topics' => array() );
		if ( $personalized ) {
			$decoded = json_decode( $profile['negatives_json'], true );
			$negatives = is_array( $decoded ) ? array_merge( $negatives, $decoded ) : $negatives;
			$decoded = json_decode( $profile['interests_json'], true );
			$interests = is_array( $decoded ) ? $this->sanitize_topics( $decoded, 50 ) : array();
		} elseif ( $session_contextual ) {
			$interests = $session_topics;
		}

		if ( $personalized ) {
			$response['results'] = array_values(
				array_filter(
					$response['results'],
					static function ( $item ) use ( $negatives ) {
						if ( in_array( $item['key'], (array) $negatives['items'], true ) ) { return false; }
						if ( $item['author_key'] && in_array( $item['author_key'], (array) $negatives['authors'], true ) ) { return false; }
						foreach ( $item['topics'] as $topic ) { if ( in_array( $topic, (array) $negatives['topics'], true ) ) { return false; } }
						return true;
					}
				)
			);
		}

		if ( $interests ) {
			foreach ( $response['results'] as &$item ) {
				$item_topics = array_map( 'sanitize_key', (array) $item['topics'] );
				$item['_interest_matches'] = count( array_intersect( $interests, $item_topics ) );
				if ( $item['_interest_matches'] ) { $item['explanation_codes'][] = $personalized ? 'consented_interest_match' : 'session_context_match'; }
			}
			unset( $item );
			usort(
				$response['results'],
				static function ( $a, $b ) {
					if ( $a['_interest_matches'] === $b['_interest_matches'] ) {
						return (float) $a['score'] === (float) $b['score'] ? strcmp( $a['key'], $b['key'] ) : ( (float) $a['score'] > (float) $b['score'] ? -1 : 1 );
					}
					return $a['_interest_matches'] > $b['_interest_matches'] ? -1 : 1;
				}
			);
			foreach ( $response['results'] as &$item ) { unset( $item['_interest_matches'] ); }
			unset( $item );
		}

		$response['results'] = array_slice( $response['results'], 0, $requested_limit );
		$response['personalized'] = $personalized;
		$response['session_contextual'] = $session_contextual;
		$response['recommendation_context'] = $context;
		$response['controls'] = array(
			'logged_in' => (bool) $user_id,
			'personalization_available' => (bool) DB::setting( 'personalization_enabled', false ),
			'consent' => $profile ? (bool) $profile['consent'] : false,
			'opted_out' => $profile ? (bool) $profile['opted_out'] : false,
			'interests' => $profile && ! empty( $profile['interests_json'] ) ? $this->sanitize_topics( (array) json_decode( $profile['interests_json'], true ), 50 ) : array(),
			'can_hide' => (bool) $user_id,
			'can_not_interested' => (bool) $user_id,
			'can_reset' => (bool) $user_id,
			'can_opt_out' => (bool) $user_id,
		);
		foreach ( $response['results'] as &$item ) { $item['why_this'] = $this->explain( $item, $personalized, $session_contextual ); }
		unset( $item );
		return $response;
	}

	public function explain( array $item, $personalized = false, $session_contextual = false ) {
		$reasons = array();
		$labels = array(
			'query_relevance' => __( 'Relevant to this discovery context', 'sabri-file26' ),
			'exact_phrase' => __( 'Contains the requested phrase', 'sabri-file26' ),
			'verified_authority' => __( 'Verified or authoritative source signal', 'sabri-file26' ),
			'source_quality' => __( 'Strong source-quality signal', 'sabri-file26' ),
			'knowledge_graph_relation' => __( 'Connected through an approved knowledge relationship', 'sabri-file26' ),
			'status_disclosed' => __( 'Correction or retraction status is disclosed', 'sabri-file26' ),
			'consented_interest_match' => __( 'Matches a topic you explicitly selected', 'sabri-file26' ),
			'session_context_match' => __( 'Matches this session’s non-persisted context', 'sabri-file26' ),
		);
		foreach ( (array) $item['explanation_codes'] as $code ) { if ( isset( $labels[ $code ] ) ) { $reasons[] = $labels[ $code ]; } }
		if ( $personalized ) { $reasons[] = __( 'Based on your consented interests and controls', 'sabri-file26' ); }
		elseif ( $session_contextual ) { $reasons[] = __( 'Uses only the context supplied for this request and does not save a profile', 'sabri-file26' ); }
		else { $reasons[] = __( 'Non-personal source-quality and diversity selection', 'sabri-file26' ); }
		return array_values( array_unique( $reasons ) );
	}

	public function record_feedback( array $request ) {
		global $wpdb;
		$access = $this->require_preference_access();
		if ( is_wp_error( $access ) ) { return $access; }
		$user_id = (int) $access['user_id'];
		if ( ! $this->security->rate_limit( 'feedback|u:' . $user_id, 60, 60 ) ) { return new \WP_Error( 'file26_rate_limited', 'Too many feedback requests.', array( 'status' => 429 ) ); }
		$type = isset( $request['type'] ) ? sanitize_key( $request['type'] ) : '';
		$allowed = array( 'helpful', 'not_interested', 'hide_item', 'hide_author', 'hide_topic', 'undo' );
		if ( ! in_array( $type, $allowed, true ) ) { return new \WP_Error( 'file26_invalid_feedback', 'Invalid feedback type.', array( 'status' => 400 ) ); }
		$item_key = isset( $request['item_key'] ) ? preg_replace( '/[^a-f0-9]/', '', strtolower( $request['item_key'] ) ) : '';
		$scope_key = isset( $request['scope_key'] ) ? substr( sanitize_text_field( $request['scope_key'] ), 0, 191 ) : '';
		if ( in_array( $type, array( 'helpful', 'not_interested', 'hide_item' ), true ) && 64 !== strlen( $item_key ) ) { return new \WP_Error( 'file26_invalid_feedback_item', 'A valid canonical item key is required.', array( 'status' => 400 ) ); }
		if ( in_array( $type, array( 'hide_author', 'hide_topic' ), true ) && '' === $scope_key ) { return new \WP_Error( 'file26_invalid_feedback_scope', 'A bounded feedback scope is required.', array( 'status' => 400 ) ); }
		$idempotency_raw = isset( $request['idempotency_key'] ) ? sanitize_text_field( $request['idempotency_key'] ) : '';
		if ( ! $idempotency_raw ) { return new \WP_Error( 'file26_idempotency_required', 'An idempotency key is required.', array( 'status' => 400 ) ); }
		$idempotency = hash( 'sha256', $user_id . '|' . $idempotency_raw );

		$wpdb->query( 'START TRANSACTION' );
		try {
			if ( 'undo' === $type ) {
				$undo_raw = isset( $request['undo_idempotency_key'] ) ? sanitize_text_field( $request['undo_idempotency_key'] ) : '';
				if ( '' === $undo_raw ) { throw new \InvalidArgumentException( 'undo_target_required' ); }
				$target = hash( 'sha256', $user_id . '|' . $undo_raw );
				$reversed = $wpdb->update( DB::table( 'feedback' ), array( 'active' => 0, 'updated_at' => DB::now() ), array( 'idempotency_key' => $target, 'user_id' => $user_id, 'active' => 1 ), array( '%d', '%s' ), array( '%s', '%d', '%d' ) );
				if ( 1 !== $reversed ) { throw new \RuntimeException( 'feedback_not_reversible' ); }
				$rebuilt = $this->rebuild_negative_controls( $user_id );
				if ( is_wp_error( $rebuilt ) ) { throw new \RuntimeException( $rebuilt->get_error_code() ); }
				if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'feedback_commit_failed' ); }
				$this->security->audit( 'recommendation_feedback_reversed', array( 'object_type' => 'recommendation', 'object_key' => $item_key ) );
				return array( 'reversed' => true, 'effective_next_request' => true );
			}

			$days = max( 30, (int) DB::setting( 'feedback_retention_days', 365 ) );
			$expires = gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
			$sql = $wpdb->prepare(
				'INSERT INTO ' . DB::table( 'feedback' ) . "
				(idempotency_key,user_id,item_key,feedback_type,scope_key,payload,active,created_at,updated_at,expires_at)
				VALUES (%s,%d,%s,%s,%s,%s,1,%s,%s,%s)
				ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)",
				$idempotency,
				$user_id,
				$item_key ?: null,
				$type,
				$scope_key,
				wp_json_encode( array( 'context' => isset( $request['context'] ) ? sanitize_key( $request['context'] ) : 'discover' ) ),
				DB::now(),
				DB::now(),
				$expires
			);
			if ( false === $wpdb->query( $sql ) ) { throw new \RuntimeException( 'feedback_write_failed' ); }
			$rebuilt = $this->rebuild_negative_controls( $user_id );
			if ( is_wp_error( $rebuilt ) ) { throw new \RuntimeException( $rebuilt->get_error_code() ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'feedback_commit_failed' ); }
		} catch ( \InvalidArgumentException $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_undo_target_required', 'The feedback action to undo is required.', array( 'status' => 400 ) );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			if ( 'feedback_not_reversible' === $e->getMessage() ) { return new \WP_Error( 'file26_feedback_not_reversible', 'The feedback action was not found or was already reversed.', array( 'status' => 409 ) ); }
			return new \WP_Error( 'file26_feedback_write_failed', 'Recommendation feedback and controls could not be updated atomically.', array( 'status' => 500 ) );
		}
		$this->security->audit( 'recommendation_feedback_recorded', array( 'object_type' => 'recommendation', 'object_key' => $item_key, 'reason' => $type ) );
		return array( 'recorded' => true, 'effective_next_request' => true, 'idempotency_key' => $idempotency_raw );
	}

	private function rebuild_negative_controls( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT item_key,feedback_type,scope_key FROM ' . DB::table( 'feedback' ) . " WHERE user_id=%d AND active=1 AND feedback_type IN ('not_interested','hide_item','hide_author','hide_topic') ORDER BY id DESC LIMIT 3000",
				$user_id
			),
			ARRAY_A
		);
		if ( null === $rows ) { return new \WP_Error( 'file26_feedback_read_failed', 'Recommendation controls could not be rebuilt.', array( 'status' => 500 ) ); }
		$negatives = array( 'items' => array(), 'authors' => array(), 'topics' => array() );
		foreach ( $rows as $row ) {
			if ( in_array( $row['feedback_type'], array( 'not_interested', 'hide_item' ), true ) && $row['item_key'] ) { $negatives['items'][] = $row['item_key']; }
			elseif ( 'hide_author' === $row['feedback_type'] && $row['scope_key'] ) { $negatives['authors'][] = $row['scope_key']; }
			elseif ( 'hide_topic' === $row['feedback_type'] && $row['scope_key'] ) { $negatives['topics'][] = $row['scope_key']; }
		}
		foreach ( $negatives as &$values ) {
			$values = array_slice( array_values( array_unique( array_map( 'sanitize_text_field', $values ) ) ), 0, 500 );
		}
		unset( $values );
		$existing = $this->profile( $user_id );
		$sql = $wpdb->prepare(
			'INSERT INTO ' . DB::table( 'profiles' ) . "
			(user_id,consent,opted_out,interests_json,negatives_json,version,updated_at)
			VALUES (%d,%d,%d,%s,%s,1,%s)
			ON DUPLICATE KEY UPDATE negatives_json=VALUES(negatives_json),version=version+1,updated_at=VALUES(updated_at)",
			$user_id,
			$existing ? (int) $existing['consent'] : 0,
			$existing ? (int) $existing['opted_out'] : 1,
			$existing ? $existing['interests_json'] : wp_json_encode( array() ),
			wp_json_encode( $negatives ),
			DB::now()
		);
		$written = $wpdb->query( $sql );
		return false === $written ? new \WP_Error( 'file26_negative_controls_write_failed', 'Recommendation controls could not be updated.', array( 'status' => 500 ) ) : true;
	}

	public function set_consent( $consent ) {
		global $wpdb;
		$access = $this->require_preference_access();
		if ( is_wp_error( $access ) ) { return $access; }
		$user_id = (int) $access['user_id'];
		$consent = (bool) $consent;
		$empty = wp_json_encode( array() );
		$wpdb->query( 'START TRANSACTION' );
		try {
			$sql = $wpdb->prepare(
				'INSERT INTO ' . DB::table( 'profiles' ) . "
				(user_id,consent,opted_out,interests_json,negatives_json,version,updated_at)
				VALUES (%d,%d,%d,%s,%s,1,%s)
				ON DUPLICATE KEY UPDATE consent=VALUES(consent),opted_out=VALUES(opted_out),interests_json=IF(VALUES(consent)=0,VALUES(interests_json),interests_json),negatives_json=IF(VALUES(consent)=0,VALUES(negatives_json),negatives_json),version=version+1,updated_at=VALUES(updated_at)",
				$user_id, $consent ? 1 : 0, $consent ? 0 : 1, $empty, $empty, DB::now()
			);
			if ( false === $wpdb->query( $sql ) ) { throw new \RuntimeException( 'Consent write failed.' ); }
			if ( ! $consent && false === $wpdb->delete( DB::table( 'feedback' ), array( 'user_id' => $user_id ), array( '%d' ) ) ) { throw new \RuntimeException( 'Feedback purge failed.' ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'Consent commit failed.' ); }
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_consent_write_failed', 'Recommendation consent could not be updated atomically.', array( 'status' => 500 ) );
		}
		$this->security->audit( 'recommendation_consent_updated', array( 'object_type' => 'user', 'object_key' => (string) $user_id, 'metadata' => array( 'consent' => $consent ) ) );
		return array( 'consent' => $consent, 'opted_out' => ! $consent, 'personalization_enabled' => $consent && DB::setting( 'personalization_enabled', false ) );
	}

	public function set_interests( array $interests ) {
		global $wpdb;
		$access = $this->require_preference_access();
		if ( is_wp_error( $access ) ) { return $access; }
		$user_id = (int) $access['user_id'];
		$profile = $this->profile( $user_id );
		if ( ! $profile || empty( $profile['consent'] ) || ! DB::setting( 'personalization_enabled', false ) ) { return new \WP_Error( 'file26_personalization_consent_required', 'Explicit personalization consent is required.', array( 'status' => 403 ) ); }
		$interests = $this->sanitize_topics( $interests, 50 );
		$updated = $wpdb->update( DB::table( 'profiles' ), array( 'interests_json' => wp_json_encode( $interests ), 'version' => (int) $profile['version'] + 1, 'updated_at' => DB::now() ), array( 'user_id' => $user_id, 'version' => (int) $profile['version'] ), array( '%s', '%d', '%s' ), array( '%d', '%d' ) );
		if ( 1 !== $updated ) { return new \WP_Error( 'file26_profile_conflict', 'Recommendation preferences changed concurrently. Reload and retry.', array( 'status' => 409 ) ); }
		$this->security->audit( 'recommendation_interests_updated', array( 'object_type' => 'user', 'object_key' => (string) $user_id, 'metadata' => array( 'count' => count( $interests ) ) ) );
		return array( 'updated' => true, 'interests' => $interests );
	}

	public function reset() {
		global $wpdb;
		$access = $this->require_preference_access();
		if ( is_wp_error( $access ) ) { return $access; }
		$user_id = (int) $access['user_id'];
		$wpdb->query( 'START TRANSACTION' );
		try {
			if ( false === $wpdb->delete( DB::table( 'feedback' ), array( 'user_id' => $user_id ), array( '%d' ) ) ) { throw new \RuntimeException( 'Feedback reset failed.' ); }
			if ( false === $wpdb->delete( DB::table( 'profiles' ), array( 'user_id' => $user_id ), array( '%d' ) ) ) { throw new \RuntimeException( 'Profile reset failed.' ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'Profile reset commit failed.' ); }
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_profile_reset_failed', 'Recommendation profile could not be reset.', array( 'status' => 500 ) );
		}
		$this->security->audit( 'recommendation_profile_reset', array( 'object_type' => 'user', 'object_key' => (string) $user_id ) );
		return array( 'reset' => true, 'personalized' => false );
	}

	public function opt_out() {
		global $wpdb;
		$access = $this->require_preference_access();
		if ( is_wp_error( $access ) ) { return $access; }
		$user_id = (int) $access['user_id'];
		$wpdb->query( 'START TRANSACTION' );
		try {
			if ( false === $wpdb->delete( DB::table( 'feedback' ), array( 'user_id' => $user_id ), array( '%d' ) ) ) { throw new \RuntimeException( 'Feedback opt-out purge failed.' ); }
			if ( false === $wpdb->delete( DB::table( 'profiles' ), array( 'user_id' => $user_id ), array( '%d' ) ) ) { throw new \RuntimeException( 'Profile opt-out reset failed.' ); }
			$inserted = $wpdb->insert(
				DB::table( 'profiles' ),
				array(
					'user_id' => $user_id,
					'consent' => 0,
					'opted_out' => 1,
					'interests_json' => wp_json_encode( array() ),
					'negatives_json' => wp_json_encode( array() ),
					'version' => 1,
					'updated_at' => DB::now(),
				)
			);
			if ( false === $inserted ) { throw new \RuntimeException( 'Opt-out marker write failed.' ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'Opt-out commit failed.' ); }
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_opt_out_record_failed', 'Opt-out state could not be persisted atomically.', array( 'status' => 500 ) );
		}
		$this->security->audit( 'recommendation_opted_out', array( 'object_type' => 'user', 'object_key' => (string) $user_id ) );
		return array( 'opted_out' => true, 'personalized' => false );
	}

	public function profile( $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'profiles' ) . ' WHERE user_id=%d', (int) $user_id ), ARRAY_A );
	}

	private function require_preference_access() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) { return new \WP_Error( 'file26_auth_required', 'Authentication is required.', array( 'status' => 401 ) ); }
		$audience = $this->security->audience();
		if ( empty( $audience['valid'] ) || ! empty( $audience['suspended'] ) ) { return new \WP_Error( 'file26_membership_invalid', 'Current membership assertions do not permit this preference action.', array( 'status' => 403 ) ); }
		if ( ! empty( $audience['is_minor'] ) && empty( $audience['guardian_verified'] ) ) { return new \WP_Error( 'file26_guardian_required', 'Verified guardian authorization is required for this preference action.', array( 'status' => 403 ) ); }
		return array( 'user_id' => (int) $user_id, 'audience' => $audience );
	}

	private function sanitize_topics( array $topics, $limit ) {
		return array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_key', $topics ) ) ) ), 0, max( 1, (int) $limit ) );
	}
}
