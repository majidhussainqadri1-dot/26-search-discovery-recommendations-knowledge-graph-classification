<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

final class Routes {
	private $search;
	private $recommendations;
	private $taxonomy;
	private $central_plan;

	public function __construct( Search $search, Recommendations $recommendations, Taxonomy $taxonomy, Central_Plan $central_plan ) {
		$this->search = $search;
		$this->recommendations = $recommendations;
		$this->taxonomy = $taxonomy;
		$this->central_plan = $central_plan;
	}

	public function register() {
		add_rewrite_rule( '^search/?$', 'index.php?sabri_f26_route=search', 'top' );
		add_rewrite_rule( '^discover/?$', 'index.php?sabri_f26_route=discover', 'top' );
		add_rewrite_rule( '^topics/([^/]+)/?$', 'index.php?sabri_f26_route=topic&sabri_f26_term=$matches[1]', 'top' );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_shortcode( 'sabri_search', array( $this, 'search_shortcode' ) );
		add_shortcode( 'sabri_discover', array( $this, 'discover_shortcode' ) );
		add_shortcode( 'sabri_topic', array( $this, 'topic_shortcode' ) );
		$routes = array(
			array( 'id' => 'file26-search', 'path' => '/search', 'owner' => 'File 26', 'version' => SABRI_FILE26_CONTRACT_VERSION, 'layout' => 'search', 'public' => true ),
			array( 'id' => 'file26-discover', 'path' => '/discover', 'owner' => 'File 26', 'version' => SABRI_FILE26_CONTRACT_VERSION, 'layout' => 'discover', 'public' => true ),
			array( 'id' => 'file26-topic', 'path' => '/topics/{concept}', 'owner' => 'File 26', 'version' => SABRI_FILE26_CONTRACT_VERSION, 'layout' => 'topic', 'public' => true ),
		);
		do_action( 'sabri_shell_register_routes', $routes, 'file26' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'sabri_f26_route';
		$vars[] = 'sabri_f26_term';
		return $vars;
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'sabri-file26', SABRI_FILE26_URL . 'assets/css/file26.css', array(), SABRI_FILE26_VERSION );
		wp_enqueue_script( 'sabri-file26', SABRI_FILE26_URL . 'assets/js/file26.js', array(), SABRI_FILE26_VERSION, true );
		wp_localize_script(
			'sabri-file26',
			'SabriFile26',
			array(
				'restUrl' => esc_url_raw( rest_url( 'sabri-search/v1/' ) ),
				'homeUrl' => esc_url_raw( home_url( '/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'loggedIn' => is_user_logged_in(),
				'strings' => array(
					'working' => __( 'Working…', 'sabri-file26' ),
					'done' => __( 'Saved', 'sabri-file26' ),
					'error' => __( 'The action could not be completed.', 'sabri-file26' ),
					'undo' => __( 'Undo', 'sabri-file26' ),
				),
			)
		);
	}

	public function template_redirect() {
		global $wp_query;
		$route = sanitize_key( (string) get_query_var( 'sabri_f26_route' ) );
		if ( ! $route ) { return; }
		if ( ! in_array( $route, array( 'search', 'discover', 'topic' ), true ) ) {
			if ( $wp_query ) { $wp_query->set_404(); }
			status_header( 404 );
			nocache_headers();
			return;
		}

		$resolved_term = null;
		if ( 'topic' === $route ) {
			$resolved = $this->resolve_topic_term( get_query_var( 'sabri_f26_term' ), true );
			if ( is_wp_error( $resolved ) ) {
				$error_data = $resolved->get_error_data();
				$error_status = is_array( $error_data ) && ! empty( $error_data['status'] ) ? max( 400, min( 599, (int) $error_data['status'] ) ) : 404;
				if ( 404 === $error_status && $wp_query ) { $wp_query->set_404(); }
				status_header( $error_status );
				nocache_headers();
				$this->enqueue_assets();
				get_header();
				echo '<main id="primary" class="sabri-f26-page" tabindex="-1"><div class="sabri-f26-state sabri-f26-state--error" role="alert">' . esc_html( $resolved->get_error_message() ) . '</div></main>';
				get_footer();
				exit;
			}
			if ( ! empty( $resolved['redirect_to'] ) ) {
				wp_safe_redirect( home_url( '/topics/' . rawurlencode( $resolved['redirect_to']['slug'] ) . '/' ), 301 );
				exit;
			}
			$resolved_term = $resolved['term'];
		}

		$this->enqueue_assets();
		status_header( 200 );
		nocache_headers();
		if ( 'topic' !== $route ) {
			add_filter( 'wp_robots', static function ( $robots ) {
				$robots['noindex'] = true;
				$robots['follow'] = true;
				return $robots;
			} );
		}
		get_header();
		echo '<main id="primary" class="sabri-f26-page" tabindex="-1">';
		if ( 'search' === $route ) {
			echo $this->search_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template contract escapes fields.
		} elseif ( 'discover' === $route ) {
			echo $this->discover_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template contract escapes fields.
		} else {
			echo $this->render_topic_term( $resolved_term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template contract escapes fields.
		}
		echo '</main>';
		get_footer();
		exit;
	}

	public function search_shortcode( $atts = array() ) {
		$this->enqueue_assets();
		$q = isset( $_GET['q'] ) && ! is_array( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filters = array(
			'entity_type' => $this->get_query_value( 'type', 'key' ),
			'country' => $this->get_query_value( 'country', 'text' ),
			'location' => $this->get_query_value( 'location', 'text' ),
			'availability' => $this->get_query_value( 'availability', 'key' ),
			'connector' => $this->get_query_value( 'connector', 'key' ),
			'domain' => $this->get_query_value( 'domain', 'key' ),
			'topic' => $this->get_query_value( 'topic', 'text' ),
			'sort' => $this->get_query_value( 'sort', 'key' ),
			'author' => $this->get_query_value( 'author', 'text' ),
			'language' => $this->get_query_value( 'language', 'text' ),
			'date_from' => $this->get_query_value( 'date_from', 'date' ),
			'date_to' => $this->get_query_value( 'date_to', 'date' ),
		);
		$filters = array_filter( $filters, static function ( $value ) { return '' !== $value; } );
		$request = array(
			'q' => $q,
			'locale' => determine_locale(),
			'limit' => 20,
			'cursor' => isset( $_GET['cursor'] ) && ! is_array( $_GET['cursor'] ) ? sanitize_text_field( wp_unslash( $_GET['cursor'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'filters' => $filters,
		);
		$data = $this->run_search_contract( $request );
		return $this->render( 'search', array( 'data' => $data, 'query' => $q, 'filters' => $filters ) );
	}

	public function discover_shortcode( $atts = array() ) {
		$this->enqueue_assets();
		$data = $this->recommendations->get( array( 'context' => 'discover', 'limit' => 12 ) );
		return $this->render( 'discover', array( 'data' => $data ) );
	}

	public function topic_shortcode( $atts = array() ) {
		$this->enqueue_assets();
		$atts = shortcode_atts( array( 'term' => '' ), $atts, 'sabri_topic' );
		$resolved = $this->resolve_topic_term( $atts['term'], false );
		if ( is_wp_error( $resolved ) ) {
			return '<div class="sabri-f26-state sabri-f26-state--error" role="alert">' . esc_html( $resolved->get_error_message() ) . '</div>';
		}
		return $this->render_topic_term( ! empty( $resolved['redirect_to'] ) ? $resolved['redirect_to'] : $resolved['term'] );
	}

	private function render_topic_term( array $term ) {
		$data = $this->run_search_contract(
			array(
				'q' => '',
				'locale' => $term['language'],
				'limit' => 20,
				'filters' => array( 'topic' => $term['term_uuid'] ),
			)
		);
		return $this->render( 'topic', array( 'data' => $data, 'term' => $term ) );
	}

	private function resolve_topic_term( $value, $allow_redirect ) {
		global $wpdb;
		$term = $this->taxonomy->get( $value );
		if ( ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'file26_topic_read_failed', 'Topic state could not be read safely.', array( 'status' => 503 ) );
		}
		if ( ! $term || ! in_array( $term['status'], array( 'active', 'merged' ), true ) ) {
			return new \WP_Error( 'file26_topic_not_found', 'This topic is unavailable.', array( 'status' => 404 ) );
		}
		if ( 'merged' === $term['status'] ) {
			if ( empty( $term['redirect_uuid'] ) ) {
				return new \WP_Error( 'file26_topic_redirect_missing', 'This merged topic has no current canonical destination.', array( 'status' => 404 ) );
			}
			$target = $this->taxonomy->get( $term['redirect_uuid'] );
			if ( ! empty( $wpdb->last_error ) ) {
				return new \WP_Error( 'file26_topic_read_failed', 'Topic redirect state could not be read safely.', array( 'status' => 503 ) );
			}
			if ( ! $target || 'active' !== $target['status'] ) {
				return new \WP_Error( 'file26_topic_not_found', 'This topic redirect is unavailable.', array( 'status' => 404 ) );
			}
			return array( 'term' => $term, 'redirect_to' => $target, 'http_redirect' => (bool) $allow_redirect );
		}
		return array( 'term' => $term, 'redirect_to' => null, 'http_redirect' => false );
	}

	private function run_search_contract( array $request ) {
		$result = $this->search->run( $request );
		return $this->central_plan->augment_search_result( $result, $request );
	}

	private function get_query_value( $name, $type ) {
		if ( ! isset( $_GET[ $name ] ) || is_array( $_GET[ $name ] ) ) { return ''; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$value = wp_unslash( $_GET[ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'key' === $type ) { return sanitize_key( $value ); }
		if ( 'date' === $type ) {
			$value = sanitize_text_field( $value );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
		}
		return function_exists( 'mb_substr' ) ? mb_substr( sanitize_text_field( $value ), 0, 191, 'UTF-8' ) : substr( sanitize_text_field( $value ), 0, 191 );
	}

	private function render( $template, array $vars ) {
		$file = SABRI_FILE26_DIR . 'templates/' . sanitize_file_name( $template ) . '.php';
		if ( ! file_exists( $file ) ) {
			return '<div class="sabri-f26-state sabri-f26-state--error" role="alert">' . esc_html__( 'This File 26 presentation template is unavailable.', 'sabri-file26' ) . '</div>';
		}
		ob_start();
		extract( $vars, EXTR_SKIP );
		include $file;
		return (string) ob_get_clean();
	}
}
