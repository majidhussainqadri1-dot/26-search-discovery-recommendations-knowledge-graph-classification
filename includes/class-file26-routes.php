<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Routes {
	private $search;
	private $recommendations;
	private $taxonomy;

	public function __construct( Search $search, Recommendations $recommendations, Taxonomy $taxonomy ) {
		$this->search = $search;
		$this->recommendations = $recommendations;
		$this->taxonomy = $taxonomy;
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
		wp_localize_script( 'sabri-file26', 'SabriFile26', array(
			'restUrl' => esc_url_raw( rest_url( 'sabri-search/v1/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'loggedIn' => is_user_logged_in(),
			'strings' => array(
				'working' => __( 'Working…', 'sabri-file26' ),
				'done' => __( 'Saved', 'sabri-file26' ),
				'error' => __( 'The action could not be completed.', 'sabri-file26' ),
				'undo' => __( 'Undo', 'sabri-file26' ),
			),
		) );
	}

	public function template_redirect() {
		$route = get_query_var( 'sabri_f26_route' );
		if ( ! $route ) {
			return;
		}
		$this->enqueue_assets();
		status_header( 200 );
		if ( 'topic' !== $route ) {
			nocache_headers();
			add_filter( 'wp_robots', static function ( $robots ) {
				$robots['noindex'] = true;
				$robots['follow'] = true;
				return $robots;
			} );
		} else {
			header( 'Cache-Control: public, max-age=300, stale-while-revalidate=600' );
		}
		get_header();
		echo '<main id="primary" class="sabri-f26-page" tabindex="-1">';
		if ( 'search' === $route ) {
			echo $this->search_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'discover' === $route ) {
			echo $this->discover_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'topic' === $route ) {
			echo $this->topic_shortcode( array( 'term' => get_query_var( 'sabri_f26_term' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</main>';
		get_footer();
		exit;
	}

	public function search_shortcode( $atts = array() ) {
		$this->enqueue_assets();
		$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
			'cursor' => isset( $_GET['cursor'] ) ? sanitize_text_field( wp_unslash( $_GET['cursor'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'filters' => $filters,
		);
		$data = $this->search->run( $request );
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
		$term = $this->taxonomy->get( $atts['term'] );
		if ( ! $term || ! in_array( $term['status'], array( 'active', 'merged' ), true ) ) {
			return '<div class="sabri-f26-state" role="status">' . esc_html__( 'This topic is unavailable.', 'sabri-file26' ) . '</div>';
		}
		if ( 'merged' === $term['status'] && $term['redirect_uuid'] ) {
			$target = $this->taxonomy->get( $term['redirect_uuid'] );
			if ( $target && get_query_var( 'sabri_f26_route' ) ) {
				wp_safe_redirect( home_url( '/topics/' . $target['slug'] . '/' ), 301 );
				exit;
			}
			if ( $target ) {
				$term = $target;
			}
		}
		$data = $this->search->run( array(
			'q' => '', 'locale' => $term['language'], 'limit' => 20,
			'filters' => array( 'topic' => $term['term_uuid'] ),
		) );
		return $this->render( 'topic', array( 'data' => $data, 'term' => $term ) );
	}

	private function get_query_value( $name, $type ) {
		if ( ! isset( $_GET[ $name ] ) || is_array( $_GET[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}
		$value = wp_unslash( $_GET[ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'key' === $type ) {
			return sanitize_key( $value );
		}
		if ( 'date' === $type ) {
			$value = sanitize_text_field( $value );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
		}
		return substr( sanitize_text_field( $value ), 0, 191 );
	}

	private function render( $template, array $vars ) {
		$file = SABRI_FILE26_DIR . 'templates/' . sanitize_file_name( $template ) . '.php';
		if ( ! file_exists( $file ) ) {
			return '';
		}
		ob_start();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $file;
		return (string) ob_get_clean();
	}
}
