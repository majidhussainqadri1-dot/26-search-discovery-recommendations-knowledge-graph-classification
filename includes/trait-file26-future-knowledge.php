<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

trait Future_Knowledge_Trait {
	public function research_search( \WP_REST_Request $request ) {
		$params = $this->params( $request ); $q = $this->query( $params ); $filters = array();
		foreach ( array( 'author', 'source', 'language', 'date_from', 'date_to', 'topic', 'evidence_level', 'edition', 'format' ) as $key ) { if ( isset( $params[ $key ] ) && '' !== $params[ $key ] ) { $filters[ $key ] = sanitize_text_field( (string) $params[ $key ] ); } }
		$advanced = array_merge( $params, array( 'q' => $q, 'limit' => isset( $params['limit'] ) ? max( 1, min( 30, (int) $params['limit'] ) ) : 30 ) );
		$result = $this->advanced_search_data( $advanced ); if ( is_wp_error( $result ) ) { return $result; }
		$results = $this->safe_results( isset( $result['results'] ) ? (array) $result['results'] : array() ); $constraint_state = 'native_advanced_constraints';
		$special = array_filter( array( 'evidence_level' => isset( $filters['evidence_level'] ) ? $filters['evidence_level'] : '', 'edition' => isset( $filters['edition'] ) ? $filters['edition'] : '' ), static function ( $value ) { return '' !== $value; } );
		if ( $special ) {
			$filtered = apply_filters( 'sabri_file26_research_constraint_provider', null, $special, $results, get_current_user_id() );
			if ( ! is_array( $filtered ) || 'owner_revalidated_for_request' !== ( isset( $filtered['eligibility_attestation'] ) ? $filtered['eligibility_attestation'] : '' ) || ! isset( $filtered['results'] ) || ! is_array( $filtered['results'] ) ) { return array( 'state' => 'owner_research_constraint_provider_unavailable', 'query' => $q, 'filters' => $filters, 'results' => array(), 'reproducibility' => array( 'state' => 'not_evaluated' ) ); }
			$eligible = array(); foreach ( $results as $eligible_item ) { if ( is_array( $eligible_item ) && ! empty( $eligible_item['key'] ) ) { $eligible[ (string) $eligible_item['key'] ] = true; } }
			$provider_results = $this->safe_results( $filtered['results'] );
			$results = array_values( array_filter( $provider_results, static function ( $item ) use ( $eligible ) { return is_array( $item ) && ! empty( $item['key'] ) && isset( $eligible[ (string) $item['key'] ] ); } ) );
			$constraint_state = 'owner_revalidated_special_constraints_eligible_keys_only';
		}
		$reproducibility = array( 'state' => 'snapshot_provider_unavailable', 'current_results_not_claimed_historical' => true );
		if ( $this->sensitive_query( $q ) ) {
			$reproducibility['state'] = 'snapshot_provider_bypassed_for_sensitive_query';
		} else {
			$snapshot = apply_filters( 'sabri_file26_research_snapshot_provider', null, array( 'query' => $q, 'filters' => $filters, 'policy_version' => isset( $result['policy_version'] ) ? $result['policy_version'] : '' ) );
			$snapshot_id = is_array( $snapshot ) && isset( $snapshot['snapshot_id'] ) ? substr( sanitize_text_field( (string) $snapshot['snapshot_id'] ), 0, 191 ) : '';
			if ( is_array( $snapshot ) && '' !== $snapshot_id && 'immutable_query_snapshot' === ( isset( $snapshot['snapshot_attestation'] ) ? $snapshot['snapshot_attestation'] : '' ) ) {
				$reproducibility = array( 'state' => 'snapshot_available', 'snapshot_id' => $snapshot_id, 'created_at' => isset( $snapshot['created_at'] ) ? substr( sanitize_text_field( (string) $snapshot['created_at'] ), 0, 64 ) : '', 'policy_version' => isset( $snapshot['policy_version'] ) ? substr( sanitize_text_field( (string) $snapshot['policy_version'] ), 0, 64 ) : '', 'current_results_not_claimed_historical' => true );
			}
		}
		return array( 'state' => 'ok', 'query' => $q, 'filters' => $filters, 'constraint_state' => $constraint_state, 'results' => $results, 'reproducibility' => $reproducibility );
	}

