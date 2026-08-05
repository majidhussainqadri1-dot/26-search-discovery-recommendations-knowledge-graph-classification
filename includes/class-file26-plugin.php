<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static $instance;
	private $security;
	private $normalizer;
	private $ranking;
	private $connectors;
	private $indexer;
	private $search;
	private $recommendations;
	private $taxonomy;
	private $graph;
	private $health;
	private $governance;
	private $doctor_ranking;
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
		$this->indexer = new Indexer( $this->connectors, $this->normalizer, $this->security );
		$this->search = new Search( $this->normalizer, $this->ranking, $this->security, $this->connectors );
		$this->recommendations = new Recommendations( $this->search, $this->security );
		$this->taxonomy = new Taxonomy( $this->normalizer, $this->security );
		$this->graph = new Graph( $this->security );
		$this->governance = new Governance( $this->security, $this->taxonomy, $this->graph );
		$this->doctor_ranking = new Doctor_Ranking( $this->security );
		$this->health = new Health( $this->connectors );
		$this->rest = new REST(
			$this->search,
			$this->recommendations,
			$this->taxonomy,
			$this->graph,
			$this->indexer,
			$this->health,
			$this->security,
			$this->connectors,
			$this->governance
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
		$this->connectors->boot();
		add_action( 'init', array( $this->routes, 'register' ), 20 );
		add_action( 'rest_api_init', array( $this->rest, 'register' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		$this->admin->register();
		$this->privacy->register();

		add_action( DB::CRON_QUEUE, array( $this->indexer, 'process_queue' ) );
		add_action( DB::CRON_RECONCILE, array( $this->indexer, 'reconcile' ) );
		add_action( DB::CRON_RETENTION, array( $this->indexer, 'retention' ) );
		add_action( DB::CRON_DOCTOR_RANKING, array( $this->doctor_ranking, 'recompute' ) );
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
	}

	public function cron_schedules( $schedules ) {
		$schedules['sabri_file26_monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Every 30 days — File 26' );
		return $schedules;
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
			'result_schema' => 'sabri.file26.result.v1.1',
			'primary_accent' => '#138A36',
		);
		return $providers;
	}

	public function connectors() { return $this->connectors; }
	public function indexer() { return $this->indexer; }
	public function search() { return $this->search; }
	public function recommendations() { return $this->recommendations; }
	public function taxonomy() { return $this->taxonomy; }
	public function graph() { return $this->graph; }
	public function doctor_ranking() { return $this->doctor_ranking; }
	public function governance() { return $this->governance; }
	public function health() { return $this->health; }
}
