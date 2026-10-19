<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

trait Future_Utility_Trait {
	private function params( \WP_REST_Request $request ) {
		$params = $request->get_params();
		$json = $request->get_json_params();
		if ( is_array( $json ) ) {
			$params = array_merge( $params, $json );
		}
		return is_array( $params ) ? $params : array();
	}

	private function query( array $params, $key = 'q' ) {
		return $this->security->sanitize_query( isset( $params[ $key ] ) ? (string) $params[ $key ] : '' );
	}

	private function base_search( $query, array $params = array() ) {
		$request = array(
			'q' => $this->security->sanitize_query( (string) $query ),
			'locale' => isset( $params['locale'] ) ? sanitize_text_field( (string) $params['locale'] ) : '',
			'filters' => isset( $params['filters'] ) && is_array( $params['filters'] ) ? $params['filters'] : array(),
			'limit' => isset( $params['limit'] ) ? max( 1, min( 30, (int) $params['limit'] ) ) : 20,
		);
		if ( ! empty( $params['cursor'] ) ) {
			$request['cursor'] = sanitize_text_field( (string) $params['cursor'] );
		}
		$result = $this->search->run( $request );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->central_plan->augment_search_result( $result, $request );
	}

	private function advanced_search_data( array $params ) {
		$request = new \WP_REST_Request( 'GET', '/' . self::REST_NAMESPACE . '/advanced-search' );
		foreach ( array( 'q', 'exact', 'entity_type', 'country', 'location', 'availability', 'topic', 'sort', 'author', 'language', 'date_from', 'date_to', 'connector', 'source', 'verification', 'format', 'access', 'locale', 'limit', 'cursor' ) as $key ) {
			if ( isset( $params[ $key ] ) && '' !== $params[ $key ] ) {
				$request->set_param( $key, $params[ $key ] );
			}
		}
		$response = $this->central_plan->advanced_search( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return is_object( $response ) && method_exists( $response, 'get_data' ) ? $response->get_data() : (array) $response;
	}

	private function safe_result( array $item ) {
		$out = array();
		if ( isset( $item['key'] ) ) { $out['key'] = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $item['key'] ) ); }
		foreach ( array( 'title' => 240, 'excerpt' => 2000, 'source' => 191, 'author' => 191, 'published_at' => 64, 'updated_at' => 64, 'edition' => 120 ) as $key => $limit ) {
			if ( array_key_exists( $key, $item ) ) {
				$value = 'excerpt' === $key ? wp_strip_all_tags( (string) $item[ $key ] ) : sanitize_text_field( (string) $item[ $key ] );
				$out[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit, 'UTF-8' ) : substr( $value, 0, $limit );
			}
		}
		foreach ( array( 'url', 'canonical_url' ) as $key ) {
			if ( array_key_exists( $key, $item ) ) { $out[ $key ] = esc_url_raw( (string) $item[ $key ], array( 'http', 'https' ) ); }
		}
		foreach ( array( 'entity_type', 'domain', 'connector', 'format', 'verification', 'status', 'evidence_level' ) as $key ) {
			if ( array_key_exists( $key, $item ) ) { $out[ $key ] = sanitize_key( (string) $item[ $key ] ); }
		}
		if ( array_key_exists( 'locale', $item ) ) { $out['locale'] = substr( sanitize_text_field( (string) $item['locale'] ), 0, 20 ); }
		if ( array_key_exists( 'score', $item ) && is_numeric( $item['score'] ) ) { $out['score'] = max( -1000000, min( 1000000, (float) $item['score'] ) ); }
		if ( array_key_exists( 'topics', $item ) ) {
			$topics = is_array( $item['topics'] ) ? $item['topics'] : array();
			$out['topics'] = array_slice( array_values( array_filter( array_map( 'sanitize_key', $topics ) ) ), 0, 50 );
		}
		foreach ( array( 'why_this', 'explanation_codes', 'freshness', 'integrity' ) as $key ) {
			if ( array_key_exists( $key, $item ) && is_array( $item[ $key ] ) ) { $out[ $key ] = $item[ $key ]; }
		}
		return $out;
	}

	private function safe_results( array $items ) {
		$out = array();
		foreach ( array_slice( $items, 0, 50 ) as $item ) {
			if ( is_array( $item ) ) {
				$out[] = $this->safe_result( $item );
			}
		}
		return $out;
	}

	private function sensitive_query( $query ) {
		$query = strtolower( (string) $query );
		$patterns = array( 'patient', 'cnic', 'passport', 'mobile number', 'phone number', 'suicide', 'self harm', 'خودکشی', 'مریض', 'شناختی کارڈ', 'پاسپورٹ', 'فون نمبر' );
		foreach ( $patterns as $needle ) {
			if ( false !== strpos( $query, $needle ) ) {
				return true;
			}
		}
		$extension = $this->security->normalize_authorization( apply_filters( 'sabri_file26_future_sensitive_query', false, $query ) );
		return $this->security->contains_sensitive_query( $query ) || $extension;
	}

	private function autonomous_clinical_intent( $query ) {
		$query = strtolower( (string) $query );
		$patterns = array( 'diagnose me', 'prescribe', 'what dose', 'what potency', 'emergency dose', 'میری تشخیص', 'نسخہ دیں', 'خوراک بتائیں', 'پوٹینسی بتائیں' );
		foreach ( $patterns as $needle ) {
			if ( false !== strpos( $query, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private function sanitize_graph_node( array $node ) {
		return array(
			'id' => isset( $node['id'] ) ? substr( sanitize_text_field( (string) $node['id'] ), 0, 191 ) : '',
			'label' => isset( $node['label'] ) ? substr( sanitize_text_field( (string) $node['label'] ), 0, 160 ) : '',
			'type' => isset( $node['type'] ) ? sanitize_key( (string) $node['type'] ) : '',
			'owner' => isset( $node['owner'] ) ? sanitize_key( (string) $node['owner'] ) : '',
			'canonical_url' => isset( $node['canonical_url'] ) ? esc_url_raw( (string) $node['canonical_url'] ) : '',
		);
	}

	private function sanitize_graph_edge( array $edge ) {
		return array(
			'from' => isset( $edge['from'] ) ? substr( sanitize_text_field( (string) $edge['from'] ), 0, 191 ) : '',
			'to' => isset( $edge['to'] ) ? substr( sanitize_text_field( (string) $edge['to'] ), 0, 191 ) : '',
			'type' => isset( $edge['type'] ) ? sanitize_key( (string) $edge['type'] ) : '',
			'provenance' => isset( $edge['provenance'] ) ? substr( sanitize_text_field( (string) $edge['provenance'] ), 0, 300 ) : '',
			'owner' => isset( $edge['owner'] ) ? sanitize_key( (string) $edge['owner'] ) : '',
		);
	}

	private function sanitize_evidence_relation( array $relation ) {
		return array(
			'type' => sanitize_key( (string) $relation['type'] ),
			'source_key' => isset( $relation['source_key'] ) ? preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $relation['source_key'] ) ) : '',
			'title' => isset( $relation['title'] ) ? substr( sanitize_text_field( (string) $relation['title'] ), 0, 180 ) : '',
			'canonical_url' => isset( $relation['canonical_url'] ) ? esc_url_raw( (string) $relation['canonical_url'] ) : '',
			'provenance' => substr( sanitize_text_field( (string) $relation['provenance'] ), 0, 300 ),
			'owner' => isset( $relation['owner'] ) ? sanitize_key( (string) $relation['owner'] ) : '',
		);
	}

	private function recommendation_sample( array $controls, $limit ) {
		$limit = max( 1, min( 30, (int) $limit ) );
		if ( ! empty( $controls['less_personalization'] ) ) {
			$result = $this->base_search( '', array( 'limit' => $limit ) );
			if ( is_wp_error( $result ) ) { return array( 'results' => array(), 'controls' => array(), 'personalized' => false ); }
			return array( 'results' => $this->safe_results( (array) $result['results'] ), 'controls' => array( 'personalization_bypassed_for_this_future_surface' => true ), 'personalized' => false );
		}
		$result = $this->recommendations->get( array( 'limit' => $limit ) );
		if ( is_wp_error( $result ) ) { return array( 'results' => array(), 'controls' => array(), 'personalized' => false ); }
		$result['results'] = $this->safe_results( isset( $result['results'] ) ? (array) $result['results'] : array() );
		return $result;
	}

	private function sanitize_reference( array $ref ) {
		return array( 'owner' => isset( $ref['owner'] ) ? sanitize_key( (string) $ref['owner'] ) : '', 'object_id' => isset( $ref['object_id'] ) ? substr( sanitize_text_field( (string) $ref['object_id'] ), 0, 191 ) : '', 'object_version' => isset( $ref['object_version'] ) ? max( 0, (int) $ref['object_version'] ) : 0, 'canonical_url' => isset( $ref['canonical_url'] ) ? esc_url_raw( (string) $ref['canonical_url'] ) : '', 'label' => isset( $ref['label'] ) ? substr( sanitize_text_field( (string) $ref['label'] ), 0, 160 ) : '' );
	}

	private function sanitize_filters( array $filters ) {
		$out = array();
		foreach ( array_slice( $filters, 0, 20, true ) as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) { continue; }
			if ( is_scalar( $value ) ) { $out[ $key ] = substr( sanitize_text_field( (string) $value ), 0, 191 ); }
		}
		return $out;
	}

	private function save_user_meta_cas( $user_id, $meta_key, $old_raw, $new_value ) {
		$user_id = (int) $user_id;
		$meta_key = (string) $meta_key;
		if ( ! metadata_exists( 'user', $user_id, $meta_key ) ) {
			return add_user_meta( $user_id, $meta_key, $new_value, true ) ? true : new \WP_Error( 'file26_future_write_conflict', 'The account data changed concurrently. Reload and retry.', array( 'status' => 409 ) );
		}
		$updated = update_user_meta( $user_id, $meta_key, $new_value, $old_raw );
		if ( false !== $updated ) {
			return true;
		}
		$current = get_user_meta( $user_id, $meta_key, true );
		if ( $current === $new_value ) {
			return true;
		}
		return new \WP_Error( 'file26_future_write_conflict', 'The account data changed concurrently. Reload and retry.', array( 'status' => 409 ) );
	}

	private function parse_smart_query( $query ) {
		$query = $this->security->sanitize_query( (string) $query );
		$filters = array();
		$map = array( 'type' => 'entity_type', 'author' => 'author', 'after' => 'date_from', 'before' => 'date_to', 'lang' => 'language', 'country' => 'country', 'source' => 'source', 'topic' => 'topic' );
		foreach ( $map as $command => $filter ) {
			$pattern = '/(?:^|\s)' . preg_quote( $command, '/' ) . ':(?:"([^"]+)"|(\S+))/iu';
			if ( preg_match( $pattern, $query, $match ) ) {
				$value = isset( $match[1] ) && '' !== $match[1] ? $match[1] : ( isset( $match[2] ) ? $match[2] : '' );
				$filters[ $filter ] = substr( sanitize_text_field( $value ), 0, 191 );
				$query = preg_replace( $pattern, ' ', $query, 1 );
			}
		}
		return array( 'query' => trim( preg_replace( '/\s+/u', ' ', $query ) ), 'filters' => $filters );
	}

	private function infer_mode( $query ) {
		$query = strtolower( (string) $query );
		$modes = array( 'research' => array( 'research', 'paper', 'citation', 'تحقیق' ), 'doctors' => array( 'doctor', 'physician', 'ڈاکٹر' ), 'clinics' => array( 'clinic', 'appointment', 'کلینک' ), 'pdfs' => array( 'pdf', 'book', 'کتاب' ), 'videos' => array( 'video', 'reel', 'ویڈیو' ), 'courses' => array( 'course', 'lesson', 'کورس', 'سبق' ), 'remedies' => array( 'remedy', 'medicine', 'دوا' ), 'diseases' => array( 'disease', 'condition', 'مرض' ), 'marketplace' => array( 'marketplace', 'listing', 'خرید' ) );
		foreach ( $modes as $mode => $needles ) { foreach ( $needles as $needle ) { if ( false !== strpos( $query, $needle ) ) { return $mode; } } }
		return 'all';
	}

	private function diversify( array $results, $per_bucket ) {
		$per_bucket = max( 1, min( 3, (int) $per_bucket ) );
		$seen = array(); $primary = array(); $overflow = array();
		foreach ( $results as $item ) {
			$key = ( ! empty( $item['domain'] ) ? $item['domain'] : 'unknown' ) . '|' . ( ! empty( $item['author'] ) ? $item['author'] : ( ! empty( $item['source'] ) ? $item['source'] : 'unknown' ) );
			$count = isset( $seen[ $key ] ) ? $seen[ $key ] : 0;
			if ( $count < $per_bucket ) { $primary[] = $item; $seen[ $key ] = $count + 1; } else { $overflow[] = $item; }
		}
		return array_merge( $primary, $overflow );
	}

	private function source_concentration( array $results ) {
		if ( ! $results ) { return 0; }
		$counts = array();
		foreach ( $results as $item ) { $key = ! empty( $item['source'] ) ? (string) $item['source'] : ( ! empty( $item['domain'] ) ? (string) $item['domain'] : 'unknown' ); $counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1; }
		return max( $counts ) / count( $results );
	}

	public function secure_future_response( $response, $server, $request ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || false === strpos( (string) $request->get_route(), '/' . self::REST_NAMESPACE . '/future/' ) ) { return $response; }
		if ( is_object( $response ) && method_exists( $response, 'header' ) ) {
			$response->header( 'X-Content-Type-Options', 'nosniff' );
			$response->header( 'Referrer-Policy', 'same-origin' );
			$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
			$response->header( 'X-Sabri-File26-Future-Contract', self::CONTRACT );
		}
		return $response;
	}
}
