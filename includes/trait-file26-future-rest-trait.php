<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

trait Future_Rest_Trait {
	public function capability_manifest() {
		return array(
			'conversational-grounded-search' => array( 'id' => 'F26-FUT-01', 'methods' => 'POST', 'auth' => 'public', 'handler' => 'conversational_grounded_search' ),
			'query-planner' => array( 'id' => 'F26-FUT-02', 'methods' => 'POST', 'auth' => 'public', 'handler' => 'query_planner' ),
			'cross-language-search' => array( 'id' => 'F26-FUT-03', 'methods' => 'POST', 'auth' => 'public', 'handler' => 'cross_language_search' ),
			'semantic-rerank' => array( 'id' => 'F26-FUT-04', 'methods' => 'POST', 'auth' => 'public', 'handler' => 'semantic_rerank' ),
			'multimodal-search' => array( 'id' => 'F26-FUT-05', 'methods' => 'POST', 'auth' => 'member', 'handler' => 'multimodal_search' ),
			'voice-search' => array( 'id' => 'F26-FUT-06', 'methods' => 'POST', 'auth' => 'member', 'handler' => 'voice_search' ),
			'segment-search' => array( 'id' => 'F26-FUT-07', 'methods' => 'GET,POST', 'auth' => 'public', 'handler' => 'segment_search' ),
			'find-similar' => array( 'id' => 'F26-FUT-08', 'methods' => 'GET,POST', 'auth' => 'public', 'handler' => 'find_similar' ),
			'research-search' => array( 'id' => 'F26-FUT-09', 'methods' => 'GET,POST', 'auth' => 'public', 'handler' => 'research_search' ),
			'result-clusters' => array( 'id' => 'F26-FUT-10', 'methods' => 'POST', 'auth' => 'public', 'handler' => 'result_clusters' ),
			'graph-path' => array( 'id' => 'F26-FUT-11', 'methods' => 'GET', 'auth' => 'public', 'handler' => 'graph_path' ),
			'evidence-map' => array( 'id' => 'F26-FUT-12', 'methods' => 'GET', 'auth' => 'public', 'handler' => 'evidence_map' ),
			'disambiguate' => array( 'id' => 'F26-FUT-13', 'methods' => 'GET', 'auth' => 'public', 'handler' => 'disambiguate' ),
			'historical-search' => array( 'id' => 'F26-FUT-14', 'methods' => 'GET,POST', 'auth' => 'public', 'handler' => 'historical_search' ),
			'research-trails' => array( 'id' => 'F26-FUT-15', 'methods' => 'GET,POST,DELETE', 'auth' => 'member', 'handler' => 'research_trails' ),
			'saved-search-alerts' => array( 'id' => 'F26-FUT-16', 'methods' => 'GET,POST,DELETE', 'auth' => 'member', 'handler' => 'saved_search_alerts' ),
			'search-history' => array( 'id' => 'F26-FUT-17', 'methods' => 'GET,POST,DELETE', 'auth' => 'member', 'handler' => 'search_history' ),
			'recommendation-transparency' => array( 'id' => 'F26-FUT-18', 'methods' => 'GET,POST', 'auth' => 'member', 'handler' => 'recommendation_transparency' ),
			'discovery-breadth' => array( 'id' => 'F26-FUT-19', 'methods' => 'GET,POST', 'auth' => 'member', 'handler' => 'discovery_breadth' ),
			'geo-availability' => array( 'id' => 'F26-FUT-20', 'methods' => 'GET,POST', 'auth' => 'public', 'handler' => 'geo_availability' ),
			'search-modes' => array( 'id' => 'F26-FUT-21', 'methods' => 'GET,POST', 'auth' => 'public', 'handler' => 'search_modes' ),
			'private-search-vault' => array( 'id' => 'F26-FUT-22', 'methods' => 'POST', 'auth' => 'step_up', 'handler' => 'private_search_vault' ),
			'external-evidence' => array( 'id' => 'F26-FUT-23', 'methods' => 'POST', 'auth' => 'public', 'handler' => 'external_evidence' ),
			'relevance-lab' => array( 'id' => 'F26-FUT-24', 'methods' => 'GET,POST', 'auth' => 'audit', 'handler' => 'relevance_lab' ),
		);
	}

	public function register_routes() {
		foreach ( $this->capability_manifest() as $slug => $definition ) {
			register_rest_route( self::REST_NAMESPACE, '/future/' . $slug, array( 'methods' => $definition['methods'], 'callback' => array( $this, 'dispatch' ), 'permission_callback' => array( $this, 'permission' ) ) );
		}
	}

