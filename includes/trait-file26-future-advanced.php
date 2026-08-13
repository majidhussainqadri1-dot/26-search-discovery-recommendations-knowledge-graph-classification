<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

trait Future_Advanced_Trait {
/** F26-FUT-22 — completely isolated private-vault search through authorized native-owner adapter. */
	public function private_search_vault( \WP_REST_Request $request ) {
		$params = $this->params( $request ); $q = $this->query( $params );
		if ( '' === $q ) { return new \WP_Error( 'file26_query_required', 'A private-vault query is required.', array( 'status' => 400 ) ); }
		$provided = apply_filters( 'sabri_file26_private_vault_provider', null, get_current_user_id(), $q, array( 'limit' => max( 1, min( 30, isset( $params['limit'] ) ? (int) $params['limit'] : 20 ) ), 'step_up_verified' => true, 'public_index_forbidden' => true, 'authorization_attestation_required' => true ) );
		if ( ! is_array( $provided ) || 'owner_authorized' !== ( isset( $provided['authorization_attestation'] ) ? $provided['authorization_attestation'] : '' ) || ! isset( $provided['results'] ) || ! is_array( $provided['results'] ) ) { return array( 'state' => 'private_owner_provider_unavailable_or_not_authorized', 'results' => array(), 'public_index_used' => false ); }
		$out = array(); foreach ( array_slice( $provided['results'], 0, 30 ) as $item ) { if ( is_array( $item ) && ! empty( $item['owner'] ) && ! empty( $item['object_id'] ) ) { $out[] = array( 'owner' => sanitize_key( (string) $item['owner'] ), 'object_id' => sanitize_text_field( (string) $item['object_id'] ), 'title' => isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '', 'excerpt' => isset( $item['excerpt'] ) ? wp_strip_all_tags( (string) $item['excerpt'] ) : '', 'open_contract' => isset( $item['open_contract'] ) ? sanitize_text_field( (string) $item['open_contract'] ) : '' ); } }
		return array( 'state' => 'ok', 'results' => $out, 'public_index_used' => false, 'cache_policy' => 'no-store', 'authorization_owner' => 'native private-data owner', 'authorization_attestation' => 'owner_authorized' );
	}

/** F26-FUT-23 — clearly separated, consented, approved external evidence lane. */
	public function external_evidence( \WP_REST_Request $request ) {
		$params = $this->params( $request ); $q = $this->query( $params );
		if ( '' === $q ) { return new \WP_Error( 'file26_query_required', 'A query is required.', array( 'status' => 400 ) ); }
		if ( $this->sensitive_query( $q ) ) { return new \WP_Error( 'file26_external_sensitive_query_prohibited', 'Sensitive queries cannot be sent to external evidence connectors.', array( 'status' => 400 ) ); }
		$consent = isset( $params['external_evidence_consent'] ) && in_array( $params['external_evidence_consent'], array( true, 1, '1', 'true', 'yes' ), true );
		if ( ! $consent ) { return new \WP_Error( 'file26_external_evidence_consent_required', 'Explicit per-request consent is required before external evidence search.', array( 'status' => 400 ) ); }
		$connector = isset( $params['connector'] ) ? sanitize_key( (string) $params['connector'] ) : '';
		if ( '' === $connector || ! apply_filters( 'sabri_file26_external_evidence_connector_approved', false, $connector ) ) { return array( 'state' => 'external_connector_not_approved', 'connector' => $connector, 'results' => array(), 'merged_into_organic_ranking' => false ); }
		$provided = apply_filters( 'sabri_file26_external_evidence_provider', null, $connector, $q, array( 'limit' => 20, 'must_label_external' => true, 'rights_required' => true, 'provenance_required' => true, 'eligibility_attestation_required' => true, 'explicit_user_consent' => true ) );
		if ( ! is_array( $provided ) || 'approved_external_public' !== ( isset( $provided['eligibility_attestation'] ) ? $provided['eligibility_attestation'] : '' ) || ! isset( $provided['results'] ) || ! is_array( $provided['results'] ) ) { return array( 'state' => 'external_provider_unavailable_or_not_attested', 'connector' => $connector, 'results' => array(), 'merged_into_organic_ranking' => false ); }
		$out = array();
		foreach ( array_slice( $provided['results'], 0, 20 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['source_name'] ) || empty( $item['source_url'] ) || empty( $item['retrieved_at'] ) || empty( $item['rights_status'] ) || empty( $item['provenance'] ) ) { continue; }
			$url = esc_url_raw( (string) $item['source_url'], array( 'https' ) ); if ( '' === $url ) { continue; }
			$retrieved_at = sanitize_text_field( (string) $item['retrieved_at'] ); if ( ! $this->valid_provider_timestamp( $retrieved_at ) ) { continue; }
			$out[] = array( 'external' => true, 'connector' => $connector, 'title' => isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '', 'excerpt' => isset( $item['excerpt'] ) ? wp_strip_all_tags( (string) $item['excerpt'] ) : '', 'source_name' => sanitize_text_field( (string) $item['source_name'] ), 'source_url' => $url, 'retrieved_at' => $retrieved_at, 'rights_status' => sanitize_key( (string) $item['rights_status'] ), 'provenance' => sanitize_text_field( (string) $item['provenance'] ) );
		}
		return array( 'state' => 'ok', 'connector' => $connector, 'results' => $out, 'merged_into_organic_ranking' => false, 'canonical_platform_truth' => false, 'external_source_label_required' => true, 'explicit_user_consent' => true, 'eligibility_attestation' => 'approved_external_public' );
	}

