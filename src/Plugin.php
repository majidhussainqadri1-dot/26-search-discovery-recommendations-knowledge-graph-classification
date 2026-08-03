<?php

declare(strict_types=1);

namespace Sabri\File26;

use Sabri\File26\Registry\ConnectorRegistry;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class Plugin
{
    private static ?self $instance = null;
    private ConnectorRegistry $registry;
    private bool $booted = false;
    private ?string $bootErrorCode = null;

    private function __construct()
    {
        $this->registry = new ConnectorRegistry();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        try {
            /**
             * Companion domain owners register versioned read/index connectors here.
             * Registration is synchronous; an invalid contract disables File 26
             * runtime work without taking the whole WordPress site down.
             */
            do_action('sabri_file26_register_connectors', $this->registry);
            do_action('sabri_file26_connectors_registered', $this->registry);
        } catch (Throwable $exception) {
            unset($exception);
            $this->registry = new ConnectorRegistry();
            $this->bootErrorCode = 'connector-registration-failed';
        }

        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function connectors(): ConnectorRegistry
    {
        return $this->registry;
    }

    public function isOperationallyAvailable(): bool
    {
        return $this->bootErrorCode === null;
    }

    public function registerRestRoutes(): void
    {
        register_rest_route(
            SABRI_FILE26_REST_NAMESPACE,
            '/health',
            [
                'methods' => 'GET',
                'callback' => [$this, 'healthResponse'],
                'permission_callback' => [$this, 'canViewOperationalHealth'],
            ]
        );
    }

    public function canViewOperationalHealth(WP_REST_Request $request): bool|WP_Error
    {
        unset($request);

        if (! is_user_logged_in() || ! current_user_can('manage_options')) {
            return new WP_Error(
                'sabri_file26_forbidden',
                __('You are not authorized to view search operations health.', 'sabri-search-discovery'),
                ['status' => 403]
            );
        }

        return true;
    }

    public function healthResponse(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        return new WP_REST_Response(
            [
                'module' => 'file-26',
                'version' => SABRI_FILE26_VERSION,
                'stage' => 'phase-26b-shadow-index',
                'status' => $this->isOperationallyAvailable() ? 'shadow-only' : 'degraded',
                'error_code' => $this->bootErrorCode,
                'connectors' => $this->registry->publicSummary(),
            ],
            $this->isOperationallyAvailable() ? 200 : 503
        );
    }

    public static function activate(): void
    {
        if (! function_exists('deactivate_plugins') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            deactivate_plugins(plugin_basename(SABRI_FILE26_PLUGIN_FILE));
            wp_die(
                esc_html__('File 26 requires PHP 8.1 or newer.', 'sabri-search-discovery'),
                esc_html__('Activation blocked', 'sabri-search-discovery'),
                ['back_link' => true]
            );
        }

        global $wp_version;
        if (isset($wp_version) && version_compare((string) $wp_version, '6.0', '<')) {
            deactivate_plugins(plugin_basename(SABRI_FILE26_PLUGIN_FILE));
            wp_die(
                esc_html__('File 26 requires WordPress 6.0 or newer.', 'sabri-search-discovery'),
                esc_html__('Activation blocked', 'sabri-search-discovery'),
                ['back_link' => true]
            );
        }

        update_option(
            'sabri_file26_runtime_state',
            [
                'version' => SABRI_FILE26_VERSION,
                'stage' => 'phase-26b-shadow-index',
                'activated_at' => gmdate('c'),
            ],
            false
        );
    }

    public static function deactivate(): void
    {
        // Non-destructive by design. Index and lifecycle cleanup require an
        // explicit, authorized reconciliation workflow rather than deactivation.
    }
}
