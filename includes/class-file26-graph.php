<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Graph {
	private $security;

	private $allowed_edges = array(
		'author-of',
		'lesson-about',
		'remedy-related',
		'doctor-published',
		'book-chapter',
		'video-explains',
		'research-supports',
		'research-contradicts',
		'topic-related',
	);

	public function __construct( Security $security ) {
		$this->security = $security;
	}

	public function create_edge( array $input ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) {
			return new \WP_Error( 'file26_forbidden', 'Graph curator capability is required.' );
		}
		$source = preg_replace( '/[^a-f0-9]/', '', strtolower( isset( $input['source_key'] ) ? $input['source_key'] : '' ) );
		$target = preg_replace( '/[^a-f0-9]/', '', strtolower( isset( $input['target_key'] ) ? $input['target_key'] : '' ) );
		$type = sanitize_key( isset( $input['edge_type'] ) ? $input['edge_type'] : '' );
		if ( strlen( $source ) !== 64 || strlen( $target ) !== 64 || ! in_array( $type, $this->allowed_edges, true ) ) {
			return new \WP_Error( 'file26_invalid_edge', 'Invalid graph edge.' );
		}
		if ( ! $this->public_node_exists( $source ) || ! $this->public_node_exists( $target ) ) {
			return new \WP_Error( 'file26_invalid_edge_endpoint', 'Both graph endpoints must be valid visible nodes.' );
		}
		$provenance = isset( $input['provenance'] ) && is_array( $input['provenance'] ) ? $this->sanitize_provenance( $input['provenance'] ) : array();
		if ( is_wp_error( $provenance ) ) {
			return $provenance;
		}
		if ( empty( $provenance ) ) {
			return new \WP_Error( 'file26_provenance_required', 'Graph provenance is required.' );
		}
		$encoded_provenance = wp_json_encode( $provenance );
		if ( false === $encoded_provenance || strlen( $encoded_provenance ) > 16384 ) {
			return new \WP_Error( 'file26_provenance_too_large', 'Graph provenance exceeds the allowed size.', array( 'status' => 413 ) );
		}
		$evidence_url = $this->sanitize_evidence_url( isset( $input['evidence_url'] ) ? $input['evidence_url'] : '' );
		if ( is_wp_error( $evidence_url ) ) {
			return $evidence_url;
		}
		$uuid = DB::uuid();
		$inserted = $wpdb->insert(
			DB::table( 'edges' ),
			array(
				'edge_uuid' => $uuid,
				'source_key' => $source,
				'target_key' => $target,
				'edge_type' => $type,
				'provenance' => $encoded_provenance,
				'owner_file' => isset( $input['owner_file'] ) ? substr( sanitize_text_field( $input['owner_file'] ), 0, 64 ) : 'File 26',
				'evidence_url' => $evidence_url,
				// Creation never self-publishes; activation is a separate audited governance transition.
				'state' => 'draft',
				'visibility' => 'public',
				'version' => 1,
				'created_at' => DB::now(),
				'updated_at' => DB::now(),
			)
		);
		if ( ! $inserted ) {
			return new \WP_Error( 'file26_edge_insert_failed', 'Graph edge could not be created.', array( 'status' => 409 ) );
		}
		$this->security->audit( 'knowledge_edge_created', array( 'object_type' => 'knowledge_edge', 'object_key' => $uuid, 'metadata' => array( 'edge_type' => $type, 'source_key' => $source, 'target_key' => $target ) ) );
		do_action( 'sabri_file26_event', 'KnowledgeEdgeCreated', array( 'edge_uuid' => $uuid, 'edge_type' => $type ) );
		return $uuid;
	}

	public function query( $start_key, $depth = 1, $degree = 10, array $allowed_types = array() ) {
		global $wpdb;
		$start_key = preg_replace( '/[^a-f0-9]/', '', strtolower( $start_key ) );
		if ( strlen( $start_key ) !== 64 || ! $this->public_node_exists( $start_key ) ) {
			return new \WP_Error( 'file26_graph_node_not_found', 'Graph node not found.', array( 'status' => 404 ) );
		}
		$depth = max( 1, min( (int) DB::setting( 'graph_max_depth', 2 ), (int) $depth ) );
		$degree = max( 1, min( (int) DB::setting( 'graph_max_degree', 20 ), (int) $degree ) );
		$allowed_types = array_values( array_intersect( array_map( 'sanitize_key', $allowed_types ), $this->allowed_edges ) );
		if ( ! $allowed_types ) {
			$allowed_types = $this->allowed_edges;
		}
		$visited = array( $start_key => true );
		$frontier = array( $start_key );
		$edges = array();
		$nodes = array();
		for ( $level = 0; $level < $depth && $frontier; $level++ ) {
			$next = array();
			foreach ( $frontier as $node_key ) {
				$placeholders = implode( ',', array_fill( 0, count( $allowed_types ), '%s' ) );
				$args = array_merge( array( $node_key ), $allowed_types, array( $degree ) );
				$sql = $wpdb->prepare(
					'SELECT * FROM ' . DB::table( 'edges' ) . "
					WHERE source_key=%s AND state='active' AND visibility='public'
					AND edge_type IN ($placeholders)
					ORDER BY edge_type,edge_uuid LIMIT %d",
					$args
				);
				$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				foreach ( $rows as $edge ) {
					if ( ! $this->public_node_exists( $edge['target_key'] ) ) {
						continue;
					}
					$edge['provenance'] = json_decode( $edge['provenance'], true );
					$edges[] = $edge;
					if ( ! isset( $visited[ $edge['target_key'] ] ) ) {
						$visited[ $edge['target_key'] ] = true;
						$next[] = $edge['target_key'];
					}
				}
			}
			$frontier = array_slice( array_values( array_unique( $next ) ), 0, $degree * $degree );
		}
		if ( $visited ) {
			$keys = array_keys( $visited );
			$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
			$sql = $wpdb->prepare(
				'SELECT node_key,node_type,canonical_url,locale,version,title FROM ' . DB::table( 'nodes' ) . "
				WHERE node_key IN ($placeholders) AND state IN ('active','published','corrected') AND visibility='public'",
				$keys
			);
			$nodes = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return array(
			'contract_version' => SABRI_FILE26_CONTRACT_VERSION,
			'start_key' => $start_key,
			'depth' => $depth,
			'nodes' => $nodes,
			'edges' => $edges,
		);
	}


	private function sanitize_provenance( array $value, $depth = 0 ) {
		if ( $depth > 3 || count( $value ) > 50 ) {
			return new \WP_Error( 'file26_provenance_complexity', 'Graph provenance is too complex.', array( 'status' => 400 ) );
		}
		$clean = array();
		foreach ( $value as $raw_key => $item ) {
			$key = sanitize_key( (string) $raw_key );
			if ( '' === $key ) {
				continue;
			}
			if ( is_array( $item ) ) {
				$item = $this->sanitize_provenance( $item, $depth + 1 );
				if ( is_wp_error( $item ) ) {
					return $item;
				}
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

	private function sanitize_evidence_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, '/' ) ) {
			return $this->security->safe_url( $url );
		}
		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		$parts = $url ? wp_parse_url( $url ) : false;
		if ( ! $parts || empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return new \WP_Error( 'file26_invalid_evidence_url', 'Invalid graph evidence URL.', array( 'status' => 400 ) );
		}
		$host = strtolower( $parts['host'] );
		if ( 'localhost' === $host || substr( $host, -6 ) === '.local' || ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) ) {
			return new \WP_Error( 'file26_unsafe_evidence_url', 'Unsafe graph evidence URL.', array( 'status' => 400 ) );
		}
		if ( ! apply_filters( 'sabri_file26_allowed_evidence_url', true, $url, $host ) ) {
			return new \WP_Error( 'file26_evidence_url_not_allowed', 'The graph evidence URL is not allowed.', array( 'status' => 403 ) );
		}
		return $url;
	}

	private function public_node_exists( $key ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM ' . DB::table( 'nodes' ) . " WHERE node_key=%s AND state IN ('active','published','corrected') AND visibility='public' LIMIT 1",
				$key
			)
		);
	}
}
