<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/**
 * FR-002/003/004/006: validate every incoming indexed projection against
 * its current canonical owner, whether the serving document exists or not.
 * Local version order cannot prove native rights if a delayed published event
 * arrived after a source correction, deletion or visibility downgrade.
 *
 * This is deliberately a bounded read-only owner proof. It does NOT store an
 * indefinite deleted identity, override the native owner, or claim an
 * owner-event ACK / durable watermark has been implemented.
 */
final class Owner_Freshness {
	/**
	 * Called for a would-be published/accessible serving write or shadow
	 * staging operation. Caller holds the File26 mutation/generation lock.
	 * The historical name is retained for source/API backwards compatibility.
	 *
	 * The native owner must provide a complete current public-safe document.
	 * Unknown, unavailable, stale, conflicting and malformed source data deny.
	 */
	public static function attest_cold_document( Connectors $connectors, array $document ) {
		$slug = isset( $document['connector_slug'] ) && is_string( $document['connector_slug'] ) ? $document['connector_slug'] : '';
		$manifest = $slug !== '' ? $connectors->get( $slug ) : null;
		if ( ! is_array( $manifest ) || ! isset( $manifest['status'], $manifest['fetch_object'] )
			|| ! in_array( $manifest['status'], array( 'shadow', 'approved', 'active' ), true )
			|| ! is_callable( $manifest['fetch_object'] ) ) {
			return new \WP_Error( 'file26_cold_owner_attestation_unavailable', 'A current canonical owner fetch contract is mandatory for cold indexing.' );
		}
		if ( ! isset( $document['entity_type'], $document['object_id'], $document['object_version'] )
			|| ! is_string( $document['entity_type'] ) || ! is_string( $document['object_id'] ) ) {
			return new \WP_Error( 'file26_cold_owner_identity_invalid', 'Cold source identity is incomplete.' );
		}
		try {
			$fresh = call_user_func( $manifest['fetch_object'], $document['entity_type'], $document['object_id'] );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'file26_cold_owner_fetch_failed', 'Current canonical owner could not be read.' );
		}
		if ( is_wp_error( $fresh ) || ! is_array( $fresh ) ) {
			return new \WP_Error( 'file26_cold_owner_attestation_denied', 'Current canonical owner did not attest that the object remains indexable.' );
		}
		// Do not coerce 1.5, scientific notation, booleans, huge versions or
		// non-scalars into a deceptively equal object_version/sequence.
		if ( ! self::same_positive_version( isset( $fresh['object_version'] ) ? $fresh['object_version'] : null, $document['object_version'] ) ) {
			return new \WP_Error( 'file26_cold_owner_version_conflict', 'Indexing refused an obsolete or incompatible owner version.' );
		}
		if ( isset( $fresh['source_event_sequence'] )
			&& ! self::same_nonnegative_version( $fresh['source_event_sequence'], isset( $document['source_event_sequence'] ) ? $document['source_event_sequence'] : null ) ) {
			return new \WP_Error( 'file26_cold_owner_event_conflict', 'Indexing refused an outdated native owner event sequence.' );
		}
		$expected = array(
			'connector_slug' => 'connector_slug',
			'domain_name' => 'domain',
			'object_id' => 'object_id',
			'entity_type' => 'entity_type',
			'state' => 'state',
			'visibility' => 'visibility',
			'locale' => 'locale',
			'title' => 'title',
			'canonical_url' => 'canonical_url',
		);
		foreach ( $expected as $indexed_key => $owner_key ) {
			if ( ! array_key_exists( $owner_key, $fresh ) || ! array_key_exists( $indexed_key, $document )
				|| ! is_string( $fresh[ $owner_key ] ) || ! is_string( $document[ $indexed_key ] ) ) {
				return new \WP_Error( 'file26_cold_owner_projection_incomplete', 'Native owner did not return a complete current search projection.' );
			}
			$owner_value = $fresh[ $owner_key ];
			$stored_value = $document[ $indexed_key ];
			if ( in_array( $owner_key, array( 'connector_slug', 'domain', 'entity_type', 'state', 'visibility' ), true ) ) {
				$owner_value = sanitize_key( $owner_value );
			} elseif ( 'title' === $owner_key ) {
				$owner_value = sanitize_text_field( $owner_value );
			} elseif ( 'locale' === $owner_key ) {
				$owner_value = substr( sanitize_text_field( $owner_value ), 0, 20 );
			}
			if ( ! hash_equals( $stored_value, $owner_value ) ) {
				return new \WP_Error( 'file26_cold_owner_projection_conflict', 'Current canonical owner projection disagrees with the stale index event.' );
			}
		}
		// A source can keep its title/URL/version while the public abstract,
		// searchable body, region or safety label is revoked. Recheck all
		// material fields used for retrieval, excerpts or public filtering.
		if ( isset( $fresh['excerpt'] ) && ! is_string( $fresh['excerpt'] ) ) {
			return new \WP_Error( 'file26_cold_owner_projection_incomplete', 'Current owner abstract is malformed.' );
		}
		$fresh_excerpt = isset( $fresh['excerpt'] ) ? wp_strip_all_tags( $fresh['excerpt'], true ) : '';
		if ( ! isset( $document['excerpt'] ) || ! is_string( $document['excerpt'] )
			|| ! hash_equals( $document['excerpt'], $fresh_excerpt ) ) {
			return new \WP_Error( 'file26_cold_owner_projection_conflict', 'Current canonical owner excerpt differs from the indexed source event.' );
		}
		if ( isset( $fresh['search_text'] ) && ! is_string( $fresh['search_text'] ) ) {
			return new \WP_Error( 'file26_cold_owner_projection_incomplete', 'Current owner searchable text is malformed.' );
		}
		if ( isset( $document['normalized_body'] ) ) {
			$fresh_body = isset( $fresh['search_text'] ) ? wp_strip_all_tags( $fresh['search_text'], true ) : $fresh_excerpt;
			$normalizer = new Normalizer();
			if ( ! is_string( $document['normalized_body'] )
				|| ! hash_equals( $document['normalized_body'], $normalizer->normalize( $fresh_body ) ) ) {
				return new \WP_Error( 'file26_cold_owner_projection_conflict', 'Current owner searchable text differs from stale indexed terms.' );
			}
		}
		$additional = array(
			'author_key' => array( '', 'text', 191 ),
			'country' => array( '', 'text', 191 ),
			'location' => array( '', 'text', 191 ),
			'availability' => array( '', 'key', 0 ),
			'safety_class' => array( 'general', 'key', 0 ),
		);
		foreach ( $additional as $key => $rule ) {
			if ( isset( $fresh[ $key ] ) && ! is_string( $fresh[ $key ] ) ) {
				return new \WP_Error( 'file26_cold_owner_projection_incomplete', 'Current owner public metadata is malformed.' );
			}
			$expected_value = isset( $fresh[ $key ] ) ? $fresh[ $key ] : $rule[0];
			$expected_value = 'key' === $rule[1] ? sanitize_key( $expected_value ) : sanitize_text_field( $expected_value );
			if ( $rule[2] > 0 ) { $expected_value = substr( $expected_value, 0, $rule[2] ); }
			if ( ! isset( $document[ $key ] ) || ! is_string( $document[ $key ] )
				|| ! hash_equals( $document[ $key ], $expected_value ) ) {
				return new \WP_Error( 'file26_cold_owner_projection_conflict', 'Current native owner public metadata differs from the indexed event.' );
			}
		}
		if ( ! in_array( $fresh['state'], array( 'published', 'active', 'corrected' ), true )
			|| 'restricted' === $fresh['visibility'] ) {
			return new \WP_Error( 'file26_cold_owner_attestation_denied', 'Owner source is withdrawn or restricted.' );
		}
		return true;
	}

	private static function normalize_integer( $value, $allow_zero ) {
		if ( is_int( $value ) ) {
			$number = (string) $value;
		} elseif ( is_string( $value ) && 1 === preg_match( '/^[0-9]+$/D', $value ) ) {
			$number = ltrim( $value, '0' );
			$number = '' === $number ? '0' : $number;
		} else {
			return null;
		}
		if ( ( ! $allow_zero && '0' === $number ) || 1 === preg_match( '/^-/', $number ) ) {
			return null;
		}
		$max = (string) PHP_INT_MAX;
		if ( strlen( $number ) > strlen( $max ) ||
			( strlen( $number ) === strlen( $max ) && strcmp( $number, $max ) > 0 ) ) {
			return null;
		}
		return $number;
	}
	private static function same_positive_version( $owner, $indexed ) {
		$a = self::normalize_integer( $owner, false );
		$b = self::normalize_integer( $indexed, false );
		return null !== $a && null !== $b && hash_equals( $a, $b );
	}
	private static function same_nonnegative_version( $owner, $indexed ) {
		$a = self::normalize_integer( $owner, true );
		$b = self::normalize_integer( $indexed, true );
		return null !== $a && null !== $b && hash_equals( $a, $b );
	}
}