	public function result_clusters( \WP_REST_Request $request ) {
		$params = $this->params( $request ); $q = $this->query( $params );
		if ( '' === $q ) { return new \WP_Error( 'file26_query_required', 'A query is required.', array( 'status' => 400 ) ); }
		$result = $this->base_search( $q, array( 'limit' => 30, 'locale' => isset( $params['locale'] ) ? $params['locale'] : '' ) ); if ( is_wp_error( $result ) ) { return $result; }
		$by = isset( $params['cluster_by'] ) ? sanitize_key( (string) $params['cluster_by'] ) : 'entity_type'; if ( ! in_array( $by, array( 'entity_type', 'domain', 'topic' ), true ) ) { $by = 'entity_type'; }
		$clusters = array();
		foreach ( (array) $result['results'] as $item ) { if ( 'topic' === $by ) { $keys = ! empty( $item['topics'] ) ? array_slice( (array) $item['topics'], 0, 3 ) : array( 'other' ); } else { $keys = array( ! empty( $item[ $by ] ) ? sanitize_key( (string) $item[ $by ] ) : 'other' ); } foreach ( $keys as $cluster_key ) { $cluster_key = sanitize_key( (string) $cluster_key ) ?: 'other'; if ( ! isset( $clusters[ $cluster_key ] ) ) { $clusters[ $cluster_key ] = array(); } if ( count( $clusters[ $cluster_key ] ) < 12 ) { $clusters[ $cluster_key ][] = $this->safe_result( $item ); } } }
		return array( 'query' => $q, 'cluster_by' => $by, 'clusters' => $clusters );
	}

	public function graph_path( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$from = isset( $params['from'] ) ? substr( sanitize_text_field( (string) $params['from'] ), 0, 191 ) : '';
		$to = isset( $params['to'] ) ? substr( sanitize_text_field( (string) $params['to'] ), 0, 191 ) : '';
		if ( '' === $from || '' === $to ) { return new \WP_Error( 'file26_graph_endpoints_required', 'Both graph endpoints are required.', array( 'status' => 400 ) ); }
		if ( $this->sensitive_query( $from . ' ' . $to ) ) { return new \WP_Error( 'file26_graph_sensitive_endpoint_not_allowed', 'Sensitive identifiers are not sent to the public graph-path provider.', array( 'status' => 400 ) ); }
		$path = apply_filters( 'sabri_file26_graph_path_provider', null, $from, $to, array( 'max_depth' => 6, 'public_only' => true, 'provenance_required' => true, 'eligibility_attestation_required' => true ) );
		if ( ! is_array( $path ) || 'owner_revalidated_for_request' !== ( isset( $path['eligibility_attestation'] ) ? $path['eligibility_attestation'] : '' ) || empty( $path['nodes'] ) || empty( $path['edges'] ) ) { return array( 'state' => 'no_verified_path_or_provider_unavailable', 'from' => $from, 'to' => $to, 'nodes' => array(), 'edges' => array() ); }
		$nodes = array(); $node_ids = array(); foreach ( array_slice( (array) $path['nodes'], 0, 40 ) as $node ) { if ( is_array( $node ) ) { $clean = $this->sanitize_graph_node( $node ); if ( '' !== $clean['id'] ) { $nodes[] = $clean; $node_ids[ $clean['id'] ] = true; } } }
		if ( ! isset( $node_ids[ $from ], $node_ids[ $to ] ) ) { return new \WP_Error( 'file26_graph_endpoint_binding_failed', 'The verified graph path must contain both requested endpoints.', array( 'status' => 409 ) ); }
		$raw_edges = array_values( (array) $path['edges'] );
		if ( count( $raw_edges ) > 6 ) { return new \WP_Error( 'file26_graph_depth_exceeded', 'The verified graph path exceeds the maximum depth of six edges.', array( 'status' => 409 ) ); }
		$edges = array();
		foreach ( $raw_edges as $edge ) {
			if ( ! is_array( $edge ) || empty( $edge['provenance'] ) ) { return new \WP_Error( 'file26_graph_provenance_required', 'Every graph edge must carry provenance.', array( 'status' => 409 ) ); }
			$clean = $this->sanitize_graph_edge( $edge );
			if ( '' === $clean['provenance'] ) { return new \WP_Error( 'file26_graph_provenance_required', 'Every graph edge must retain provenance after sanitization.', array( 'status' => 409 ) ); }
			if ( '' === $clean['owner'] || '' === $clean['type'] ) { return new \WP_Error( 'file26_graph_edge_owner_type_required', 'Every graph edge must retain owner and type identity after sanitization.', array( 'status' => 409 ) ); }
			if ( '' === $clean['from'] || '' === $clean['to'] || ! isset( $node_ids[ $clean['from'] ], $node_ids[ $clean['to'] ] ) ) { return new \WP_Error( 'file26_graph_referential_integrity', 'Every graph edge must reference returned graph nodes.', array( 'status' => 409 ) ); }
			$edges[] = $clean;
		}
		if ( ! $this->graph_path_connected( $from, $to, $edges, 6 ) ) { return new \WP_Error( 'file26_graph_path_not_connected', 'The provider response does not contain a connected path between the requested endpoints.', array( 'status' => 409 ) ); }
		return array( 'state' => 'ok', 'from' => $from, 'to' => $to, 'nodes' => $nodes, 'edges' => $edges, 'inferred_sensitive_relationships' => false );
	}

