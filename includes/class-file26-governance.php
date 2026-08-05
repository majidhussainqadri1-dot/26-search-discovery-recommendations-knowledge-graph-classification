<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/**
 * High-risk lifecycle commands for connectors, ranking, taxonomy assignments and graph edges.
 * Canonical domain content remains read-only to File 26.
 */
final class Governance {
	private $security;
	private $taxonomy;
	private $graph;

	public function __construct( Security $security, Taxonomy $taxonomy, Graph $graph ) {
		$this->security = $security;
		$this->taxonomy = $taxonomy;
		$this->graph = $graph;
	}

	public function transition_connector( $slug, $target, $reason ) {
		global $wpdb;
		if ( ! $this->security->can_operate() ) {
			return new \WP_Error( 'file26_forbidden', 'Search operator capability is required.', array( 'status' => 403 ) );
		}
		$slug = sanitize_key( $slug );
		$target = sanitize_key( $target );
		$reason = sanitize_text_field( $reason );
		$allowed = array(
			'proposed' => array( 'contract_tested', 'retired' ),
			'contract_tested' => array( 'shadow', 'suspended', 'retired' ),
			'shadow' => array( 'approved', 'suspended', 'retired' ),
			'approved' => array( 'active', 'suspended', 'retired' ),
			'active' => array( 'degraded', 'suspended', 'retired' ),
			'degraded' => array( 'active', 'suspended', 'retired' ),
			'suspended' => array( 'shadow', 'retired' ),
			'retired' => array(),
		);
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'connectors' ) . ' WHERE slug=%s', $slug ), ARRAY_A );
		if ( ! $row ) {
			return new \WP_Error( 'file26_connector_not_found', 'Connector not found.', array( 'status' => 404 ) );
		}
		if ( empty( $allowed[ $row['status'] ] ) || ! in_array( $target, $allowed[ $row['status'] ], true ) ) {
			return new \WP_Error( 'file26_invalid_transition', 'Invalid connector lifecycle transition.', array( 'status' => 409 ) );
		}
		if ( in_array( $target, array( 'active', 'retired' ), true ) && ! $this->security->require_step_up( 'connector_' . $target ) ) {
			return new \WP_Error( 'file26_step_up_required', 'Fresh high-risk authorization is required.', array( 'status' => 403 ) );
		}
		$updated = $wpdb->update( DB::table( 'connectors' ), array( 'status' => $target, 'updated_at' => DB::now() ), array( 'slug' => $slug, 'status' => $row['status'] ), array( '%s', '%s' ), array( '%s', '%s' ) );
		if ( 1 !== $updated ) {
			return new \WP_Error( 'file26_transition_conflict', 'Connector state changed concurrently.', array( 'status' => 409 ) );
		}
		$this->security->audit( 'connector_' . $target, array( 'object_type' => 'connector', 'object_key' => $slug, 'reason' => $reason, 'metadata' => array( 'from' => $row['status'], 'to' => $target ) ) );
		do_action( 'sabri_file26_event', 'SearchConnector' . str_replace( ' ', '', ucwords( str_replace( '_', ' ', $target ) ) ), array( 'connector' => $slug, 'from' => $row['status'], 'to' => $target ) );
		return true;
	}

	public function stage_ranking_policy( array $input ) {
		global $wpdb;
		if ( ! $this->security->can_approve_ranking() ) {
			return new \WP_Error( 'file26_forbidden', 'Ranking approval capability is required.', array( 'status' => 403 ) );
		}
		$context = sanitize_key( isset( $input['context'] ) ? $input['context'] : 'search' );
		$audience = sanitize_key( isset( $input['audience'] ) ? $input['audience'] : 'public' );
		$version = sanitize_text_field( isset( $input['version'] ) ? $input['version'] : '' );
		$features = isset( $input['features'] ) && is_array( $input['features'] ) ? $this->sanitize_policy_features( $input['features'] ) : array();
		if ( is_wp_error( $features ) ) {
			return $features;
		}
		if ( ! $version || ! $features ) {
			return new \WP_Error( 'file26_invalid_policy', 'Version and bounded feature definitions are required.' );
		}
		$encoded_features = wp_json_encode( $features );
		if ( false === $encoded_features || strlen( $encoded_features ) > 65535 ) {
			return new \WP_Error( 'file26_policy_too_large', 'Ranking policy features exceed the allowed size.', array( 'status' => 413 ) );
		}
		$uuid = DB::uuid();
		$ok = $wpdb->insert( DB::table( 'ranking_policies' ), array(
			'policy_uuid' => $uuid,
			'context_name' => $context,
			'audience' => $audience,
			'version' => $version,
			'status' => 'staged',
			'features_json' => $encoded_features,
			'approval_one' => get_current_user_id(),
			'approval_two' => null,
			'effective_at' => null,
			'created_at' => DB::now(),
			'updated_at' => DB::now(),
		) );
		if ( ! $ok ) {
			return new \WP_Error( 'file26_policy_conflict', 'The ranking policy version already exists.', array( 'status' => 409 ) );
		}
		$this->security->audit( 'ranking_policy_staged', array( 'object_type' => 'ranking_policy', 'object_key' => $uuid, 'metadata' => array( 'context' => $context, 'audience' => $audience, 'version' => $version ) ) );
		return $uuid;
	}

	public function activate_ranking_policy( $uuid, $second_approver_id, $reason = '' ) {
		global $wpdb;
		if ( ! $this->security->can_approve_ranking() || ! $this->security->require_step_up( 'ranking_policy_activate' ) ) {
			return new \WP_Error( 'file26_forbidden', 'Dual approval and fresh authorization are required.', array( 'status' => 403 ) );
		}
		$uuid = sanitize_text_field( $uuid );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'ranking_policies' ) . ' WHERE policy_uuid=%s', $uuid ), ARRAY_A );
		$second = absint( $second_approver_id );
		if ( ! $row || 'staged' !== $row['status'] || ! $second || $second === (int) $row['approval_one'] ) {
			return new \WP_Error( 'file26_dual_approval_required', 'A distinct second approver is required.', array( 'status' => 409 ) );
		}
		if ( ! apply_filters( 'sabri_file26_validate_ranking_approver', user_can( $second, 'approve_sabri_ranking' ), $second, $row ) ) {
			return new \WP_Error( 'file26_invalid_second_approver', 'The second approver is not authorized.', array( 'status' => 403 ) );
		}
		$wpdb->query( 'START TRANSACTION' );
		try {
			$wpdb->update( DB::table( 'ranking_policies' ), array( 'status' => 'rolled_back', 'updated_at' => DB::now() ), array( 'context_name' => $row['context_name'], 'audience' => $row['audience'], 'status' => 'active' ) );
			$updated = $wpdb->update( DB::table( 'ranking_policies' ), array( 'status' => 'active', 'approval_two' => $second, 'effective_at' => DB::now(), 'updated_at' => DB::now() ), array( 'policy_uuid' => $uuid, 'status' => 'staged' ), array( '%s', '%d', '%s', '%s' ), array( '%s', '%s' ) );
			if ( 1 !== $updated ) {
				throw new \RuntimeException( 'Concurrent policy transition.' );
			}
			if ( 'search' === $row['context_name'] && 'public' === $row['audience'] ) {
				DB::update_settings( array( 'policy_version' => $row['version'] ) );
			}
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_policy_activation_failed', 'Ranking policy activation failed.', array( 'status' => 409 ) );
		}
		$this->security->audit( 'ranking_policy_activated', array( 'object_type' => 'ranking_policy', 'object_key' => $uuid, 'reason' => sanitize_text_field( $reason ), 'metadata' => array( 'second_approver' => $second, 'version' => $row['version'] ) ) );
		do_action( 'sabri_file26_event', 'RankingPolicyActivated', array( 'policy_uuid' => $uuid, 'version' => $row['version'] ) );
		return true;
	}

	public function rollback_ranking_policy( $uuid, $reason ) {
		global $wpdb;
		if ( ! $this->security->can_approve_ranking() || ! $this->security->require_step_up( 'ranking_policy_rollback' ) ) {
			return new \WP_Error( 'file26_forbidden', 'Fresh ranking approval is required.', array( 'status' => 403 ) );
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'ranking_policies' ) . ' WHERE policy_uuid=%s', sanitize_text_field( $uuid ) ), ARRAY_A );
		if ( ! $row || 'active' !== $row['status'] ) {
			return new \WP_Error( 'file26_policy_not_active', 'The ranking policy is not active.', array( 'status' => 409 ) );
		}
		$wpdb->update( DB::table( 'ranking_policies' ), array( 'status' => 'rolled_back', 'updated_at' => DB::now() ), array( 'policy_uuid' => $row['policy_uuid'], 'status' => 'active' ) );
		DB::update_settings( array( 'activated' => false ) );
		$this->security->audit( 'ranking_policy_rolled_back', array( 'object_type' => 'ranking_policy', 'object_key' => $row['policy_uuid'], 'reason' => sanitize_text_field( $reason ) ) );
		do_action( 'sabri_file26_event', 'RankingPolicyRolledBack', array( 'policy_uuid' => $row['policy_uuid'] ) );
		return true;
	}

	public function review_classification( $object_key, $term_uuid, $decision, $reason = '' ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) {
			return new \WP_Error( 'file26_forbidden', 'Taxonomy curator capability is required.', array( 'status' => 403 ) );
		}
		$decision = sanitize_key( $decision );
		if ( ! in_array( $decision, array( 'approved', 'rejected', 'corrected', 'removed' ), true ) ) {
			return new \WP_Error( 'file26_invalid_classification_decision', 'Invalid classification decision.' );
		}
		$object_key = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $object_key ) );
		$term_uuid = sanitize_text_field( $term_uuid );
		if ( 64 !== strlen( $object_key ) || ! preg_match( '/^[a-f0-9]{64}$/', $object_key ) || ! preg_match( '/^[a-f0-9-]{36}$/i', $term_uuid ) ) {
			return new \WP_Error( 'file26_invalid_classification_reference', 'Invalid classification reference.', array( 'status' => 400 ) );
		}
		$term_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . DB::table( 'terms' ) . " WHERE term_uuid=%s AND status IN ('approved','active','deprecated','merged','split') LIMIT 1", $term_uuid ) );
		if ( ! $term_exists ) {
			return new \WP_Error( 'file26_term_not_found', 'Classification term not found.', array( 'status' => 404 ) );
		}
		$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . DB::table( 'classifications' ) . ' SET status=%s,reviewer_id=%d,version=version+1,updated_at=%s WHERE object_key=%s AND term_uuid=%s', $decision, get_current_user_id(), DB::now(), $object_key, $term_uuid ) );
		if ( ! $updated ) {
			return new \WP_Error( 'file26_classification_not_found', 'Classification assignment not found.', array( 'status' => 404 ) );
		}
		$this->security->audit( 'classification_' . $decision, array( 'object_type' => 'classification', 'object_key' => $object_key . ':' . $term_uuid, 'reason' => sanitize_text_field( $reason ) ) );
		do_action( 'sabri_file26_event', 'Classification' . ucfirst( $decision ), array( 'object_key' => $object_key, 'term_uuid' => $term_uuid ) );
		return true;
	}

	public function transition_edge( $edge_uuid, $target, $reason = '' ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) {
			return new \WP_Error( 'file26_forbidden', 'Graph curator capability is required.', array( 'status' => 403 ) );
		}
		$edge_uuid = sanitize_text_field( $edge_uuid );
		$target = sanitize_key( $target );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT edge_uuid,state,version FROM ' . DB::table( 'edges' ) . ' WHERE edge_uuid=%s', $edge_uuid ), ARRAY_A );
		if ( ! $row ) {
			return new \WP_Error( 'file26_edge_not_found', 'Graph edge not found.', array( 'status' => 404 ) );
		}
		$allowed = array(
			'draft' => array( 'active', 'removed' ),
			'active' => array( 'corrected', 'removed' ),
			'corrected' => array( 'active', 'removed' ),
			'removed' => array(),
		);
		if ( empty( $allowed[ $row['state'] ] ) || ! in_array( $target, $allowed[ $row['state'] ], true ) ) {
			return new \WP_Error( 'file26_invalid_edge_state', 'Invalid graph edge lifecycle transition.', array( 'status' => 409 ) );
		}
		if ( 'active' === $target && ! $this->security->require_step_up( 'knowledge_edge_activate' ) ) {
			return new \WP_Error( 'file26_step_up_required', 'Fresh authorization is required to publish a graph edge.', array( 'status' => 403 ) );
		}
		$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . DB::table( 'edges' ) . ' SET state=%s,version=version+1,updated_at=%s WHERE edge_uuid=%s AND state=%s AND version=%d', $target, DB::now(), $edge_uuid, $row['state'], (int) $row['version'] ) );
		if ( 1 !== $updated ) {
			return new \WP_Error( 'file26_edge_transition_conflict', 'Graph edge changed concurrently.', array( 'status' => 409 ) );
		}
		$this->security->audit( 'knowledge_edge_' . $target, array( 'object_type' => 'knowledge_edge', 'object_key' => $edge_uuid, 'reason' => sanitize_text_field( $reason ), 'metadata' => array( 'from' => $row['state'], 'to' => $target ) ) );
		do_action( 'sabri_file26_event', 'removed' === $target ? 'KnowledgeEdgeRemoved' : 'KnowledgeEdgePublished', array( 'edge_uuid' => $edge_uuid, 'state' => $target ) );
		return true;
	}


	private function sanitize_policy_features( array $features, $depth = 0 ) {
		if ( $depth > 3 || count( $features ) > 100 ) {
			return new \WP_Error( 'file26_policy_complexity', 'Ranking policy feature definitions are too complex.', array( 'status' => 400 ) );
		}
		$forbidden = array( 'donation', 'payment', 'purchase', 'founder_favoritism', 'religion_inference', 'clinical_query', 'message_body', 'patient_record', 'identity_document' );
		$clean = array();
		foreach ( $features as $raw_key => $value ) {
			$key = sanitize_key( (string) $raw_key );
			if ( '' === $key ) {
				continue;
			}
			if ( in_array( $key, $forbidden, true ) ) {
				return new \WP_Error( 'file26_forbidden_ranking_signal', 'A prohibited ranking signal was supplied.', array( 'status' => 400 ) );
			}
			if ( is_array( $value ) ) {
				$value = $this->sanitize_policy_features( $value, $depth + 1 );
				if ( is_wp_error( $value ) ) {
					return $value;
				}
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				// Preserve bounded scalar policy values.
			} elseif ( is_scalar( $value ) ) {
				$value = sanitize_text_field( (string) $value );
				$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 512, 'UTF-8' ) : substr( $value, 0, 512 );
			} else {
				continue;
			}
			$clean[ $key ] = $value;
		}
		return $clean;
	}

	public function reports() {
		global $wpdb;
		if ( ! $this->security->can_audit() ) {
			return new \WP_Error( 'file26_forbidden', 'Audit capability is required.', array( 'status' => 403 ) );
		}
		return array(
			'connector_health' => $wpdb->get_results( 'SELECT slug,status,health_state,last_health,last_event_version FROM ' . DB::table( 'connectors' ) . ' ORDER BY slug', ARRAY_A ),
			'jobs' => $wpdb->get_results( 'SELECT job_uuid,job_type,status,attempts,error_code,created_at,finished_at FROM ' . DB::table( 'jobs' ) . ' ORDER BY id DESC LIMIT 100', ARRAY_A ),
			'zero_results' => $wpdb->get_results( $wpdb->prepare( 'SELECT metric_date,locale,SUM(count_value) count_value FROM ' . DB::table( 'metrics' ) . ' WHERE metric_key=%s GROUP BY metric_date,locale ORDER BY metric_date DESC LIMIT 100', 'search_zero_result' ), ARRAY_A ),
			'policy_version' => DB::setting( 'policy_version', 'organic-1.0' ),
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
		);
	}
}
