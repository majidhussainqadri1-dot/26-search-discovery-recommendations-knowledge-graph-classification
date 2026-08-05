<?php
/**
 * Plugin Name: File 26 — Search, Discovery, Recommendations, Knowledge Graph and Classification
 * Plugin URI: https://sabrihomeopathy.com/
 * Description: Federated, privacy-safe search, discovery, recommendations, taxonomy, knowledge graph and content-classification infrastructure for the Sabri Social Homeopathy Platform.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * Text Domain: sabri-file26
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'SABRI_FILE26_VERSION', '1.1.0' );
define( 'SABRI_FILE26_SCHEMA_VERSION', '1.0.0' );
define( 'SABRI_FILE26_CONTRACT_VERSION', '1.1' );
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
require_once SABRI_FILE26_DIR . 'includes/class-file26-plugin.php';

register_activation_hook( __FILE__, static function () {
	\Sabri\File26\DB::activate();
	\Sabri\File26\Roles::install( true );
	\Sabri\File26\Doctor_Appeals::install_schema();
} );
register_deactivation_hook( __FILE__, array( 'Sabri\\File26\\DB', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\Sabri\File26\Plugin::instance()->boot();
	},
	5
);

/**
 * Documented compatibility contracts. These wrappers are intentionally thin:
 * they never bypass connector validation, authorization or lifecycle rules.
 */
function sabri_file26_register_connector( array $manifest ) {
	return \Sabri\File26\Plugin::instance()->connectors()->register( $manifest );
}

function sabri_file26_upsert_document( array $document ) {
	return \Sabri\File26\Plugin::instance()->indexer()->upsert( $document );
}

function sabri_file26_restrict_document( $connector, $domain, $object_id, $object_version, $reason = 'restricted' ) {
	return \Sabri\File26\Plugin::instance()->indexer()->restrict(
		(string) $connector,
		(string) $domain,
		(string) $object_id,
		(int) $object_version,
		(string) $reason
	);
}

function sabri_file26_tombstone_document( $connector, $domain, $object_id, $object_version, $reason = 'deleted' ) {
	return \Sabri\File26\Plugin::instance()->indexer()->tombstone(
		(string) $connector,
		(string) $domain,
		(string) $object_id,
		(int) $object_version,
		(string) $reason
	);
}

function sabri_file26_search( array $request ) {
	return \Sabri\File26\Plugin::instance()->search()->run( $request );
}

function sabri_file26_recommendations( array $request = array() ) {
	return \Sabri\File26\Plugin::instance()->recommendations()->get( $request );
}

/**
 * Recompute the explainable global verified-doctor ranking projection.
 * Manual calls remain capability-gated inside the service.
 */
function sabri_file26_recompute_doctor_ranking( $reason = 'manual' ) {
	return \Sabri\File26\Plugin::instance()->doctor_ranking()->recompute( (string) $reason );
}
