<?php
/**
 * Plugin Name: File 26 — Search, Discovery, Recommendations, Knowledge Graph and Classification
 * Plugin URI: https://sabrihomeopathy.com/
 * Description: Federated, privacy-safe search, discovery, recommendations, taxonomy, knowledge graph and content-classification infrastructure for the Sabri Social Homeopathy Platform.
 * Version: 1.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * Text Domain: sabri-file26
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'SABRI_FILE26_VERSION', '1.2.0' );
define( 'SABRI_FILE26_SCHEMA_VERSION', '1.0.0' );
define( 'SABRI_FILE26_CONTRACT_VERSION', '1.2' );
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
require_once SABRI_FILE26_DIR . 'includes/class-file26-shadow-reindex.php';
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
require_once SABRI_FILE26_DIR . 'includes/class-file26-evaluation.php';
require_once SABRI_FILE26_DIR . 'includes/class-file26-plugin.php';

register_activation_hook( __FILE__, static function () {
	$result = \Sabri\File26\DB::activate();
	if ( ! is_wp_error( $result ) ) { $result = \Sabri\File26\Roles::install( true ); }
	if ( ! is_wp_error( $result ) ) { $result = \Sabri\File26\Doctor_Appeals::install_schema(); }
	if ( is_wp_error( $result ) ) {
		\Sabri\File26\DB::deactivate();
		\Sabri\File26\Roles::uninstall();
		\Sabri\File26\DB::update_settings( array( 'activated'=>false, 'public_search_enabled'=>false, 'personalization_enabled'=>false ) );
		update_option( 'sabri_file26_last_activation_failure', array( 'at'=>\Sabri\File26\DB::now(), 'code'=>$result->get_error_code() ), false );
		if ( function_exists( 'deactivate_plugins' ) ) { deactivate_plugins( plugin_basename( __FILE__ ), true ); }
		wp_die( esc_html( $result->get_error_message() ), esc_html__( 'File 26 activation failed safely', 'sabri-file26' ), array( 'back_link'=>true ) );
	}
	delete_option( 'sabri_file26_last_activation_failure' );
} );
register_deactivation_hook( __FILE__, array( 'Sabri\\File26\\DB', 'deactivate' ) );

add_action( 'plugins_loaded', static function () {
	$plugin = \Sabri\File26\Plugin::instance();
	$plugin->boot();
	\Sabri\File26\Shadow_Reindex::instance( $plugin->connectors(), new \Sabri\File26\Normalizer(), new \Sabri\File26\Security(), $plugin->indexer() )->boot();
	\Sabri\File26\Evaluation::instance()->boot();
}, 5 );

function sabri_file26_runtime() { $plugin=\Sabri\File26\Plugin::instance(); return $plugin->ready()?$plugin:$plugin->not_ready_error(); }
function sabri_file26_register_connector( array $manifest ) { $plugin=sabri_file26_runtime();return is_wp_error($plugin)?$plugin:$plugin->connectors()->register($manifest); }
function sabri_file26_upsert_document( array $document ) { $plugin=sabri_file26_runtime();return is_wp_error($plugin)?$plugin:$plugin->indexer()->upsert($document); }
function sabri_file26_restrict_document( $connector,$domain,$object_id,$object_version,$reason='restricted' ) { $plugin=sabri_file26_runtime();return is_wp_error($plugin)?$plugin:$plugin->indexer()->restrict((string)$connector,(string)$domain,(string)$object_id,(int)$object_version,(string)$reason); }
function sabri_file26_tombstone_document( $connector,$domain,$object_id,$object_version,$reason='deleted' ) { $plugin=sabri_file26_runtime();return is_wp_error($plugin)?$plugin:$plugin->indexer()->tombstone((string)$connector,(string)$domain,(string)$object_id,(int)$object_version,(string)$reason); }
function sabri_file26_search( array $request ) { $plugin=sabri_file26_runtime();if(is_wp_error($plugin)){return$plugin;}$result=$plugin->search()->run($request);return $plugin->central_plan()->augment_search_result($result,$request); }
function sabri_file26_recommendations( array $request=array() ) { $plugin=sabri_file26_runtime();return is_wp_error($plugin)?$plugin:$plugin->recommendations()->get($request); }
function sabri_file26_ranking_constitution() { $plugin=sabri_file26_runtime();return is_wp_error($plugin)?$plugin:$plugin->central_plan()->ranking_constitution(); }
function sabri_file26_record_relevance_evaluation( array $record ) { $plugin=sabri_file26_runtime();return is_wp_error($plugin)?$plugin:\Sabri\File26\Evaluation::instance()->record_evaluation($record); }
function sabri_file26_stage_search_experiment( array $record ) { $plugin=sabri_file26_runtime();return is_wp_error($plugin)?$plugin:\Sabri\File26\Evaluation::instance()->stage_experiment($record); }
function sabri_file26_enqueue_shadow_reindex( $connector, array $scope=array() ) { $plugin=sabri_file26_runtime();if(is_wp_error($plugin)){return$plugin;}$service=\Sabri\File26\Shadow_Reindex::instance();return $service?$service->enqueue($connector,$scope):new \WP_Error('file26_shadow_service_unavailable','Governed shadow reindex service is unavailable.'); }
function sabri_file26_recompute_doctor_ranking( $reason='manual' ) { $plugin=sabri_file26_runtime();return is_wp_error($plugin)?$plugin:$plugin->doctor_ranking()->recompute((string)$reason); }
