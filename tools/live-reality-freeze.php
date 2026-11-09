<?php
/**
 * File 26 read-only exact deployed-state freeze.
 *
 * Usage: wp eval-file wp-content/plugins/sabri-file26-search-discovery/tools/live-reality-freeze.php
 * Execute from authenticated WP-CLI shell, save the JSON privately.
 *
 * No secrets, credentials, user records, content or full database rows are emitted.
 * No writes, migrations, plugin changes, or system commands are performed.
 */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI || PHP_SAPI !== 'cli') {
    exit(2);
}
if (!function_exists('get_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
global $wpdb;
$plugin_slug = 'sabri-file26-search-discovery/file-26-search-discovery.php';
$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug;
$plugin_root = dirname($plugin_file);
$read_error = array();
$files = array();
$source_hash = null;
$file_count = 0;
if (is_file($plugin_file) && is_dir($plugin_root)) {
    $walk = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($plugin_root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($walk as $path => $item) {
        if ($item->isLink() || !$item->isFile()) {
            $read_error[] = 'source_link_or_non_file_ignored';
            continue;
        }
        $rel = substr((string)$path, strlen($plugin_root) + 1);
        $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
        if (!preg_match('/^[A-Za-z0-9_\.\/\-]+$/D', $rel) || $file_count >= 20000) {
            $read_error[] = 'source_inventory_limit_or_unsafe_path';
            break;
        }
        $checksum = hash_file('sha256', $path);
        if (!is_string($checksum) || strlen($checksum) !== 64) {
            $read_error[] = 'source_file_read_failure';
            break;
        }
        $files[$rel] = $checksum;
        ++$file_count;
    }
    ksort($files, SORT_STRING);
    $h = hash_init('sha256');
    foreach ($files as $relative => $hash) {
        hash_update($h, $relative . "\0" . $hash . "\n");
    }
    $source_hash = hash_final($h);
} else {
    $read_error[] = 'file26_plugin_root_unavailable';
}
$all_plugins = get_plugins();
$active = (array)get_option('active_plugins', array());
$network_active = is_multisite() ? (array)get_site_option('active_sitewide_plugins', array()) : array();
$installed = array_key_exists($plugin_slug, $all_plugins);
$plugin_version = $installed ? (string)$all_plugins[$plugin_slug]['Version'] : null;

$tables = array();
$prefix = $wpdb->prefix;
foreach (array('documents','nodes','edges','classifications','tombstones','jobs','connectors','audit','profiles') as $short) {
    $table = $prefix . 'f26_' . $short;
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
        $tables[$short] = array('status' => 'invalid_identifier');
        continue;
    }
    $wpdb->last_error = '';
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    if ($exists !== $table || (string)$wpdb->last_error !== '') {
        $tables[$short] = array('status' => $wpdb->last_error ? 'lookup_error' : 'absent');
        continue;
    }
    $wpdb->last_error = '';
    $columns = $wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A);
    if (!is_array($columns) || $wpdb->last_error) {
        $tables[$short] = array('status' => 'columns_unreadable');
        continue;
    }
    $types = array();
    foreach ($columns as $column) {
        $types[(string)$column['Field']] = (string)$column['Type'];
    }
    $wpdb->last_error = '';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
    $tables[$short] = array(
        'status' => $wpdb->last_error ? 'count_unreadable' : 'present',
        'column_types' => $types,
        'row_count' => $wpdb->last_error ? null : (int)$count
    );
}
$settings = get_option('sabri_file26_settings', array());
$settings = is_array($settings) ? $settings : array();
$safe_settings = array();
foreach (array('activated','public_search_enabled','personalization_enabled','telemetry_enabled','policy_version','doctor_ranking_policy_version') as $key) {
    if (isset($settings[$key]) && (is_scalar($settings[$key]) || is_null($settings[$key]))) {
        $safe_settings[$key] = $settings[$key];
    }
}
$wpdb->last_error = '';
$db_version = $wpdb->get_var('SELECT VERSION()');
if ($wpdb->last_error) {
    $db_version = null;
    $read_error[] = 'database_version_unreadable';
}
$option_prefix = $wpdb->esc_like('sabri_f26_shadow_') . '%';
$wpdb->last_error = '';
$shadow_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE %s", $option_prefix));
if ($wpdb->last_error) {
    $shadow_count = null;
    $read_error[] = 'shadow_provenance_count_unreadable';
}
$state = array(
    'audit_contract' => 'sabri.file26.live-reality-freeze.v1',
    'observed_at_utc' => gmdate('c'),
    'wordpress_version' => get_bloginfo('version'),
    'php_version' => PHP_VERSION,
    'database_version' => $db_version,
    'wordpress_multisite' => is_multisite(),
    'file26' => array(
        'plugin_slug' => $plugin_slug,
        'plugin_installed' => $installed,
        'plugin_active' => in_array($plugin_slug, $active, true) || array_key_exists($plugin_slug, $network_active),
        'declared_plugin_version' => $plugin_version,
        'declared_schema_option' => get_option('sabri_file26_schema_version', null),
        'declared_contract_version' => defined('SABRI_FILE26_CONTRACT_VERSION') ? SABRI_FILE26_CONTRACT_VERSION : null,
        'doctor_appeals_schema_option' => class_exists('Sabri\\File26\\Doctor_Appeals')
            ? get_option(\Sabri\File26\Doctor_Appeals::OPTION_SCHEMA, null) : null,
        'owner_runtime_readiness' => class_exists('Sabri\\File26\\Plugin') && defined('SABRI_FILE26_VERSION')
            ? \Sabri\File26\Plugin::instance()->owner_contracts()->readiness() : null,
        'entry_file_sha256' => is_file($plugin_file) ? hash_file('sha256', $plugin_file) : null,
        'all_plugin_file_count' => $file_count,
        'all_plugin_files_sha256' => $source_hash,
        'tree_inventory_complete' => empty($read_error),
        'shadow_provenance_option_count' => $shadow_count,
        'safe_settings' => $safe_settings,
        'tables' => $tables,
    ),
    'read_errors' => array_values(array_unique($read_error)),
    'live_verified_end_to_end' => false,
);
echo wp_json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
