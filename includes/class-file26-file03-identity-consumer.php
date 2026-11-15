<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/**
 * File03/26 recovery of an already committed File00 identity-name change.
 *
 * File03 owns the public profile version/event. File26 owns only the
 * derivative search projection. A direct post-commit callback minimizes stale
 * name delay; File03's existing transactional outbox replays an unsuccessful
 * delivery with its own CAS lease, backoff, and dead-letter monitoring.
 * Nothing here treats the public WP name as File26-owned identity truth.
 */
final class File03_Identity_Consumer {
	private $connectors;
	private $indexer;
	private $security;
	public function __construct( Connectors $connectors, Indexer $indexer, Security $security ) {
		$this->connectors = $connectors;
		$this->indexer = $indexer;
		$this->security = $security;
	}
	private function enabled() {
		return $this->connectors->is_active( 'file03-profiles' ) && true === (bool) DB::setting( 'activated', false );
	}
	private function valid_public_id( $value ) {
		return is_string( $value ) &&
			1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', strtolower( $value ) );
	}

	/** Re-read current public File03 from an active *native* connector. */
	public function refresh( $public_id, $at_least_version = 0 ) {
		if ( ! $this->enabled() ) { return false; }
		if ( ! $this->valid_public_id( $public_id ) ||
			! is_int( $at_least_version ) || $at_least_version < 0 ) {
			return new \WP_Error( 'file26_file03_identity_invalid', 'The canonical public-profile update identity is invalid.' );
		}
		$manifest = $this->connectors->get( 'file03-profiles' );
		if ( ! is_array( $manifest ) || ! isset( $manifest['fetch_object'], $manifest['entity_types'] ) ||
			! is_callable( $manifest['fetch_object'] ) ) {
			return new \WP_Error( 'file26_file03_native_fetch_unavailable', 'The live File03 native fetch contract is missing.' );
		}
		foreach ( array( 'founder', 'doctor', 'member_profile' ) as $type ) {
			if ( ! in_array( $type, $manifest['entity_types'], true ) ) { continue; }
			try {
				$record = call_user_func( $manifest['fetch_object'], $type, $public_id );
			} catch ( \Throwable $exception ) {
				return new \WP_Error( 'file26_file03_native_fetch_failed', 'The current canonical File03 profile is unavailable.' );
			}
			if ( is_wp_error( $record ) ) {
				return new \WP_Error( 'file26_file03_native_fetch_failed', 'The current canonical File03 profile could not be read.' );
			}
			if ( ! is_array( $record ) || empty( $record ) ) { continue; }
			if ( ! isset( $record['connector_slug'], $record['object_id'], $record['entity_type'],
				$record['object_version'] ) || 'file03-profiles' !== $record['connector_slug'] ||
				$public_id !== $record['object_id'] || $type !== $record['entity_type'] ) {
				return new \WP_Error( 'file26_file03_native_identity_conflict', 'Native File03 current identity is inconsistent.' );
			}
			$current_version = $record['object_version'];
			if ( ! ( is_int( $current_version ) || is_string( $current_version ) ) ||
				1 !== preg_match( '/^[1-9][0-9]*$/D', (string) $current_version ) ||
				(int) $current_version < $at_least_version ||
				(string) (int) $current_version !== (string) $current_version ) {
				return new \WP_Error( 'file26_file03_native_version_stale', 'Current native profile revision is older than the committed identity event.' );
			}
			$result = $this->indexer->upsert( $record );
			return $result;
		}
		return new \WP_Error( 'file26_file03_public_projection_unavailable', 'No approved current public profile projection was available.' );
	}
	public function on_profile_changed( $public_id, $version ) {
		if ( ! $this->enabled() ) { return; }
		$validated = self::strict_version( $version );
		$result = $this->refresh( $public_id, null === $validated ? 0 : $validated );
		if ( is_wp_error( $result ) ) { $this->record_failure( $result, $public_id ); }
		else { delete_option( 'sabri_file26_file03_identity_refresh_error' ); }
	}
	/**
	 * File03::SPD_Outbox_Dispatcher owns retry / dead-letter semantics.
	 * Throw only if an active File26 owner projection MUST be reconciled.
	 * File03's outbox catches this and will retry with its existing CAS lease.
	 */
	public function on_file03_outbox( $event_name, $payload, $event_row ) {
		if ( ! $this->enabled() || 'PublicProfileUpdated.v1' !== $event_name ||
			! is_array( $payload ) ||
			! in_array( 'file00_public_display_name', isset( $payload['changed_fields'] ) && is_array( $payload['changed_fields'] ) ? $payload['changed_fields'] : array(), true ) ) {
			return;
		}
		$id = is_array( $event_row ) && isset( $event_row['aggregate_id'] ) ? $event_row['aggregate_id'] : null;
		$version = self::strict_version( isset( $payload['version'] ) ? $payload['version'] : null );
		if ( null === $version ) {
			throw new \RuntimeException( 'file26_file03_event_version_invalid' );
		}
		$result = $this->refresh( $id, $version );
		if ( is_wp_error( $result ) ) {
			$this->record_failure( $result, $id );
			throw new \RuntimeException( 'file26_file03_reindex_pending_' . sanitize_key( $result->get_error_code() ) );
		}
		delete_option( 'sabri_file26_file03_identity_refresh_error' );
	}
	private static function strict_version( $version ) {
		if ( is_int( $version ) && $version > 0 ) { return $version; }
		if ( ! is_string( $version ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $version ) ) { return null; }
		$max = (string) PHP_INT_MAX;
		if ( strlen( $version ) > strlen( $max ) ||
			( strlen( $version ) === strlen( $max ) && strcmp( $version, $max ) > 0 ) ) { return null; }
		return (int) $version;
	}
	private function record_failure( \WP_Error $error, $public_id ) {
		$code = sanitize_key( $error->get_error_code() );
		update_option( 'sabri_file26_file03_identity_refresh_error', array(
			'code' => $code,
			'public_id_hash' => hash( 'sha256', is_string( $public_id ) ? $public_id : '' ),
			'at' => DB::now(),
		), false );
		$this->security->audit( 'file03_identity_search_sync_failed', array(
			'object_type' => 'owner_connector', 'object_key' => 'file03-profiles', 'reason' => $code,
		) );
	}
}
