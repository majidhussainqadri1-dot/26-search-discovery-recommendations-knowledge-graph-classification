<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static $instance;
	private $security; private $normalizer; private $ranking; private $connectors; private $owner_contracts;
	private $indexer; private $search; private $recommendations; private $taxonomy; private $graph; private $health;
	private $governance; private $doctor_ranking; private $doctor_appeals; private $central_plan; private $rest; private $routes;
	private $admin; private $privacy; private $booted = false;

	public static function instance() { if ( ! self::$instance ) { self::$instance = new self(); } return self::$instance; }
	private function __construct() {
		$this->security = new Security(); $this->normalizer = new Normalizer(); $this->ranking = new Ranking( $this->normalizer );
		$this->connectors = new Connectors( $this->security ); $this->owner_contracts = new Owner_Contracts( $this->connectors );
		$this->indexer = new Indexer( $this->connectors, $this->normalizer, $this->security );
		$this->search = new Search( $this->normalizer, $this->ranking, $this->security, $this->connectors );
		$this->recommendations = new Recommendations( $this->search, $this->security ); $this->taxonomy = new Taxonomy( $this->normalizer, $this->security );
		$this->graph = new Graph( $this->security ); $this->governance = new Governance( $this->security, $this->taxonomy, $this->graph );
		$this->doctor_ranking = new Doctor_Ranking( $this->security ); $this->doctor_appeals = new Doctor_Appeals( $this->security );
		$this->health = new Health( $this->connectors, $this->owner_contracts ); $this->central_plan = new Central_Plan( $this->search, $this->normalizer, $this->security, $this->ranking, $this->doctor_ranking, $this->health );
		$this->rest = new REST( $this->search, $this->recommendations, $this->taxonomy, $this->graph, $this->indexer, $this->health, $this->security, $this->connectors, $this->governance, $this->doctor_ranking, $this->doctor_appeals );
		$this->routes = new Routes( $this->search, $this->recommendations, $this->taxonomy ); $this->admin = new Admin( $this->health, $this->connectors, $this->indexer, $this->taxonomy, $this->security ); $this->privacy = new Privacy();
	}

	public function boot() {
		if ( $this->booted ) { return; }
		$this->booted = true;
		load_plugin_textdomain( 'sabri-file26', false, dirname( plugin_basename( SABRI_FILE26_FILE ) ) . '/languages' );
		$migration = $this->ensure_schema_current();
		if ( is_wp_error( $migration ) ) {
			DB::update_settings( array( 'activated' => false, 'public_search_enabled' => false, 'personalization_enabled' => false ) );
			add_action( 'admin_notices', static function () { echo '<div class="notice notice-error"><p>' . esc_html__( 'File 26 is fail-closed because its database migration could not be verified. Review migration evidence before reactivation.', 'sabri-file26' ) . '</p></div>'; } );
			return;
		}
		Roles::install();
		add_filter( 'sabri_file26_connector_manifests', array( $this->owner_contracts, 'collect' ), 5 );
		add_filter( 'sabri_file26_activation_gate_approved', array( $this->owner_contracts, 'activation_gate' ), 10, 3 );
		$this->connectors->boot();
		add_action( 'init', array( $this->routes, 'register' ), 20 );
		add_action( 'rest_api_init', array( $this->rest, 'register' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		$this->admin->register(); $this->privacy->register(); $this->central_plan->boot();
		add_action( DB::CRON_QUEUE, array( $this->indexer, 'process_queue' ) );
		add_action( DB::CRON_RECONCILE, array( $this->indexer, 'reconcile' ) );
		add_action( DB::CRON_RETENTION, array( $this->indexer, 'retention' ) );
		add_action( DB::CRON_RETENTION, array( $this, 'retain_doctor_appeals' ), 20 );
		add_action( DB::CRON_DOCTOR_RANKING, array( $this->doctor_ranking, 'recompute' ) );
		add_action( 'sabri_file26_doctor_ranking_recompute_requested', array( $this, 'recompute_doctor_ranking_after_appeal' ), 10, 2 );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'sabri_file26_source_upsert', array( $this->indexer, 'upsert' ), 10, 1 );
		add_action( 'sabri_file26_source_restrict', array( $this, 'source_restrict' ), 10, 5 );
		add_action( 'sabri_file26_source_tombstone', array( $this, 'source_tombstone' ), 10, 5 );
		add_filter( 'sabri_file24_module_manifest', array( $this, 'assurance_manifest' ) );
		add_filter( 'sabri_file25_search_provider', array( $this, 'visual_provider' ) );
		DB::schedule();
	}

	/** Serialize and verify schema changes before connectors/routes/search are exposed. */
	private function ensure_schema_current() {
		global $wpdb;
		$main_current = SABRI_FILE26_SCHEMA_VERSION === get_option( DB::OPTION_SCHEMA );
		$appeal_current = Doctor_Appeals::SCHEMA_VERSION === get_option( Doctor_Appeals::OPTION_SCHEMA );
		$shape = $this->verify_schema_shape();
		if ( $main_current && $appeal_current && true === $shape ) { return true; }

		$lock_name = 'file26:schema-migration';
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) ) ) { return new \WP_Error( 'file26_migration_busy', 'File 26 schema migration is already running.' ); }
		try {
			// Version options alone never prove schema parity. dbDelta is rerun whenever shape verification fails.
			if ( ! $main_current || is_wp_error( $shape ) ) { DB::install_schema(); }
			if ( ! $appeal_current || is_wp_error( $shape ) ) { Doctor_Appeals::install_schema( true ); }
			$verified = $this->verify_schema_shape();
			if ( is_wp_error( $verified ) ) { return $verified; }
			update_option( DB::OPTION_SCHEMA, SABRI_FILE26_SCHEMA_VERSION, false );
			update_option( Doctor_Appeals::OPTION_SCHEMA, Doctor_Appeals::SCHEMA_VERSION, false );
			return true;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	/** Table existence is insufficient: verify critical columns required by runtime contracts. */
	private function verify_schema_shape() {
		global $wpdb;
		$requirements = array(
			'connectors' => array( 'slug', 'owner_file', 'contract_version', 'status', 'manifest', 'last_event_version', 'health_state' ),
			'documents' => array( 'canonical_key', 'connector_slug', 'object_id', 'object_version', 'entity_type', 'state', 'visibility', 'freshness_at', 'payload', 'source_event_sequence', 'checksum' ),
			'tombstones' => array( 'canonical_key', 'object_version', 'reason_class', 'expires_at' ),
			'terms' => array( 'term_uuid', 'slug', 'language', 'owner_file', 'status', 'version', 'redirect_uuid' ),
			'term_aliases' => array( 'term_uuid', 'alias_normalized', 'language', 'status' ),
			'classifications' => array( 'object_key', 'term_uuid', 'confidence', 'status', 'provenance', 'version' ),
			'nodes' => array( 'node_key', 'node_type', 'visibility', 'state', 'version' ),
			'edges' => array( 'edge_uuid', 'source_key', 'target_key', 'edge_type', 'provenance', 'state', 'visibility', 'version' ),
			'ranking_policies' => array( 'policy_uuid', 'context_name', 'audience', 'version', 'status', 'features_json', 'approval_one', 'approval_two', 'effective_at' ),
			'feedback' => array( 'idempotency_key', 'user_id', 'feedback_type', 'active', 'expires_at' ),
			'profiles' => array( 'user_id', 'consent', 'opted_out', 'interests_json', 'negatives_json', 'version' ),
			'jobs' => array( 'job_uuid', 'job_type', 'status', 'cursor_value', 'counts_json', 'attempts', 'lock_token', 'available_at' ),
			'audit' => array( 'action_name', 'actor_id', 'object_type', 'object_key', 'reason_code', 'trace_id', 'metadata', 'created_at' ),
			'metrics' => array( 'metric_name', 'dimensions_hash', 'bucket_start', 'metric_value' ),
			'rate_limits' => array( 'bucket_key', 'window_start', 'count_value', 'expires_at' ),
		);
		foreach ( $requirements as $name => $required_columns ) {
			$table = DB::table( $name );
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
				return new \WP_Error( 'file26_schema_incomplete', 'A required File 26 table is missing after migration.' );
			}
			$columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '`', 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $required_columns as $column ) {
				if ( ! in_array( $column, $columns, true ) ) {
					return new \WP_Error( 'file26_schema_column_missing', sprintf( 'File 26 schema verification failed: %s.%s is missing.', $name, $column ) );
				}
			}
		}
		$appeals = Doctor_Appeals::table();
		if ( $appeals !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $appeals ) ) ) ) {
			return new \WP_Error( 'file26_schema_incomplete', 'The ranking appeals table is missing after migration.' );
		}
		$appeal_columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM `' . esc_sql( $appeals ) . '`', 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( array( 'appeal_uuid', 'doctor_key', 'appellant_user_id', 'status', 'reviewer_id', 'policy_version', 'rank_snapshot', 'version', 'submitted_at', 'updated_at', 'decided_at' ) as $column ) {
			if ( ! in_array( $column, $appeal_columns, true ) ) {
				return new \WP_Error( 'file26_schema_column_missing', sprintf( 'File 26 ranking-appeal schema verification failed: %s is missing.', $column ) );
			}
		}
		return true;
	}

	public function maybe_upgrade() { return $this->ensure_schema_current(); }
	public function cron_schedules( $schedules ) { $schedules['sabri_file26_monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Every 30 days — File 26' ); return $schedules; }

	public function retain_doctor_appeals() {
		global $wpdb; $table = Doctor_Appeals::table();
		$final_days = max( 365, min( 3650, (int) DB::setting( 'ranking_appeal_retention_days', 1095 ) ) ); $open_days = max( $final_days, min( 3650, (int) DB::setting( 'ranking_appeal_open_retention_days', 1460 ) ) );
		$final_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $final_days * DAY_IN_SECONDS ) ); $open_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $open_days * DAY_IN_SECONDS ) );
		$withdrawn = $wpdb->query( $wpdb->prepare( "UPDATE $table SET status='withdrawn',reason_text=%s,evidence_json='[]',decision_reason=%s,appellant_user_id=0,version=version+1,updated_at=%s,decided_at=%s WHERE status IN ('submitted','under_review','changes_requested') AND submitted_at<%s", '[redacted after retention expiry]', 'Closed after the documented maximum open-appeal retention period.', DB::now(), DB::now(), $open_cutoff ) );
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE status IN ('upheld','corrected','rejected','withdrawn') AND COALESCE(decided_at,updated_at)<%s", $final_cutoff ) );
		if ( $withdrawn || $deleted ) { $this->security->audit( 'doctor_ranking_appeal_retention', array( 'object_type' => 'ranking_appeal', 'object_key' => 'retention', 'metadata' => array( 'withdrawn_count' => max( 0, (int) $withdrawn ), 'deleted_count' => max( 0, (int) $deleted ), 'final_retention_days' => $final_days, 'open_retention_days' => $open_days ) ) ); }
		return array( 'withdrawn' => max( 0, (int) $withdrawn ), 'deleted' => max( 0, (int) $deleted ) );
	}

	public function recompute_doctor_ranking_after_appeal( $doctor_key, $appeal_uuid ) {
		$result = $this->doctor_ranking->recompute( 'appeal_corrected' );
		$this->security->audit( 'doctor_ranking_appeal_recompute', array( 'object_type' => 'ranking_appeal', 'object_key' => sanitize_text_field( $appeal_uuid ), 'metadata' => array( 'doctor_key' => sanitize_text_field( $doctor_key ), 'success' => ! is_wp_error( $result ) ) ) );
		return $result;
	}

	public function source_restrict( $connector, $domain, $object_id, $object_version, $reason = 'restricted' ) { return $this->indexer->restrict( $connector, $domain, $object_id, $object_version, $reason ); }
	public function source_tombstone( $connector, $domain, $object_id, $object_version, $reason = 'deleted' ) { return $this->indexer->tombstone( $connector, $domain, $object_id, $object_version, $reason ); }

	public function assurance_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests['file26'] = array( 'file' => 26, 'name' => 'Search, Discovery, Recommendations, Knowledge Graph and Classification', 'version' => SABRI_FILE26_VERSION, 'contract_version' => SABRI_FILE26_CONTRACT_VERSION, 'data_classes' => array( 'C1-public-derivative', 'C2-internal', 'C3-private-derived' ), 'native_controls' => array( 'three-stage-visibility','production-lane-connector-isolation','tombstone-purge','query-redaction','bounded-graph','consent-controls','separation-of-duties','dual-approved-policy-rollback','doctor-ranking-appeals','doctor-ranking-appeal-retention','required-owner-contract-gate','advanced-search','account-owned-saved-queries','zero-result-recovery','search-safety-diversion','index-freshness-evidence','privacy-minimized-editorial-radar','public-ranking-constitution','single-free-tier-rank-parity','rate-limits','audit' ), 'health' => array( $this->health, 'snapshot' ) );
		return $manifests;
	}
	public function search_contract( array $request ) { $result = $this->search->run( $request ); return $this->central_plan->augment_search_result( $result, $request ); }
	public function visual_provider( $providers ) { $providers = is_array( $providers ) ? $providers : array(); $providers['file26'] = array( 'contract_version' => SABRI_FILE26_CONTRACT_VERSION, 'search' => array( $this, 'search_contract' ), 'discover' => array( $this->recommendations, 'get' ), 'doctor_ranking' => array( $this->doctor_ranking, 'directory' ), 'ranking_constitution' => array( $this->central_plan, 'ranking_constitution' ), 'result_schema' => 'sabri.file26.result.v1.2', 'primary_accent_fallback' => '#087A4E', 'visual_owner' => 'File 25' ); return $providers; }
	public function connectors() { return $this->connectors; } public function owner_contracts() { return $this->owner_contracts; } public function indexer() { return $this->indexer; } public function search() { return $this->search; } public function recommendations() { return $this->recommendations; } public function taxonomy() { return $this->taxonomy; } public function graph() { return $this->graph; } public function doctor_ranking() { return $this->doctor_ranking; } public function doctor_appeals() { return $this->doctor_appeals; } public function governance() { return $this->governance; } public function health() { return $this->health; } public function central_plan() { return $this->central_plan; }
}
