<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

trait Future_User_Discovery_Trait {
/** F26-FUT-18 — transparent recommendation controls and sample explanations. */
	public function recommendation_transparency( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$stored = get_user_meta( $user_id, self::META_DISCOVERY, true );
		$controls = is_array( $stored ) ? $stored : array( 'breadth' => 'standard', 'less_personalization' => false );
		if ( 'POST' === strtoupper( $request->get_method() ) ) {
			$params = $this->params( $request );
			if ( isset( $params['less_personalization'] ) ) { $controls['less_personalization'] = (bool) $params['less_personalization']; }
			if ( isset( $params['breadth'] ) && in_array( sanitize_key( (string) $params['breadth'] ), array( 'standard', 'diverse', 'broad' ), true ) ) { $controls['breadth'] = sanitize_key( (string) $params['breadth'] ); }
			if ( ! empty( $params['reset'] ) ) { $controls = array( 'breadth' => 'standard', 'less_personalization' => false ); }
			$saved = $this->save_user_meta_cas( $user_id, self::META_DISCOVERY, $stored, $controls );
			if ( is_wp_error( $saved ) ) { return $saved; }
		}
		$sample = $this->recommendation_sample( $controls, 6 );
		return array( 'controls' => $controls, 'native_recommendation_controls' => isset( $sample['controls'] ) ? $sample['controls'] : array(), 'sample' => isset( $sample['results'] ) ? $sample['results'] : array(), 'why_this_available' => empty( $controls['less_personalization'] ), 'paid_or_donor_signal' => false, 'less_personalization_effective' => ! empty( $controls['less_personalization'] ) );
	}

/** F26-FUT-19 — anti-filter-bubble breadth modes with deterministic source/author diversification. */
	public function discovery_breadth( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$stored = get_user_meta( $user_id, self::META_DISCOVERY, true );
		$controls = is_array( $stored ) ? $stored : array( 'breadth' => 'standard', 'less_personalization' => false );
		$params = $this->params( $request );
		if ( 'POST' === strtoupper( $request->get_method() ) && isset( $params['breadth'] ) ) {
			$breadth = sanitize_key( (string) $params['breadth'] );
			if ( in_array( $breadth, array( 'standard', 'diverse', 'broad' ), true ) ) {
				$controls['breadth'] = $breadth;
				$saved = $this->save_user_meta_cas( $user_id, self::META_DISCOVERY, $stored, $controls );
				if ( is_wp_error( $saved ) ) { return $saved; }
			}
		}
		$response = $this->recommendation_sample( $controls, 24 );
		$results = isset( $response['results'] ) ? (array) $response['results'] : array();
		if ( in_array( $controls['breadth'], array( 'diverse', 'broad' ), true ) ) { $results = $this->diversify( $results, 'broad' === $controls['breadth'] ? 1 : 2 ); }
		return array( 'controls' => $controls, 'results' => $this->safe_results( array_slice( $results, 0, 12 ) ), 'personalization_disabled_by_breadth' => ! empty( $controls['less_personalization'] ) );
	}

/** F26-FUT-20 — geo/language/availability discovery; File 07/08 retain truth. */
	public function geo_availability( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$entity_type = isset( $params['entity_type'] ) && in_array( sanitize_key( (string) $params['entity_type'] ), array( 'doctor', 'clinic' ), true ) ? sanitize_key( (string) $params['entity_type'] ) : 'doctor';
		$filters = array( 'entity_type' => $entity_type );
		foreach ( array( 'country', 'location', 'language', 'specialization' ) as $key ) { if ( ! empty( $params[ $key ] ) ) { $filters[ $key ] = sanitize_text_field( (string) $params[ $key ] ); } }
		$availability_request = array();
		foreach ( array( 'availability', 'timezone', 'mode' ) as $key ) { if ( ! empty( $params[ $key ] ) ) { $availability_request[ $key ] = sanitize_text_field( (string) $params[ $key ] ); } }
		if ( isset( $params['radius_km'] ) ) { $availability_request['radius_km'] = max( 1, min( 500, (int) $params['radius_km'] ) ); }
		$owner_constraints = apply_filters( 'sabri_file26_geo_availability_constraints', null, $entity_type, $filters, $availability_request, $params );
		$owner_available = is_array( $owner_constraints );
		if ( $owner_available ) { $filters = array_merge( $filters, $this->sanitize_filters( $owner_constraints ) ); }
		$result = $this->base_search( $this->query( $params ), array( 'filters' => $filters, 'limit' => 30 ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'state' => $availability_request && ! $owner_available ? 'owner_availability_provider_unavailable' : 'ok', 'filters' => $filters, 'availability_request' => $availability_request, 'availability_provider_available' => $owner_available, 'availability_claims_suppressed' => $availability_request && ! $owner_available, 'results' => $this->safe_results( (array) $result['results'] ), 'doctor_truth_owner' => 'File 07', 'clinic_and_appointment_truth_owner' => 'File 08', 'availability_truth_computed_by_file26' => false, 'click_time_owner_revalidation_required' => true );
	}

/** F26-FUT-21 — user-facing search modes plus bounded smart-command parsing. */
	public function search_modes( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		$parsed = $this->parse_smart_query( $q );
		$mode = isset( $params['mode'] ) ? sanitize_key( (string) $params['mode'] ) : $this->infer_mode( $q );
		$mode_map = array( 'all' => '', 'research' => 'research', 'learn' => 'lesson', 'doctors' => 'doctor', 'clinics' => 'clinic', 'remedies' => 'remedy', 'diseases' => 'disease', 'pdfs' => 'pdf', 'videos' => 'video', 'courses' => 'course', 'marketplace' => 'listing' );
		if ( ! isset( $mode_map[ $mode ] ) ) { $mode = 'all'; }
		if ( $mode_map[ $mode ] ) { $parsed['filters']['entity_type'] = $mode_map[ $mode ]; }
		$result = $this->base_search( $parsed['query'], array( 'filters' => $parsed['filters'], 'limit' => 30, 'locale' => isset( $params['locale'] ) ? $params['locale'] : '' ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'mode' => $mode, 'available_modes' => array_keys( $mode_map ), 'parsed_query' => $parsed['query'], 'parsed_filters' => $parsed['filters'], 'results' => $this->safe_results( (array) $result['results'] ) );
	}
}
