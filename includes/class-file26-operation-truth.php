<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Verifies that success-shaped REST mutations and retention views match durable state. */
final class Operation_Truth {
	const META_SAVED_QUERIES = 'sabri_file26_saved_queries_v1';
	const OPTION_CONTENT_GAPS = 'sabri_file26_explicit_content_gaps_v1';
	private static $pre = array();

	public static function boot() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'capture_pre_state' ), 90, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'verify_post_state' ), 90, 3 );
	}

	public static function capture_pre_state( $result, $server, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) { return $result; }
		$route = $request->get_route();
		if ( '/sabri-search/v1/content-gap' !== $route || 'POST' !== $request->get_method() ) { return $result; }
		$params = (array) $request->get_json_params();
		$key = self::content_gap_key( isset( $params['q'] ) ? $params['q'] : '' );
		if ( ! $key ) { return $result; }
		$registry = get_option( self::OPTION_CONTENT_GAPS, array() );
		$registry = is_array( $registry ) ? $registry : array();
		$current = isset( $registry[ $key ] ) && is_array( $registry[ $key ] ) ? $registry[ $key ] : array();
		self::$pre['content_gap'] = array( 'key' => $key, 'count' => isset( $current['count'] ) ? (int) $current['count'] : 0 );
		return $result;
	}

	public static function verify_post_state( $response, $server, $request ) {
		if ( ! $request instanceof \WP_REST_Request || is_wp_error( $response ) ) { return $response; }
		$route = $request->get_route();
		$method = $request->get_method();
		$response = rest_ensure_response( $response );
		$data = $response->get_data();
		if ( ! is_array( $data ) || $response->get_status() >= 400 ) { return $response; }

		if ( '/sabri-search/v1/saved-queries' === $route && 'GET' === $method ) {
			$stored = get_user_meta( get_current_user_id(), self::META_SAVED_QUERIES, true );
			$stored = is_array( $stored ) ? $stored : array();
			$now = time();
			foreach ( $stored as $record ) {
				$expires = is_array( $record ) && ! empty( $record['expires_at'] ) ? strtotime( $record['expires_at'] . ' UTC' ) : false;
				if ( false === $expires || $expires < $now ) {
					return self::storage_failure( 'file26_saved_query_retention_failed', 'Expired saved-query state could not be durably pruned.' );
				}
			}
		}

		if ( '/sabri-search/v1/saved-queries' === $route && 'POST' === $method && ! empty( $data['id'] ) ) {
			$stored = get_user_meta( get_current_user_id(), self::META_SAVED_QUERIES, true );
			$stored = is_array( $stored ) ? $stored : array();
			$id = (string) $data['id'];
			if ( ! isset( $stored[ $id ] ) || ! is_array( $stored[ $id ] ) || (int) $stored[ $id ]['version'] !== (int) $data['version'] ) {
				return self::storage_failure( 'file26_saved_query_persistence_failed', 'The saved query was not durably stored.' );
			}
		}

		if ( preg_match( '#^/sabri-search/v1/saved-queries/([a-f0-9-]{36})$#', $route, $match ) && 'DELETE' === $method && ! empty( $data['deleted'] ) ) {
			$stored = get_user_meta( get_current_user_id(), self::META_SAVED_QUERIES, true );
			$stored = is_array( $stored ) ? $stored : array();
			if ( isset( $stored[ $match[1] ] ) ) {
				return self::storage_failure( 'file26_saved_query_delete_failed', 'The saved query deletion was not durably stored.' );
			}
		}

		if ( '/sabri-search/v1/content-gap' === $route && 'POST' === $method && ! empty( $data['accepted'] ) ) {
			$pre = isset( self::$pre['content_gap'] ) ? self::$pre['content_gap'] : array();
			$key = isset( $pre['key'] ) ? $pre['key'] : '';
			$expected = isset( $pre['count'] ) ? min( 1000000, (int) $pre['count'] + 1 ) : 1;
			$registry = get_option( self::OPTION_CONTENT_GAPS, array() );
			$registry = is_array( $registry ) ? $registry : array();
			if ( ! $key || ! isset( $registry[ $key ] ) || ! is_array( $registry[ $key ] ) || (int) $registry[ $key ]['count'] !== $expected ) {
				return self::storage_failure( 'file26_content_gap_persistence_failed', 'The content-gap submission was not durably stored.' );
			}
		}

		if ( '/sabri-search/v1/admin/editorial-radar' === $route && 'GET' === $method ) {
			if ( ! isset( $data['aggregate_metrics'] ) || ! is_array( $data['aggregate_metrics'] ) ) {
				return self::storage_failure( 'file26_editorial_radar_read_failed', 'Editorial telemetry could not be read safely.' );
			}
		}
		return $response;
	}

	private static function content_gap_key( $query ) {
		$security = new Security();
		$normalizer = new Normalizer();
		$query = $security->sanitize_query( $query );
		if ( '' === $query || $security->contains_sensitive_query( $query ) ) { return ''; }
		$normalized = substr( $normalizer->normalize( $query ), 0, 180 );
		return $normalized ? hash_hmac( 'sha256', $normalized, wp_salt( 'auth' ) ) : '';
	}

	private static function storage_failure( $code, $message ) {
		return rest_convert_error_to_response( new \WP_Error( $code, $message, array( 'status' => 503 ) ) );
	}
}
