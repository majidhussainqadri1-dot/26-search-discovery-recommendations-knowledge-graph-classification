<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Taxonomy {
	private $normalizer;
	private $security;

	public function __construct( Normalizer $normalizer, Security $security ) {
		$this->normalizer = $normalizer;
		$this->security = $security;
	}

	public function create( array $input ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) {
			return new \WP_Error( 'file26_forbidden', 'Taxonomy capability is required.', array( 'status' => 403 ) );
		}
		$label = isset( $input['preferred_label'] ) ? sanitize_text_field( $input['preferred_label'] ) : '';
		$language = isset( $input['language'] ) ? substr( sanitize_text_field( $input['language'] ), 0, 20 ) : 'en-US';
		$slug = isset( $input['slug'] ) ? sanitize_title( $input['slug'] ) : sanitize_title( $label );
		if ( ! $label || ! $slug ) {
			return new \WP_Error( 'file26_invalid_term', 'A preferred label and slug are required.' );
		}
		$uuid = DB::uuid();
		$ok = $wpdb->insert(
			DB::table( 'terms' ),
			array(
				'term_uuid' => $uuid,
				'slug' => $slug,
				'preferred_label' => $label,
				'definition' => isset( $input['definition'] ) ? sanitize_textarea_field( $input['definition'] ) : '',
				'language' => $language,
				'parent_uuid' => ! empty( $input['parent_uuid'] ) ? sanitize_text_field( $input['parent_uuid'] ) : null,
				'related_json' => wp_json_encode( array() ),
				'owner_file' => isset( $input['owner_file'] ) ? sanitize_text_field( $input['owner_file'] ) : 'File 26',
				'status' => 'draft',
				'version' => 1,
				'created_at' => DB::now(),
				'updated_at' => DB::now(),
			)
		);
		if ( ! $ok ) {
			return new \WP_Error( 'file26_term_conflict', 'The taxonomy term conflicts with an existing active identifier.' );
		}
		foreach ( isset( $input['aliases'] ) ? (array) $input['aliases'] : array() as $alias ) {
			if ( ! $this->add_alias( $uuid, $alias, $language ) ) {
				return new \WP_Error( 'file26_alias_write_failed', 'A taxonomy alias could not be stored.' );
			}
		}
		$this->security->audit( 'taxonomy_term_created', array( 'object_type' => 'taxonomy_term', 'object_key' => $uuid ) );
		return $this->get( $uuid );
	}

	public function approve( $uuid ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) {
			return new \WP_Error( 'file26_forbidden', 'Taxonomy capability is required.' );
		}
		$term = $this->get( $uuid );
		if ( ! $term ) {
			return new \WP_Error( 'file26_term_not_found', 'Taxonomy term not found.' );
		}
		if ( $term['parent_uuid'] && ! $this->get( $term['parent_uuid'] ) ) {
			return new \WP_Error( 'file26_orphan_term', 'Parent term does not exist.' );
		}
		if ( $this->would_cycle( $uuid, $term['parent_uuid'] ) ) {
			return new \WP_Error( 'file26_taxonomy_cycle', 'Taxonomy cycle detected.' );
		}
		if ( ! in_array( $term['status'], array( 'draft', 'in_review', 'corrected' ), true ) ) {
			return new \WP_Error( 'file26_invalid_term_transition', 'Term cannot be approved from its current state.', array( 'status' => 409 ) );
		}
		$updated = $wpdb->update(
			DB::table( 'terms' ),
			array( 'status' => 'active', 'version' => (int) $term['version'] + 1, 'updated_at' => DB::now() ),
			array( 'term_uuid' => $uuid, 'version' => (int) $term['version'] ),
			array( '%s','%d','%s' ),
			array( '%s','%d' )
		);
		if ( 1 !== $updated ) { return new \WP_Error( 'file26_term_conflict', 'Term changed concurrently.', array( 'status' => 409 ) ); }
		$this->security->audit( 'taxonomy_term_approved', array( 'object_type' => 'taxonomy_term', 'object_key' => $uuid ) );
		do_action( 'sabri_file26_event', 'TaxonomyTermApproved', array( 'term_uuid' => $uuid, 'contract_version' => SABRI_FILE26_CONTRACT_VERSION ) );
		return $this->get( $uuid );
	}

	public function merge_preview( $source_uuid, $target_uuid ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) {
			return new \WP_Error( 'file26_forbidden', 'Taxonomy capability is required.', array( 'status' => 403 ) );
		}
		$source = $this->get( $source_uuid );
		$target = $this->get( $target_uuid );
		if ( ! $source || ! $target || $source['term_uuid'] === $target['term_uuid'] ) {
			return new \WP_Error( 'file26_invalid_merge', 'Invalid taxonomy merge.' );
		}
		return array(
			'source_uuid' => $source['term_uuid'],
			'target_uuid' => $target['term_uuid'],
			'source_status' => $source['status'],
			'target_status' => $target['status'],
			'source_owner' => $source['owner_file'],
			'target_owner' => $target['owner_file'],
			'impacted_classifications' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . DB::table( 'classifications' ) . ' WHERE term_uuid=%s', $source['term_uuid'] ) ),
			'impacted_aliases' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . DB::table( 'term_aliases' ) . ' WHERE term_uuid=%s', $source['term_uuid'] ) ),
			'rollback_mapping' => array( 'source_uuid' => $source['term_uuid'], 'previous_status' => $source['status'], 'previous_redirect_uuid' => $source['redirect_uuid'] ),
		);
	}

	public function merge( $source_uuid, $target_uuid, $reason = '' ) {
		global $wpdb;
		if ( ! $this->security->can_curate() || ! $this->security->require_step_up( 'taxonomy_merge' ) ) {
			return new \WP_Error( 'file26_step_up_required', 'High-risk taxonomy merge requires step-up authorization.', array( 'status' => 403 ) );
		}
		$source = $this->get( $source_uuid );
		$target = $this->get( $target_uuid );
		if ( ! $source || ! $target || $source['term_uuid'] === $target['term_uuid'] ) {
			return new \WP_Error( 'file26_invalid_merge', 'Invalid taxonomy merge.' );
		}
		if ( ! in_array( $source['status'], array( 'active', 'corrected' ), true ) || 'active' !== $target['status'] ) {
			return new \WP_Error( 'file26_invalid_merge_state', 'Merge requires a current source and an active canonical target.', array( 'status' => 409 ) );
		}
		$preview = $this->merge_preview( $source['term_uuid'], $target['term_uuid'] );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		if ( ! $this->domain_owner_approved( 'merge', array( $source, $target ), $preview ) ) {
			return new \WP_Error( 'file26_domain_owner_approval_required', 'Affected domain-owner approval is required for this taxonomy merge.', array( 'status' => 403 ) );
		}
		$wpdb->query( 'START TRANSACTION' );
		try {
			$current_target = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'terms' ) . ' WHERE term_uuid=%s FOR UPDATE', $target['term_uuid'] ), ARRAY_A );
			if ( ! $current_target || 'active' !== $current_target['status'] || (int) $current_target['version'] !== (int) $target['version'] ) {
				throw new \RuntimeException( 'Target term changed concurrently.' );
			}
			$term_updated = $wpdb->update(
				DB::table( 'terms' ),
				array( 'status' => 'merged', 'redirect_uuid' => $target['term_uuid'], 'version' => (int) $source['version'] + 1, 'updated_at' => DB::now() ),
				array( 'term_uuid' => $source['term_uuid'], 'version' => (int) $source['version'] )
			);
			if ( 1 !== $term_updated ) { throw new \RuntimeException( 'Concurrent term update.' ); }
			$assignments = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'classifications' ) . ' WHERE term_uuid=%s', $source['term_uuid'] ), ARRAY_A );
			foreach ( $assignments as $assignment ) {
				$sql = $wpdb->prepare(
					'INSERT INTO ' . DB::table( 'classifications' ) . ' (object_key,term_uuid,confidence,method,method_version,reviewer_id,status,provenance,version,created_at,updated_at) VALUES (%s,%s,%f,%s,%s,%d,%s,%s,%d,%s,%s) ON DUPLICATE KEY UPDATE confidence=GREATEST(confidence,VALUES(confidence)),status=IF(status=\'approved\',status,VALUES(status)),provenance=VALUES(provenance),version=version+1,updated_at=VALUES(updated_at)',
					$assignment['object_key'], $target['term_uuid'], (float) $assignment['confidence'], $assignment['method'], $assignment['method_version'], (int) $assignment['reviewer_id'], $assignment['status'], $assignment['provenance'], (int) $assignment['version'] + 1, $assignment['created_at'], DB::now()
				);
				if ( false === $wpdb->query( $sql ) ) { throw new \RuntimeException( 'Classification merge failed.' ); }
			}
			if ( false === $wpdb->delete( DB::table( 'classifications' ), array( 'term_uuid' => $source['term_uuid'] ), array( '%s' ) ) ) {
				throw new \RuntimeException( 'Source classification cleanup failed.' );
			}
			$aliases = $wpdb->get_results(
				$wpdb->prepare( 'SELECT alias_label,language FROM ' . DB::table( 'term_aliases' ) . ' WHERE term_uuid=%s', $source['term_uuid'] ),
				ARRAY_A
			);
			foreach ( $aliases as $alias ) {
				if ( ! $this->add_alias( $target['term_uuid'], $alias['alias_label'], $alias['language'] ) ) {
					throw new \RuntimeException( 'Alias merge failed.' );
				}
			}
			if ( ! $this->add_alias( $target['term_uuid'], $source['preferred_label'], $source['language'] ) ) {
				throw new \RuntimeException( 'Source label redirect alias failed.' );
			}
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_merge_failed', 'Taxonomy merge failed or changed concurrently.' );
		}
		$this->security->audit(
			'taxonomy_terms_merged',
			array(
				'object_type' => 'taxonomy_term',
				'object_key' => $source['term_uuid'],
				'reason' => $reason,
				'metadata' => array( 'target_uuid' => $target['term_uuid'], 'rollback_mapping' => $preview['rollback_mapping'], 'impacted_classifications' => $preview['impacted_classifications'] ),
			)
		);
		do_action( 'sabri_file26_taxonomy_reindex_required', array( 'action' => 'merge', 'source_uuid' => $source['term_uuid'], 'target_uuids' => array( $target['term_uuid'] ) ) );
		do_action( 'sabri_file26_event', 'TaxonomyTermsMerged', array( 'source_uuid' => $source['term_uuid'], 'target_uuid' => $target['term_uuid'], 'rollback_mapping' => $preview['rollback_mapping'] ) );
		return true;
	}

	public function submit( $uuid ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) { return new \WP_Error( 'file26_forbidden', 'Taxonomy capability is required.', array( 'status' => 403 ) ); }
		$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . DB::table( 'terms' ) . " SET status='in_review',version=version+1,updated_at=%s WHERE term_uuid=%s AND status IN ('draft','corrected')", DB::now(), sanitize_text_field( $uuid ) ) );
		return 1 === (int) $updated ? true : new \WP_Error( 'file26_invalid_term_transition', 'Term cannot be submitted from its current state.', array( 'status' => 409 ) );
	}

	public function deprecate( $uuid, $redirect_uuid = '', $reason = '' ) {
		global $wpdb;
		if ( ! $this->security->can_curate() || ! $this->security->require_step_up( 'taxonomy_deprecate' ) ) { return new \WP_Error( 'file26_forbidden', 'Fresh taxonomy authorization is required.', array( 'status' => 403 ) ); }
		$term = $this->get( $uuid );
		$redirect = $redirect_uuid ? $this->get( $redirect_uuid ) : null;
		if ( ! $term || ( $redirect_uuid && ! $redirect ) || $term['term_uuid'] === $redirect_uuid ) { return new \WP_Error( 'file26_invalid_deprecation', 'Invalid term or redirect target.' ); }
		if ( ! in_array( $term['status'], array( 'active', 'corrected' ), true ) || ( $redirect && 'active' !== $redirect['status'] ) ) {
			return new \WP_Error( 'file26_invalid_deprecation_state', 'Deprecation requires a current term and an active redirect target.', array( 'status' => 409 ) );
		}
		if ( ! $this->domain_owner_approved( 'deprecate', $redirect ? array( $term, $redirect ) : array( $term ), array( 'redirect_uuid' => $redirect ? $redirect['term_uuid'] : null ) ) ) {
			return new \WP_Error( 'file26_domain_owner_approval_required', 'Affected domain-owner approval is required for taxonomy deprecation.', array( 'status' => 403 ) );
		}
		$updated = $wpdb->update( DB::table( 'terms' ), array( 'status' => $redirect ? 'merged' : 'deprecated', 'redirect_uuid' => $redirect ? $redirect['term_uuid'] : null, 'version' => (int) $term['version'] + 1, 'updated_at' => DB::now() ), array( 'term_uuid' => $term['term_uuid'], 'version' => (int) $term['version'] ) );
		if ( 1 !== $updated ) { return new \WP_Error( 'file26_term_conflict', 'Term changed concurrently.', array( 'status' => 409 ) ); }
		$this->security->audit( 'taxonomy_term_deprecated', array( 'object_type' => 'taxonomy_term', 'object_key' => $term['term_uuid'], 'reason' => sanitize_text_field( $reason ), 'metadata' => array( 'redirect_uuid' => $redirect ? $redirect['term_uuid'] : null ) ) );
		do_action( 'sabri_file26_taxonomy_reindex_required', array( 'action' => 'deprecate', 'source_uuid' => $term['term_uuid'], 'target_uuids' => $redirect ? array( $redirect['term_uuid'] ) : array() ) );
		return true;
	}

	public function split_preview( $source_uuid, array $targets ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) {
			return new \WP_Error( 'file26_forbidden', 'Taxonomy capability is required.', array( 'status' => 403 ) );
		}
		$source = $this->get( $source_uuid );
		if ( ! $source || count( $targets ) < 2 || count( $targets ) > 10 ) {
			return new \WP_Error( 'file26_invalid_split', 'A valid source and two to ten target terms are required.' );
		}
		return array(
			'source_uuid' => $source['term_uuid'],
			'source_status' => $source['status'],
			'source_owner' => $source['owner_file'],
			'target_count' => count( $targets ),
			'impacted_classifications' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . DB::table( 'classifications' ) . ' WHERE term_uuid=%s', $source['term_uuid'] ) ),
			'rollback_mapping' => array( 'source_uuid' => $source['term_uuid'], 'previous_status' => $source['status'], 'previous_redirect_uuid' => $source['redirect_uuid'] ),
		);
	}

	public function split( $source_uuid, array $targets, $reason = '' ) {
		global $wpdb;
		if ( ! $this->security->can_curate() || ! $this->security->require_step_up( 'taxonomy_split' ) ) { return new \WP_Error( 'file26_forbidden', 'Fresh taxonomy authorization is required.', array( 'status' => 403 ) ); }
		$source = $this->get( $source_uuid );
		if ( ! $source || count( $targets ) < 2 || count( $targets ) > 10 ) { return new \WP_Error( 'file26_invalid_split', 'A valid source and two to ten target terms are required.' ); }
		if ( ! in_array( $source['status'], array( 'active', 'corrected' ), true ) ) {
			return new \WP_Error( 'file26_invalid_split_state', 'Only a current active concept can be split.', array( 'status' => 409 ) );
		}
		$preview = $this->split_preview( $source['term_uuid'], $targets );
		if ( is_wp_error( $preview ) ) { return $preview; }
		if ( ! $this->domain_owner_approved( 'split', array( $source ), $preview ) ) {
			return new \WP_Error( 'file26_domain_owner_approval_required', 'Affected domain-owner approval is required for this taxonomy split.', array( 'status' => 403 ) );
		}
		$created = array();
		$wpdb->query( 'START TRANSACTION' );
		try {
			$current_source = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'terms' ) . ' WHERE term_uuid=%s FOR UPDATE', $source['term_uuid'] ), ARRAY_A );
			if ( ! $current_source || (int) $current_source['version'] !== (int) $source['version'] || ! in_array( $current_source['status'], array( 'active', 'corrected' ), true ) ) {
				throw new \RuntimeException( 'Source term changed concurrently.' );
			}
			foreach ( $targets as $target ) {
				$target = is_array( $target ) ? $target : array( 'preferred_label' => $target );
				$target['language'] = isset( $target['language'] ) ? $target['language'] : $source['language'];
				$target['owner_file'] = isset( $target['owner_file'] ) ? $target['owner_file'] : $source['owner_file'];
				$result = $this->create( $target );
				if ( is_wp_error( $result ) ) { throw new \RuntimeException( 'Split target creation failed.' ); }
				$created[] = $result;
			}
			$ids = array_column( $created, 'term_uuid' );
			$updated = $wpdb->update( DB::table( 'terms' ), array( 'status' => 'split', 'redirect_uuid' => $ids[0], 'related_json' => wp_json_encode( array( 'split_targets' => $ids ) ), 'version' => (int) $source['version'] + 1, 'updated_at' => DB::now() ), array( 'term_uuid' => $source['term_uuid'], 'version' => (int) $source['version'] ) );
			if ( 1 !== $updated ) { throw new \RuntimeException( 'Source term changed concurrently.' ); }
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'file26_split_failed', 'Taxonomy split failed or changed concurrently.', array( 'status' => 409 ) );
		}
		$ids = array_column( $created, 'term_uuid' );
		$this->security->audit( 'taxonomy_term_split', array( 'object_type' => 'taxonomy_term', 'object_key' => $source['term_uuid'], 'reason' => sanitize_text_field( $reason ), 'metadata' => array( 'targets' => $ids, 'rollback_mapping' => $preview['rollback_mapping'], 'impacted_classifications' => $preview['impacted_classifications'] ) ) );
		do_action( 'sabri_file26_taxonomy_reindex_required', array( 'action' => 'split', 'source_uuid' => $source['term_uuid'], 'target_uuids' => $ids ) );
		do_action( 'sabri_file26_event', 'TaxonomyTermSplit', array( 'source_uuid' => $source['term_uuid'], 'target_uuids' => $ids, 'rollback_mapping' => $preview['rollback_mapping'] ) );
		return array( 'source_uuid' => $source['term_uuid'], 'targets' => $created, 'preview' => $preview );
	}

	public function get( $uuid_or_slug ) {
		global $wpdb;
		$value = sanitize_text_field( $uuid_or_slug );
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . DB::table( 'terms' ) . ' WHERE term_uuid=%s OR slug=%s LIMIT 1',
				$value,
				sanitize_title( $value )
			),
			ARRAY_A
		);
	}

	public function list_active( $language = null, $limit = 100 ) {
		global $wpdb;
		$limit = max( 1, min( 500, (int) $limit ) );
		if ( $language ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . DB::table( 'terms' ) . " WHERE status='active' AND language=%s ORDER BY preferred_label ASC LIMIT %d",
					substr( sanitize_text_field( $language ), 0, 20 ),
					$limit
				),
				ARRAY_A
			);
		}
		return $wpdb->get_results(
			"SELECT * FROM " . DB::table( 'terms' ) . " WHERE status='active' ORDER BY preferred_label ASC LIMIT $limit", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
	}

	public function classify( $object_key, $term_uuid, $confidence, $method, $method_version, $status = 'suggested', $provenance = array() ) {
		global $wpdb;
		$object_key = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $object_key ) );
		$term = $this->get( $term_uuid );
		$status = sanitize_key( $status );
		$allowed_statuses = array( 'suggested', 'review_pending', 'approved', 'rejected', 'corrected', 'removed' );
		if ( 64 !== strlen( $object_key ) || ! $term || ! in_array( $status, $allowed_statuses, true ) ) {
			return new \WP_Error( 'file26_invalid_classification', 'Invalid object, taxonomy term or classification state.', array( 'status' => 400 ) );
		}
		$document_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . DB::table( 'documents' ) . ' WHERE canonical_key=%s LIMIT 1', $object_key ) );
		if ( ! $document_exists ) {
			return new \WP_Error( 'file26_classification_orphan', 'Classification target does not exist in the derivative index.', array( 'status' => 404 ) );
		}
		$writer_allowed = (bool) apply_filters( 'sabri_file26_classification_writer_authorized', $this->security->can_curate(), get_current_user_id(), $object_key, $term, $status );
		if ( ! $writer_allowed ) {
			return new \WP_Error( 'file26_forbidden', 'An authorized classification writer is required.', array( 'status' => 403 ) );
		}
		if ( 'approved' === $status && ( ! $this->security->can_curate() || 'active' !== $term['status'] ) ) {
			return new \WP_Error( 'file26_human_review_required', 'An active term and authorized curator approval are required.', array( 'status' => 403 ) );
		}
		$confidence = min( 1, max( 0, (float) $confidence ) );
		$provenance = is_array( $provenance ) ? $this->sanitize_classification_provenance( $provenance ) : array();
		if ( is_wp_error( $provenance ) ) {
			return $provenance;
		}
		$high_impact = ! empty( $provenance['high_impact'] );
		if ( $high_impact && $confidence < 0.95 && 'approved' === $status ) {
			return new \WP_Error( 'file26_human_review_required', 'Low-confidence high-impact labels cannot be auto-approved.', array( 'status' => 409 ) );
		}
		$encoded = wp_json_encode( $provenance );
		if ( false === $encoded || strlen( $encoded ) > 16384 ) {
			return new \WP_Error( 'file26_classification_provenance_too_large', 'Classification provenance exceeds the allowed size.', array( 'status' => 413 ) );
		}
		$sql = $wpdb->prepare(
			'INSERT INTO ' . DB::table( 'classifications' ) . "
			(object_key,term_uuid,confidence,method,method_version,reviewer_id,status,provenance,version,created_at,updated_at)
			VALUES (%s,%s,%f,%s,%s,%d,%s,%s,1,%s,%s)
			ON DUPLICATE KEY UPDATE
				confidence=VALUES(confidence),method=VALUES(method),method_version=VALUES(method_version),
				reviewer_id=VALUES(reviewer_id),status=VALUES(status),provenance=VALUES(provenance),
				version=version+1,updated_at=VALUES(updated_at)",
			$object_key,
			$term_uuid,
			$confidence,
			sanitize_key( $method ),
			substr( sanitize_text_field( $method_version ), 0, 64 ),
			get_current_user_id() ?: 0,
			$status,
			$encoded,
			DB::now(),
			DB::now()
		);
		if ( false === $wpdb->query( $sql ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return new \WP_Error( 'file26_classification_write_failed', 'Classification could not be stored.', array( 'status' => 409 ) );
		}
		$this->security->audit( 'classification_' . $status, array( 'object_type' => 'classification', 'object_key' => $object_key . ':' . $term_uuid, 'metadata' => array( 'method' => sanitize_key( $method ), 'confidence' => $confidence ) ) );
		do_action( 'sabri_file26_event', 'ClassificationRecorded', array( 'object_key' => $object_key, 'term_uuid' => $term_uuid, 'status' => $status ) );
		return true;
	}

	private function sanitize_classification_provenance( array $value, $depth = 0 ) {
		if ( $depth > 3 || count( $value ) > 50 ) {
			return new \WP_Error( 'file26_classification_provenance_complexity', 'Classification provenance is too complex.', array( 'status' => 400 ) );
		}
		$clean = array();
		foreach ( $value as $raw_key => $item ) {
			$key = sanitize_key( (string) $raw_key );
			if ( '' === $key ) { continue; }
			if ( is_array( $item ) ) {
				$item = $this->sanitize_classification_provenance( $item, $depth + 1 );
				if ( is_wp_error( $item ) ) { return $item; }
			} elseif ( is_bool( $item ) || is_int( $item ) || is_float( $item ) ) {
				// Preserve bounded scalar evidence values.
			} elseif ( is_scalar( $item ) ) {
				$item = sanitize_text_field( (string) $item );
				$item = function_exists( 'mb_substr' ) ? mb_substr( $item, 0, 512, 'UTF-8' ) : substr( $item, 0, 512 );
			} else {
				continue;
			}
			$clean[ $key ] = $item;
		}
		return $clean;
	}

	private function add_alias( $term_uuid, $alias, $language ) {
		global $wpdb;
		$alias = sanitize_text_field( $alias );
		$normalized = $this->normalizer->normalize( $alias );
		if ( ! $alias || ! $normalized ) {
			return false;
		}
		$sql = $wpdb->prepare(
			'INSERT IGNORE INTO ' . DB::table( 'term_aliases' ) . '
			(term_uuid,alias_label,alias_normalized,language,status,created_at)
			VALUES (%s,%s,%s,%s,%s,%s)',
			$term_uuid,
			$alias,
			$normalized,
			substr( sanitize_text_field( $language ), 0, 20 ),
			'active',
			DB::now()
		);
		return false !== $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function domain_owner_approved( $action, array $terms, array $preview ) {
		$owners = array();
		foreach ( $terms as $term ) {
			if ( is_array( $term ) && ! empty( $term['owner_file'] ) ) {
				$owners[] = sanitize_text_field( $term['owner_file'] );
			}
		}
		$owners = array_values( array_unique( array_filter( $owners ) ) );
		$external = array_filter( $owners, static function ( $owner ) {
			return ! in_array( strtolower( trim( $owner ) ), array( 'file 26', '26', 'file26' ), true );
		} );
		$default = empty( $external );
		return (bool) apply_filters( 'sabri_file26_taxonomy_domain_owner_approved', $default, sanitize_key( $action ), $owners, $preview, get_current_user_id() );
	}

	private function would_cycle( $uuid, $parent_uuid ) {
		$seen = array( $uuid => true );
		$current = $parent_uuid;
		$depth = 0;
		while ( $current && $depth < 100 ) {
			if ( isset( $seen[ $current ] ) ) {
				return true;
			}
			$seen[ $current ] = true;
			$term = $this->get( $current );
			$current = $term ? $term['parent_uuid'] : null;
			$depth++;
		}
		return $depth >= 100;
	}
}
