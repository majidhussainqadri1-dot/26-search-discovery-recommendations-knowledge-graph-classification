<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

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
		$context = isset( $request['context'] ) ? sanitize_key( $request['context'] ) : 'discover';
		$filters = isset( $request['filters'] ) && is_array( $request['filters'] ) ? $request['filters'] : array();
		if (
			$user_id &&
			! empty( $audience['valid'] ) &&
			( empty( $audience['is_minor'] ) || ! empty( $audience['guardian_verified'] ) ) &&
			DB::setting( 'personalization_enabled', false ) &&
			$profile &&
			! empty( $profile['consent'] ) &&
			empty( $profile['opted_out'] )
		) {
			$personalized = true;
		}
		$response = $this->search->run(
			array(
				'q' => '',
				'locale' => isset( $request['locale'] ) ? $request['locale'] : determine_locale(),
				'filters' => $filters,
				'cursor' => isset( $request['cursor'] ) ? $request['cursor'] : '',
				'limit' => isset( $request['limit'] ) ? $request['limit'] : 12,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( $personalized ) {
			$negatives = json_decode( $profile['negatives_json'], true );
			$negatives = is_array( $negatives ) ? $negatives : array();
			$interests = json_decode( $profile['interests_json'], true );
			$interests = is_array( $interests ) ? array_slice( array_values( array_unique( array_map( 'sanitize_key', $interests ) ) ), 0, 50 ) : array();
			$response['results'] = array_values( array_filter( $response['results'], static function ( $item ) use ( $negatives ) {
				if ( in_array( $item['key'], isset( $negatives['items'] ) ? (array) $negatives['items'] : array(), true ) ) { return false; }
				if ( $item['author_key'] && in_array( $item['author_key'], isset( $negatives['authors'] ) ? (array) $negatives['authors'] : array(), true ) ) { return false; }
				foreach ( $item['topics'] as $topic ) {
					if ( in_array( $topic, isset( $negatives['topics'] ) ? (array) $negatives['topics'] : array(), true ) ) { return false; }
				}
				return true;
			} ) );
			foreach ( $response['results'] as &$personalized_item ) {
				$personalized_item['_interest_matches'] = count( array_intersect( $interests, array_map( 'sanitize_key', (array) $personalized_item['topics'] ) ) );
				if ( $personalized_item['_interest_matches'] ) { $personalized_item['explanation_codes'][] = 'consented_interest_match'; }
			}
			unset( $personalized_item );
			usort( $response['results'], static function ( $a, $b ) {
				if ( $a['_interest_matches'] === $b['_interest_matches'] ) { return (float) $a['score'] === (float) $b['score'] ? strcmp( $a['key'], $b['key'] ) : ( (float) $a['score'] > (float) $b['score'] ? -1 : 1 ); }
				return $a['_interest_matches'] > $b['_interest_matches'] ? -1 : 1;
			} );
			foreach ( $response['results'] as &$personalized_item ) { unset( $personalized_item['_interest_matches'] ); }
			unset( $personalized_item );
		}
		$response['personalized'] = $personalized;
		$response['recommendation_context'] = $context;
		$response['controls'] = array(
			'can_hide' => (bool) $user_id,
			'can_not_interested' => (bool) $user_id,
			'can_reset' => (bool) $user_id,
			'can_opt_out' => (bool) $user_id,
		);
		foreach ( $response['results'] as &$item ) {
			$item['why_this'] = $this->explain( $item, $personalized );
		}
		unset( $item );
		return $response;
	}

	public function explain( array $item, $personalized = false ) {
		$reasons = array();
		foreach ( (array) $item['explanation_codes'] as $code ) {
			$labels = array(
				'query_relevance' => __( 'Relevant to this discovery context', 'sabri-file26' ),
				'verified_authority' => __( 'Verified or authoritative source signal', 'sabri-file26' ),
				'source_quality' => __( 'Strong source-quality signal', 'sabri-file26' ),
				'status_disclosed' => __( 'Correction or retraction status is disclosed', 'sabri-file26' ),
				'consented_interest_match' => __( 'Matches a topic you explicitly selected', 'sabri-file26' ),
			);
			if ( isset( $labels[ $code ] ) ) {
				$reasons[] = $labels[ $code ];
			}
		}
		if ( $personalized ) {
			$reasons[] = __( 'Based on your consented interests and controls', 'sabri-file26' );
		} else {
			$reasons[] = __( 'Non-personal source-quality and diversity selection', 'sabri-file26' );
		}
		return array_values( array_unique( $reasons ) );
	}

	public function record_feedback( array $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new \WP_Error( 'file26_auth_required', 'Authentication is required.', array( 'status' => 401 ) );
		}
		if ( ! $this->security->rate_limit( 'feedback|u:' . $user_id, 60, 60 ) ) {
			return new \WP_Error( 'file26_rate_limited', 'Too many feedback requests.', array( 'status' => 429 ) );
		}
		$type = isset( $request['type'] ) ? sanitize_key( $request['type'] ) : '';
		$allowed = array( 'helpful', 'not_interested', 'hide_item', 'hide_author', 'hide_topic', 'undo' );
		if ( ! in_array( $type, $allowed, true ) ) {
			return new \WP_Error( 'file26_invalid_feedback', 'Invalid feedback type.', array( 'status' => 400 ) );
		}
		$item_key = isset( $request['item_key'] ) ? preg_replace( '/[^a-f0-9]/', '', strtolower( $request['item_key'] ) ) : '';
		$scope_key = isset( $request['scope_key'] ) ? substr( sanitize_text_field( $request['scope_key'] ), 0, 191 ) : '';
		if ( in_array( $type, array( 'helpful', 'not_interested', 'hide_item' ), true ) && 64 !== strlen( $item_key ) ) {
			return new \WP_Error( 'file26_invalid_feedback_item', 'A valid canonical item key is required.', array( 'status' => 400 ) );
		}
		if ( in_array( $type, array( 'hide_author', 'hide_topic' ), true ) && '' === $scope_key ) {
			return new \WP_Error( 'file26_invalid_feedback_scope', 'A bounded feedback scope is required.', array( 'status' => 400 ) );
		}
		$idempotency = isset( $request['idempotency_key'] ) ? sanitize_text_field( $request['idempotency_key'] ) : '';
		if ( ! $idempotency ) {
			return new \WP_Error( 'file26_idempotency_required', 'An idempotency key is required.', array( 'status' => 400 ) );
		}
		$idempotency = hash( 'sha256', $user_id . '|' . $idempotency );
		if ( 'undo' === $type ) {
			$target = isset( $request['undo_idempotency_key'] ) ? hash( 'sha256', $user_id . '|' . sanitize_text_field( $request['undo_idempotency_key'] ) ) : '';
			if ( ! $target ) { return new \WP_Error( 'file26_undo_target_required', 'The feedback action to undo is required.', array( 'status' => 400 ) ); }
			$reversed = $wpdb->update( DB::table( 'feedback' ), array( 'active' => 0, 'updated_at' => DB::now() ), array( 'idempotency_key' => $target, 'user_id' => $user_id, 'active' => 1 ), array( '%d', '%s' ), array( '%s', '%d', '%d' ) );
			if ( 1 !== $reversed ) {
				return new \WP_Error( 'file26_feedback_not_reversible', 'The feedback action was not found or was already reversed.', array( 'status' => 409 ) );
			}
			$this->rebuild_negative_controls( $user_id );
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
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->rebuild_negative_controls( $user_id );
		$this->security->audit(
			'recommendation_feedback_recorded',
			array(
				'object_type' => 'recommendation',
				'object_key' => $item_key,
				'reason' => $type,
			)
		);
		return array( 'recorded' => true, 'effective_next_request' => true );
	}

	private function rebuild_negative_controls( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT item_key,feedback_type,scope_key FROM ' . DB::table( 'feedback' ) . " WHERE user_id=%d AND active=1 AND feedback_type IN ('not_interested','hide_item','hide_author','hide_topic') ORDER BY id ASC LIMIT 1000", $user_id ), ARRAY_A );
		$negatives = array( 'items' => array(), 'authors' => array(), 'topics' => array() );
		foreach ( $rows as $row ) {
			if ( in_array( $row['feedback_type'], array( 'not_interested', 'hide_item' ), true ) && $row['item_key'] ) { $negatives['items'][] = $row['item_key']; }
			elseif ( 'hide_author' === $row['feedback_type'] && $row['scope_key'] ) { $negatives['authors'][] = $row['scope_key']; }
			elseif ( 'hide_topic' === $row['feedback_type'] && $row['scope_key'] ) { $negatives['topics'][] = $row['scope_key']; }
		}
		foreach ( $negatives as &$values ) { $values = array_slice( array_values( array_unique( array_map( 'sanitize_text_field', $values ) ) ), -500 ); }
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
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function set_consent( $consent ) {
		global $wpdb;
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new \WP_Error( 'file26_auth_required', 'Authentication is required.', array( 'status' => 401 ) );
		}
		$consent = (bool) $consent;
		$sql = $wpdb->prepare(
			'INSERT INTO ' . DB::table( 'profiles' ) . "
			(user_id,consent,opted_out,interests_json,negatives_json,version,updated_at)
			VALUES (%d,%d,%d,%s,%s,1,%s)
			ON DUPLICATE KEY UPDATE consent=VALUES(consent),opted_out=VALUES(opted_out),version=version+1,updated_at=VALUES(updated_at)",
			$user_id,
			$consent ? 1 : 0,
			$consent ? 0 : 1,
			wp_json_encode( array() ),
			wp_json_encode( array() ),
			DB::now()
		);
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array( 'consent' => $consent, 'personalization_enabled' => $consent && DB::setting( 'personalization_enabled', false ) );
	}

	public function set_interests( array $interests ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$profile = $user_id ? $this->profile( $user_id ) : null;
		if ( ! $user_id || ! $profile || empty( $profile['consent'] ) || ! DB::setting( 'personalization_enabled', false ) ) {
			return new \WP_Error( 'file26_personalization_consent_required', 'Explicit personalization consent is required.', array( 'status' => 403 ) );
		}
		$interests = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_key', $interests ) ) ) ), 0, 50 );
		$updated = $wpdb->update( DB::table( 'profiles' ), array( 'interests_json' => wp_json_encode( $interests ), 'version' => (int) $profile['version'] + 1, 'updated_at' => DB::now() ), array( 'user_id' => $user_id, 'version' => (int) $profile['version'] ), array( '%s', '%d', '%s' ), array( '%d', '%d' ) );
		if ( 1 !== $updated ) {
			return new \WP_Error( 'file26_profile_conflict', 'Recommendation preferences changed concurrently. Reload and retry.', array( 'status' => 409 ) );
		}
		$this->security->audit( 'recommendation_interests_updated', array( 'object_type' => 'user', 'object_key' => (string) $user_id, 'metadata' => array( 'count' => count( $interests ) ) ) );
		return array( 'updated' => true, 'interests' => $interests );
	}

	public function reset() {
		global $wpdb;
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new \WP_Error( 'file26_auth_required', 'Authentication is required.', array( 'status' => 401 ) );
		}
		$wpdb->delete( DB::table( 'feedback' ), array( 'user_id' => $user_id ), array( '%d' ) );
		$wpdb->delete( DB::table( 'profiles' ), array( 'user_id' => $user_id ), array( '%d' ) );
		$this->security->audit( 'recommendation_profile_reset', array( 'object_type' => 'user', 'object_key' => (string) $user_id ) );
		return array( 'reset' => true, 'personalized' => false );
	}

	public function opt_out() {
		$result = $this->reset();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		global $wpdb;
		$user_id = get_current_user_id();
		$wpdb->insert(
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
		return array( 'opted_out' => true, 'personalized' => false );
	}

	public function profile( $user_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . DB::table( 'profiles' ) . ' WHERE user_id=%d', (int) $user_id ),
			ARRAY_A
		);
	}
}
