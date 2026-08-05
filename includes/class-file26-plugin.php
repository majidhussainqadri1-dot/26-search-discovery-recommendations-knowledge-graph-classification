<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static $instance;
	private $security;
	private $normalizer;
	private $ranking;
	private $connectors;
	private $owner_contracts;
	private $indexer;
	private $search;
	private $recommendations;
	private $taxonomy;
	private $graph;
	private $health;
	private $governance;
	private $doctor_ranking;
	private $doctor_appeals;
	private $rest;
	private $routes;
	private $admin;
	private $privacy;
	private $booted = false;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->security = new Security();
		$this->normalizer = new Normalizer();
		$this->ranking = new Ranking( $this->normalizer );
		$this->connectors = new Connectors( $this->security );
		$this->owner_contracts = new Owner_Contracts( $this->connectors );
		$this->indexer = new Indexer( $this->connectors, $this->normalizer, $this->security );
		$this->search = new Search( $this->normalizer, $this->ranking, $this->security, $this->connectors );
		$this->recommendations = new Recommendations( $this->search, $this->security );
		$this->taxonomy = new Taxonomy( $this->normalizer, $this->security );
		$this->graph = new Graph( $this->security );
		$this->governance = new Governance( $this->security, $this->taxonomy, $this->graph );
		$this->doctor_ranking = new Doctor_Ranking( $this->security );
		$this->doctor_appeals = new Doctor_Appeals( $this->security );
		$this->health = new Health( $this->connectors, $this->owner_contracts );
		$this->rest = new REST(
			$this->search,
			$this->recommendations,
			$this->taxonomy,
			$this->graph,
			$this->indexer,
			$this->health,
			$this->security,
			$this->connectors,
			$this->governance,
			$this->doctor_ranking,
			$this->doctor_appeals
		);
		$this->routes = new Routes( $this->search, $this->recommendations, $this->taxonomy );
		$this->admin = new Admin( $this->health, $this->connectors, $this->indexer, $this->taxonomy, $this->security );
		$this->privacy = new Privacy();
	}

	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		load_plugin_textdomain( 'sabri-file26', false, dirname( plugin_basename( SABRI_FILE26_FILE ) ) . '/languages' );
		Roles::install();
		Doctor_Appeals::install_schema();
		add_filter( 'sabri_file26_connector_manifests', array( $this->owner_contracts, 'collect' ), 5 );
		add_filter( 'sabri_file26_activation_gate_approved', array( $this->owner_contracts, 'activation_gate' ), 10, 3 );
		$this->connectors->boot();
		add_action( 'init', array( $this->routes, 'register' ), 20 );
		add_action( 'rest_api_init', array( $this->rest, 'register' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		$this->admin->register();
		$this->privacy->register();

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

	public function maybe_upgrade() {
		if ( SABRI_FILE26_SCHEMA_VERSION !== get_option( DB::OPTION_SCHEMA ) ) {
			DB::install_schema();
			update_option( DB::OPTION_SCHEMA, SABRI_FILE26_SCHEMA_VERSION, false );
		}
		Roles::install();
		Doctor_Appeals::install_schema();
	}

	public function cron_schedules( $schedules ) {
		$schedules['sabri_file26_monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Every 30 days — File 26' );
		return $schedules;
	}

	public function retain_doctor_appeals() {
		global $wpdb;
		$table = Doctor_Appeals::table();
		$final_days = max( 365, min( 3650, (int) DB::setting( 'ranking_appeal_retention_days', 1095 ) ) );
		$open_days = max( $final_days, min( 3650, (int) DB::setting( 'ranking_appeal_open_retention_days', 1460 ) ) );
		$final_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $final_days * DAY_IN_SECONDS ) );
		$open_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $open_days * DAY_IN_SECONDS ) );
		$withdrawn = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET status='withdrawn',reason_text=%s,evidence_json='[]',decision_reason=%s,appellant_user_id=0,version=version+1,updated_at=%s,decided_at=%s WHERE status IN ('submitted','under_review','changes_requested') AND submitted_at<%s",
				'[redacted after retention expiry]',
				'Closed after the documented maximum open-appeal retention period.',
				DB::now(),
				DB::now(),
				$open_cutoff
			)
		);
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table WHERE status IN ('upheld','corrected','rejected','withdrawn') AND COALESCE(decided_at,updated_at)<%s",
				$final_cutoff
			)
		);
		if ( $withdrawn || $deleted ) {
			$this->security->audit( 'doctor_ranking_appeal_retention', array(
				'object_type' => 'ranking_appeal',
				'object_key' => 'retention',
				'metadata' => array(
					'withdrawn_count' => max( 0, (int) $withdrawn ),
					'deleted_count' => max( 0, (int) $deleted ),
					'final_retention_days' => $final_days,
					'open_retention_days' => $open_days,
				),
			) );
		}
		return array( 'withdrawn' => max( 0, (int) $withdrawn ), 'deleted' => max( 0, (int) $deleted ) );
	}

	public function recompute_doctor_ranking_after_appeal( $doctor_key, $appeal_uuid ) {
		$result = $this->doctor_ranking->recompute( 'appeal_corrected' );
		$this->security->audit( 'doctor_ranking_appeal_recompute', array(
			'object_type' => 'ranking_appeal',
			'object_key' => sanitize_text_field( $appeal_uuid ),
			'metadata' => array(
				'doctor_key' => sanitize_text_field( $doctor_key ),
				'success' => ! is_wp_error( $result ),
			),
		) );
		return $result;
	}

	public function source_restrict( $connector, $domain, $object_id, $object_version, $reason = 'restricted' ) {
		return $this->indexer->restrict( $connector, $domain, $object_id, $object_version, $reason );
	}

	public function source_tombstone( $connector, $domain, $object_id, $object_version, $reason = 'deleted' ) {
		return $this->indexer->tombstone( $connector, $domain, $object_id, $object_version, $reason );
	}

	public function assurance_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests['file26'] = array(
			'file' => 26,
			'name' => 'Search, Discovery, Recommendations, Knowledge Graph and Classification',
			'version' => SABRI_FILE26_VERSION,
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
			'data_classes' => array( 'C1-public-derivative', 'C2-internal', 'C3-private-derived' ),
			'native_controls' => array(
				'three-stage-visibility',
				'production-lane-connector-isolation',
				'tombstone-purge',
				'query-redaction',
				'bounded-graph',
				'consent-controls',
				'separation-of-duties',
				'dual-approved-policy-rollback',
				'doctor-ranking-appeals',
				'doctor-ranking-appeal-retention',
				'required-owner-contract-gate',
				'rate-limits',
				'audit',
			),
			'health' => array( $this->health, 'snapshot' ),
		);
		return $manifests;
	}

	public function visual_provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		$providers['file26'] = array(
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
			'search' => array( $this->search, 'run' ),
			'discover' => array( $this->recommendations, 'get' ),
			'doctor_ranking' => array( $this->doctor_ranking, 'directory' ),
			'result_schema' => 'sabri.file26.result.v1.1',
			'primary_accent' => '#138A36',
		);
		return $providers;
	}

	public function connectors() { return $this->connectors; }
	public function owner_contracts() { return $this->owner_contracts; }
	public function indexer() { return $this->indexer; }
	public function search() { return $this->search; }
	public function recommendations() { return $this->recommendations; }
	public function taxonomy() { return $this->taxonomy; }
	public function graph() { return $this->graph; }
	public function doctor_ranking() { return $this->doctor_ranking; }
	public function doctor_appeals() { return $this->doctor_appeals; }
	public function governance() { return $this->governance; }
	public function health() { return $this->health; }
}