/** F26-FUT-24 — read-only relevance laboratory; cannot write active production policy. */
	public function relevance_lab( \WP_REST_Request $request ) {
		if ( 'GET' === strtoupper( $request->get_method() ) ) { return array( 'state' => 'ready', 'production_mutation' => false, 'capability_count' => 24, 'health' => $this->health->snapshot(), 'guardrails' => array( 'staged_candidate_only', 'no_direct_live_policy_write', 'medical_safety_immutable', 'paid_donor_founder_signal_prohibited', 'rollback_evidence_required' ) ); }
		$params = $this->params( $request ); $q = $this->query( $params ); if ( '' === $q ) { return new \WP_Error( 'file26_query_required', 'A benchmark query is required.', array( 'status' => 400 ) ); }
		$baseline = $this->base_search( $q, array( 'limit' => 20, 'locale' => isset( $params['locale'] ) ? $params['locale'] : '' ) ); if ( is_wp_error( $baseline ) ) { return $baseline; }
		$baseline_results = $this->safe_results( (array) $baseline['results'] );
		$baseline_by_key = array(); foreach ( $baseline_results as $item ) { if ( ! empty( $item['key'] ) ) { $baseline_by_key[ (string) $item['key'] ] = $item; } }
		$provided = apply_filters( 'sabri_file26_relevance_lab_candidate', $baseline_results, $q, $baseline_results, array( 'read_only' => true, 'production_write' => false, 'eligible_keys_only' => true ) );
		$candidate = array(); foreach ( is_array( $provided ) ? $provided : $baseline_results as $item ) { $key = is_array( $item ) && ! empty( $item['key'] ) ? (string) $item['key'] : ''; if ( '' === $key || ! isset( $baseline_by_key[ $key ] ) ) { return new \WP_Error( 'file26_relevance_candidate_injected_result', 'Relevance-lab candidates may only select or reorder baseline eligible result keys.', array( 'status' => 409 ) ); } if ( ! isset( $candidate[ $key ] ) ) { $candidate[ $key ] = $baseline_by_key[ $key ]; } }
		$candidate = array_values( $candidate ); $base_keys = array_values( array_filter( array_column( $baseline_results, 'key' ) ) ); $candidate_keys = array_values( array_filter( array_column( $candidate, 'key' ) ) ); $intersection = count( array_intersect( array_slice( $base_keys, 0, 10 ), array_slice( $candidate_keys, 0, 10 ) ) ); $overlap_denominator = max( 1, min( 10, count( $base_keys ) ) );
		return array( 'query' => $q, 'production_mutation' => false, 'baseline' => array_slice( $baseline_results, 0, 10 ), 'candidate' => array_slice( $candidate, 0, 10 ), 'metrics' => array( 'top10_overlap' => $intersection, 'top10_overlap_ratio' => $intersection / $overlap_denominator, 'baseline_source_concentration' => $this->source_concentration( array_slice( $baseline_results, 0, 10 ) ), 'candidate_source_concentration' => $this->source_concentration( array_slice( $candidate, 0, 10 ) ) ), 'release_gate' => 'Candidate requires separate approval, staging evidence and rollbackable versioned release.' );
	}
}
