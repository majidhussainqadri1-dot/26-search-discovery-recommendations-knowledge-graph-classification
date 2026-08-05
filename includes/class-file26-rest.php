<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class REST {
	private $namespace = 'sabri-search/v1';
	private $search;
	private $recommendations;
	private $taxonomy;
	private $graph;
	private $indexer;
	private $health;
	private $security;
	private $connectors;
	private $governance;

	public function __construct( Search $search, Recommendations $recommendations, Taxonomy $taxonomy, Graph $graph, Indexer $indexer, Health $health, Security $security, Connectors $connectors, Governance $governance ) {
		$this->search = $search;
		$this->recommendations = $recommendations;
		$this->taxonomy = $taxonomy;
		$this->graph = $graph;
		$this->indexer = $indexer;
		$this->health = $health;
		$this->security = $security;
		$this->connectors = $connectors;
		$this->governance = $governance;
	}

	public function register() {
		$this->route( '/search', 'GET', 'search', '__return_true' );
		$this->route( '/suggest', 'GET', 'suggest', '__return_true' );
		$this->route( '/discover', 'GET', 'discover', '__return_true' );
		$this->route( '/feedback', 'POST', 'feedback', 'logged_in' );
		$this->route( '/personalization/consent', 'POST', 'consent', 'logged_in' );
		$this->route( '/personalization/interests', 'POST', 'interests', 'logged_in' );
		$this->route( '/personalization/reset', 'POST', 'reset', 'logged_in' );
		$this->route( '/personalization/opt-out', 'POST', 'opt_out', 'logged_in' );
		$this->route( '/topics/(?P<term>[a-zA-Z0-9\-_]+)', 'GET', 'topic', '__return_true' );
		$this->route( '/graph/(?P<key>[a-f0-9]{64})', 'GET', 'graph', '__return_true' );
		$this->route( '/health', 'GET', 'health', 'can_audit' );
		$this->route( '/admin/reindex', 'POST', 'reindex', 'can_operate' );
		$this->route( '/admin/reconcile', 'POST', 'reconcile', 'can_operate' );
		$this->route( '/admin/taxonomy', 'POST', 'create_term', 'can_curate' );
		$this->route( '/admin/taxonomy/(?P<term>[a-f0-9\-]{36})/submit', 'POST', 'submit_term', 'can_curate' );
		$this->route( '/admin/taxonomy/(?P<term>[a-f0-9\-]{36})/approve', 'POST', 'approve_term', 'can_curate' );
		$this->route( '/admin/taxonomy/(?P<term>[a-f0-9\-]{36})/deprecate', 'POST', 'deprecate_term', 'can_curate' );
		$this->route( '/admin/taxonomy/(?P<term>[a-f0-9\-]{36})/split', 'POST', 'split_term', 'can_curate' );
		$this->route( '/admin/graph/edge', 'POST', 'create_edge', 'can_curate' );
		$this->route( '/admin/connectors/(?P<slug>[a-z0-9_-]+)/transition', 'POST', 'transition_connector', 'can_operate' );
		$this->route( '/admin/ranking/stage', 'POST', 'stage_ranking', 'can_approve_ranking' );
		$this->route( '/admin/ranking/(?P<policy>[a-f0-9-]+)/activate', 'POST', 'activate_ranking', 'can_approve_ranking' );
		$this->route( '/admin/ranking/(?P<policy>[a-f0-9-]+)/rollback', 'POST', 'rollback_ranking', 'can_approve_ranking' );
		$this->route( '/admin/classification/review', 'POST', 'review_classification', 'can_curate' );
		$this->route( '/admin/graph/(?P<edge>[a-f0-9-]+)/transition', 'POST', 'transition_edge', 'can_curate' );
		$this->route( '/admin/reports', 'GET', 'reports', 'can_audit' );
	}

	private function route( $path, $methods, $callback, $permission ) {
		register_rest_route( $this->namespace, $path, array(
			'methods' => $methods,
			'callback' => array( $this, $callback ),
			'permission_callback' => '__return_true' === $permission ? '__return_true' : array( $this, $permission ),
		) );
	}

	public function search( \WP_REST_Request $request ) {
		$filters = array();
		foreach ( array( 'entity_type', 'country', 'location', 'availability', 'connector', 'domain', 'topic', 'sort', 'author', 'date_from', 'date_to' ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$filters[ $key ] = $request->get_param( $key );
			}
		}
		return $this->respond( $this->search->run( array(
			'q' => $request->get_param( 'q' ),
			'locale' => $request->get_param( 'locale' ),
			'cursor' => $request->get_param( 'cursor' ),
			'limit' => $request->get_param( 'limit' ),
			'filters' => $filters,
		) ), 200, true );
	}

	public function suggest( \WP_REST_Request $request ) {
		return $this->respond( array(
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
			'suggestions' => $this->search->suggest( $request->get_param( 'q' ), $request->get_param( 'locale' ), $request->get_param( 'limit' ) ?: 8 ),
		), 200, true );
	}

	public function discover( \WP_REST_Request $request ) {
		return $this->respond( $this->recommendations->get( array(
			'context' => $request->get_param( 'context' ) ?: 'discover',
			'limit' => $request->get_param( 'limit' ) ?: 12,
			'cursor' => $request->get_param( 'cursor' ),
		) ), 200, ! is_user_logged_in() );
	}

	public function feedback( \WP_REST_Request $request ) {
		return $this->respond( $this->recommendations->record_feedback( (array) $request->get_json_params() ), 201 );
	}

	public function consent( \WP_REST_Request $request ) {
		return $this->respond( $this->recommendations->set_consent( (bool) $request->get_param( 'consent' ) ) );
	}

	public function interests( \WP_REST_Request $request ) { return $this->respond( $this->recommendations->set_interests( (array) $request->get_param( 'interests' ) ) ); }
	public function reset() { return $this->respond( $this->recommendations->reset() ); }
	public function opt_out() { return $this->respond( $this->recommendations->opt_out() ); }

	public function topic( \WP_REST_Request $request ) {
		$term = $this->taxonomy->get( $request['term'] );
		if ( ! $term || ! in_array( $term['status'], array( 'active', 'merged' ), true ) ) {
			return new \WP_Error( 'file26_topic_not_found', 'Topic not found.', array( 'status' => 404 ) );
		}
		if ( 'merged' === $term['status'] && ! empty( $term['redirect_uuid'] ) ) {
			$redirect = $this->taxonomy->get( $term['redirect_uuid'] );
			if ( $redirect ) { $term = $redirect; }
		}
		$results = $this->search->run( array( 'q' => $term['preferred_label'], 'locale' => $term['language'], 'limit' => 20 ) );
		return $this->respond( array(
			'term' => $term,
			'related' => is_wp_error( $results ) ? array() : $results['results'],
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
		), 200, true );
	}

	public function graph( \WP_REST_Request $request ) {
		return $this->respond( $this->graph->query( $request['key'], $request->get_param( 'depth' ) ?: 1, $request->get_param( 'degree' ) ?: 10, (array) $request->get_param( 'types' ) ), 200, true );
	}

	public function health() { return $this->respond( $this->health->snapshot() ); }

	public function reindex( \WP_REST_Request $request ) {
		$job = $this->indexer->enqueue_reindex( sanitize_key( $request->get_param( 'connector' ) ), (array) $request->get_param( 'scope' ) );
		return is_wp_error( $job ) ? $job : $this->respond( array( 'job_uuid' => $job ), 202 );
	}

	public function reconcile() { $this->indexer->reconcile(); return $this->respond( array( 'reconciled' => true ) ); }
	public function create_term( \WP_REST_Request $request ) { return $this->respond( $this->taxonomy->create( (array) $request->get_json_params() ), 201 ); }
	public function submit_term( \WP_REST_Request $request ) { return $this->respond( $this->taxonomy->submit( $request['term'] ) ); }
	public function approve_term( \WP_REST_Request $request ) { return $this->respond( $this->taxonomy->approve( $request['term'] ) ); }
	public function deprecate_term( \WP_REST_Request $request ) { return $this->respond( $this->taxonomy->deprecate( $request['term'], $request->get_param( 'redirect_uuid' ), $request->get_param( 'reason' ) ) ); }
	public function split_term( \WP_REST_Request $request ) { return $this->respond( $this->taxonomy->split( $request['term'], (array) $request->get_param( 'targets' ), $request->get_param( 'reason' ) ), 201 ); }

	public function create_edge( \WP_REST_Request $request ) {
		$edge = $this->graph->create_edge( (array) $request->get_json_params() );
		return is_wp_error( $edge ) ? $edge : $this->respond( array( 'edge_uuid' => $edge ), 201 );
	}

	public function transition_connector( \WP_REST_Request $request ) { return $this->respond( $this->governance->transition_connector( $request['slug'], $request->get_param( 'target' ), $request->get_param( 'reason' ) ) ); }

	public function stage_ranking( \WP_REST_Request $request ) {
		$uuid = $this->governance->stage_ranking_policy( (array) $request->get_json_params() );
		return is_wp_error( $uuid ) ? $uuid : $this->respond( array( 'policy_uuid' => $uuid ), 201 );
	}

	public function activate_ranking( \WP_REST_Request $request ) { return $this->respond( $this->governance->activate_ranking_policy( $request['policy'], $request->get_param( 'second_approver_id' ), $request->get_param( 'reason' ) ) ); }
	public function rollback_ranking( \WP_REST_Request $request ) { return $this->respond( $this->governance->rollback_ranking_policy( $request['policy'], $request->get_param( 'reason' ) ) ); }
	public function review_classification( \WP_REST_Request $request ) { return $this->respond( $this->governance->review_classification( $request->get_param( 'object_key' ), $request->get_param( 'term_uuid' ), $request->get_param( 'decision' ), $request->get_param( 'reason' ) ) ); }
	public function transition_edge( \WP_REST_Request $request ) { return $this->respond( $this->governance->transition_edge( $request['edge'], $request->get_param( 'target' ), $request->get_param( 'reason' ) ) ); }
	public function reports() { return $this->respond( $this->governance->reports() ); }

	public function logged_in() { return is_user_logged_in(); }
	public function can_operate() { return $this->security->can_operate(); }
	public function can_curate() { return $this->security->can_curate(); }
	public function can_approve_ranking() { return $this->security->can_approve_ranking(); }
	public function can_audit() { return $this->security->can_audit(); }

	private function respond( $data, $status = 200, $public_cache = false ) {
		if ( is_wp_error( $data ) ) { return $data; }
		$response = new \WP_REST_Response( $data, $status );
		$response->header( 'X-Sabri-File26-Contract', SABRI_FILE26_CONTRACT_VERSION );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		if ( $public_cache && ! is_user_logged_in() ) {
			$response->header( 'Cache-Control', 'public, max-age=60, stale-while-revalidate=120' );
			$response->header( 'ETag', '"' . hash( 'sha256', wp_json_encode( $data ) ) . '"' );
		} else {
			$response->header( 'Cache-Control', 'private, no-store' );
		}
		return $response;
	}
}
