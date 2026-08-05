<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical owner-connector contract catalogue and activation gate.
 * This class defines strict interfaces but never fabricates an installed owner.
 */
final class Owner_Contracts {
	private $connectors;

	public function __construct( Connectors $connectors ) {
		$this->connectors = $connectors;
	}

	public function requirements() {
		return array(
			'file03' => array( 'owner_file' => 'File 03', 'entity_types' => array( 'founder', 'doctor', 'member_profile' ) ),
			'file05' => array( 'owner_file' => 'File 05', 'entity_types' => array( 'lesson', 'course', 'book' ) ),
			'file06' => array( 'owner_file' => 'File 06', 'entity_types' => array( 'encyclopedia_entry', 'concept' ) ),
			'file07' => array( 'owner_file' => 'File 07', 'entity_types' => array( 'doctor_directory_projection' ) ),
			'file08' => array( 'owner_file' => 'File 08', 'entity_types' => array( 'clinic', 'appointment_availability' ) ),
			'file10' => array( 'owner_file' => 'File 10', 'entity_types' => array( 'video', 'live_video', 'channel' ) ),
			'file11' => array( 'owner_file' => 'File 11', 'entity_types' => array( 'reel' ) ),
			'file12' => array( 'owner_file' => 'File 12', 'entity_types' => array( 'pdf', 'book_pack' ) ),
			'file15' => array( 'owner_file' => 'File 15', 'entity_types' => array( 'radar_study', 'research_signal' ) ),
			'file18' => array( 'owner_file' => 'File 18', 'entity_types' => array( 'marketplace_listing' ) ),
			'file21' => array( 'owner_file' => 'File 21', 'entity_types' => array( 'post', 'news', 'article' ) ),
		);
	}

	/**
	 * Owner modules publish adapters through sabri_file26_owner_connector_adapters.
	 * Each adapter stays proposed until governance moves it through contract-tested,
	 * shadow, approved and active states.
	 */
	public function collect( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$adapters = apply_filters( 'sabri_file26_owner_connector_adapters', array() );
		if ( ! is_array( $adapters ) ) {
			return $manifests;
		}
		$requirements = $this->requirements();
		foreach ( $adapters as $owner_key => $adapter ) {
			$owner_key = sanitize_key( $owner_key );
			if ( ! isset( $requirements[ $owner_key ] ) || ! is_array( $adapter ) ) {
				continue;
			}
			$required = $requirements[ $owner_key ];
			$provided_types = isset( $adapter['entity_types'] ) ? array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $adapter['entity_types'] ) ) ) ) : array();
			if ( array_diff( $required['entity_types'], $provided_types ) ) {
				continue;
			}
			if (
				empty( $adapter['contract_version'] ) ||
				empty( $adapter['list_batch'] ) || ! is_callable( $adapter['list_batch'] ) ||
				empty( $adapter['can_view'] ) || ! is_callable( $adapter['can_view'] ) ||
				empty( $adapter['health'] ) || ! is_callable( $adapter['health'] )
			) {
				continue;
			}
			$manifests[] = array(
				'slug' => isset( $adapter['slug'] ) ? sanitize_key( $adapter['slug'] ) : $owner_key . '-search-owner',
				'owner_file' => $required['owner_file'],
				'contract_version' => sanitize_text_field( $adapter['contract_version'] ),
				'entity_types' => $provided_types,
				'privacy_classes' => isset( $adapter['privacy_classes'] ) ? (array) $adapter['privacy_classes'] : array( 'public' ),
				'visibility_fields' => isset( $adapter['visibility_fields'] ) ? (array) $adapter['visibility_fields'] : array( 'state', 'visibility' ),
				'deletion_semantics' => isset( $adapter['deletion_semantics'] ) ? sanitize_key( $adapter['deletion_semantics'] ) : 'versioned_tombstone',
				'status' => isset( $adapter['status'] ) ? sanitize_key( $adapter['status'] ) : 'proposed',
				'list_batch' => $adapter['list_batch'],
				'can_view' => $adapter['can_view'],
				'health' => $adapter['health'],
				'fetch_object' => isset( $adapter['fetch_object'] ) && is_callable( $adapter['fetch_object'] ) ? $adapter['fetch_object'] : null,
				'event_contract' => isset( $adapter['event_contract'] ) ? sanitize_text_field( $adapter['event_contract'] ) : '',
				'index_schema' => isset( $adapter['index_schema'] ) ? sanitize_text_field( $adapter['index_schema'] ) : 'sabri.file26.document.v1.1',
			);
		}
		return $manifests;
	}

	public function readiness() {
		$requirements = $this->requirements();
		$registry = $this->connectors->all();
		$result = array();
		foreach ( $requirements as $key => $required ) {
			$matched = null;
			foreach ( $registry as $connector ) {
				if ( $connector['owner_file'] !== $required['owner_file'] ) {
					continue;
				}
				if ( array_diff( $required['entity_types'], (array) $connector['entity_types'] ) ) {
					continue;
				}
				$matched = $connector;
				break;
			}
			$result[ $key ] = array(
				'owner_file' => $required['owner_file'],
				'required_entity_types' => $required['entity_types'],
				'registered' => (bool) $matched,
				'contract_version' => $matched ? $matched['contract_version'] : null,
				'status' => $matched ? $matched['status'] : 'missing',
				'callbacks_complete' => $matched && isset( $matched['list_batch'], $matched['can_view'], $matched['health'] ) && is_callable( $matched['list_batch'] ) && is_callable( $matched['can_view'] ) && is_callable( $matched['health'] ),
				'production_ready' => $matched && 'active' === $matched['status'] && isset( $matched['list_batch'], $matched['can_view'], $matched['health'] ) && is_callable( $matched['list_batch'] ) && is_callable( $matched['can_view'] ) && is_callable( $matched['health'] ),
			);
		}
		return $result;
	}

	public function all_required_active() {
		foreach ( $this->readiness() as $owner ) {
			if ( empty( $owner['production_ready'] ) ) {
				return false;
			}
		}
		return true;
	}

	/** Default activation decision; external evidence must be explicitly supplied. */
	public function activation_gate( $approved, array $health, array $connectors ) {
		$evidence = apply_filters( 'sabri_file26_cross_file_gate_evidence', array(
			'file00_identity_contract' => false,
			'file20_shell_contract' => false,
			'file24_assurance_contract' => false,
			'file25_visual_contract' => false,
			'staging_acceptance' => false,
			'migration_rehearsal' => false,
			'rollback_rehearsal' => false,
		) );
		$evidence = is_array( $evidence ) ? $evidence : array();
		$required_evidence = array(
			'file00_identity_contract', 'file20_shell_contract', 'file24_assurance_contract',
			'file25_visual_contract', 'staging_acceptance', 'migration_rehearsal', 'rollback_rehearsal',
		);
		foreach ( $required_evidence as $key ) {
			if ( empty( $evidence[ $key ] ) ) {
				return false;
			}
		}
		return $this->all_required_active() && ! empty( $connectors ) && in_array( $health['status'], array( 'inactive', 'healthy' ), true );
	}
}
