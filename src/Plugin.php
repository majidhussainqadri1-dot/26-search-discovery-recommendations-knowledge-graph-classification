<?php

declare(strict_types=1);

namespace Sabri\File26;

use Sabri\File26\Operations\WordPressCliAdapter;
use Sabri\File26\Operations\WordPressRuntime;
use Sabri\File26\Operations\WordPressWorkerScheduler;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Search\PersistentQueryService;
use Sabri\File26\Storage\SchemaManager;
use Sabri\File26\Support\InvariantViolation;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use wpdb;

final class Plugin
{
    private static ?self $instance = null;
    private ConnectorRegistry $registry;
    private bool $booted = false;
    private ?string $bootErrorCode = null;
    private ?WordPressRuntime $runtime = null;

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

        if ((string) get_option('sabri_file26_schema_version', '') !== SchemaManager::SCHEMA_VERSION) {
            $this->bootErrorCode = 'schema-version-mismatch';
        } else {
            try {
                do_action('sabri_file26_register_connectors', $this->registry);
                do_action('sabri_file26_connectors_registered', $this->registry);
            } catch (Throwable $exception) {
                unset($exception);
                $this->registry = new ConnectorRegistry();
                $this->bootErrorCode = 'connector-registration-failed';
            }

            if ($this->bootErrorCode === null) {
                try {
                    global $wpdb;
                    if (! $wpdb instanceof wpdb) {
                        throw new InvariantViolation('WordPress database access is unavailable.');
                    }

                    $secret = function_exists('wp_salt')
                        ? wp_salt('auth')
                        : (defined('AUTH_SALT') ? (string) AUTH_SALT : '');
                    if (strlen($secret) < 32) {
                        throw new InvariantViolation('A sufficiently strong WordPress authentication salt is required.');
                    }

                    $this->runtime = new WordPressRuntime($wpdb, $this->registry, $secret);
                    $this->runtime->register();
                    (new WordPressCliAdapter($this->runtime))->register();
                    do_action('sabri_file26_runtime_ready', $this);
                } catch (Throwable $exception) {
                    unset($exception);
                    $this->runtime = null;
                    $this->bootErrorCode = 'runtime-initialization-failed';
                }
            }
        }

        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function connectors(): ConnectorRegistry
    {
        return $this->registry;
    }

    public function internalQueryService(): PersistentQueryService
    {
        if ($this->runtime === null || ! $this->isOperationallyAvailable()) {
            throw new InvariantViolation('File 26 internal query service is unavailable.');
        }

        return $this->runtime->queryService();
    }

    public function isOperationallyAvailable(): bool
    {
        return $this->bootErrorCode === null && $this->runtime !== null;
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

        register_rest_route(
            SABRI_FILE26_REST_NAMESPACE,
            '/operations',
            [
                'methods' => 'GET',
                'callback' => [$this, 'operationsResponse'],
                'permission_callback' => [$this, 'canViewOperationalHealth'],
            ]
        );

        register_rest_route(
            SABRI_FILE26_REST_NAMESPACE,
            '/operations/dead-letter/replay',
            [
                'methods' => 'POST',
                'callback' => [$this, 'replayDeadLetterResponse'],
                'permission_callback' => [$this, 'canViewOperationalHealth'],
            ]
        );

        register_rest_route(
            SABRI_FILE26_REST_NAMESPACE,
            '/operations/connectors/(?P<connector>[a-z][a-z0-9.-]{2,99})/probe',
            [
                'methods' => 'POST',
                'callback' => [$this, 'probeConnectorResponse'],
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
                __('You are not authorized to view or operate search infrastructure.', 'sabri-search-discovery'),
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
                'schema_version' => SchemaManager::SCHEMA_VERSION,
                'stage' => 'phase-26d-query-operations',
                'status' => $this->isOperationallyAvailable() ? 'internal-query-and-operations-only' : 'degraded',
                'error_code' => $this->bootErrorCode,
                'connectors' => $this->registry->publicSummary(),
                'public_query_route_enabled' => false,
            ],
            $this->isOperationallyAvailable() ? 200 : 503
        );
    }

    public function operationsResponse(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        if ($this->runtime === null) {
            return $this->runtimeUnavailableError();
        }

        return new WP_REST_Response($this->runtime->diagnostics(), 200);
    }

    public function replayDeadLetterResponse(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($this->runtime === null) {
            return $this->runtimeUnavailableError();
        }

        $jobId = $request->get_param('job_id');
        $errorCode = $request->get_param('expected_error_code');
        if (! is_string($jobId) || ! is_string($errorCode)) {
            return new WP_Error(
                'sabri_file26_invalid_replay_request',
                __('Both job_id and expected_error_code are required.', 'sabri-search-discovery'),
                ['status' => 400]
            );
        }

        try {
            return new WP_REST_Response($this->runtime->replayDeadLetter($jobId, $errorCode), 200);
        } catch (InvariantViolation $exception) {
            return new WP_Error('sabri_file26_replay_rejected', $exception->getMessage(), ['status' => 409]);
        }
    }

    public function probeConnectorResponse(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($this->runtime === null) {
            return $this->runtimeUnavailableError();
        }

        $connector = $request->get_param('connector');
        $batch = $request->get_param('batch_limit');
        $pages = $request->get_param('maximum_pages');
        if (! is_string($connector)) {
            return new WP_Error('sabri_file26_invalid_probe_request', __('Connector key is required.', 'sabri-search-discovery'), ['status' => 400]);
        }
        $batch = is_int($batch) ? $batch : 50;
        $pages = is_int($pages) ? $pages : 50;

        try {
            return new WP_REST_Response($this->runtime->probeConnector($connector, $batch, $pages), 200);
        } catch (InvariantViolation $exception) {
            return new WP_Error('sabri_file26_probe_rejected', $exception->getMessage(), ['status' => 409]);
        }
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

        try {
            global $wpdb;
            if (! $wpdb instanceof wpdb) {
                throw new \RuntimeException('WordPress database access is unavailable.');
            }

            SchemaManager::install($wpdb);

            update_option(
                'sabri_file26_runtime_state',
                [
                    'version' => SABRI_FILE26_VERSION,
                    'schema_version' => SchemaManager::SCHEMA_VERSION,
                    'stage' => 'phase-26d-query-operations',
                    'activated_at' => gmdate('c'),
                ],
                false
            );
        } catch (Throwable $exception) {
            unset($exception);
            deactivate_plugins(plugin_basename(SABRI_FILE26_PLUGIN_FILE));
            wp_die(
                esc_html__('File 26 could not install or upgrade its persistent shadow schema safely. No public search feature was enabled.', 'sabri-search-discovery'),
                esc_html__('Activation blocked', 'sabri-search-discovery'),
                ['back_link' => true]
            );
        }
    }

    public static function deactivate(): void
    {
        if (function_exists('wp_next_scheduled') && function_exists('wp_unschedule_event')) {
            WordPressWorkerScheduler::unschedule();
        }
    }

    private function runtimeUnavailableError(): WP_Error
    {
        return new WP_Error(
            'sabri_file26_runtime_unavailable',
            __('File 26 operations runtime is unavailable.', 'sabri-search-discovery'),
            ['status' => 503]
        );
    }
}
