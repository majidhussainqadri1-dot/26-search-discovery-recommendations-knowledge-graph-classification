<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

trait Future_User_Discovery_Trait {
	public function recommendation_transparency( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$stored = get_user_meta( $user_id, self::META_DISCOVERY, true );
		$controls = $this->normalize_discovery_controls( $stored );
		if ( 'POST' === strtoupper( $request->get_method() ) ) {
			$params = $this->params( $request );
			if ( array_key_exists( 'less_personalization', $params ) ) {
				$v = $this->future_strict_bool( $params['less_personalization'] );
				if ( null === $v ) { return new \WP_Error( 'file26_less_personalization_invalid', 'less_personalization must be an explicit boolean value.', array( 'status' => 400 ) ); }
				$controls['less_personalization'] = $v;
			}
			if ( array_key_exists( 'breadth', $params ) ) {
				$b = sanitize_key( (string) $params['breadth'] );
				if ( ! in_array( $b, array( 'standard', 'diverse', 'broad' ), true ) ) { return new \WP_Error( 'file26_discovery_breadth_invalid', 'Discovery breadth must be standard, diverse or broad.', array( 'status' => 400 ) ); }
				$controls['breadth'] = $b;
			}
			if ( array_key_exists( 'reset', $params ) ) {
				$reset = $this->future_strict_bool( $params['reset'] );
				if ( null === $reset ) { return new \WP_Error( 'file26_discovery_reset_invalid', 'reset must be an explicit boolean value.', array( 'status' => 400 ) ); }
				if ( $reset ) { $controls = array( 'breadth' => 'standard', 'less_personalization' => false ); }
			}
			$saved = $this->save_user_meta_cas( $user_id, self::META_DISCOVERY, $stored, $controls );
			if ( is_wp_error( $saved ) ) { return $saved; }
		}

		$sample = $this->recommendation_sample( $controls, 6 );
		$sample_results = isset( $sample['results'] ) ? (array) $sample['results'] : array();
		$native_controls = isset( $sample['controls'] ) && is_array( $sample['controls'] ) ? $sample['controls'] : array();
		if ( ! empty( $controls['less_personalization'] ) ) {
			foreach ( $sample_results as &$item ) {
				if ( is_array( $item ) ) { $item['why_this'] = $this->recommendations->explain( $item, false, false ); }
			}
			unset( $item );
			$profile = $this->recommendations->profile( $user_id );
			$interests = array();
			if ( is_array( $profile ) && ! empty( $profile['interests_json'] ) ) {
				$decoded = json_decode( $profile['interests_json'], true );
				if ( is_array( $decoded ) ) { $interests = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_key', $decoded ) ) ) ), 0, 50 ); }
			}
			$native_controls = array(
				'logged_in' => true,
				'personalization_available' => (bool) DB::setting( 'personalization_enabled', false ),
				'consent' => is_array( $profile ) ? ! empty( $profile['consent'] ) : false,
				'opted_out' => is_array( $profile ) ? ! empty( $profile['opted_out'] ) : false,
				'interests' => $interests,
				'can_hide' => true,
				'can_not_interested' => true,
				'can_reset' => true,
				'can_opt_out' => true,
			);
		}
		return array(
			'controls' => $controls,
			'native_recommendation_controls' => $native_controls,
			'sample' => $sample_results,
			'why_this_available' => true,
			'paid_or_donor_signal' => false,
			'less_personalization_effective' => ! empty( $controls['less_personalization'] ),
		);
	}

	public function discovery_breadth( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$stored = get_user_meta( $user_id, self::META_DISCOVERY, true );
		$controls = $this->normalize_discovery_controls( $stored );
		$params = $this->params( $request );
		if ( 'POST' === strtoupper( $request->get_method() ) && array_key_exists( 'breadth', $params ) ) {
			$b = sanitize_key( (string) $params['breadth'] );
			if ( ! in_array( $b, array( 'standard', 'diverse', 'broad' ), true ) ) { return new \WP_Error( 'file26_discovery_breadth_invalid', 'Discovery breadth must be standard, diverse or broad.', array( 'status' => 400 ) ); }
			$controls['breadth'] = $b;
			$saved = $this->save_user_meta_cas( $user_id, self::META_DISCOVERY, $stored, $controls );
			if ( is_wp_error( $saved ) ) { return $saved; }
		}
		$response = $this->recommendation_sample( $controls, 24 );
		$results = isset( $response['results'] ) ? (array) $response['results'] : array();
		if ( in_array( $controls['breadth'], array( 'diverse', 'broad' ), true ) ) { $results = $this->diversify_discovery( $results, 'broad' === $controls['breadth'] ? 1 : 2 ); }
		return array( 'controls' => $controls, 'results' => $this->safe_results( array_slice( $results, 0, 12 ) ), 'less_personalization_effective' => ! empty( $controls['less_personalization'] ) );
	}

	public function geo_availability( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$entity_type = 'doctor';
		if ( array_key_exists( 'entity_type', $params ) && '' !== trim( (string) $params['entity_type'] ) ) {
			$requested_entity_type = sanitize_key( (string) $params['entity_type'] );
			if ( ! in_array( $requested_entity_type, array( 'doctor', 'clinic' ), true ) ) { return new \WP_Error( 'file26_geo_entity_type_invalid', 'Geo discovery entity_type must be doctor or clinic.', array( 'status' => 400 ) ); }
			$entity_type = $requested_entity_type;
		}
		$filters = array( 'entity_type' => $entity_type );
		foreach ( array( 'country', 'location', 'language' ) as $key ) { if ( ! empty( $params[ $key ] ) ) { $filters[ $key ] = substr( sanitize_text_field( (string) $params[ $key ] ), 0, 191 ); } }
		if ( ! empty( $params['specialization'] ) ) { $filters['topic'] = sanitize_key( (string) $params['specialization'] ); }
		$user_filters = $filters;
		$availability_request = array();
		foreach ( array( 'availability', 'timezone', 'mode' ) as $key ) { if ( ! empty( $params[ $key ] ) ) { $availability_request[ $key ] = substr( sanitize_text_field( (string) $params[ $key ] ), 0, 191 ); } }
		if ( array_key_exists( 'radius_km', $params ) ) {
			$radius_raw = is_scalar( $params['radius_km'] ) ? trim( (string) $params['radius_km'] ) : '';
			if ( '' === $radius_raw || ! preg_match( '/^\d+$/', $radius_raw ) ) { return new \WP_Error( 'file26_geo_radius_invalid', 'radius_km must be a whole number from 1 to 500.', array( 'status' => 400 ) ); }
			$radius = (int) $radius_raw;
			if ( $radius < 1 || $radius > 500 ) { return new \WP_Error( 'file26_geo_radius_invalid', 'radius_km must be between 1 and 500.', array( 'status' => 400 ) ); }
			$availability_request['radius_km'] = $radius;
		}

		$owner_constraints = apply_filters( 'sabri_file26_geo_availability_constraints', null, $entity_type, $filters, $availability_request, array( 'authorization_attestation_required' => true ) );
		$owner_available = is_array( $owner_constraints ) && 'owner_revalidated_for_request' === ( isset( $owner_constraints['authorization_attestation'] ) ? $owner_constraints['authorization_attestation'] : '' ) && isset( $owner_constraints['filters'] ) && is_array( $owner_constraints['filters'] );
		if ( $owner_available ) {
			$owner_filters = $this->sanitize_filters( $owner_constraints['filters'] );
			unset( $owner_filters['entity_type'] );
			foreach ( $owner_filters as $key => $value ) { if ( isset( $user_filters[ $key ] ) && (string) $user_filters[ $key ] !== (string) $value ) { return new \WP_Error( 'file26_geo_owner_constraint_conflict', 'Owner-verified constraints conflict with an explicit user filter; discovery fails closed.', array( 'status' => 409 ) ); } }
			$filters = array_merge( $user_filters, $owner_filters );
			$filters['entity_type'] = $entity_type;
		}
		$result = $this->base_search( $this->query( $params ), array( 'filters' => $filters, 'limit' => 30 ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'state' => $availability_request && ! $owner_available ? 'owner_availability_provider_unavailable_or_not_authorized' : 'ok', 'filters' => $filters, 'availability_request' => $availability_request, 'availability_provider_available' => $owner_available, 'availability_claims_suppressed' => $availability_request && ! $owner_available, 'results' => $this->safe_results( (array) $result['results'] ), 'doctor_truth_owner' => 'File 07', 'clinic_and_appointment_truth_owner' => 'File 08', 'availability_truth_computed_by_file26' => false, 'click_time_owner_revalidation_required' => true );
	}

	public function search_modes( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		$parsed = $this->parse_smart_query( $q );
		$mode = isset( $params['mode'] ) ? sanitize_key( (string) $params['mode'] ) : $this->infer_mode( $q );
		$mode_map = array( 'all' => '', 'research' => 'research', 'learn' => 'lesson', 'doctors' => 'doctor', 'clinics' => 'clinic', 'remedies' => 'remedy', 'diseases' => 'disease', 'pdfs' => 'pdf', 'videos' => 'video', 'courses' => 'course', 'marketplace' => 'listing' );
		if ( ! isset( $mode_map[ $mode ] ) ) { $mode = 'all'; }
		if ( $mode_map[ $mode ] ) { $parsed['filters']['entity_type'] = $mode_map[ $mode ]; }
		if ( ! empty( $parsed['filters']['source'] ) ) {
			$advanced = array( 'q' => $parsed['query'], 'limit' => 30, 'locale' => isset( $params['locale'] ) ? $params['locale'] : '' );
			foreach ( $parsed['filters'] as $filter_key => $filter_value ) { $advanced[ $filter_key ] = $filter_value; }
			$result = $this->advanced_search_data( $advanced );
		} else {
			$result = $this->base_search( $parsed['query'], array( 'filters' => $parsed['filters'], 'limit' => 30, 'locale' => isset( $params['locale'] ) ? $params['locale'] : '' ) );
		}
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'mode' => $mode, 'available_modes' => array_keys( $mode_map ), 'parsed_query' => $parsed['query'], 'parsed_filters' => $parsed['filters'], 'source_constraint_enforced' => ! empty( $parsed['filters']['source'] ), 'results' => $this->safe_results( isset( $result['results'] ) ? (array) $result['results'] : array() ) );
	}

	private function normalize_discovery_controls( $stored ) {
		$stored = is_array( $stored ) ? $stored : array();
		$breadth = isset( $stored['breadth'] ) ? sanitize_key( (string) $stored['breadth'] ) : 'standard';
		if ( ! in_array( $breadth, array( 'standard', 'diverse', 'broad' ), true ) ) { $breadth = 'standard'; }
		$less = isset( $stored['less_personalization'] ) ? $this->future_strict_bool( $stored['less_personalization'] ) : false;
		return array( 'breadth' => $breadth, 'less_personalization' => true === $less );
	}

	private function diversify_discovery( array $results, $limit ) {
		$limit = max( 1, min( 3, (int) $limit ) );
		$sources = array(); $authors = array(); $primary = array(); $overflow = array();
		foreach ( $results as $item ) {
			$source = ! empty( $item['source'] ) ? (string) $item['source'] : ( ! empty( $item['domain'] ) ? (string) $item['domain'] : 'unknown-source' );
			$author = ! empty( $item['author'] ) ? (string) $item['author'] : 'unknown-author:' . ( ! empty( $item['key'] ) ? (string) $item['key'] : hash( 'sha256', wp_json_encode( $item ) ) );
			$sc = isset( $sources[ $source ] ) ? $sources[ $source ] : 0;
			$ac = isset( $authors[ $author ] ) ? $authors[ $author ] : 0;
			if ( $sc < $limit && $ac < $limit ) { $primary[] = $item; $sources[ $source ] = $sc + 1; $authors[ $author ] = $ac + 1; } else { $overflow[] = $item; }
		}
		return array_merge( $primary, $overflow );
	}
}
