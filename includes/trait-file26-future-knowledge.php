<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

trait Future_Knowledge_Trait {
/** F26-FUT-09 — research mode with explicit scholarly filters and reproducibility status. */
	public function research_search( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		$filters = array();
		foreach ( array( 'author', 'source', 'language', 'date_from', 'date_to', 'topic', 'evidence_level', 'edition', 'format' ) as $key ) {
			if ( isset( $params[ $key ] ) && '' !== $params[ $key ] ) { $filters[ $key ] = sanitize_text_field( (string) $params[ $key ] ); }
		}
		$filters['research_mode'] = true;
		$result = $this->base_search( $q, array( 'filters' => $filters, 'limit' => isset( $params['limit'] ) ? $params['limit'] : 30, 'locale' => isset( $params['locale'] ) ? $params['locale'] : '' ) );
		if ( is_wp_error( $result ) ) { return $result; }
		$snapshot = apply_filters( 'sabri_file26_research_snapshot_provider', null, array( 'query' => $q, 'filters' => $filters, 'policy_version' => isset( $result['policy_version'] ) ? $result['policy_version'] : '' ) );
		$reproducibility = array( 'state' => 'snapshot_provider_unavailable', 'current_results_not_claimed_historical' => true );
		if ( is_array( $snapshot ) && ! empty( $snapshot['snapshot_id'] ) ) {
			$reproducibility = array( 'state' => 'snapshot_available', 'snapshot_id' => substr( sanitize_text_field( (string) $snapshot['snapshot_id'] ), 0, 191 ), 'created_at' => isset( $snapshot['created_at'] ) ? sanitize_text_field( (string) $snapshot['created_at'] ) : '', 'policy_version' => isset( $snapshot['policy_version'] ) ? sanitize_text_field( (string) $snapshot['policy_version'] ) : '', 'current_results_not_claimed_historical' => true );
		}
		return array( 'query' => $q, 'filters' => $filters, 'results' => $this->safe_results( (array) $result['results'] ), 'reproducibility' => $reproducibility );
	}

/** F26-FUT-10 — deterministic clustering over currently eligible search results. */
	public function result_clusters( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		$result = $this->base_search( $q, array( 'limit' => 30, 'locale' => isset( $params['locale'] ) ? $params['locale'] : '' ) );
		if ( is_wp_error( $result ) ) { return $result; }
		$by = isset( $params['cluster_by'] ) ? sanitize_key( (string) $params['cluster_by'] ) : 'entity_type';
		if ( ! in_array( $by, array( 'entity_type', 'domain', 'topic' ), true ) ) { $by = 'entity_type'; }
		$clusters = array();
		foreach ( (array) $result['results'] as $item ) {
			if ( 'topic' === $by ) {
				$keys = ! empty( $item['topics'] ) ? array_slice( (array) $item['topics'], 0, 3 ) : array( 'other' );
			} else {
				$keys = array( ! empty( $item[ $by ] ) ? sanitize_key( (string) $item[ $by ] ) : 'other' );
			}
			foreach ( $keys as $cluster_key ) {
				$cluster_key = sanitize_key( (string) $cluster_key ) ?: 'other';
				if ( ! isset( $clusters[ $cluster_key ] ) ) { $clusters[ $cluster_key ] = array(); }
				if ( count( $clusters[ $cluster_key ] ) < 12 ) { $clusters[ $cluster_key ][] = $this->safe_result( $item ); }
			}
		}
		return array( 'query' => $q, 'cluster_by' => $by, 'clusters' => $clusters );
	}

/** F26-FUT-11 — public, bounded relationship-path projection from owner-vetted graph providers. */
	public function graph_path( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$from = isset( $params['from'] ) ? sanitize_text_field( (string) $params['from'] ) : '';
		$to = isset( $params['to'] ) ? sanitize_text_field( (string) $params['to'] ) : '';
		if ( '' === $from || '' === $to ) { return new \WP_Error( 'file26_graph_endpoints_required', 'Both graph endpoints are required.', array( 'status' => 400 ) ); }
		$path = apply_filters( 'sabri_file26_graph_path_provider', null, $from, $to, array( 'max_depth' => 6, 'public_only' => true, 'provenance_required' => true ) );
		if ( ! is_array( $path ) || empty( $path['nodes'] ) || empty( $path['edges'] ) ) { return array( 'state' => 'no_verified_path_or_provider_unavailable', 'from' => $from, 'to' => $to, 'nodes' => array(), 'edges' => array() ); }
		$nodes = array();
		foreach ( array_slice( (array) $path['nodes'], 0, 40 ) as $node ) { if ( is_array( $node ) ) { $nodes[] = $this->sanitize_graph_node( $node ); } }
		$edges = array();
		foreach ( array_slice( (array) $path['edges'], 0, 60 ) as $edge ) {
			if ( ! is_array( $edge ) || empty( $edge['provenance'] ) ) { return new \WP_Error( 'file26_graph_provenance_required', 'Every graph edge must carry provenance.', array( 'status' => 409 ) ); }
			$edges[] = $this->sanitize_graph_edge( $edge );
		}
		return array( 'state' => 'ok', 'from' => $from, 'to' => $to, 'nodes' => $nodes, 'edges' => $edges, 'inferred_sensitive_relationships' => false );
	}

/** F26-FUT-12 — evidence/support/contradiction/correction map with provenance. */
	public function evidence_map( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$claim = isset( $params['claim'] ) ? $this->security->sanitize_query( (string) $params['claim'] ) : '';
		if ( '' === $claim ) { return new \WP_Error( 'file26_claim_required', 'A claim or concept is required.', array( 'status' => 400 ) ); }
		$map = apply_filters( 'sabri_file26_evidence_map_provider', null, $claim, array( 'allowed_relations' => array( 'supports', 'discusses', 'contradicts', 'corrects', 'retracts' ), 'public_only' => true, 'provenance_required' => true ) );
		if ( ! is_array( $map ) ) { return array( 'state' => 'provider_unavailable', 'claim' => $claim, 'relations' => array() ); }
		$relations = array();
		foreach ( array_slice( isset( $map['relations'] ) ? (array) $map['relations'] : array(), 0, 100 ) as $relation ) {
			if ( ! is_array( $relation ) || empty( $relation['type'] ) || empty( $relation['provenance'] ) || ! in_array( $relation['type'], array( 'supports', 'discusses', 'contradicts', 'corrects', 'retracts' ), true ) ) { continue; }
			$relations[] = $this->sanitize_evidence_relation( $relation );
		}
		return array( 'state' => 'ok', 'claim' => $claim, 'relations' => $relations, 'hidden_inference_presented_as_fact' => false );
	}

/** F26-FUT-13 — entity/name/edition disambiguation from eligible search candidates. */
	public function disambiguate( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		if ( '' === $q ) { return new \WP_Error( 'file26_query_required', 'A name or concept is required.', array( 'status' => 400 ) ); }
		$result = $this->base_search( '"' . str_replace( '"', '', $q ) . '"', array( 'limit' => 20 ) );
		if ( is_wp_error( $result ) ) { return $result; }
		$candidates = array();
		foreach ( $this->safe_results( (array) $result['results'] ) as $item ) {
			$candidates[] = array( 'key' => isset( $item['key'] ) ? $item['key'] : '', 'title' => isset( $item['title'] ) ? $item['title'] : '', 'entity_type' => isset( $item['entity_type'] ) ? $item['entity_type'] : '', 'domain' => isset( $item['domain'] ) ? $item['domain'] : '', 'source' => isset( $item['source'] ) ? $item['source'] : '', 'url' => isset( $item['url'] ) ? $item['url'] : ( isset( $item['canonical_url'] ) ? $item['canonical_url'] : '' ) );
		}
		return array( 'query' => $q, 'ambiguous' => count( $candidates ) > 1, 'candidates' => $candidates, 'auto_merge' => false );
	}

/** F26-FUT-14 — historical as-of search requires actual owner snapshot evidence. */
	public function historical_search( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		$as_of = isset( $params['as_of'] ) ? sanitize_text_field( (string) $params['as_of'] ) : '';
		if ( '' === $q || ! preg_match( '/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}(?::\d{2})?)?$/', $as_of ) ) { return new \WP_Error( 'file26_historical_query_invalid', 'A query and ISO as_of date/time are required.', array( 'status' => 400 ) ); }
		$snapshot = apply_filters( 'sabri_file26_historical_snapshot_search', null, $q, $as_of, $params );
		if ( ! is_array( $snapshot ) || empty( $snapshot['snapshot_id'] ) ) { return array( 'state' => 'historical_snapshot_unavailable', 'query' => $q, 'as_of' => $as_of, 'results' => array(), 'current_results_substituted' => false ); }
		return array( 'state' => 'ok', 'query' => $q, 'as_of' => $as_of, 'snapshot_id' => sanitize_text_field( (string) $snapshot['snapshot_id'] ), 'results' => $this->safe_results( isset( $snapshot['results'] ) ? (array) $snapshot['results'] : array() ), 'current_results_substituted' => false );
	}

}