	public function permission( \WP_REST_Request $request ) {
		$slug = $this->route_slug( $request );
		$manifest = $this->capability_manifest();
		if ( ! isset( $manifest[ $slug ] ) ) { return new \WP_Error( 'file26_future_unknown_capability', 'Unknown Future Search Intelligence capability.', array( 'status' => 404 ) ); }
		$auth = $manifest[ $slug ]['auth'];
		if ( 'public' === $auth ) { return true; }
		if ( ! is_user_logged_in() ) { return new \WP_Error( 'file26_auth_required', 'Authentication is required.', array( 'status' => 401 ) ); }
		$audience = $this->security->audience();
		if ( empty( $audience['valid'] ) || ! empty( $audience['suspended'] ) ) { return new \WP_Error( 'file26_membership_assertion_invalid', 'Current membership/identity assertions are required.', array( 'status' => 403 ) ); }
		if ( 'member' === $auth ) { return true; }
		if ( 'audit' === $auth ) { return $this->security->can_audit() ? true : new \WP_Error( 'file26_forbidden', 'Search audit capability is required.', array( 'status' => 403 ) ); }
		if ( 'step_up' === $auth ) {
			$fallback = $this->security->normalize_authorization( apply_filters( 'sabri_file26_future_step_up_verified', false, $slug, get_current_user_id() ) );
			$verified = $this->security->require_step_up( 'future_' . $slug ) || $fallback;
			return $verified ? true : new \WP_Error( 'file26_step_up_required', 'Recent step-up verification is required.', array( 'status' => 403 ) );
		}
		return new \WP_Error( 'file26_forbidden', 'The capability is not available for this request.', array( 'status' => 403 ) );
	}

	public function dispatch( \WP_REST_Request $request ) {
		$slug = $this->route_slug( $request );
		$manifest = $this->capability_manifest();
		if ( ! isset( $manifest[ $slug ] ) || ! method_exists( $this, $manifest[ $slug ]['handler'] ) ) { return new \WP_Error( 'file26_future_unknown_capability', 'Unknown Future Search Intelligence capability.', array( 'status' => 404 ) ); }
		if ( ! $this->security->rate_limit( 'future|' . $slug . '|' . $this->security->client_bucket(), 'audit' === $manifest[ $slug ]['auth'] ? 20 : 40, 60 ) ) { return new \WP_Error( 'file26_rate_limited', 'Too many Future Search Intelligence requests. Please retry shortly.', array( 'status' => 429 ) ); }

		$params = $this->params( $request );
		if ( 'external-evidence' === $slug ) {
			$q = $this->query( $params );
			if ( $this->sensitive_query( $q ) || $this->autonomous_clinical_intent( $q ) ) { return new \WP_Error( 'file26_external_query_not_eligible', 'This query is not eligible for an external evidence request.', array( 'status' => 400 ) ); }
			if ( ! $this->security->normalize_authorization( isset( $params['external_consent'] ) ? $params['external_consent'] : false ) ) { return new \WP_Error( 'file26_external_consent_required', 'Explicit per-request consent is required for external evidence.', array( 'status' => 400 ) ); }
		}

		$handler = $manifest[ $slug ]['handler'];
		$result = $this->{$handler}( $request );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( is_array( $result ) ) {
			if ( 'relevance-lab' === $slug && isset( $result['baseline'], $result['candidate'] ) && is_array( $result['baseline'] ) && is_array( $result['candidate'] ) ) {
				$eligible = array();
				foreach ( $result['baseline'] as $item ) { if ( is_array( $item ) && ! empty( $item['key'] ) ) { $eligible[ (string) $item['key'] ] = true; } }
				$result['candidate'] = array_values( array_filter( $result['candidate'], static function ( $item ) use ( $eligible ) { return is_array( $item ) && ! empty( $item['key'] ) && isset( $eligible[ (string) $item['key'] ] ); } ) );
				$result['candidate_scope'] = 'eligible_baseline_keys_only';
			}
			$result['future_contract'] = self::CONTRACT;
			$result['future_capability_id'] = $manifest[ $slug ]['id'];
			$result['canonical_owner_boundary'] = 'File 26 returns derivative discovery data only; click/action time must revalidate native owner authority.';
		}
		return rest_ensure_response( $result );
	}

	private function route_slug( \WP_REST_Request $request ) {
		$route = (string) $request->get_route();
		$prefix = '/' . self::REST_NAMESPACE . '/future/';
		return 0 === strpos( $route, $prefix ) ? sanitize_key( substr( $route, strlen( $prefix ) ) ) : '';
	}
}
