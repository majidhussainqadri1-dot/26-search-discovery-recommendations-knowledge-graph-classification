<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Versioned connector registry with strict production-lane isolation. */
final class Connectors {
	private $security;
	private $registry = array();
	private $required = array( 'slug', 'owner_file', 'contract_version', 'entity_types', 'privacy_classes', 'visibility_fields', 'deletion_semantics' );

	public function __construct( Security $security ) { $this->security = $security; }

	public function boot() {
		$manifests = apply_filters( 'sabri_file26_connector_manifests', array() );
		foreach ( (array) $manifests as $manifest ) { if ( is_array( $manifest ) ) { $this->register( $manifest ); } }
		do_action( 'sabri_file26_connectors_ready', $this );
	}

	public function register( array $manifest ) {
		foreach ( $this->required as $field ) { if ( ! isset( $manifest[ $field ] ) || '' === $manifest[ $field ] || array() === $manifest[ $field ] ) { return new \WP_Error( 'file26_invalid_manifest', sprintf( 'Connector manifest is missing %s.', $field ) ); } }
		$slug = sanitize_key( $manifest['slug'] );
		if ( ! $slug || strlen( $slug ) > 191 ) { return new \WP_Error( 'file26_invalid_connector_slug', 'Invalid connector slug.' ); }
		$manifest['slug'] = $slug;
		$manifest['owner_file'] = sanitize_text_field( $manifest['owner_file'] );
		$manifest['contract_version'] = sanitize_text_field( $manifest['contract_version'] );
		$manifest['entity_types'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $manifest['entity_types'] ) ) ) );
		$manifest['privacy_classes'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $manifest['privacy_classes'] ) ) ) );
		$manifest['visibility_fields'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $manifest['visibility_fields'] ) ) );
		$manifest['deletion_semantics'] = sanitize_key( $manifest['deletion_semantics'] );
		if ( '' === $manifest['owner_file'] || '' === $manifest['contract_version'] || empty( $manifest['entity_types'] ) || empty( $manifest['privacy_classes'] ) || empty( $manifest['visibility_fields'] ) || '' === $manifest['deletion_semantics'] ) { return new \WP_Error( 'file26_invalid_manifest_after_normalization', 'Connector manifest becomes empty or invalid after normalization.' ); }
		$manifest['status'] = isset( $manifest['status'] ) ? sanitize_key( $manifest['status'] ) : 'proposed';
		$allowed_status = array( 'proposed', 'contract_tested', 'shadow', 'approved', 'active', 'degraded', 'suspended', 'retired' );
		if ( ! in_array( $manifest['status'], $allowed_status, true ) ) { return new \WP_Error( 'file26_invalid_connector_status', 'Invalid connector lifecycle status.' ); }
		foreach ( array( 'list_batch', 'can_view', 'health', 'fetch_object' ) as $callback ) { if ( isset( $manifest[ $callback ] ) && ! is_callable( $manifest[ $callback ] ) ) { return new \WP_Error( 'file26_invalid_connector_callback', $callback . ' is not callable.' ); } }

		$public_manifest = array();
		foreach ( array( 'slug', 'owner_file', 'contract_version', 'entity_types', 'privacy_classes', 'visibility_fields', 'deletion_semantics', 'status', 'event_contract', 'index_schema' ) as $safe_key ) {
			if ( array_key_exists( $safe_key, $manifest ) ) { $public_manifest[ $safe_key ] = $manifest[ $safe_key ]; }
		}
		$persisted = $this->persist( $public_manifest );
		if ( is_wp_error( $persisted ) ) { return $persisted; }
		$manifest['status'] = $persisted;
		$this->registry[ $slug ] = $manifest;
		return true;
	}

	private function persist( array $manifest ) {
		global $wpdb;
		$table = DB::table( 'connectors' ); $now = DB::now();
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT owner_file,contract_version,status FROM $table WHERE slug=%s", $manifest['slug'] ), ARRAY_A );
		if ( $existing && $existing['owner_file'] === $manifest['owner_file'] && $existing['contract_version'] === $manifest['contract_version'] ) { $manifest['status'] = $existing['status']; } else { $manifest['status'] = 'proposed'; }
		$sql = $wpdb->prepare(
			"INSERT INTO $table (slug,owner_file,contract_version,status,manifest,last_event_version,health_state,last_health,created_at,updated_at)
			 VALUES (%s,%s,%s,%s,%s,0,'unknown',NULL,%s,%s)
			 ON DUPLICATE KEY UPDATE
			 last_event_version=IF(owner_file=VALUES(owner_file) AND contract_version=VALUES(contract_version),last_event_version,0),
			 health_state=IF(owner_file=VALUES(owner_file) AND contract_version=VALUES(contract_version),health_state,'unknown'),
			 last_health=IF(owner_file=VALUES(owner_file) AND contract_version=VALUES(contract_version),last_health,NULL),
			 owner_file=VALUES(owner_file),contract_version=VALUES(contract_version),status=VALUES(status),manifest=VALUES(manifest),updated_at=VALUES(updated_at)",
			$manifest['slug'], $manifest['owner_file'], $manifest['contract_version'], $manifest['status'], wp_json_encode( $manifest ), $now, $now
		);
		$written = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $written ) { return new \WP_Error( 'file26_connector_persistence_failed', 'Connector governance state could not be persisted; registration failed closed.' ); }
		return $manifest['status'];
	}

	public function get( $slug ) { $slug = sanitize_key( $slug ); return isset( $this->registry[ $slug ] ) ? $this->registry[ $slug ] : null; }
	public function all() { return $this->registry; }
	public function is_active( $slug ) { $manifest = $this->get( $slug ); return $manifest && 'active' === $manifest['status']; }
	public function is_index_eligible( $slug ) { $manifest = $this->get( $slug ); return $manifest && in_array( $manifest['status'], array( 'shadow', 'approved', 'active' ), true ); }

	public function validate_document( array $document ) {
		$required = array( 'connector_slug', 'domain', 'object_id', 'object_version', 'entity_type', 'locale', 'state', 'visibility', 'title', 'canonical_url' );
		foreach ( $required as $field ) { if ( ! isset( $document[ $field ] ) || '' === $document[ $field ] ) { return new \WP_Error( 'file26_invalid_document', sprintf( 'Indexed document is missing %s.', $field ) ); } }
		$manifest = $this->get( $document['connector_slug'] );
		if ( ! $manifest ) { return new \WP_Error( 'file26_unknown_connector', 'Unknown connector; fail closed.' ); }
		if ( ! $this->is_index_eligible( $document['connector_slug'] ) ) { return new \WP_Error( 'file26_connector_not_eligible', 'Connector is not eligible for indexing.' ); }
		if ( ! in_array( sanitize_key( $document['entity_type'] ), $manifest['entity_types'], true ) ) { return new \WP_Error( 'file26_invalid_entity_type', 'Entity type is outside the connector contract.' ); }
		return true;
	}

	public function can_view( $slug, array $document, array $audience ) {
		$manifest = $this->get( $slug ); if ( ! $manifest || 'active' !== $manifest['status'] ) { return false; }
		if ( isset( $manifest['can_view'] ) && is_callable( $manifest['can_view'] ) ) {
			try { return (bool) call_user_func( $manifest['can_view'], $document, $audience ); }
			catch ( \Throwable $e ) { $this->security->audit( 'connector_visibility_error', array( 'object_type' => 'connector', 'object_key' => $slug, 'reason' => 'callback_exception', 'metadata' => array( 'error_class' => get_class( $e ) ) ) ); return false; }
		}
		return $this->security->can_view_visibility( isset( $document['visibility'] ) ? $document['visibility'] : 'restricted', $audience, isset( $document['payload'] ) && is_array( $document['payload'] ) ? $document['payload'] : array() );
	}

	public function health_snapshot() {
		global $wpdb; $result = array();
		foreach ( $this->registry as $slug => $manifest ) {
			$state = 'unknown'; $detail = array();
			if ( isset( $manifest['health'] ) && is_callable( $manifest['health'] ) ) {
				try { $value = call_user_func( $manifest['health'] ); if ( is_array( $value ) ) { $state = isset( $value['state'] ) ? sanitize_key( $value['state'] ) : 'unknown'; $detail = $this->sanitize_health_detail( $value ); } else { $state = $value ? 'healthy' : 'degraded'; } }
				catch ( \Throwable $e ) { $state = 'degraded'; $detail = array( 'error_class' => sanitize_text_field( get_class( $e ) ) ); }
			}
			$persisted = $wpdb->update( DB::table( 'connectors' ), array( 'health_state' => $state, 'last_health' => DB::now(), 'updated_at' => DB::now() ), array( 'slug' => $slug ), array( '%s', '%s', '%s' ), array( '%s' ) );
			if ( false === $persisted ) { $detail['health_persistence'] = 'failed'; $state = 'degraded'; }
			$result[ $slug ] = array( 'state' => $state, 'contract_version' => $manifest['contract_version'], 'owner_file' => $manifest['owner_file'], 'status' => $manifest['status'], 'detail' => $detail );
		}
		return $result;
	}

	private function sanitize_health_detail( array $detail, $depth = 0 ) {
		if ( $depth > 2 ) { return array(); }
		$out = array(); $count = 0;
		foreach ( $detail as $key => $value ) {
			if ( $count >= 24 ) { break; }
			$key = sanitize_key( (string) $key );
			if ( '' === $key || preg_match( '/(?:secret|token|password|credential|authorization|api[_-]?key|cookie|session)/i', $key ) ) { continue; }
			if ( is_array( $value ) ) { $out[ $key ] = $this->sanitize_health_detail( $value, $depth + 1 ); }
			elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) { $out[ $key ] = $value; }
			elseif ( is_scalar( $value ) ) { $out[ $key ] = substr( sanitize_text_field( (string) $value ), 0, 512 ); }
			else { continue; }
			$count++;
		}
		return $out;
	}

	public function degraded_domains() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT slug,owner_file,status,health_state,last_health FROM " . DB::table( 'connectors' ) . " WHERE status IN ('active','degraded') AND health_state NOT IN ('healthy','ok') ORDER BY slug", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$output = array(); foreach ( (array) $rows as $row ) { $output[] = array( 'connector' => $row['slug'], 'owner_file' => $row['owner_file'], 'status' => $row['status'], 'health' => $row['health_state'], 'last_health' => $row['last_health'] ); }
		return $output;
	}
}
