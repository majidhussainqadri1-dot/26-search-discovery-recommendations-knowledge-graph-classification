<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

/** Federated search with production-lane isolation and truthful partial-state reporting. */
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
			return new \WP_Error( 'file26_not_activated', 'Search is not yet activated. Approved connector and staging gates are required.', array( 'status' => 503, 'trace_id' => $trace ) );
		}
		$query = $this->security->sanitize_query( isset( $request['q'] ) ? $request['q'] : '' );
		$locale = isset( $request['locale'] ) ? substr( sanitize_text_field( $request['locale'] ), 0, 20 ) : determine_locale();
		$limit = isset( $request['limit'] ) ? (int) $request['limit'] : (int) DB::setting( 'results_per_page', 20 );
		$limit = max( 1, min( (int) DB::setting( 'max_results_per_page', 30 ), $limit ) );
		$filters = $this->sanitize_filters( isset( $request['filters'] ) ? $request['filters'] : array() );
		$policy_version = $this->ranking->policy_version();
		$cursor_context = hash( 'sha256', wp_json_encode( array(
			'q' => $this->normalizer->normalize( $query ), 'locale' => $locale, 'filters' => $filters,
			'limit' => $limit, 'policy' => $policy_version,
		) ) );
		$offset = 0;
		if ( ! empty( $request['cursor'] ) ) {
			$cursor = $this->security->verify_cursor( $request['cursor'] );
			if ( ! $cursor || empty( $cursor['p'] ) || empty( $cursor['h'] ) || $cursor['p'] !== $policy_version || ! hash_equals( $cursor_context, (string) $cursor['h'] ) ) {
				return new \WP_Error( 'file26_invalid_cursor', 'The result cursor is invalid or expired.', array( 'status' => 400, 'trace_id' => $trace ) );
			}
			$offset = max( 0, min( 100000, (int) $cursor['o'] ) );
		}
		if ( ! $this->security->rate_limit( 'search|' . $this->security->client_bucket(), 120, 60 ) ) {
			return new \WP_Error( 'file26_rate_limited', 'Too many search requests. Please retry shortly.', array( 'status' => 429, 'trace_id' => $trace ) );
		}

		$audience = $this->security->audience();
		$sensitive_query = $this->security->contains_sensitive_query( $query );
		$public_cache = empty( $audience['authenticated'] ) && empty( $filters['availability'] ) && ! $sensitive_query;
		$cache_key = 'search:' . hash( 'sha256', wp_json_encode( array(
			'q' => $query, 'locale' => $locale, 'filters' => $filters, 'offset' => $offset,
			'limit' => $limit, 'policy' => $policy_version,
		) ) );
		if ( $public_cache ) {
			$cached = wp_cache_get( $cache_key, 'sabri_file26' );
			if ( is_array( $cached ) ) {
				$cached['trace_id'] = $trace;
				return $cached;
			}
		}

		$where = array( "d.state IN ('published','active','corrected','retracted')" );
		$args = array();
		if ( empty( $audience['authenticated'] ) || empty( $audience['valid'] ) ) {
			$where[] = "d.visibility='public'";
		} else {
			$where[] = "d.visibility IN ('public','members','entitled','minor_guarded')";
		}
		if ( $locale ) {
			$where[] = '(d.locale=%s OR d.locale=%s OR d.locale=%s)';
			$args[] = $locale;
			$args[] = substr( $locale, 0, 2 );
			$args[] = 'und';
		}
		$filter_map = array(
			'entity_type' => 'd.entity_type', 'country' => 'd.country', 'location' => 'd.location',
			'availability' => 'd.availability', 'connector' => 'd.connector_slug', 'domain' => 'd.domain_name',
			'language' => 'd.locale',
		);
		foreach ( $filter_map as $key => $column ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$where[] = "$column=%s";
				$args[] = $filters[ $key ];
			}
		}
		if ( ! empty( $filters['author'] ) ) {
			$where[] = 'd.author_key=%s';
			$args[] = $filters['author'];
		}
		if ( ! empty( $filters['topic'] ) ) {
			$where[] = "(d.topic_ids LIKE %s OR EXISTS (SELECT 1 FROM " . DB::table( 'classifications' ) . " fc WHERE fc.object_key=d.canonical_key AND fc.term_uuid=%s AND fc.status IN ('approved','corrected')))";
			$args[] = '%"' . $wpdb->esc_like( $filters['topic'] ) . '"%';
			$args[] = $filters['topic'];
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'd.freshness_at >= %s';
			$args[] = $filters['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'd.freshness_at <= %s';
			$args[] = $filters['date_to'] . ' 23:59:59';
		}

		$search_terms = $this->normalizer->retrieval_terms( $query );
		$phrases = $this->normalizer->phrases( $query );
		if ( $search_terms || $phrases ) {
			$search_parts = array();
			foreach ( $phrases as $phrase ) {
				$search_parts[] = '(d.normalized_title LIKE %s OR d.normalized_body LIKE %s)';
				$like = '%' . $wpdb->esc_like( $phrase ) . '%';
				$args[] = $like;
				$args[] = $like;
			}
			foreach ( $search_terms as $term ) {
				$search_parts[] = '(d.normalized_title LIKE %s OR d.normalized_body LIKE %s)';
				$like = '%' . $wpdb->esc_like( $term ) . '%';
				$args[] = $like;
				$args[] = $like;
			}
			$where[] = '(' . implode( ' OR ', $search_parts ) . ')';
		}

		$documents = DB::table( 'documents' );
		$connectors = DB::table( 'connectors' );
		$batch_size = max( 50, min( 500, (int) DB::setting( 'search_scan_batch', 250 ) ) );
		$max_scan = max( $batch_size, min( 50000, (int) DB::setting( 'max_candidate_scan', 5000 ) ) );
		$scan_offset = 0;
		$eligible = array();
		$scan_limit_hit = false;
		$order = '' === $query ? 'd.freshness_at DESC,d.canonical_key ASC' : 'd.canonical_key ASC';
		$base_sql = "SELECT d.* FROM $documents d INNER JOIN $connectors c ON c.slug=d.connector_slug AND c.status='active' WHERE " . implode( ' AND ', $where ) . " ORDER BY $order";
		while ( $scan_offset < $max_scan ) {
			$sql = $base_sql . ' LIMIT %d OFFSET %d';
			$query_args = array_merge( $args, array( $batch_size, $scan_offset ) );
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				$row = $this->hydrate_row( $row );
				if ( ! $this->security->can_view_visibility( $row['visibility'], $audience, $row['payload'] ) ) {
					continue;
				}
				if ( ! $this->connectors->can_view( $row['connector_slug'], $row, $audience ) ) {
					continue;
				}
				if ( $query && ! $this->ranking->matches_query( $row, $query ) ) {
					continue;
				}
				$eligible[] = $row;
			}
			$scan_offset += count( $rows );
			if ( count( $rows ) < $batch_size ) {
				break;
			}
		}
		if ( $scan_offset >= $max_scan ) {
			$scan_limit_hit = true;
		}

		// Active, public, provenance-governed graph edges contribute only a bounded relationship signal.
		$eligible = $this->apply_graph_relationship_scores( $eligible );
		$ranked = $this->ranking->sort_and_diversify( $eligible, $query, count( $eligible ) );
		if ( ! empty( $filters['sort'] ) && in_array( $filters['sort'], array( 'newest', 'oldest', 'authority' ), true ) ) {
			usort( $ranked, static function ( $a, $b ) use ( $filters ) {
				if ( 'authority' === $filters['sort'] ) {
					return (float) $a['authority_score'] === (float) $b['authority_score'] ? strcmp( $a['canonical_key'], $b['canonical_key'] ) : ( (float) $a['authority_score'] > (float) $b['authority_score'] ? -1 : 1 );
				}
				$at = strtotime( $a['freshness_at'] . ' UTC' );
				$bt = strtotime( $b['freshness_at'] . ' UTC' );
				if ( $at === $bt ) {
					return strcmp( $a['canonical_key'], $b['canonical_key'] );
				}
				return 'oldest' === $filters['sort'] ? $at <=> $bt : $bt <=> $at;
			} );
		}
		$page_rows = array_slice( $ranked, $offset, $limit );
		$has_more = count( $ranked ) > ( $offset + $limit );
		$results = array();
		foreach ( $page_rows as $row ) {
			$results[] = $this->to_result( $row, $query );
		}
		$partial = $this->connectors->degraded_domains();
		if ( $scan_limit_hit ) {
			$partial[] = array( 'connector' => '*', 'owner_file' => 'File 26', 'status' => 'bounded', 'health' => 'scan_limit', 'last_health' => null );
		}
		$response = array(
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
			'query' => $query,
			'query_normalized' => $this->normalizer->normalize( $query ),
			'policy_version' => $policy_version,
			'results' => $results,
			'facets' => $this->facets_from_rows( $eligible ),
			'next_cursor' => $has_more ? $this->security->sign_cursor( array( 'o' => $offset + $limit, 'p' => $policy_version, 'h' => $cursor_context ) ) : null,
			'partial' => (bool) $partial,
			'partial_domains' => $partial,
			'personalized' => false,
			'scanned_candidates' => $scan_offset,
			'eligible_candidates' => count( $eligible ),
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
		$connector_table = DB::table( 'connectors' );
		$locale = $locale ? substr( sanitize_text_field( $locale ), 0, 20 ) : determine_locale();
		$limit = max( 1, min( 10, (int) $limit ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.* FROM $table d INNER JOIN $connector_table c ON c.slug=d.connector_slug AND c.status='active'
				 WHERE d.state IN ('published','active','corrected') AND d.visibility='public' AND d.normalized_title LIKE %s
				 AND (d.locale=%s OR d.locale=%s OR d.locale='und')
				 ORDER BY d.authority_score DESC,d.quality_score DESC,d.title ASC LIMIT %d",
				$like, $locale, substr( $locale, 0, 2 ), min( 50, $limit * 5 )
			), ARRAY_A
		);
		$output = array();
		$audience = array( 'authenticated' => false, 'valid' => true, 'is_minor' => false, 'guardian_verified' => false, 'entitlements' => array() );
		foreach ( $rows as $row ) {
			$row = $this->hydrate_row( $row );
			if ( ! $this->connectors->can_view( $row['connector_slug'], $row, $audience ) ) {
				continue;
			}
			$output[] = array( 'key' => $row['canonical_key'], 'label' => $row['title'], 'entity_type' => $row['entity_type'], 'url' => $row['canonical_url'] );
			if ( count( $output ) >= $limit ) {
				break;
			}
		}
		return $output;
	}

	private function apply_graph_relationship_scores( array $rows ) {
		global $wpdb;
		if ( count( $rows ) < 2 ) {
			return $rows;
		}
		$index = array();
		foreach ( $rows as $position => $row ) {
			$index[ $row['canonical_key'] ] = $position;
		}
		$keys = array_keys( $index );
		$counts = array_fill_keys( $keys, 0 );
		$seen = array();
		$edge_table = DB::table( 'edges' );
		foreach ( array_chunk( $keys, 200 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$args = array_merge( $chunk, $chunk, array( 5000 ) );
			$sql = $wpdb->prepare(
				"SELECT edge_uuid,source_key,target_key FROM $edge_table WHERE state='active' AND visibility='public' AND (source_key IN ($placeholders) OR target_key IN ($placeholders)) ORDER BY edge_uuid LIMIT %d",
				$args
			);
			$edges = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( (array) $edges as $edge ) {
				if ( isset( $seen[ $edge['edge_uuid'] ] ) ) {
					continue;
				}
				$seen[ $edge['edge_uuid'] ] = true;
				if ( isset( $index[ $edge['source_key'] ], $index[ $edge['target_key'] ] ) ) {
					$counts[ $edge['source_key'] ]++;
					$counts[ $edge['target_key'] ]++;
				}
			}
		}
		foreach ( $counts as $key => $count ) {
			$position = $index[ $key ];
			$rows[ $position ]['payload']['relationship_score'] = min( 1.0, $count / 5 );
			$rows[ $position ]['payload']['graph_relation_count'] = min( 1000, (int) $count );
		}
		return $rows;
	}

	private function hydrate_row( array $row ) {
		$row['payload'] = json_decode( isset( $row['payload'] ) ? $row['payload'] : '', true );
		$row['payload'] = is_array( $row['payload'] ) ? $row['payload'] : array();
		$row['topic_ids_array'] = json_decode( isset( $row['topic_ids'] ) ? $row['topic_ids'] : '', true );
		$row['topic_ids_array'] = is_array( $row['topic_ids_array'] ) ? $row['topic_ids_array'] : array();
		return $row;
	}

	private function sanitize_filters( $filters ) {
		if ( ! is_array( $filters ) ) {
			return array();
		}
		$clean = array();
		foreach ( array( 'entity_type', 'country', 'location', 'availability', 'connector', 'domain', 'topic', 'sort', 'language' ) as $key ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$clean[ $key ] = 'language' === $key ? substr( sanitize_text_field( $filters[ $key ] ), 0, 20 ) : sanitize_key( $filters[ $key ] );
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

	private function to_result( array $row, $query ) {
		$payload = is_array( $row['payload'] ) ? $row['payload'] : array();
		$actions = array( 'open' => array( 'url' => $row['canonical_url'], 'label' => __( 'Open', 'sabri-file26' ), 'icon' => 'external' ) );
		if ( ! empty( $payload['download_allowed'] ) && ! empty( $payload['download_url'] ) ) {
			$actions['download'] = array(
				'url' => $payload['download_url'],
				'label' => ! empty( $payload['download_label'] ) ? $payload['download_label'] : __( 'Download', 'sabri-file26' ),
				'icon' => 'download',
			);
		}
		return array(
			'key' => $row['canonical_key'], 'entity_type' => $row['entity_type'], 'title' => $row['title'],
			'excerpt' => $row['excerpt'], 'url' => $row['canonical_url'], 'locale' => $row['locale'],
			'state' => $row['state'], 'author_key' => $row['author_key'], 'topics' => $row['topic_ids_array'],
			'country' => $row['country'], 'location' => $row['location'], 'availability' => $row['availability'],
			'score' => isset( $row['_score'] ) ? (float) $row['_score'] : 0.0,
			'explanation_codes' => $this->explanation_codes( $row, $query ),
			'doctor_tier' => 'doctor' === $row['entity_type'] ? $this->ranking->doctor_tier( $payload ) : null,
			'payload' => $payload, 'actions' => $actions,
		);
	}

	private function explanation_codes( array $row, $query ) {
		$codes = array( 'query_relevance', 'policy_' . sanitize_key( $this->ranking->policy_version() ) );
		foreach ( $this->normalizer->phrases( $query ) as $phrase ) {
			if ( false !== $this->strpos( $row['normalized_title'], $phrase ) || false !== $this->strpos( $row['normalized_body'], $phrase ) ) {
				$codes[] = 'exact_phrase';
				break;
			}
		}
		if ( (float) $row['authority_score'] >= 0.7 ) {
			$codes[] = 'verified_authority';
		}
		if ( (float) $row['quality_score'] >= 0.7 ) {
			$codes[] = 'source_quality';
		}
		if ( ! empty( $row['payload']['relationship_score'] ) ) {
			$codes[] = 'knowledge_graph_relation';
		}
		if ( in_array( $row['state'], array( 'corrected', 'retracted' ), true ) ) {
			$codes[] = 'status_disclosed';
		}
		return array_values( array_unique( $codes ) );
	}

	private function facets_from_rows( array $rows ) {
		$facets = array( 'entity_type' => array(), 'country' => array(), 'locale' => array(), 'availability' => array(), 'topic' => array() );
		foreach ( $rows as $row ) {
			foreach ( array( 'entity_type', 'country', 'locale', 'availability' ) as $field ) {
				$value = isset( $row[ $field ] ) ? (string) $row[ $field ] : '';
				if ( '' !== $value ) {
					$facets[ $field ][ $value ] = isset( $facets[ $field ][ $value ] ) ? $facets[ $field ][ $value ] + 1 : 1;
				}
			}
			foreach ( isset( $row['topic_ids_array'] ) ? $row['topic_ids_array'] : array() as $topic ) {
				$topic = sanitize_text_field( $topic );
				if ( $topic ) {
					$facets['topic'][ $topic ] = isset( $facets['topic'][ $topic ] ) ? $facets['topic'][ $topic ] + 1 : 1;
				}
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
		$bucket = hash_hmac( 'sha256', $class . '|' . substr( sanitize_text_field( $locale ), 0, 20 ) . '|' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) );
		$table = DB::table( 'metrics' );
		$sql = $wpdb->prepare(
			"INSERT INTO $table (metric_date,metric_key,bucket_hash,locale,count_value,sum_value,updated_at)
			 VALUES (%s,%s,%s,%s,1,%f,%s)
			 ON DUPLICATE KEY UPDATE count_value=count_value+1,sum_value=sum_value+VALUES(sum_value),updated_at=VALUES(updated_at)",
			gmdate( 'Y-m-d' ), $count ? 'search_with_results' : 'search_zero_result', $bucket,
			substr( sanitize_text_field( $locale ), 0, 20 ), max( 0, (float) $latency_ms ), DB::now()
		);
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function strpos( $haystack, $needle ) {
		if ( function_exists( 'mb_strpos' ) ) {
			return mb_strpos( (string) $haystack, (string) $needle, 0, 'UTF-8' );
		}
		return strpos( (string) $haystack, (string) $needle );
	}
}
