<?php
/**
 * Plugin Name: Sabri Search, Discovery and Knowledge Graph
 * Plugin URI:  https://sabrihomeopathy.com/
 * Description: Canonical connector, indexing, discovery, recommendation, taxonomy, classification and knowledge-graph foundation for the Sabri Social Homeopathy Platform.
 * Version:     0.4.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author:      Dr. Allamah Majid Hussain Sabri
 * Text Domain: sabri-search-discovery
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('SABRI_FILE26_VERSION', '0.4.0');
define('SABRI_FILE26_PLUGIN_FILE', __FILE__);
define('SABRI_FILE26_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SABRI_FILE26_REST_NAMESPACE', 'sabri-search/v1');

require_once SABRI_FILE26_PLUGIN_DIR . 'src/Autoloader.php';

\Sabri\File26\Autoloader::register();

register_activation_hook(
    __FILE__,
    static function (): void {
        \Sabri\File26\Plugin::activate();
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        \Sabri\File26\Plugin::deactivate();
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        \Sabri\File26\Plugin::instance()->boot();
    },
    5
);
