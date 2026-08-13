<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

trait Future_Multimodal_Trait {
/** F26-FUT-05 — multimodal retrieval accepts owner references; patient-image diagnosis is prohibited. */
	public function multimodal_search( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$asset = isset( $params['asset'] ) && is_array( $params['asset'] ) ? $params['asset'] : array();
		$asset_kind = isset( $asset['kind'] ) ? sanitize_key( (string) $asset['kind'] ) : '';
		if ( ! empty( $asset['patient_image'] ) || ! empty( $params['diagnose'] ) || in_array( $asset_kind, array( 'patient_image', 'clinical_image', 'patient_photo' ), true ) ) {
			return new \WP_Error( 'file26_multimodal_clinical_diagnosis_prohibited', 'Patient-image diagnosis is outside File 26 multimodal search scope.', array( 'status' => 400 ) );
		}
		$reference = array(
			'owner' => isset( $asset['owner'] ) ? sanitize_key( (string) $asset['owner'] ) : '',
			'object_id' => isset( $asset['object_id'] ) ? sanitize_text_field( (string) $asset['object_id'] ) : '',
			'object_version' => isset( $asset['object_version'] ) ? max( 0, (int) $asset['object_version'] ) : 0,
			'kind' => $asset_kind,
		);
		if ( '' === $reference['owner'] || '' === $reference['object_id'] ) {
			return new \WP_Error( 'file26_multimodal_owner_reference_required', 'A canonical owner and object reference are required.', array( 'status' => 400 ) );
		}
		$adapter = apply_filters( 'sabri_file26_multimodal_query_adapter', null, $reference, get_current_user_id(), array( 'authorization_attestation_required' => true ) );
		if ( ! is_array( $adapter ) || 'owner_authorized' !== ( isset( $adapter['authorization_attestation'] ) ? $adapter['authorization_attestation'] : '' ) || empty( $adapter['query_text'] ) ) {
			return array( 'state' => 'provider_unavailable_or_not_authorized', 'asset' => $reference, 'results' => array(), 'diagnosis_performed' => false );
		}
		$q = $this->security->sanitize_query( (string) $adapter['query_text'] );
		$result = $this->base_search( $q, array( 'limit' => 20, 'filters' => isset( $adapter['filters'] ) && is_array( $adapter['filters'] ) ? $this->sanitize_filters( $adapter['filters'] ) : array() ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'state' => 'ok', 'asset' => $reference, 'derived_query' => $q, 'results' => $this->safe_results( (array) $result['results'] ), 'diagnosis_performed' => false, 'authorization_attestation' => 'owner_authorized' );
	}

/** F26-FUT-06 — Urdu/English voice search via replaceable transcription adapter; no audio retained. */
	public function voice_search( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$transcript = isset( $params['transcript'] ) ? $this->security->sanitize_query( (string) $params['transcript'] ) : '';
		$provider = 'client_transcript';
		if ( '' === $transcript && ! empty( $params['audio_ref'] ) ) {
			$adapter = apply_filters( 'sabri_file26_voice_transcription_adapter', null, sanitize_text_field( (string) $params['audio_ref'] ), isset( $params['locale'] ) ? sanitize_text_field( (string) $params['locale'] ) : '', get_current_user_id(), array( 'authorization_attestation_required' => true ) );
			if ( is_array( $adapter ) && 'owner_authorized' === ( isset( $adapter['authorization_attestation'] ) ? $adapter['authorization_attestation'] : '' ) && ! empty( $adapter['transcript'] ) ) {
				$transcript = $this->security->sanitize_query( (string) $adapter['transcript'] );
				$provider = 'owner_adapter';
			}
		}
		if ( '' === $transcript ) {
			return array( 'state' => 'transcription_unavailable_or_not_authorized', 'audio_retained_by_file26' => false, 'results' => array() );
		}
		$result = $this->base_search( $transcript, array( 'locale' => isset( $params['locale'] ) ? $params['locale'] : '', 'limit' => 20 ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'state' => 'ok', 'transcript' => $transcript, 'transcript_source' => $provider, 'audio_retained_by_file26' => false, 'results' => $this->safe_results( (array) $result['results'] ) );
	}

/** F26-FUT-07 — owner-provided paragraph/page/timestamp segments; no invented locations. */
	public function segment_search( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$q = $this->query( $params );
		$kind = isset( $params['kind'] ) ? sanitize_key( (string) $params['kind'] ) : '';
		$provided = apply_filters( 'sabri_file26_segment_search_provider', null, $q, $kind, array_merge( $params, array( 'eligibility_attestation_required' => true ) ) );
		if ( ! is_array( $provided ) || 'owner_revalidated_for_request' !== ( isset( $provided['eligibility_attestation'] ) ? $provided['eligibility_attestation'] : '' ) || ! isset( $provided['segments'] ) || ! is_array( $provided['segments'] ) ) {
			return array( 'query' => $q, 'kind' => $kind, 'state' => 'owner_segment_provider_unavailable_or_not_authorized', 'segments' => array(), 'invented_position' => false );
		}
		$valid = array();
		foreach ( array_slice( $provided['segments'], 0, 50 ) as $segment ) {
			if ( ! is_array( $segment ) || empty( $segment['owner'] ) || empty( $segment['object_id'] ) || empty( $segment['canonical_url'] ) || empty( $segment['provenance'] ) ) { continue; }
			$position = array();
			foreach ( array( 'page', 'paragraph', 'timestamp_seconds' ) as $key ) {
				if ( isset( $segment[ $key ] ) && is_numeric( $segment[ $key ] ) ) { $position[ $key ] = max( 0, (int) $segment[ $key ] ); }
			}
			foreach ( array( 'chapter', 'lesson' ) as $key ) {
				if ( isset( $segment[ $key ] ) && '' !== $segment[ $key ] ) { $position[ $key ] = substr( sanitize_text_field( (string) $segment[ $key ] ), 0, 120 ); }
			}
			if ( ! $position ) { continue; }
			$valid[] = array( 'owner' => sanitize_key( (string) $segment['owner'] ), 'object_id' => sanitize_text_field( (string) $segment['object_id'] ), 'canonical_url' => esc_url_raw( (string) $segment['canonical_url'] ), 'title' => isset( $segment['title'] ) ? sanitize_text_field( (string) $segment['title'] ) : '', 'excerpt' => isset( $segment['excerpt'] ) ? wp_strip_all_tags( (string) $segment['excerpt'] ) : '', 'position' => $position, 'provenance' => substr( sanitize_text_field( (string) $segment['provenance'] ), 0, 300 ) );
		}
		return array( 'query' => $q, 'kind' => $kind, 'state' => $valid ? 'ok' : 'no_match', 'segments' => $valid, 'invented_position' => false, 'eligibility_attestation' => 'owner_revalidated_for_request' );
	}

/** F26-FUT-08 — semantically similar public discovery from a canonical owner seed. */
	public function find_similar( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$key = isset( $params['canonical_key'] ) ? preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $params['canonical_key'] ) ) : '';
		if ( 64 !== strlen( $key ) ) { return new \WP_Error( 'file26_canonical_key_required', 'A valid canonical search key is required.', array( 'status' => 400 ) ); }
		$seed = apply_filters( 'sabri_file26_similarity_seed', null, $key, array( 'eligibility_attestation_required' => true ) );
		if ( ! is_array( $seed ) || 'owner_revalidated_for_request' !== ( isset( $seed['eligibility_attestation'] ) ? $seed['eligibility_attestation'] : '' ) || empty( $seed['query'] ) ) {
			return array( 'state' => 'seed_provider_unavailable_or_not_authorized', 'canonical_key' => $key, 'results' => array() );
		}
		$filters = isset( $seed['filters'] ) && is_array( $seed['filters'] ) ? $this->sanitize_filters( $seed['filters'] ) : array();
		$result = $this->base_search( $seed['query'], array( 'filters' => $filters, 'limit' => 24 ) );
		if ( is_wp_error( $result ) ) { return $result; }
		$items = array_values( array_filter( (array) $result['results'], static function ( $item ) use ( $key ) { return empty( $item['key'] ) || $key !== $item['key']; } ) );
		return array( 'state' => 'ok', 'canonical_key' => $key, 'seed_provenance' => isset( $seed['provenance'] ) ? sanitize_text_field( (string) $seed['provenance'] ) : '', 'results' => $this->safe_results( $items ), 'eligibility_attestation' => 'owner_revalidated_for_request' );
	}

}
