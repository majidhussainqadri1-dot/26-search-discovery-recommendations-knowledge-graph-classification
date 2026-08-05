<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Search {
	private $normalizer;
	private $ranking;
	private $security;
	private $connectors;

	public function __construct( Normalizer $normalizer, Ranking $ranking, Security $security, Connectors $connectors ) {
		$this->normalizer = $normalizer;
		$this->ranking = $ranking;
		$this->security = $security;
		$this->connectors = $connectors;
	}

	public function run( array $request ) {
		global $wpdb;
		$trace = $this->security->trace_id();
		$started_at = microtime( true );
		if ( ! DB::setting( 'activated', false ) || ! DB::setting( 'public_search_enabled', true ) ) {
			return new \WP_Error( 'file26_not_activated', 'Search is not yet activated. The approved connector and staging gates must be completed first.', array( 'status' => 503, 'trace_id' => $trace ) );
		}
		$query = $this->security->sanitize_query( isset( $request['q'] ) ? $request['q'] : '' );
		$locale = isset( $request['locale'] ) ? substr( sanitize_text_field( $request['locale'] ), 0, 20 ) : determine_locale();
		$limit = isset( $request['limit'] ) ? (int) $request['limit'] : (int) DB::setting( 'results_per_page', 20 );
		$limit = max( 1, min( (int) DB::setting( 'max_results_per_page', 30 ), $limit ) );
		$filters = $this->sanitize_filters( isset( $request['filters'] ) ? $request['filters'] : array() );
		$cursor_context = hash( 'sha256', wp_json_encode( array( 'q' => $this->normalizer->normalize( $query ), 'locale' => $locale, 'filters' => $filters, 'limit' => $limit ) ) );
		$offset = 0;
		if ( ! empty( $request['cursor'] ) ) {
			$cursor = $this->security->verify_cursor( $request['cursor'] );
			if ( ! $cursor || empty( $cursor['p'] ) || empty( $cursor['h'] ) || $cursor['p'] !== $this->ranking->policy_version() || ! hash_equals( $cursor_context, (string) $cursor['h'] ) ) {
				return new \WP_Error( 'file26_invalid_cursor', 'The result cursor is invalid or expired.', array( 'status' => 400, 'trace_id' => $trace ) );
			}
			$offset = max( 0, min( 10000, (int) $cursor['o'] ) );
		}
		if ( ! $this->security->rate_limit( 'search|' . $this->security->client_bucket(), 120, 60 ) ) {
			return new \WP_Error( 'file26_rate_limited', 'Too many search requests. Please retry shortly.', array( 'status' => 429, 'trace_id' => $trace ) );
		}
		$audience = $this->security->audience();
		$public_cache = empty( $audience['authenticated'] ) && empty( $filters['availability'] );
		$cache_key = 'search:' . hash( 'sha256', wp_json_encode( array(
			'q' => $query,
			'locale' => $locale,
			'filters' => $filters,
			'offset' => $offset,
			'limit' => $limit,
			'policy' => $this->ranking->policy_version(),
		) ) );
		if ( $public_cache ) {
			$cached = wp_cache_get( $cache_key, 'sabri_file26' );
			if ( is_array( $cached ) ) {
				$cached['trace_id'] = $trace;
				return $cached;
			}
		}

		$table = DB::table( 'documents' );
		$where = array( "state IN ('published','active','corrected','retracted')" );
		$args = array();
		if ( empty( $audience['authenticated'] ) || empty( $audience['valid'] ) ) {
			$where[] = "visibility='public'";
		} else {
			$where[] = "visibility IN ('public','members','entitled','minor_guarded')";
		}
		if ( $locale ) {
			$where[] = '(locale=%s OR locale=%s OR locale=%s)';
			$args[] = $locale;
			$args[] = substr( $locale, 0, 2 );
			$args[] = 'und';
		}
		$filter_map = array(
			'entity_type' => 'entity_type',
			'country' => 'country',
			'location' => 'location',
			'availability' => 'availability',
			'connector' => 'connector_slug',
			'domain' => 'domain_name',
		);
		foreach ( $filter_map as $key => $column ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$where[] = "$column=%s";
				$args[] = $filters[ $key ];
			}
		}
		if ( ! empty( $filters['author'] ) ) {
			$where[] = 'author_key=%s';
			$args[] = $filters['author'];
		}
		if ( ! empty( $filters['topic'] ) ) {
			$where[] = 'topic_ids LIKE %s';
			$args[] = '%"' . $wpdb->esc_like( $filters['topic'] ) . '"%';
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'freshness_at >= %s';
			$args[] = $filters['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'freshness_at <= %s';
			$args[] = $filters['date_to'] . ' 23:59:59';
		}

		$expansions = $this->normalizer->expansions( $query );
		$tokens = array();
		foreach ( $expansions as $expansion ) {
			$tokens = array_merge( $tokens, $this->normalizer->tokens( $expansion ) );
		}
		$tokens = array_slice( array_values( array_unique( $tokens ) ), 0, 12 );
		if ( $tokens ) {
			$search_parts = array();
			foreach ( $tokens as $token ) {
				$search_parts[] = '(normalized_title LIKE %s OR normalized_body LIKE %s)';
				$like = '%' . $wpdb->esc_like( $token ) . '%';
				$args[] = $like;
				$args[] = $like;
			}
			$where[] = '(' . implode( ' OR ', $search_parts ) . ')';
		}

		$candidate_limit = max( 50, min( 500, (int) DB::setting( 'candidate_limit', 200 ) ) );
		$sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . " ORDER BY freshness_at DESC, canonical_key ASC LIMIT $candidate_limit";
		if ( $args ) {
			$sql = $wpdb->prepare( $sql, $args );
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$eligible = array();
		$partial = array();
		foreach ( $rows as $row ) {
			$row['payload'] = json_decode( $row['payload'], true );
			$row['payload'] = is_array( $row['payload'] ) ? $row['payload'] : array();
			$row['topic_ids_array'] = json_decode( $row['topic_ids'], true );
			$row['topic_ids_array'] = is_array( $row['topic_ids_array'] ) ? $row['topic_ids_array'] : array();
			if ( ! $this->security->can_view_visibility( $row['visibility'], $audience, $row['payload'] ) ) {
				continue;
			}
			if ( ! $this->connectors->can_view( $row['connector_slug'], $row, $audience ) ) {
				continue;
			}
			$eligible[] = $row;
		}
		$ranked = $this->ranking->sort_and_diversify( $eligible, $query, $offset + $limit + 1 );
		if ( ! empty( $filters['sort'] ) && in_array( $filters['sort'], array( 'newest', 'oldest', 'authority' ), true ) ) {
			usort( $ranked, static function ( $a, $b ) use ( $filters ) {
				if ( 'authority' === $filters['sort'] ) { return (float) $a['authority_score'] === (float) $b['authority_score'] ? strcmp( $a['canonical_key'], $b['canonical_key'] ) : ( (float) $a['authority_score'] > (float) $b['authority_score'] ? -1 : 1 ); }
				$at = strtotime( $a['freshness_at'] . ' UTC' ); $bt = strtotime( $b['freshness_at'] . ' UTC' );
				return 'oldest' === $filters['sort'] ? $at <=> $bt : $bt <=> $at;
			} );
		}

		$page_rows = array_slice( $ranked, $offset, $limit );
		$has_more = count( $ranked ) > ( $offset + $limit );
		$results = array();
		foreach ( $page_rows as $row ) {
			$results[] = $this->to_result( $row );
		}
		$response = array(
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
			'query' => $query,
			'query_normalized' => $this->normalizer->normalize( $query ),
			'policy_version' => $this->ranking->policy_version(),
			'results' => $results,
			'facets' => $this->facets_from_rows( $eligible ),
			'next_cursor' => $has_more ? $this->security->sign_cursor( array( 'o' => $offset + $limit, 'p' => $this->ranking->policy_version(), 'h' => $cursor_context ) ) : null,
			'partial' => (bool) $partial,
			'partial_domains' => $partial,
			'personalized' => false,
			'trace_id' => $trace,
		);
		if ( DB::setting( 'telemetry_enabled', true ) ) {
			$this->record_metric( $query, $locale, count( $results ), $trace, ( microtime( true ) - $started_at ) * 1000 );
		}
		if ( $public_cache ) {
			wp_cache_set( $cache_key, $response, 'sabri_file26', 300 );
		}
		return $response;
	}

	public function suggest( $prefix, $locale = null, $limit = 8 ) {
		global $wpdb;
		if ( ! DB::setting( 'activated', false ) || ! DB::setting( 'public_search_enabled', true ) ) {
			return array();
		}
		$prefix = $this->security->sanitize_query( $prefix );
		if ( ! $this->normalizer->prefix_is_safe( $prefix ) || $this->security->contains_sensitive_query( $prefix ) ) {
			return array();
		}
		if ( ! $this->security->rate_limit( 'suggest|' . $this->security->client_bucket(), 180, 60 ) ) {
			return array();
		}
		$normalized = $this->normalizer->normalize( $prefix );
		$like = $wpdb->esc_like( $normalized ) . '%';
		$table = DB::table( 'documents' );
		$locale = $locale ? substr( sanitize_text_field( $locale ), 0, 20 ) : determine_locale();
		$limit = max( 1, min( 10, (int) $limit ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table
				WHERE state IN ('published','active','corrected')
				AND visibility='public'
				AND normalized_title LIKE %s
				AND (locale=%s OR locale=%s OR locale='und')
				ORDER BY authority_score DESC,quality_score DESC,title ASC
				LIMIT %d",
				$like, $locale, substr( $locale, 0, 2 ), min( 50, $limit * 5 )
			), ARRAY_A
		);
		$output = array();
		$audience = array( 'authenticated' => false, 'valid' => true, 'is_minor' => false, 'guardian_verified' => false, 'entitlements' => array() );
		foreach ( $rows as $row ) {
			$row['payload'] = json_decode( $row['payload'], true );
			$row['payload'] = is_array( $row['payload'] ) ? $row['payload'] : array();
			if ( ! $this->connectors->can_view( $row['connector_slug'], $row, $audience ) ) { continue; }
			$output[] = array( 'key' => $row['canonical_key'], 'label' => $row['title'], 'entity_type' => $row['entity_type'], 'url' => $row['canonical_url'] );
			if ( count( $output ) >= $limit ) { break; }
		}
		return $output;
	}

	private function sanitize_filters( $filters ) {
		if ( ! is_array( $filters ) ) {
			return array();
		}
		$clean = array();
		foreach ( array( 'entity_type', 'country', 'location', 'availability', 'connector', 'domain', 'topic', 'sort' ) as $key ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$clean[ $key ] = sanitize_key( $filters[ $key ] );
			}
		}
		if ( ! empty( $filters['author'] ) ) {
			$clean['author'] = sanitize_text_field( $filters['author'] );
		}
		foreach ( array( 'date_from', 'date_to' ) as $date_key ) {
			if ( ! empty( $filters[ $date_key ] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters[ $date_key ] ) ) {
				$clean[ $date_key ] = $filters[ $date_key ];
			}
		}
		return $clean;
	}

	private function to_result( array $row ) {
		$payload = is_array( $row['payload'] ) ? $row['payload'] : array();
		$actions = array(
			'open' => array(
				'url' => $row['canonical_url'],
				'label' => __( 'Open', 'sabri-file26' ),
				'icon' => 'external',
			),
		);
		if ( ! empty( $payload['download_allowed'] ) && ! empty( $payload['download_url'] ) ) {
			$actions['download'] = array(
				'url' => $payload['download_url'],
				'label' => ! empty( $payload['download_label'] ) ? $payload['download_label'] : __( 'Download', 'sabri-file26' ),
				'icon' => 'download',
			);
		}
		return array(
			'key' => $row['canonical_key'],
			'entity_type' => $row['entity_type'],
			'title' => $row['title'],
			'excerpt' => $row['excerpt'],
			'url' => $row['canonical_url'],
			'locale' => $row['locale'],
			'state' => $row['state'],
			'author_key' => $row['author_key'],
			'topics' => $row['topic_ids_array'],
			'country' => $row['country'],
			'location' => $row['location'],
			'availability' => $row['availability'],
			'score' => (float) $row['_score'],
			'explanation_codes' => $this->explanation_codes( $row ),
			'doctor_tier' => 'doctor' === $row['entity_type'] ? $this->ranking->doctor_tier( $payload ) : null,
			'payload' => $payload,
			'actions' => $actions,
		);
	}

	private function explanation_codes( array $row ) {
		$codes = array( 'query_relevance' );
		if ( (float) $row['authority_score'] >= 0.7 ) {
			$codes[] = 'verified_authority';
		}
		if ( (float) $row['quality_score'] >= 0.7 ) {
			$codes[] = 'source_quality';
		}
		if ( in_array( $row['state'], array( 'corrected', 'retracted' ), true ) ) {
			$codes[] = 'status_disclosed';
		}
		return $codes;
	}

	private function facets_from_rows( array $rows ) {
		$facets = array( 'entity_type' => array(), 'country' => array(), 'locale' => array(), 'availability' => array() );
		foreach ( $rows as $row ) {
			foreach ( array_keys( $facets ) as $field ) {
				$value = isset( $row[ $field ] ) ? (string) $row[ $field ] : '';
				if ( '' === $value ) {
					continue;
				}
				$facets[ $field ][ $value ] = isset( $facets[ $field ][ $value ] ) ? $facets[ $field ][ $value ] + 1 : 1;
			}
		}
		foreach ( $facets as &$values ) {
			arsort( $values );
		}
		return $facets;
	}

	private function record_metric( $query, $locale, $count, $trace, $latency_ms ) {
		global $wpdb;
		$sensitive = $this->security->contains_sensitive_query( $query );
		$class = $sensitive ? 'sensitive_dropped' : ( '' === $query ? 'browse' : 'search' );
		// Exact query text is never retained, even as a reversible/dictionary-prone bucket.
		$bucket = hash_hmac( 'sha256', $class . '|' . substr( sanitize_text_field( $locale ), 0, 20 ) . '|' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) );
		$table = DB::table( 'metrics' );
		$sql = $wpdb->prepare(
			"INSERT INTO $table (metric_date,metric_key,bucket_hash,locale,count_value,sum_value,updated_at)
			VALUES (%s,%s,%s,%s,1,%f,%s)
			ON DUPLICATE KEY UPDATE count_value=count_value+1,sum_value=sum_value+VALUES(sum_value),updated_at=VALUES(updated_at)",
			gmdate( 'Y-m-d' ),
			$count ? 'search_with_results' : 'search_zero_result',
			$bucket,
			substr( sanitize_text_field( $locale ), 0, 20 ),
			max( 0, (float) $latency_ms ),
			DB::now()
		);
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
