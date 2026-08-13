<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

trait Future_Search_Core_Trait {
/** F26-FUT-01 — source-grounded conversational discovery, never autonomous prescribing. */
	public function conversational_grounded_search( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		if ( '' === $q ) {
			return new \WP_Error( 'file26_query_required', 'A query is required.', array( 'status' => 400 ) );
		}
		$result = $this->base_search( $q, array( 'locale' => isset( $params['locale'] ) ? $params['locale'] : '', 'limit' => 12 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$sources = $this->safe_results( isset( $result['results'] ) ? (array) $result['results'] : array() );
		$clinical = $this->autonomous_clinical_intent( $q );
		$answer = null;
		$provider_state = 'not_called';
		if ( ! $clinical && ! $this->sensitive_query( $q ) && $sources ) {
			$candidate = apply_filters( 'sabri_file26_grounded_answer_provider', null, $q, $sources, array( 'no_autonomous_diagnosis' => true, 'no_autonomous_prescription' => true ) );
			if ( is_array( $candidate ) && ! empty( $candidate['answer'] ) && isset( $candidate['safety_attestation'] ) && 'grounded_non_prescriptive' === $candidate['safety_attestation'] ) {
				$allowed_keys = array();
				foreach ( $sources as $source ) {
					if ( ! empty( $source['key'] ) ) { $allowed_keys[] = (string) $source['key']; }
				}
				$citations = isset( $candidate['citations'] ) ? array_values( array_intersect( $allowed_keys, (array) $candidate['citations'] ) ) : array();
				if ( $citations ) {
					$answer = array( 'text' => wp_strip_all_tags( (string) $candidate['answer'] ), 'citations' => $citations, 'mode' => 'provider_grounded' );
					$provider_state = 'grounded';
				}
			}
		}
		if ( null === $answer && $sources ) {
			$extracts = array();
			foreach ( array_slice( $sources, 0, 3 ) as $source ) {
				$extracts[] = array( 'key' => isset( $source['key'] ) ? $source['key'] : '', 'title' => isset( $source['title'] ) ? $source['title'] : '', 'excerpt' => isset( $source['excerpt'] ) ? $source['excerpt'] : '' );
			}
			$answer = array( 'text' => '', 'citations' => array_values( array_filter( array_column( $extracts, 'key' ) ) ), 'extracts' => $extracts, 'mode' => 'extractive_only' );
			$provider_state = 'extractive_only';
		}
		return array(
			'query' => $q,
			'answer' => $answer,
			'sources' => $sources,
			'answer_provider_state' => $provider_state,
			'safety' => array(
				'autonomous_clinical_intent' => $clinical,
				'sensitive_query' => $this->sensitive_query( $q ),
				'autonomous_diagnosis' => false,
				'autonomous_prescription' => false,
				'note' => $clinical ? 'Clinical-treatment intent is limited to source discovery; no generated diagnosis, dose or potency is returned.' : 'Answer content must remain grounded in returned source keys.',
			),
		);
	}

/** F26-FUT-02 — bounded, explainable decomposition of complex search intent. */
	public function query_planner( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		if ( '' === $q ) {
			return new \WP_Error( 'file26_query_required', 'A query is required.', array( 'status' => 400 ) );
		}
		$normalized = $this->normalizer->normalize( $q );
		$pieces = preg_split( '/\s+(?:and|اور|vs\.?|versus)\s+|[;؛]+/iu', $q, -1, PREG_SPLIT_NO_EMPTY );
		$pieces = array_slice( array_values( array_unique( array_map( 'trim', (array) $pieces ) ) ), 0, 6 );
		if ( ! $pieces ) { $pieces = array( $q ); }
		$plan = array();
		foreach ( $pieces as $piece ) {
			$parsed = $this->parse_smart_query( $piece );
			$plan[] = array(
				'query' => $parsed['query'],
				'filters' => $parsed['filters'],
				'intent' => $this->infer_mode( $piece ),
				'execution' => 'owner-federated-search',
			);
		}
		$executed = array();
		if ( ! empty( $params['execute'] ) ) {
			foreach ( $plan as $step ) {
				if ( '' === $step['query'] && empty( $step['filters'] ) ) {
					$executed[] = array( 'query' => '', 'state' => 'skipped_empty' );
					continue;
				}
				$r = $this->base_search( $step['query'], array( 'filters' => $step['filters'], 'limit' => 8, 'locale' => isset( $params['locale'] ) ? $params['locale'] : '' ) );
				$executed[] = is_wp_error( $r ) ? array( 'query' => $step['query'], 'state' => 'failed', 'code' => $r->get_error_code() ) : array( 'query' => $step['query'], 'state' => 'ok', 'results' => $this->safe_results( (array) $r['results'] ) );
			}
		}
		return array( 'query' => $q, 'query_normalized' => $normalized, 'bounded_steps' => $plan, 'executed' => $executed );
	}

/** F26-FUT-03 — multilingual/cross-script orchestration without unsafe automatic translation claims. */
	public function cross_language_search( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		if ( '' === $q ) { return new \WP_Error( 'file26_query_required', 'A query is required.', array( 'status' => 400 ) ); }
		$locale = isset( $params['locale'] ) ? sanitize_text_field( (string) $params['locale'] ) : '';
		$variants = array();
		foreach ( $this->normalizer->expansions( $q ) as $expansion ) {
			$variants[] = array( 'query' => $expansion, 'locale' => $locale, 'source' => 'file26_normalizer' );
		}
		$provided = array();
		if ( ! $this->sensitive_query( $q ) ) {
			$provided = apply_filters( 'sabri_file26_cross_language_variants', array(), $q, $locale );
		}
		foreach ( array_slice( (array) $provided, 0, 8 ) as $variant ) {
			if ( is_array( $variant ) && ! empty( $variant['query'] ) ) {
				$sanitized_variant = $this->security->sanitize_query( (string) $variant['query'] );
				if ( '' === $sanitized_variant ) { continue; }
				$variants[] = array( 'query' => $sanitized_variant, 'locale' => isset( $variant['locale'] ) ? sanitize_text_field( (string) $variant['locale'] ) : '', 'source' => 'approved_cross_language_provider' );
			}
		}
		$unique_variants = array();
		foreach ( $variants as $variant ) {
			if ( '' === $variant['query'] ) { continue; }
			$variant_key = hash( 'sha256', $this->normalizer->normalize( $variant['query'] ) . '|' . $variant['locale'] );
			if ( ! isset( $unique_variants[ $variant_key ] ) ) { $unique_variants[ $variant_key ] = $variant; }
		}
		$variants = array_slice( array_values( $unique_variants ), 0, 20 );

		$merged = array();
		$seen = array();
		$states = array();
		foreach ( $variants as $variant ) {
			$r = $this->base_search( $variant['query'], array( 'locale' => $variant['locale'], 'limit' => 12 ) );
			if ( is_wp_error( $r ) ) { $states[] = array( 'locale' => $variant['locale'], 'state' => 'failed' ); continue; }
			$states[] = array( 'locale' => $variant['locale'], 'state' => 'ok' );
			foreach ( (array) $r['results'] as $item ) {
				$key = isset( $item['key'] ) ? (string) $item['key'] : hash( 'sha256', wp_json_encode( $item ) );
				if ( isset( $seen[ $key ] ) ) { continue; }
				$seen[ $key ] = true;
				$item['cross_language_match'] = true;
				$merged[] = $item;
			}
		}
		usort( $merged, static function ( $a, $b ) { $as = isset( $a['score'] ) ? (float) $a['score'] : 0; $bs = isset( $b['score'] ) ? (float) $b['score'] : 0; return $as === $bs ? 0 : ( $as > $bs ? -1 : 1 ); } );
		return array( 'query' => $q, 'variant_count' => count( $variants ), 'variant_states' => $states, 'results' => $this->safe_results( array_slice( $merged, 0, 30 ) ), 'translation_claim' => false, 'semantic_cross_language_provider_available' => ! empty( $provided ), 'sensitive_provider_bypass' => $this->sensitive_query( $q ) );
	}

/** F26-FUT-04 — second-stage semantic reranking with immutable safety/eligibility guardrails. */
	public function semantic_rerank( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		if ( '' === $q ) { return new \WP_Error( 'file26_query_required', 'A query is required.', array( 'status' => 400 ) ); }
		$result = $this->base_search( $q, array( 'limit' => 30, 'locale' => isset( $params['locale'] ) ? $params['locale'] : '', 'filters' => isset( $params['filters'] ) && is_array( $params['filters'] ) ? $this->sanitize_filters( $params['filters'] ) : array() ) );
		if ( is_wp_error( $result ) ) { return $result; }
		$candidates = $this->safe_results( (array) $result['results'] );
		$scores = array();
		if ( ! $this->sensitive_query( $q ) && ! $this->autonomous_clinical_intent( $q ) ) {
			$scores = apply_filters( 'sabri_file26_semantic_reranker', array(), $q, $candidates, array( 'safety_gates_immutable' => true, 'visibility_gates_immutable' => true, 'financial_signals_prohibited' => true ) );
		}
		$provider = false;
		if ( is_array( $scores ) && $scores ) {
			$provider = true;
			foreach ( $candidates as &$item ) {
				$key = isset( $item['key'] ) ? (string) $item['key'] : '';
				$item['semantic_rerank_score'] = isset( $scores[ $key ] ) && is_numeric( $scores[ $key ] ) ? max( -1000, min( 1000, (float) $scores[ $key ] ) ) : null;
			}
			unset( $item );
			usort( $candidates, static function ( $a, $b ) {
				$as = null !== $a['semantic_rerank_score'] ? $a['semantic_rerank_score'] : ( isset( $a['score'] ) ? (float) $a['score'] : 0 );
				$bs = null !== $b['semantic_rerank_score'] ? $b['semantic_rerank_score'] : ( isset( $b['score'] ) ? (float) $b['score'] : 0 );
				return $as === $bs ? 0 : ( $as > $bs ? -1 : 1 );
			} );
		}
		return array( 'query' => $q, 'provider_available' => $provider, 'provider_bypassed_for_sensitive_or_clinical' => $this->sensitive_query( $q ) || $this->autonomous_clinical_intent( $q ), 'results' => array_slice( $candidates, 0, 20 ), 'guardrails' => array( 'visibility' => 'immutable', 'safety' => 'immutable', 'paid_or_donor_signal' => 'prohibited' ) );
	}

}
