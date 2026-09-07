<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/**
 * Functional FR-024/FR-032 registry.
 * Stores only bounded evaluation/experiment metadata and dataset references; raw user queries are never accepted here.
 */
final class Evaluation {
	const OPTION_EVALUATIONS = 'sabri_file26_relevance_evaluations_v1';
	const OPTION_EXPERIMENTS = 'sabri_file26_search_experiments_v1';
	const OPTION_FAILURE = 'sabri_file26_evaluation_failure';
	const REST_NAMESPACE = 'sabri-search/v1';
	private static $instance;
	private $security;

	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self( new Security() ); }
		return self::$instance;
	}

	public function __construct( Security $security ) { $this->security = $security; }

	public function boot() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 35 );
		add_action( DB::CRON_RETENTION, array( $this, 'retention' ), 50 );
		add_filter( 'sabri_file26_health_extension', array( $this, 'health_extension' ) );
		add_filter( 'sabri_file26_experiment_assignment', array( $this, 'assignment_filter' ), 10, 3 );
	}

	public function register_routes() {
		register_rest_route( self::REST_NAMESPACE, '/admin/evaluations', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'rest_evaluations' ), 'permission_callback' => array( $this, 'can_audit' ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'rest_record_evaluation' ), 'permission_callback' => array( $this, 'can_approve' ) ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/admin/experiments', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'rest_experiments' ), 'permission_callback' => array( $this, 'can_audit' ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'rest_stage_experiment' ), 'permission_callback' => array( $this, 'can_approve' ) ),
		) );
		register_rest_route( self::REST_NAMESPACE, '/admin/experiments/(?P<experiment_id>[a-f0-9-]{36})/(?P<command>approve|activate|stop)', array(
			'methods' => 'POST', 'callback' => array( $this, 'rest_experiment_command' ), 'permission_callback' => array( $this, 'can_approve' ),
		) );
	}

	public function can_audit() { return $this->security->can_audit() ? true : new \WP_Error( 'file26_forbidden', 'Search audit capability is required.', array( 'status' => 403 ) ); }
	public function can_approve() { return $this->security->can_approve_ranking() ? true : new \WP_Error( 'file26_forbidden', 'Ranking approval capability is required.', array( 'status' => 403 ) ); }

	public function record_evaluation( array $input ) {
		if ( ! $this->security->can_approve_ranking() || ! $this->security->require_step_up( 'relevance_evaluation_record' ) ) {
			return new \WP_Error( 'file26_forbidden', 'Fresh ranking-evaluation authorization is required.', array( 'status' => 403 ) );
		}
		$dataset = $this->bounded_id( isset( $input['dataset_version'] ) ? $input['dataset_version'] : '' );
		$judgments = $this->bounded_id( isset( $input['judgments_version'] ) ? $input['judgments_version'] : '' );
		$reviewer_version = $this->bounded_id( isset( $input['reviewer_version'] ) ? $input['reviewer_version'] : '' );
		$policy = $this->bounded_id( isset( $input['policy_version'] ) ? $input['policy_version'] : '' );
		if ( ! $dataset || ! $judgments || ! $reviewer_version || ! $policy ) { return new \WP_Error( 'file26_invalid_evaluation', 'Dataset, judgments, reviewer and policy versions are required.', array( 'status' => 400 ) ); }
		$languages = $this->bounded_list( isset( $input['languages'] ) ? $input['languages'] : array(), 20 );
		$domains = $this->bounded_list( isset( $input['domains'] ) ? $input['domains'] : array(), 30 );
		$safety_cases = $this->bounded_list( isset( $input['safety_cases'] ) ? $input['safety_cases'] : array(), 30 );
		$metrics = $this->numeric_map( isset( $input['metrics'] ) ? $input['metrics'] : array(), 30 );
		$baseline = $this->numeric_map( isset( $input['baseline_metrics'] ) ? $input['baseline_metrics'] : array(), 30 );
		$tolerance = isset( $input['approved_tolerance'] ) && is_numeric( $input['approved_tolerance'] ) ? max( 0.0, min( 1.0, (float) $input['approved_tolerance'] ) ) : 0.0;
		if ( ! $languages || ! $domains || ! $metrics || ! $baseline ) { return new \WP_Error( 'file26_invalid_evaluation', 'Language/domain coverage and current/baseline metrics are required.', array( 'status' => 400 ) ); }
		$regressions = array();
		foreach ( $baseline as $name => $base ) { if ( isset( $metrics[ $name ] ) && $metrics[ $name ] + $tolerance < $base ) { $regressions[] = $name; } }
		$record = array(
			'evaluation_uuid' => DB::uuid(), 'dataset_version' => $dataset, 'judgments_version' => $judgments, 'reviewer_version' => $reviewer_version,
			'policy_version' => $policy, 'languages' => $languages, 'domains' => $domains, 'safety_cases' => $safety_cases,
			'metric_definitions_version' => $this->bounded_id( isset( $input['metric_definitions_version'] ) ? $input['metric_definitions_version'] : 'file26-metrics-1' ),
			'baseline_metrics' => $baseline, 'metrics' => $metrics, 'approved_tolerance' => $tolerance, 'regressions' => $regressions,
			'release_comparison_passed' => empty( $regressions ) && ! empty( $input['safety_passed'] ), 'safety_passed' => ! empty( $input['safety_passed'] ),
			'reviewer_user_id' => get_current_user_id(), 'created_at' => DB::now(),
		);
		$result = $this->append_option_record( self::OPTION_EVALUATIONS, $record['evaluation_uuid'], $record, 200 );
		if ( is_wp_error( $result ) ) { return $result; }
		$audit = $this->security->audit( 'relevance_evaluation_recorded', array( 'object_type' => 'relevance_evaluation', 'object_key' => $record['evaluation_uuid'], 'metadata' => array( 'policy_version' => $policy, 'passed' => $record['release_comparison_passed'], 'regression_count' => count( $regressions ) ) ) );
		if ( is_wp_error( $audit ) ) { return $this->mutation_audit_failure( 'evaluation_recorded', $audit ); }
		return $record;
	}

	public function stage_experiment( array $input ) {
		if ( ! $this->security->can_approve_ranking() || ! $this->security->require_step_up( 'search_experiment_stage' ) ) { return new \WP_Error( 'file26_forbidden', 'Fresh experiment authorization is required.', array( 'status' => 403 ) ); }
		$hypothesis = $this->bounded_text( isset( $input['hypothesis'] ) ? $input['hypothesis'] : '', 500 );
		$policy = $this->bounded_id( isset( $input['policy_version'] ) ? $input['policy_version'] : '' );
		$sample = isset( $input['sample_basis_points'] ) ? max( 1, min( 1000, (int) $input['sample_basis_points'] ) ) : 100;
		$duration_hours = isset( $input['duration_hours'] ) ? max( 1, min( 336, (int) $input['duration_hours'] ) ) : 24;
		$guardrails = isset( $input['guardrails'] ) && is_array( $input['guardrails'] ) ? $input['guardrails'] : array();
		$required_true = array( 'medical_safety_unchanged', 'minors_unchanged', 'visibility_unchanged', 'privacy_reviewed', 'rollback_ready' );
		foreach ( $required_true as $key ) { if ( empty( $guardrails[ $key ] ) ) { return new \WP_Error( 'file26_experiment_guardrail_required', 'All safety, minors, visibility, privacy and rollback guardrails must be explicit.', array( 'status' => 400, 'guardrail' => $key ) ); } }
		if ( ! empty( $guardrails['paid_or_donor_ranking'] ) ) { return new \WP_Error( 'file26_experiment_forbidden_signal', 'Experiments may not introduce paid or donor ranking.', array( 'status' => 400 ) ); }
		if ( ! $hypothesis || ! $policy ) { return new \WP_Error( 'file26_invalid_experiment', 'Hypothesis and policy version are required.', array( 'status' => 400 ) ); }
		$proposed = array( 'hypothesis' => $hypothesis, 'policy_version' => $policy, 'sample_basis_points' => $sample, 'duration_hours' => $duration_hours, 'guardrails' => $guardrails );
		if ( ! (bool) apply_filters( 'sabri_file26_experiment_founder_approved', false, $proposed, get_current_user_id() ) ) { return new \WP_Error( 'file26_founder_experiment_approval_required', 'Founder-approved experiment evidence is required.', array( 'status' => 403 ) ); }
		$uuid = DB::uuid(); $now = time();
		$record = array(
			'experiment_uuid' => $uuid, 'hypothesis' => $hypothesis, 'policy_version' => $policy, 'sample_basis_points' => $sample,
			'duration_hours' => $duration_hours, 'guardrails' => array_fill_keys( $required_true, true ) + array( 'paid_or_donor_ranking' => false ),
			'status' => 'staged', 'approval_one' => get_current_user_id(), 'approval_two' => null, 'version' => 1,
			'starts_at' => null, 'ends_at' => null, 'stop_reason' => '', 'created_at' => gmdate( 'Y-m-d H:i:s', $now ), 'updated_at' => gmdate( 'Y-m-d H:i:s', $now ),
		);
		$result = $this->append_option_record( self::OPTION_EXPERIMENTS, $uuid, $record, 100 ); if ( is_wp_error( $result ) ) { return $result; }
		$audit = $this->security->audit( 'search_experiment_staged', array( 'object_type' => 'search_experiment', 'object_key' => $uuid, 'metadata' => array( 'policy_version' => $policy, 'sample_basis_points' => $sample, 'duration_hours' => $duration_hours ) ) );
		if ( is_wp_error( $audit ) ) { return $this->mutation_audit_failure( 'experiment_staged', $audit ); }
		return $record;
	}

	public function command( $uuid, $command, $reason = '' ) {
		if ( ! $this->security->can_approve_ranking() || ! $this->security->require_step_up( 'search_experiment_' . sanitize_key( $command ) ) ) { return new \WP_Error( 'file26_forbidden', 'Fresh experiment command authorization is required.', array( 'status' => 403 ) ); }
		$uuid = strtolower( sanitize_text_field( $uuid ) ); $command = sanitize_key( $command ); $reason = $this->bounded_text( $reason, 500 );
		$lock = $this->lock( 'experiments' ); if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$records = get_option( self::OPTION_EXPERIMENTS, array() ); $records = is_array( $records ) ? $records : array();
			if ( empty( $records[ $uuid ] ) || ! is_array( $records[ $uuid ] ) ) { return new \WP_Error( 'file26_experiment_not_found', 'Experiment not found.', array( 'status' => 404 ) ); }
			$record = $records[ $uuid ]; $current = get_current_user_id(); $now = time();
			if ( 'approve' === $command ) {
				if ( 'staged' !== $record['status'] || $current === (int) $record['approval_one'] || ! empty( $record['approval_two'] ) ) { return new \WP_Error( 'file26_experiment_second_approval_required', 'A distinct second approval is required.', array( 'status' => 409 ) ); }
				$record['approval_two'] = $current; $record['version']++;
			} elseif ( 'activate' === $command ) {
				if ( 'staged' !== $record['status'] || empty( $record['approval_two'] ) || (int) $record['approval_two'] === (int) $record['approval_one'] ) { return new \WP_Error( 'file26_experiment_dual_approval_required', 'A staged experiment with two distinct approvals is required.', array( 'status' => 409 ) ); }
				foreach ( $records as $other_id => $other ) { if ( $other_id !== $uuid && is_array( $other ) && 'active' === $other['status'] ) { return new \WP_Error( 'file26_experiment_already_active', 'Only one bounded ranking experiment may be active at a time.', array( 'status' => 409 ) ); } }
				$record['status'] = 'active'; $record['starts_at'] = gmdate( 'Y-m-d H:i:s', $now ); $record['ends_at'] = gmdate( 'Y-m-d H:i:s', $now + ( (int) $record['duration_hours'] * HOUR_IN_SECONDS ) ); $record['version']++;
			} elseif ( 'stop' === $command ) {
				if ( ! in_array( $record['status'], array( 'staged', 'active' ), true ) ) { return new \WP_Error( 'file26_experiment_not_stoppable', 'Experiment is not in a stoppable state.', array( 'status' => 409 ) ); }
				$record['status'] = 'stopped'; $record['stop_reason'] = $reason ? $reason : 'manual_stop'; $record['ends_at'] = gmdate( 'Y-m-d H:i:s', $now ); $record['version']++;
			} else { return new \WP_Error( 'file26_invalid_experiment_command', 'Invalid experiment command.', array( 'status' => 400 ) ); }
			$record['updated_at'] = gmdate( 'Y-m-d H:i:s', $now ); $records[ $uuid ] = $record;
			$write = $this->write_option_verified( self::OPTION_EXPERIMENTS, $records ); if ( is_wp_error( $write ) ) { return $write; }
		} finally { $this->unlock( $lock ); }
		$audit = $this->security->audit( 'search_experiment_' . $command, array( 'object_type' => 'search_experiment', 'object_key' => $uuid, 'reason' => $reason, 'metadata' => array( 'status' => $record['status'], 'version' => $record['version'] ) ) );
		if ( is_wp_error( $audit ) ) { return $this->mutation_audit_failure( 'experiment_' . $command, $audit ); }
		return $record;
	}

	public function assignment( $context = 'search', $query = '' ) {
		$audience = $this->security->audience();
		if ( ! empty( $audience['is_minor'] ) || $this->security->contains_sensitive_query( $query ) || 'search' !== sanitize_key( $context ) ) { return array( 'variant' => 'control', 'experiment' => null ); }
		$records = get_option( self::OPTION_EXPERIMENTS, array() ); $now = time();
		foreach ( is_array( $records ) ? $records : array() as $record ) {
			if ( ! is_array( $record ) || 'active' !== $record['status'] ) { continue; }
			$start = strtotime( $record['starts_at'] . ' UTC' ); $end = strtotime( $record['ends_at'] . ' UTC' ); if ( ! $start || ! $end || $start > $now || $end < $now ) { continue; }
			$bucket = hexdec( substr( hash_hmac( 'sha256', $this->security->client_bucket() . '|' . $record['experiment_uuid'], wp_salt( 'auth' ) ), 0, 8 ) ) % 10000;
			return array( 'variant' => $bucket < (int) $record['sample_basis_points'] ? 'candidate' : 'control', 'experiment' => $record['experiment_uuid'], 'policy_version' => $record['policy_version'] );
		}
		return array( 'variant' => 'control', 'experiment' => null );
	}

	public function assignment_filter( $value, $context, $query ) { return $this->assignment( $context, $query ); }
	public function evaluations() { $value = get_option( self::OPTION_EVALUATIONS, array() ); return is_array( $value ) ? array_values( $value ) : array(); }
	public function experiments() { $value = get_option( self::OPTION_EXPERIMENTS, array() ); return is_array( $value ) ? array_values( $value ) : array(); }

	public function health_extension( $extra ) {
		$extra = is_array( $extra ) ? $extra : array(); $evaluations = $this->evaluations(); $experiments = $this->experiments(); $active = 0;
		foreach ( $experiments as $record ) { if ( isset( $record['status'] ) && 'active' === $record['status'] ) { $active++; } }
		$latest = $evaluations ? end( $evaluations ) : null;
		$extra['evaluation'] = array( 'registry_count' => count( $evaluations ), 'active_experiments' => $active, 'latest_release_comparison_passed' => is_array( $latest ) ? ! empty( $latest['release_comparison_passed'] ) : null, 'failure' => (bool) get_option( self::OPTION_FAILURE, false ) );
		return $extra;
	}

	public function retention() {
		$records = get_option( self::OPTION_EXPERIMENTS, array() ); if ( ! is_array( $records ) ) { return; } $cutoff = time() - ( 365 * DAY_IN_SECONDS ); $changed = false;
		foreach ( $records as $id => $record ) { if ( is_array( $record ) && in_array( isset( $record['status'] ) ? $record['status'] : '', array( 'stopped', 'completed' ), true ) && ! empty( $record['updated_at'] ) && strtotime( $record['updated_at'] . ' UTC' ) < $cutoff ) { unset( $records[ $id ] ); $changed = true; } }
		if ( $changed ) { $this->write_option_verified( self::OPTION_EXPERIMENTS, $records ); }
	}

	public function rest_evaluations() { return rest_ensure_response( array( 'contract_version' => SABRI_FILE26_CONTRACT_VERSION, 'evaluations' => $this->evaluations() ) ); }
	public function rest_experiments() { return rest_ensure_response( array( 'contract_version' => SABRI_FILE26_CONTRACT_VERSION, 'experiments' => $this->experiments() ) ); }
	public function rest_record_evaluation( \WP_REST_Request $request ) { $result = $this->record_evaluation( (array) $request->get_json_params() ); return is_wp_error( $result ) ? $result : rest_ensure_response( $result ); }
	public function rest_stage_experiment( \WP_REST_Request $request ) { $result = $this->stage_experiment( (array) $request->get_json_params() ); return is_wp_error( $result ) ? $result : rest_ensure_response( $result ); }
	public function rest_experiment_command( \WP_REST_Request $request ) { $params = (array) $request->get_json_params(); $result = $this->command( $request['experiment_id'], $request['command'], isset( $params['reason'] ) ? $params['reason'] : '' ); return is_wp_error( $result ) ? $result : rest_ensure_response( $result ); }

	private function append_option_record( $option, $id, array $record, $max ) {
		$lock = $this->lock( $option ); if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$value = get_option( $option, array() ); $value = is_array( $value ) ? $value : array(); $value[ $id ] = $record;
			if ( count( $value ) > $max ) { $value = array_slice( $value, -$max, null, true ); }
			return $this->write_option_verified( $option, $value );
		} finally { $this->unlock( $lock ); }
	}

	private function write_option_verified( $option, array $value ) {
		$written = update_option( $option, $value, false ); $stored = get_option( $option, null );
		if ( ( ! $written && $stored !== $value ) || $stored !== $value ) { update_option( self::OPTION_FAILURE, array( 'at' => DB::now(), 'option' => sanitize_key( $option ) ), false ); return new \WP_Error( 'file26_evaluation_persistence_failed', 'Evaluation/experiment state could not be persisted and verified.', array( 'status' => 500 ) ); }
		delete_option( self::OPTION_FAILURE ); return true;
	}

	private function lock( $scope ) { global $wpdb; $name = 'file26:eval:' . substr( hash( 'sha256', (string) $scope ), 0, 40 ); $got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $name ) ); return '1' === (string) $got ? $name : new \WP_Error( 'file26_evaluation_busy', 'Evaluation registry is busy; retry safely.', array( 'status' => 409 ) ); }
	private function unlock( $name ) { global $wpdb; if ( is_string( $name ) ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); } }
	private function bounded_id( $value ) { return substr( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ), 0, 120 ); }
	private function bounded_text( $value, $max ) { $value = trim( wp_strip_all_tags( is_scalar( $value ) ? (string) $value : '', true ) ); return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max, 'UTF-8' ) : substr( $value, 0, $max ); }
	private function bounded_list( $value, $max ) { $out = array(); foreach ( array_slice( is_array( $value ) ? $value : array(), 0, $max ) as $item ) { if ( is_scalar( $item ) ) { $item = $this->bounded_id( $item ); if ( $item ) { $out[] = $item; } } } return array_values( array_unique( $out ) ); }
	private function numeric_map( $value, $max ) { $out = array(); if ( ! is_array( $value ) ) { return $out; } foreach ( array_slice( $value, 0, $max, true ) as $key => $number ) { $key = substr( sanitize_key( $key ), 0, 64 ); if ( $key && is_numeric( $number ) ) { $out[ $key ] = round( (float) $number, 8 ); } } return $out; }
	private function mutation_audit_failure( $stage, $audit ) { return new \WP_Error( 'file26_required_audit_failed', 'Evaluation/experiment mutation was persisted but required audit evidence failed; File 26 is degraded until audit storage is repaired.', array( 'status' => 500, 'mutation_persisted' => true, 'stage' => sanitize_key( $stage ), 'audit_error' => is_wp_error( $audit ) ? $audit->get_error_code() : 'unknown' ) ); }
}