	private function graph_path_connected( $from, $to, array $edges, $max_depth ) {
		if ( $from === $to ) { return true; }
		$adjacency = array();
		foreach ( $edges as $edge ) {
			$a = isset( $edge['from'] ) ? (string) $edge['from'] : ''; $b = isset( $edge['to'] ) ? (string) $edge['to'] : '';
			if ( '' === $a || '' === $b ) { continue; }
			$adjacency[ $a ][] = $b; $adjacency[ $b ][] = $a;
		}
		$queue = array( array( $from, 0 ) ); $seen = array( $from => true );
		while ( $queue ) {
			$current = array_shift( $queue ); $node = $current[0]; $depth = (int) $current[1];
			if ( $depth >= $max_depth ) { continue; }
			foreach ( isset( $adjacency[ $node ] ) ? array_unique( $adjacency[ $node ] ) : array() as $next ) {
				if ( $next === $to ) { return true; }
				if ( ! isset( $seen[ $next ] ) ) { $seen[ $next ] = true; $queue[] = array( $next, $depth + 1 ); }
			}
		}
		return false;
	}

	public function evidence_map( \WP_REST_Request $request ) {
		$params = $this->params( $request ); $claim = isset( $params['claim'] ) ? $this->security->sanitize_query( (string) $params['claim'] ) : '';
		if ( '' === $claim ) { return new \WP_Error( 'file26_claim_required', 'A claim or concept is required.', array( 'status' => 400 ) ); }
		if ( $this->sensitive_query( $claim ) ) { return new \WP_Error( 'file26_evidence_sensitive_claim_not_allowed', 'Sensitive claims are not sent to the public evidence-map provider.', array( 'status' => 400 ) ); }
		$map = apply_filters( 'sabri_file26_evidence_map_provider', null, $claim, array( 'allowed_relations' => array( 'supports', 'discusses', 'contradicts', 'corrects', 'retracts' ), 'public_only' => true, 'provenance_required' => true, 'eligibility_attestation_required' => true ) );
		if ( ! is_array( $map ) || 'owner_revalidated_for_request' !== ( isset( $map['eligibility_attestation'] ) ? $map['eligibility_attestation'] : '' ) ) { return array( 'state' => 'provider_unavailable', 'claim' => $claim, 'relations' => array() ); }
		$relations = array();
		foreach ( array_slice( isset( $map['relations'] ) ? (array) $map['relations'] : array(), 0, 100 ) as $relation ) {
			if ( ! is_array( $relation ) || empty( $relation['type'] ) || empty( $relation['provenance'] ) || ! in_array( $relation['type'], array( 'supports', 'discusses', 'contradicts', 'corrects', 'retracts' ), true ) ) { continue; }
			$clean = $this->sanitize_evidence_relation( $relation );
			$canonical_url = $this->security->safe_resource_url( isset( $clean['canonical_url'] ) ? $clean['canonical_url'] : '', 'evidence_map_canonical_url' );
			if ( 64 !== strlen( $clean['source_key'] ) || '' === $clean['owner'] || '' === $clean['provenance'] || '' === $canonical_url ) { continue; }
			$clean['canonical_url'] = $canonical_url; $relations[] = $clean;
		}
		return array( 'state' => 'ok', 'claim' => $claim, 'relations' => $relations, 'hidden_inference_presented_as_fact' => false );
	}

