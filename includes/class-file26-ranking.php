<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Versioned, policy-driven organic ranking. */
final class Ranking {
	private $normalizer;
	private $policy_cache = array();

	public function __construct( Normalizer $normalizer ) {
		$this->normalizer = $normalizer;
	}

	public function policy_version( $context = 'search', $audience = 'public' ) {
		$policy = $this->policy( $context, $audience );
		return (string) $policy['version'];
	}

	public function policy( $context = 'search', $audience = 'public' ) {
		global $wpdb;
		$key = sanitize_key( $context ) . '|' . sanitize_key( $audience );
		if ( isset( $this->policy_cache[ $key ] ) ) {
			return $this->policy_cache[ $key ];
		}
		$defaults = array(
			'version' => (string) DB::setting( 'policy_version', 'organic-1.0' ),
			'weights' => array(
				'exact_phrase' => 18.0,
				'title_relevance' => 10.0,
				'body_relevance' => 4.0,
				'fuzzy_relevance' => 3.0,
				'authority' => 8.0,
				'quality' => 7.0,
				'freshness' => 3.0,
				'popularity' => 0.6,
				'relationship' => 2.0,
			),
			'limits' => array( 'max_author_first_page' => 3, 'max_connector_first_page' => 8 ),
		);
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) ) {
			$this->policy_cache[ $key ] = $defaults;
			return $defaults;
		}
		$table = DB::table( 'ranking_policies' );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT version,features_json FROM $table WHERE context_name=%s AND audience=%s AND status='active' ORDER BY effective_at DESC,id DESC LIMIT 1",
				sanitize_key( $context ),
				sanitize_key( $audience )
			),
			ARRAY_A
		);
		if ( $row ) {
			$features = json_decode( $row['features_json'], true );
			$features = is_array( $features ) ? $features : array();
			$weights = isset( $features['weights'] ) && is_array( $features['weights'] ) ? $features['weights'] : $features;
			foreach ( $defaults['weights'] as $name => $fallback ) {
				if ( isset( $weights[ $name ] ) && is_numeric( $weights[ $name ] ) ) {
					$defaults['weights'][ $name ] = min( 100.0, max( 0.0, (float) $weights[ $name ] ) );
				}
			}
			if ( isset( $features['limits'] ) && is_array( $features['limits'] ) ) {
				foreach ( $defaults['limits'] as $name => $fallback ) {
					if ( isset( $features['limits'][ $name ] ) ) {
						$defaults['limits'][ $name ] = min( 100, max( 1, (int) $features['limits'][ $name ] ) );
					}
				}
			}
			$defaults['version'] = (string) $row['version'];
		}
		$this->policy_cache[ $key ] = $defaults;
		return $defaults;
	}

	public function score( array $document, $query ) {
		$policy = $this->policy();
		$weights = $policy['weights'];
		$query_tokens = $this->normalizer->tokens( $query );
		$title = isset( $document['normalized_title'] ) ? (string) $document['normalized_title'] : '';
		$body = isset( $document['normalized_body'] ) ? (string) $document['normalized_body'] : '';
		$title_tokens = $this->normalizer->tokens( $title );
		$body_tokens = $this->normalizer->tokens( $body );
		$title_hits = 0.0;
		$body_hits = 0.0;
		$fuzzy_hits = 0.0;

		foreach ( $query_tokens as $token ) {
			if ( $this->contains( $title, $token ) ) {
				$title_hits += 1.0;
			} elseif ( $this->best_similarity( $token, $title_tokens ) >= 0.72 ) {
				$fuzzy_hits += $this->best_similarity( $token, $title_tokens );
			}
			if ( $this->contains( $body, $token ) ) {
				$body_hits += 1.0;
			} elseif ( $this->best_similarity( $token, array_slice( $body_tokens, 0, 120 ) ) >= 0.78 ) {
				$fuzzy_hits += 0.5 * $this->best_similarity( $token, array_slice( $body_tokens, 0, 120 ) );
			}
		}
		$phrase_hits = 0.0;
		foreach ( $this->normalizer->phrases( $query ) as $phrase ) {
			if ( $this->contains( $title, $phrase ) ) {
				$phrase_hits += 1.0;
			} elseif ( $this->contains( $body, $phrase ) ) {
				$phrase_hits += 0.55;
			}
		}

		$authority = min( 1.0, max( 0.0, (float) $document['authority_score'] ) );
		$quality = min( 1.0, max( 0.0, (float) $document['quality_score'] ) );
		$popularity = min( 1.0, log( 1 + max( 0.0, (float) $document['popularity_score'] ) ) / 10 );
		$freshness = 0.0;
		if ( ! empty( $document['freshness_at'] ) ) {
			$age_days = max( 0, ( time() - strtotime( $document['freshness_at'] . ' UTC' ) ) / DAY_IN_SECONDS );
			$half_life = in_array( $document['entity_type'], array( 'news', 'post', 'reel' ), true ) ? 30 : 365;
			$freshness = exp( -log( 2 ) * $age_days / $half_life );
		}
		$payload = isset( $document['payload'] ) && is_array( $document['payload'] ) ? $document['payload'] : array();
		$relationship = isset( $payload['relationship_score'] ) ? min( 1.0, max( 0.0, (float) $payload['relationship_score'] ) ) : 0.0;
		$safety = in_array( $document['safety_class'], array( 'blocked', 'restricted' ), true ) ? -10000.0 : 0.0;

		$score =
			( $phrase_hits * $weights['exact_phrase'] ) +
			( $title_hits * $weights['title_relevance'] ) +
			( $body_hits * $weights['body_relevance'] ) +
			( $fuzzy_hits * $weights['fuzzy_relevance'] ) +
			( $authority * $weights['authority'] ) +
			( $quality * $weights['quality'] ) +
			( $freshness * $weights['freshness'] ) +
			( $popularity * $weights['popularity'] ) +
			( $relationship * $weights['relationship'] ) +
			$safety;

		// Forbidden financial, advertising and favoritism signals are never read.
		return round( $score, 6 );
	}

	public function matches_query( array $document, $query, $threshold = 0.70 ) {
		$query_tokens = $this->normalizer->tokens( $query );
		if ( ! $query_tokens ) {
			return true;
		}
		$title = isset( $document['normalized_title'] ) ? (string) $document['normalized_title'] : '';
		$body = isset( $document['normalized_body'] ) ? (string) $document['normalized_body'] : '';
		$candidates = array_merge( $this->normalizer->tokens( $title ), array_slice( $this->normalizer->tokens( $body ), 0, 120 ) );
		$matched = 0;
		foreach ( $query_tokens as $token ) {
			if ( $this->contains( $title, $token ) || $this->contains( $body, $token ) || $this->best_similarity( $token, $candidates ) >= (float) $threshold ) {
				$matched++;
			}
		}
		return $matched >= max( 1, (int) ceil( count( $query_tokens ) * 0.5 ) );
	}

	public function sort_and_diversify( array $documents, $query, $limit ) {
		foreach ( $documents as &$document ) {
			$document['_score'] = $this->score( $document, $query );
		}
		unset( $document );
		usort( $documents, static function ( $a, $b ) {
			if ( $a['_score'] === $b['_score'] ) {
				return strcmp( $a['canonical_key'], $b['canonical_key'] );
			}
			return $a['_score'] > $b['_score'] ? -1 : 1;
		} );

		$limits = $this->policy()['limits'];
		$result = array();
		$deferred = array();
		$author_counts = array();
		$connector_counts = array();
		foreach ( $documents as $document ) {
			$author = ! empty( $document['author_key'] ) ? (string) $document['author_key'] : 'none';
			$connector = (string) $document['connector_slug'];
			$blocked = count( $result ) < 20 && (
				( isset( $author_counts[ $author ] ) && $author_counts[ $author ] >= $limits['max_author_first_page'] ) ||
				( isset( $connector_counts[ $connector ] ) && $connector_counts[ $connector ] >= $limits['max_connector_first_page'] )
			);
			if ( $blocked ) {
				$deferred[] = $document;
				continue;
			}
			$author_counts[ $author ] = isset( $author_counts[ $author ] ) ? $author_counts[ $author ] + 1 : 1;
			$connector_counts[ $connector ] = isset( $connector_counts[ $connector ] ) ? $connector_counts[ $connector ] + 1 : 1;
			$result[] = $document;
		}
		foreach ( $deferred as $document ) {
			$result[] = $document;
		}
		return array_slice( $result, 0, max( 1, (int) $limit ) );
	}

	private function best_similarity( $token, array $candidates ) {
		$best = 0.0;
		foreach ( $candidates as $candidate ) {
			$best = max( $best, $this->normalizer->token_similarity( $token, $candidate ) );
			if ( $best >= 1.0 ) {
				break;
			}
		}
		return $best;
	}

	private function contains( $haystack, $needle ) {
		if ( function_exists( 'mb_strpos' ) ) {
			return false !== mb_strpos( (string) $haystack, (string) $needle, 0, 'UTF-8' );
		}
		return false !== strpos( (string) $haystack, (string) $needle );
	}

	public function doctor_tier( array $payload ) {
		if ( empty( $payload['verified_doctor'] ) || empty( $payload['global_doctor_rank'] ) ) {
			return null;
		}
		$rank = (int) $payload['global_doctor_rank'];
		if ( $rank <= 0 ) {
			return null;
		}
		if ( $rank <= 10 ) {
			return array( 'key' => 'top_10', 'label' => __( 'Top 10 Verified Doctors', 'sabri-file26' ), 'rank' => $rank );
		}
		if ( $rank <= 100 ) {
			return array( 'key' => 'top_100', 'label' => __( 'Top 100 Verified Doctors', 'sabri-file26' ), 'rank' => $rank );
		}
		if ( $rank <= 1000 ) {
			return array( 'key' => 'top_1000', 'label' => __( 'Top 1000 Verified Doctors', 'sabri-file26' ), 'rank' => $rank );
		}
		return array( 'key' => 'all_verified', 'label' => __( 'All Verified Doctors', 'sabri-file26' ), 'rank' => $rank );
	}
}
