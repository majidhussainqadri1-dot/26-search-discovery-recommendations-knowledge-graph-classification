<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** High-risk lifecycle commands for connectors, ranking, classifications and graph edges. */
final class Governance {
	private $security;
	private $taxonomy;
	private $graph;

	public function __construct( Security $security, Taxonomy $taxonomy, Graph $graph ) {
		$this->security = $security;
		$this->taxonomy = $taxonomy;
		$this->graph = $graph;
	}

	private function require_audit( $action, array $context ) {
		if ( ! $this->security->audit( $action, $context ) ) {
			throw new \RuntimeException( 'Required governance audit could not be persisted.' );
		}
	}

	private function update_policy_setting( $version ) {
		DB::update_settings( array( 'policy_version' => $version ) );
		if ( (string) DB::setting( 'policy_version', '' ) !== (string) $version ) {
			throw new \RuntimeException( 'Ranking policy setting did not persist.' );
		}
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
		if ( in_array( $target, array( 'active', 'retired' ), true ) && ! $this->security->require_step_up( 'connector_' . $target ) ) {
			return new \WP_Error( 'file26_step_up_required', 'Fresh high-risk authorization is required.', array( 'status' => 403 ) );
		}
		$wpdb->query( 'START TRANSACTION' );
		try {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'connectors' ) . ' WHERE slug=%s FOR UPDATE', $slug ), ARRAY_A );
			if ( ! $row ) { throw new \RuntimeException( 'Connector not found.' ); }
			if ( empty( $allowed[ $row['status'] ] ) || ! in_array( $target, $allowed[ $row['status'] ], true ) ) { throw new \DomainException( 'Invalid connector lifecycle transition.' ); }
			$updated = $wpdb->update( DB::table( 'connectors' ), array( 'status' => $target, 'updated_at' => DB::now() ), array( 'slug' => $slug, 'status' => $row['status'] ), array( '%s', '%s' ), array( '%s', '%s' ) );
			if ( 1 !== $updated ) { throw new \RuntimeException( 'Connector state changed concurrently.' ); }
			$this->require_audit( 'connector_' . $target, array( 'object_type' => 'connector', 'object_key' => $slug, 'reason' => $reason, 'metadata' => array( 'from' => $row['status'], 'to' => $target ) ) );
			$wpdb->query( 'COMMIT' );
		} catch ( \DomainException $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_invalid_transition', 'Invalid connector lifecycle transition.', array( 'status' => 409 ) );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_connector_transition_failed', 'Connector transition or its required audit could not be committed.', array( 'status' => 409 ) );
		}
		do_action( 'sabri_file26_event', 'SearchConnector' . str_replace( ' ', '', ucwords( str_replace( '_', ' ', $target ) ) ), array( 'connector' => $slug, 'from' => $row['status'], 'to' => $target ) );
		return true;
	}

	public function stage_ranking_policy( array $input ) {
		global $wpdb;
		if ( ! $this->security->can_approve_ranking() ) { return new \WP_Error( 'file26_forbidden', 'Ranking approval capability is required.', array( 'status' => 403 ) ); }
		$context = sanitize_key( isset( $input['context'] ) ? $input['context'] : 'search' );
		$audience = sanitize_key( isset( $input['audience'] ) ? $input['audience'] : 'public' );
		$version = sanitize_text_field( isset( $input['version'] ) ? $input['version'] : '' );
		$features = isset( $input['features'] ) && is_array( $input['features'] ) ? $this->sanitize_policy_features( $input['features'] ) : array();
		if ( is_wp_error( $features ) ) { return $features; }
		if ( ! $version || ! $features ) { return new \WP_Error( 'file26_invalid_policy', 'Version and bounded feature definitions are required.', array( 'status' => 400 ) ); }
		$encoded = wp_json_encode( $features );
		if ( false === $encoded || strlen( $encoded ) > 65535 ) { return new \WP_Error( 'file26_policy_too_large', 'Ranking policy features exceed the allowed size.', array( 'status' => 413 ) ); }
		$uuid = DB::uuid();
		$wpdb->query( 'START TRANSACTION' );
		try {
			$ok = $wpdb->insert( DB::table( 'ranking_policies' ), array(
				'policy_uuid' => $uuid, 'context_name' => $context, 'audience' => $audience,
				'version' => $version, 'status' => 'staged', 'features_json' => $encoded,
				'approval_one' => get_current_user_id(), 'approval_two' => null, 'effective_at' => null,
				'created_at' => DB::now(), 'updated_at' => DB::now(),
			) );
			if ( ! $ok ) { throw new \RuntimeException( 'Policy insert failed.' ); }
			$this->require_audit( 'ranking_policy_staged', array( 'object_type' => 'ranking_policy', 'object_key' => $uuid, 'metadata' => array( 'context' => $context, 'audience' => $audience, 'version' => $version ) ) );
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_policy_conflict', 'Ranking policy could not be staged with durable audit evidence.', array( 'status' => 409 ) );
		}
		return $uuid;
	}

	/** A distinct logged-in approver must explicitly record approval; naming their user ID is never sufficient. */
	public function second_approve_ranking_policy( $uuid ) {
		global $wpdb;
		if ( ! $this->security->can_approve_ranking() || ! $this->security->require_step_up( 'ranking_policy_second_approval' ) ) { return new \WP_Error( 'file26_forbidden', 'Fresh ranking approval authorization is required.', array( 'status' => 403 ) ); }
		$uuid = sanitize_text_field( $uuid );
		$current = get_current_user_id();
		$wpdb->query( 'START TRANSACTION' );
		try {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'ranking_policies' ) . ' WHERE policy_uuid=%s FOR UPDATE', $uuid ), ARRAY_A );
			if ( ! $row || 'staged' !== $row['status'] || ! $current || $current === (int) $row['approval_one'] || ! empty( $row['approval_two'] ) ) { throw new \DomainException( 'Dual approval required.' ); }
			$updated = $wpdb->update( DB::table( 'ranking_policies' ), array( 'approval_two' => $current, 'updated_at' => DB::now() ), array( 'policy_uuid' => $uuid, 'status' => 'staged', 'approval_two' => null ), array( '%d', '%s' ), array( '%s', '%s', '%d' ) );
			if ( 1 !== $updated ) { throw new \RuntimeException( 'Ranking policy changed concurrently.' ); }
			$this->require_audit( 'ranking_policy_second_approved', array( 'object_type' => 'ranking_policy', 'object_key' => $uuid, 'metadata' => array( 'second_approver' => $current ) ) );
			$wpdb->query( 'COMMIT' );
		} catch ( \DomainException $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_dual_approval_required', 'A distinct unrecorded second approval is required.', array( 'status' => 409 ) );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_policy_conflict', 'Second approval could not be committed with durable audit evidence.', array( 'status' => 409 ) );
		}
		return true;
	}

	public function activate_ranking_policy( $uuid, $second_approver_id = 0, $reason = '' ) {
		global $wpdb;
		if ( ! $this->security->can_approve_ranking() || ! $this->security->require_step_up( 'ranking_policy_activate' ) ) { return new \WP_Error( 'file26_forbidden', 'Dual approval and fresh authorization are required.', array( 'status' => 403 ) ); }
		$uuid = sanitize_text_field( $uuid );
		$wpdb->query( 'START TRANSACTION' );
		try {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'ranking_policies' ) . ' WHERE policy_uuid=%s FOR UPDATE', $uuid ), ARRAY_A );
			$second = $row ? (int) $row['approval_two'] : 0;
			if ( ! $row || 'staged' !== $row['status'] || ! $second || $second === (int) $row['approval_one'] || ( $second_approver_id && $second !== absint( $second_approver_id ) ) ) { throw new \DomainException( 'Dual approval required.' ); }
			if ( ! apply_filters( 'sabri_file26_validate_ranking_approver', user_can( $second, 'approve_sabri_ranking' ), $second, $row ) ) { throw new \UnexpectedValueException( 'Second approver lost authorization.' ); }
			$rolled = $wpdb->update( DB::table( 'ranking_policies' ), array( 'status' => 'rolled_back', 'updated_at' => DB::now() ), array( 'context_name' => $row['context_name'], 'audience' => $row['audience'], 'status' => 'active' ) );
			if ( false === $rolled ) { throw new \RuntimeException( 'Previous policy transition failed.' ); }
			$updated = $wpdb->update( DB::table( 'ranking_policies' ), array( 'status' => 'active', 'effective_at' => DB::now(), 'updated_at' => DB::now() ), array( 'policy_uuid' => $uuid, 'status' => 'staged', 'approval_two' => $second ), array( '%s', '%s', '%s' ), array( '%s', '%s', '%d' ) );
			if ( 1 !== $updated ) { throw new \RuntimeException( 'Concurrent policy transition.' ); }
			if ( 'search' === $row['context_name'] && 'public' === $row['audience'] ) { $this->update_policy_setting( $row['version'] ); }
			$this->require_audit( 'ranking_policy_activated', array( 'object_type' => 'ranking_policy', 'object_key' => $uuid, 'reason' => sanitize_text_field( $reason ), 'metadata' => array( 'second_approver' => $second, 'version' => $row['version'] ) ) );
			$wpdb->query( 'COMMIT' );
		} catch ( \DomainException $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_dual_approval_required', 'A separately recorded distinct second approval is required.', array( 'status' => 409 ) );
		} catch ( \UnexpectedValueException $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_invalid_second_approver', 'The recorded second approver is no longer authorized.', array( 'status' => 403 ) );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_policy_activation_failed', 'Ranking policy activation or required audit failed safely.', array( 'status' => 409 ) );
		}
		do_action( 'sabri_file26_event', 'RankingPolicyActivated', array( 'policy_uuid' => $uuid, 'version' => $row['version'] ) );
		return true;
	}

	/** Second person explicitly approves a rollback for a short bounded window. */
	public function second_approve_ranking_rollback( $uuid ) {
		global $wpdb;
		if ( ! $this->security->can_approve_ranking() || ! $this->security->require_step_up( 'ranking_policy_rollback_second_approval' ) ) { return new \WP_Error( 'file26_forbidden', 'Fresh ranking rollback approval is required.', array( 'status' => 403 ) ); }
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT policy_uuid,status,version,effective_at FROM ' . DB::table( 'ranking_policies' ) . ' WHERE policy_uuid=%s', sanitize_text_field( $uuid ) ), ARRAY_A );
		if ( ! $row || 'active' !== $row['status'] ) { return new \WP_Error( 'file26_policy_not_active', 'The ranking policy is not active.', array( 'status' => 409 ) ); }
		$receipt_key = 'file26_rb_' . hash( 'sha256', $row['policy_uuid'] );
		$receipt = array(
			'user_id' => get_current_user_id(),
			'policy_uuid' => $row['policy_uuid'],
			'policy_version' => $row['version'],
			'effective_at' => $row['effective_at'],
			'nonce' => DB::uuid(),
			'expires_at' => time() + 600,
		);
		set_transient( $receipt_key, $receipt, 600 );
		$stored = get_transient( $receipt_key );
		if ( ! is_array( $stored ) || empty( $stored['nonce'] ) || ! hash_equals( (string) $receipt['nonce'], (string) $stored['nonce'] ) ) { return new \WP_Error( 'file26_rollback_approval_write_failed', 'Rollback approval receipt could not be persisted.', array( 'status' => 503 ) ); }
		if ( ! $this->security->audit( 'ranking_policy_rollback_second_approved', array( 'object_type' => 'ranking_policy', 'object_key' => $row['policy_uuid'], 'metadata' => array( 'second_approver' => get_current_user_id(), 'policy_version' => $row['version'], 'receipt_nonce' => $receipt['nonce'] ) ) ) {
			delete_transient( $receipt_key );
			return new \WP_Error( 'file26_rollback_approval_audit_failed', 'Rollback approval was not retained because its audit evidence could not be stored.', array( 'status' => 503 ) );
		}
		return true;
	}

	public function rollback_ranking_policy( $uuid, $reason, $second_approver_id = 0 ) {
		global $wpdb;
		if ( ! $this->security->can_approve_ranking() || ! $this->security->require_step_up( 'ranking_policy_rollback' ) ) { return new \WP_Error( 'file26_forbidden', 'Fresh ranking approval is required.', array( 'status' => 403 ) ); }
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'ranking_policies' ) . ' WHERE policy_uuid=%s', sanitize_text_field( $uuid ) ), ARRAY_A );
		if ( ! $row || 'active' !== $row['status'] ) { return new \WP_Error( 'file26_policy_not_active', 'The ranking policy is not active.', array( 'status' => 409 ) ); }
		$receipt_key = 'file26_rb_' . hash( 'sha256', $row['policy_uuid'] );
		$receipt = get_transient( $receipt_key );
		$receipt_matches = is_array( $receipt ) && ! empty( $receipt['user_id'] ) && ! empty( $receipt['nonce'] ) && (int) $receipt['expires_at'] >= time() && hash_equals( (string) $receipt['policy_uuid'], (string) $row['policy_uuid'] ) && hash_equals( (string) $receipt['policy_version'], (string) $row['version'] ) && hash_equals( (string) $receipt['effective_at'], (string) $row['effective_at'] );
		$second = $receipt_matches ? (int) $receipt['user_id'] : 0;
		if ( ! $second || $second === get_current_user_id() || ( $second_approver_id && $second !== absint( $second_approver_id ) ) || ! apply_filters( 'sabri_file26_validate_ranking_approver', user_can( $second, 'approve_sabri_ranking' ), $second, $row ) ) { return new \WP_Error( 'file26_dual_approval_required', 'A separately recorded distinct authorized second rollback approval is required.', array( 'status' => 409 ) ); }
		$previous = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'ranking_policies' ) . " WHERE context_name=%s AND audience=%s AND status='rolled_back' AND policy_uuid<>%s ORDER BY effective_at DESC,id DESC LIMIT 1", $row['context_name'], $row['audience'], $row['policy_uuid'] ), ARRAY_A );
		if ( ! $previous ) { return new \WP_Error( 'file26_no_previous_policy', 'No previously active policy is available to restore.', array( 'status' => 409 ) ); }
		// Consume the approval before mutation. A failed rollback requires a new second approval rather than permitting replay.
		delete_transient( $receipt_key );
		if ( false !== get_transient( $receipt_key ) ) { return new \WP_Error( 'file26_rollback_receipt_consume_failed', 'Rollback approval receipt could not be consumed safely.', array( 'status' => 503 ) ); }
		$wpdb->query( 'START TRANSACTION' );
		try {
			$current_locked = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'ranking_policies' ) . ' WHERE policy_uuid=%s FOR UPDATE', $row['policy_uuid'] ), ARRAY_A );
			$previous_locked = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'ranking_policies' ) . ' WHERE policy_uuid=%s FOR UPDATE', $previous['policy_uuid'] ), ARRAY_A );
			if ( ! $current_locked || 'active' !== $current_locked['status'] || ! hash_equals( (string) $current_locked['version'], (string) $row['version'] ) || ! hash_equals( (string) $current_locked['effective_at'], (string) $row['effective_at'] ) || ! $previous_locked || 'rolled_back' !== $previous_locked['status'] ) { throw new \RuntimeException( 'Rollback state changed.' ); }
			$current_updated = $wpdb->update( DB::table( 'ranking_policies' ), array( 'status' => 'rolled_back', 'updated_at' => DB::now() ), array( 'policy_uuid' => $row['policy_uuid'], 'status' => 'active' ), array( '%s', '%s' ), array( '%s', '%s' ) );
			$previous_updated = $wpdb->update( DB::table( 'ranking_policies' ), array( 'status' => 'active', 'approval_two' => $second, 'effective_at' => DB::now(), 'updated_at' => DB::now() ), array( 'policy_uuid' => $previous['policy_uuid'], 'status' => 'rolled_back' ), array( '%s', '%d', '%s', '%s' ), array( '%s', '%s' ) );
			if ( 1 !== $current_updated || 1 !== $previous_updated ) { throw new \RuntimeException( 'Concurrent rollback transition.' ); }
			if ( 'search' === $row['context_name'] && 'public' === $row['audience'] ) { $this->update_policy_setting( $previous['version'] ); }
			$this->require_audit( 'ranking_policy_rolled_back', array( 'object_type' => 'ranking_policy', 'object_key' => $row['policy_uuid'], 'reason' => sanitize_text_field( $reason ), 'metadata' => array( 'restored_policy_uuid' => $previous['policy_uuid'], 'restored_version' => $previous['version'], 'second_approver' => $second, 'receipt_nonce' => $receipt['nonce'] ) ) );
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_policy_rollback_failed', 'Ranking policy rollback or required audit failed safely; a new second approval is required.', array( 'status' => 409 ) );
		}
		do_action( 'sabri_file26_event', 'RankingPolicyRolledBack', array( 'policy_uuid' => $row['policy_uuid'], 'restored_policy_uuid' => $previous['policy_uuid'], 'restored_version' => $previous['version'] ) );
		return array( 'rolled_back' => $row['policy_uuid'], 'restored' => $previous['policy_uuid'], 'version' => $previous['version'] );
	}

	public function review_classification( $object_key, $term_uuid, $decision, $reason = '', $expected_version = 0 ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) { return new \WP_Error( 'file26_forbidden', 'Taxonomy curator capability is required.', array( 'status' => 403 ) ); }
		$decision = sanitize_key( $decision );
		if ( ! in_array( $decision, array( 'approved', 'rejected', 'corrected', 'removed' ), true ) ) { return new \WP_Error( 'file26_invalid_classification_decision', 'Invalid classification decision.', array( 'status' => 400 ) ); }
		$object_key = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $object_key ) );
		$term_uuid = sanitize_text_field( $term_uuid );
		if ( 64 !== strlen( $object_key ) || ! preg_match( '/^[a-f0-9]{64}$/', $object_key ) || ! preg_match( '/^[a-f0-9-]{36}$/i', $term_uuid ) || $expected_version < 1 ) { return new \WP_Error( 'file26_invalid_classification_reference', 'Valid classification references and expected version are required.', array( 'status' => 400 ) ); }
		$wpdb->query( 'START TRANSACTION' );
		try {
			$term = $wpdb->get_row( $wpdb->prepare( 'SELECT term_uuid,status FROM ' . DB::table( 'terms' ) . ' WHERE term_uuid=%s FOR UPDATE', $term_uuid ), ARRAY_A );
			if ( ! $term || ( 'approved' === $decision && 'active' !== $term['status'] ) ) { throw new \DomainException( 'Term not eligible.' ); }
			$assignment = $wpdb->get_row( $wpdb->prepare( 'SELECT version,provenance FROM ' . DB::table( 'classifications' ) . ' WHERE object_key=%s AND term_uuid=%s FOR UPDATE', $object_key, $term_uuid ), ARRAY_A );
			if ( ! $assignment || (int) $assignment['version'] !== (int) $expected_version ) { throw new \RuntimeException( 'Classification changed concurrently.' ); }
			$provenance = json_decode( $assignment['provenance'], true );
			if ( 'approved' === $decision && ! empty( $provenance['high_impact'] ) && ! apply_filters( 'sabri_file26_classification_domain_reviewer_approved', false, $object_key, $term_uuid, $provenance, get_current_user_id() ) ) { throw new \UnexpectedValueException( 'Domain review required.' ); }
			$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . DB::table( 'classifications' ) . ' SET status=%s,reviewer_id=%d,version=version+1,updated_at=%s WHERE object_key=%s AND term_uuid=%s AND version=%d', $decision, get_current_user_id(), DB::now(), $object_key, $term_uuid, (int) $expected_version ) );
			if ( 1 !== (int) $updated ) { throw new \RuntimeException( 'Classification assignment changed concurrently.' ); }
			$this->require_audit( 'classification_' . $decision, array( 'object_type' => 'classification', 'object_key' => $object_key . ':' . $term_uuid, 'reason' => sanitize_text_field( $reason ), 'metadata' => array( 'from_version' => (int) $expected_version ) ) );
			$wpdb->query( 'COMMIT' );
		} catch ( \DomainException $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_term_not_eligible', 'Classification term is not eligible for this decision.', array( 'status' => 409 ) );
		} catch ( \UnexpectedValueException $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_domain_review_required', 'High-impact classification requires an independent domain reviewer approval.', array( 'status' => 403 ) );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_classification_conflict', 'Classification decision or required audit could not be committed.', array( 'status' => 409 ) );
		}
		do_action( 'sabri_file26_event', 'Classification' . ucfirst( $decision ), array( 'object_key' => $object_key, 'term_uuid' => $term_uuid ) );
		return true;
	}

	public function transition_edge( $edge_uuid, $target, $reason = '' ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) { return new \WP_Error( 'file26_forbidden', 'Graph curator capability is required.', array( 'status' => 403 ) ); }
		$edge_uuid = sanitize_text_field( $edge_uuid );
		$target = sanitize_key( $target );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT edge_uuid,state,version FROM ' . DB::table( 'edges' ) . ' WHERE edge_uuid=%s', $edge_uuid ), ARRAY_A );
		if ( ! $row ) { return new \WP_Error( 'file26_edge_not_found', 'Graph edge not found.', array( 'status' => 404 ) ); }
		if ( 'active' === $target ) { return $this->graph->approve_edge( $edge_uuid, (int) $row['version'] ); }
		if ( 'removed' === $target ) { return $this->graph->remove_edge( $edge_uuid, (int) $row['version'], $reason ); }
		if ( 'corrected' !== $target || 'active' !== $row['state'] ) { return new \WP_Error( 'file26_invalid_edge_state', 'Invalid graph edge lifecycle transition.', array( 'status' => 409 ) ); }
		$wpdb->query( 'START TRANSACTION' );
		try {
			$locked = $wpdb->get_row( $wpdb->prepare( 'SELECT edge_uuid,state,version FROM ' . DB::table( 'edges' ) . ' WHERE edge_uuid=%s FOR UPDATE', $edge_uuid ), ARRAY_A );
			if ( ! $locked || 'active' !== $locked['state'] || (int) $locked['version'] !== (int) $row['version'] ) { throw new \RuntimeException( 'Graph edge changed.' ); }
			$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . DB::table( 'edges' ) . " SET state='corrected',version=version+1,updated_at=%s WHERE edge_uuid=%s AND state='active' AND version=%d", DB::now(), $edge_uuid, (int) $row['version'] ) );
			if ( 1 !== (int) $updated ) { throw new \RuntimeException( 'Graph edge changed concurrently.' ); }
			$this->require_audit( 'knowledge_edge_corrected', array( 'object_type' => 'knowledge_edge', 'object_key' => $edge_uuid, 'reason' => sanitize_text_field( $reason ), 'metadata' => array( 'from' => $row['state'], 'to' => 'corrected' ) ) );
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_edge_transition_conflict', 'Graph edge correction or required audit could not be committed.', array( 'status' => 409 ) );
		}
		return true;
	}

	private function sanitize_policy_features( array $features, $depth = 0 ) {
		if ( $depth > 3 || count( $features ) > 100 ) { return new \WP_Error( 'file26_policy_complexity', 'Ranking policy feature definitions are too complex.', array( 'status' => 400 ) ); }
		$forbidden = array( 'donation', 'payment', 'purchase', 'paid_promotion', 'advertising_spend', 'founder_favoritism', 'religion_inference', 'clinical_query', 'message_body', 'patient_record', 'identity_document' );
		$clean = array();
		foreach ( $features as $raw_key => $value ) {
			$key = sanitize_key( (string) $raw_key );
			if ( '' === $key ) { continue; }
			if ( in_array( $key, $forbidden, true ) ) { return new \WP_Error( 'file26_forbidden_ranking_signal', 'A prohibited ranking signal was supplied.', array( 'status' => 400 ) ); }
			if ( is_array( $value ) ) { $value = $this->sanitize_policy_features( $value, $depth + 1 ); if ( is_wp_error( $value ) ) { return $value; } }
			elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) { /* preserve bounded scalar policy values */ }
			elseif ( is_scalar( $value ) ) { $value = sanitize_text_field( (string) $value ); $value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 512, 'UTF-8' ) : substr( $value, 0, 512 ); }
			else { continue; }
			$clean[ $key ] = $value;
		}
		return $clean;
	}

	public function reports() {
		global $wpdb;
		if ( ! $this->security->can_audit() ) { return new \WP_Error( 'file26_forbidden', 'Audit capability is required.', array( 'status' => 403 ) ); }
		return array(
			'connector_health' => $wpdb->get_results( 'SELECT slug,status,health_state,last_health,last_event_version FROM ' . DB::table( 'connectors' ) . ' ORDER BY slug', ARRAY_A ),
			'jobs' => $wpdb->get_results( 'SELECT job_uuid,job_type,status,attempts,error_code,created_at,finished_at FROM ' . DB::table( 'jobs' ) . ' ORDER BY id DESC LIMIT 100', ARRAY_A ),
			'zero_results' => $wpdb->get_results( $wpdb->prepare( 'SELECT metric_date,locale,SUM(count_value) count_value FROM ' . DB::table( 'metrics' ) . ' WHERE metric_key=%s GROUP BY metric_date,locale ORDER BY metric_date DESC LIMIT 100', 'search_zero_result' ), ARRAY_A ),
			'active_policies' => $wpdb->get_results( "SELECT policy_uuid,context_name,audience,version,effective_at FROM " . DB::table( 'ranking_policies' ) . " WHERE status='active' ORDER BY context_name,audience", ARRAY_A ),
			'policy_version' => DB::setting( 'policy_version', 'organic-1.0' ),
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
		);
	}
}
