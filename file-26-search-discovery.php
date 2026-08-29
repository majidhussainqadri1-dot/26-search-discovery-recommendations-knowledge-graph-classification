<?php
/**
 * Plugin Name: File 26 — Search, Discovery, Recommendations, Knowledge Graph and Classification
 * Plugin URI: https://sabrihomeopathy.com/
 * Description: Federated, privacy-safe search, discovery, recommendations, taxonomy, knowledge graph and content-classification infrastructure for the Sabri Social Homeopathy Platform.
 * Version: 1.3.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * Text Domain: sabri-file26
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'SABRI_FILE26_VERSION', '1.3.0' );
define( 'SABRI_FILE26_SCHEMA_VERSION', '1.0.0' );
define( 'SABRI_FILE26_CONTRACT_VERSION', '1.3' );
define( 'SABRI_FILE26_FILE', __FILE__ );
define( 'SABRI_FILE26_DIR', plugin_dir_path( __FILE__ ) );
define( 'SABRI_FILE26_URL', plugin_dir_url( __FILE__ ) );

require_once SABRI_FILE26_DIR . 'includes/class-file26-db.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-roles.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-security.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-normalizer.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-ranking.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-connectors.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-owner-contracts.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-indexer.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-search.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-recommendations.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-taxonomy.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-graph.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-governance.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-doctor-ranking.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-doctor-appeals.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-rest.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-routes.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-admin.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-privacy.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-health.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-central-plan.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-plugin.php';
require_once SABRI_FILE26_DIR . 'includes/trait-file26-future-infra-trait.php';
require_once SABRI_FILE26_DIR . 'includes/trait-file26-future-rest-trait.php';
require_once SABRI_FILE26_DIR . 'includes/trait-file26-future-utility-trait.php';
require_once SABRI_FILE26_DIR . 'includes/trait-file26-future-search-core.php';
require_once SABRI_FILE26_DIR . 'includes/trait-file26-future-multimodal.php';
require_once SABRI_FILE26_DIR . 'includes/trait-file26-future-knowledge.php';
require_once SABRI_FILE26_DIR . 'includes/trait-file26-future-user-data.php';
require_once SABRI_FILE26_DIR . 'includes/trait-file26-future-user-discovery.php';
require_once SABRI_FILE26_DIR . 'includes/trait-file26-future-advanced.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-future-intelligence.php';

// Security-sensitive authorization filters must resolve to an explicit true value; truthy strings are never sufficient.
foreach (
	array(
		'sabri_file26_validate_ranking_approver',
		'sabri_file26_classification_writer_authorized',
		'sabri_file26_taxonomy_domain_owner_approved',
		'sabri_file26_classification_domain_reviewer_approved',
		'sabri_file26_graph_edge_owner_approved',
		'sabri_file26_allowed_evidence_url',
		'sabri_file26_allowed_external_resource_url',
	) as $sabri_file26_boolean_authorization_filter
) {
	add_filter(
		$sabri_file26_boolean_authorization_filter,
		static function ( $value ) {
			$security = new \Sabri\File26\Security();
			return $security->normalize_authorization( $value );
		},
		PHP_INT_MAX,
		1
	);
}
unset( $sabri_file26_boolean_authorization_filter );

// Until all visibility-changing mutations participate in a shared cache epoch, File 26 REST responses fail safe to no-store.
add_filter(
	'rest_post_dispatch',
	static function ( $response, $server, $request ) {
		$route = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( 0 === strpos( $route, '/sabri-search/v1/' ) && $response instanceof \WP_REST_Response ) {
			$response->header( 'Cache-Control', 'private, no-store' );
			if ( method_exists( $response, 'remove_header' ) ) {
				$response->remove_header( 'ETag' );
			}
		}
		return $response;
	},
	PHP_INT_MAX,
	3
);

register_activation_hook(
	__FILE__,
	static function () {
		$fail_activation = static function ( $message ) {
			\Sabri\File26\DB::deactivate();
			if ( function_exists( 'deactivate_plugins' ) ) {
				deactivate_plugins( plugin_basename( SABRI_FILE26_FILE ), true );
			}
			wp_die( esc_html( $message ) );
		};
		add_filter( 'cron_schedules', static function ( $schedules ) {
			$schedules['sabri_file26_monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Every 30 days — File 26' );
			return $schedules;
		} );
		add_rewrite_rule( '^search/?$', 'index.php?sabri_f26_route=search', 'top' );
		add_rewrite_rule( '^discover/?$', 'index.php?sabri_f26_route=discover', 'top' );
		add_rewrite_rule( '^topics/([^/]+)/?$', 'index.php?sabri_f26_route=topic&sabri_f26_term=$matches[1]', 'top' );
		if ( ! \Sabri\File26\DB::activate() ) { $fail_activation( __( 'File 26 activation failed because its database, settings, schedules or rewrite state could not be verified.', 'sabri-file26' ) ); }
		if ( ! \Sabri\File26\Roles::install( true ) ) { $fail_activation( __( 'File 26 activation failed because its separation-of-duties roles could not be verified.', 'sabri-file26' ) ); }
		if ( ! \Sabri\File26\Doctor_Appeals::install_schema() ) { $fail_activation( __( 'File 26 activation failed because the ranking-appeals schema could not be verified.', 'sabri-file26' ) ); }
	}
);
register_deactivation_hook( __FILE__, array( 'Sabri\\File26\\DB', 'deactivate' ) );

add_action( 'plugins_loaded', static function () { \Sabri\File26\Plugin::instance()->boot(); }, 5 );

function sabri_file26_register_connector( array $manifest ) { return \Sabri\File26\Plugin::instance()->connectors()->register( $manifest ); }
function sabri_file26_upsert_document( array $document ) { return \Sabri\File26\Plugin::instance()->indexer()->upsert( $document ); }
function sabri_file26_restrict_document( $connector, $domain, $object_id, $object_version, $reason = 'restricted' ) { return \Sabri\File26\Plugin::instance()->indexer()->restrict( (string) $connector, (string) $domain, (string) $object_id, (int) $object_version, (string) $reason ); }
function sabri_file26_tombstone_document( $connector, $domain, $object_id, $object_version, $reason = 'deleted' ) { return \Sabri\File26\Plugin::instance()->indexer()->tombstone( (string) $connector, (string) $domain, (string) $object_id, (int) $object_version, (string) $reason ); }
function sabri_file26_search( array $request ) { $plugin = \Sabri\File26\Plugin::instance(); $result = $plugin->search()->run( $request ); return $plugin->central_plan()->augment_search_result( $result, $request ); }
function sabri_file26_recommendations( array $request = array() ) { return \Sabri\File26\Plugin::instance()->recommendations()->get( $request ); }
function sabri_file26_ranking_constitution() { return \Sabri\File26\Plugin::instance()->central_plan()->ranking_constitution(); }
function sabri_file26_future_capabilities() { $future = isset( $GLOBALS['sabri_file26_future_intelligence'] ) ? $GLOBALS['sabri_file26_future_intelligence'] : null; return $future instanceof \Sabri\File26\Future_Intelligence ? $future->capability_manifest() : array(); }
function sabri_file26_recompute_doctor_ranking( $reason = 'manual' ) { return \Sabri\File26\Plugin::instance()->doctor_ranking()->recompute( (string) $reason ); }
