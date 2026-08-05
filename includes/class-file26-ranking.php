<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Ranking {
	private $normalizer;

	public function __construct( Normalizer $normalizer ) {
		$this->normalizer = $normalizer;
	}

	public function policy_version() {
		return (string) DB::setting( 'policy_version', 'organic-1.0' );
	}

	public function score( array $document, $query ) {
		$query_tokens = $this->normalizer->tokens( $query );
		$title = isset( $document['normalized_title'] ) ? (string) $document['normalized_title'] : '';
		$body = isset( $document['normalized_body'] ) ? (string) $document['normalized_body'] : '';
		$relevance = 0.0;
		foreach ( $query_tokens as $token ) {
			if ( $this->contains( $title, $token ) ) {
				$relevance += 2.5;
			}
			if ( $this->contains( $body, $token ) ) {
				$relevance += 1.0;
			}
		}
		if ( $query && $this->normalizer->normalize( $query ) === $title ) {
			$relevance += 5.0;
		}

		$authority = min( 1.0, max( 0.0, (float) $document['authority_score'] ) );
		$quality = min( 1.0, max( 0.0, (float) $document['quality_score'] ) );
		$popularity = min( 0.6, log( 1 + max( 0.0, (float) $document['popularity_score'] ) ) / 10 );
		$freshness = 0.0;
		if ( ! empty( $document['freshness_at'] ) ) {
			$age_days = max( 0, ( time() - strtotime( $document['freshness_at'] . ' UTC' ) ) / DAY_IN_SECONDS );
			$half_life = in_array( $document['entity_type'], array( 'news', 'post', 'reel' ), true ) ? 30 : 365;
			$freshness = exp( -log( 2 ) * $age_days / $half_life );
		}

		$safety = in_array( $document['safety_class'], array( 'blocked', 'restricted' ), true ) ? -100 : 0;
		$score = ( $relevance * 10 ) + ( $authority * 8 ) + ( $quality * 7 ) + ( $freshness * 3 ) + $popularity + $safety;

		/**
		 * Donation, payment, purchase, advertising spend and Founder favoritism are
		 * deliberately absent from this score. Paid placement, if ever approved,
		 * must be a separately labelled lane and cannot alter this organic score.
		 */
		return round( $score, 6 );
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

		$result = array();
		$author_counts = array();
		$connector_counts = array();
		foreach ( $documents as $document ) {
			$author = $document['author_key'] ? (string) $document['author_key'] : 'none';
			$connector = (string) $document['connector_slug'];
			if ( count( $result ) < 20 ) {
				if ( isset( $author_counts[ $author ] ) && $author_counts[ $author ] >= 3 ) {
					continue;
				}
				if ( isset( $connector_counts[ $connector ] ) && $connector_counts[ $connector ] >= 8 ) {
					continue;
				}
			}
			$author_counts[ $author ] = isset( $author_counts[ $author ] ) ? $author_counts[ $author ] + 1 : 1;
			$connector_counts[ $connector ] = isset( $connector_counts[ $connector ] ) ? $connector_counts[ $connector ] + 1 : 1;
			$result[] = $document;
			if ( count( $result ) >= $limit ) {
				break;
			}
		}
		return $result;
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