	public function disambiguate( \WP_REST_Request $request ) {
		$params = $this->params( $request ); $q = $this->query( $params );
		if ( '' === $q ) { return new \WP_Error( 'file26_query_required', 'A name or concept is required.', array( 'status' => 400 ) ); }
		$result = $this->base_search( '"' . str_replace( '"', '', $q ) . '"', array( 'limit' => 20 ) ); if ( is_wp_error( $result ) ) { return $result; }
		$candidates = array(); foreach ( $this->safe_results( (array) $result['results'] ) as $item ) { $candidates[] = array( 'key' => isset( $item['key'] ) ? $item['key'] : '', 'title' => isset( $item['title'] ) ? $item['title'] : '', 'entity_type' => isset( $item['entity_type'] ) ? $item['entity_type'] : '', 'domain' => isset( $item['domain'] ) ? $item['domain'] : '', 'source' => isset( $item['source'] ) ? $item['source'] : '', 'url' => isset( $item['url'] ) ? $item['url'] : ( isset( $item['canonical_url'] ) ? $item['canonical_url'] : '' ) ); }
		return array( 'query' => $q, 'ambiguous' => count( $candidates ) > 1, 'candidates' => $candidates, 'auto_merge' => false );
	}

	public function historical_search( \WP_REST_Request $request ) {
		$params = $this->params( $request ); $q = $this->query( $params ); $as_of = isset( $params['as_of'] ) ? sanitize_text_field( (string) $params['as_of'] ) : '';
		if ( '' === $q || ! preg_match( '/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}(?::\d{2})?)?$/', $as_of ) ) { return new \WP_Error( 'file26_historical_query_invalid', 'A query and ISO as_of date/time are required.', array( 'status' => 400 ) ); }
		$format = false !== strpos( $as_of, 'T' ) ? ( 19 === strlen( $as_of ) ? '!Y-m-d\TH:i:s' : '!Y-m-d\TH:i' ) : '!Y-m-d';
		$parsed = \DateTimeImmutable::createFromFormat( $format, $as_of, new \DateTimeZone( 'UTC' ) );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( false === $parsed || ( is_array( $errors ) && ( ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] ) ) ) || $parsed->format( ltrim( $format, '!' ) ) !== $as_of ) { return new \WP_Error( 'file26_historical_date_invalid', 'The as_of calendar date/time is invalid.', array( 'status' => 400 ) ); }
		if ( $this->sensitive_query( $q ) ) { return array( 'state' => 'historical_provider_bypassed_for_sensitive_query', 'query' => $q, 'as_of' => $as_of, 'results' => array(), 'current_results_substituted' => false ); }
		$provider_context = array(
			'eligibility_attestation_required' => true,
			'locale' => isset( $params['locale'] ) ? substr( sanitize_text_field( (string) $params['locale'] ), 0, 20 ) : '',
			'filters' => isset( $params['filters'] ) && is_array( $params['filters'] ) ? $this->sanitize_filters( $params['filters'] ) : array(),
			'limit' => isset( $params['limit'] ) ? max( 1, min( 30, (int) $params['limit'] ) ) : 20,
		);
		$snapshot = apply_filters( 'sabri_file26_historical_snapshot_search', null, $q, $as_of, $provider_context );
		$snapshot_id = is_array( $snapshot ) && isset( $snapshot['snapshot_id'] ) ? substr( sanitize_text_field( (string) $snapshot['snapshot_id'] ), 0, 191 ) : '';
		$provenance = is_array( $snapshot ) && isset( $snapshot['provenance'] ) ? substr( sanitize_text_field( (string) $snapshot['provenance'] ), 0, 600 ) : '';
		if ( ! is_array( $snapshot ) || '' === $snapshot_id || '' === $provenance || 'owner_revalidated_for_request' !== ( isset( $snapshot['eligibility_attestation'] ) ? $snapshot['eligibility_attestation'] : '' ) ) { return array( 'state' => 'historical_snapshot_unavailable', 'query' => $q, 'as_of' => $as_of, 'results' => array(), 'current_results_substituted' => false ); }
		return array( 'state' => 'ok', 'query' => $q, 'as_of' => $as_of, 'snapshot_id' => $snapshot_id, 'snapshot_provenance' => $provenance, 'results' => $this->safe_results( isset( $snapshot['results'] ) ? (array) $snapshot['results'] : array() ), 'current_results_substituted' => false, 'eligibility_attestation' => 'owner_revalidated_for_request' );
	}
}
