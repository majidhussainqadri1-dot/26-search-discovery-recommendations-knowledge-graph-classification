<?php
/**
 * FR003/006: real disposable MySQL graph node/edge source-rights revocation
 * regression. Native owner is a synthetic in-memory callback, never Live.
 */
if ( 'yes' !== getenv( 'FILE26_CI_EPHEMERAL_DB' ) ||
	false === strpos( home_url( '/' ), 'file26-ci.local' ) ) {
	fwrite( STDERR, "REFUSED: graph-rights test requires ephemeral WordPress/MySQL.\n" );
	exit( 2 );
}
function f26_graph_assert( $condition, $what ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $what\n" ); exit( 1 ); }
	echo "PASS: $what\n";
}
global $wpdb;
$slug = 'file26_ci_graph_acl';
$owner_documents = array();
$owner_outage = false;
$manifest = array(
	'slug' => $slug, 'owner_file' => 'File 26 isolated graph fixture',
	'contract_version' => '1.0', 'entity_types' => array( 'article' ),
	'privacy_classes' => array( 'public' ),
	'visibility_fields' => array( 'public' ),
	'deletion_semantics' => 'versioned_tombstone',
	'status' => 'proposed',
	'list_batch' => static function () { return array( 'items' => array(), 'done' => true ); },
	'health' => static function () { return array( 'state' => 'healthy' ); },
	'fetch_object' => static function ( $entity_type, $object_id ) use ( &$owner_documents, &$owner_outage ) {
		if ( $owner_outage ) { throw new \RuntimeException( 'source unavailable' ); }
		$doc = isset( $owner_documents[ $object_id ] ) ? $owner_documents[ $object_id ] : null;
		return is_array( $doc ) && $entity_type === $doc['entity_type'] ? $doc : null;
	},
	'can_view' => static function ( $indexed, $audience ) use ( &$owner_documents, &$owner_outage ) {
		if ( $owner_outage ) { throw new \RuntimeException( 'owner ACL unavailable' ); }
		if ( ! is_array( $indexed ) ) { return false; }
		$current = isset( $owner_documents[ $indexed['object_id'] ] ) ? $owner_documents[ $indexed['object_id'] ] : null;
		return is_array( $current ) && 'published' === $current['state']
			&& 'public' === $current['visibility']
			&& (string) $current['object_version'] === (string) $indexed['object_version']
			&& $current['title'] === $indexed['title'];
	},
);
$registered = sabri_file26_register_connector( $manifest );
f26_graph_assert( ! is_wp_error( $registered ), 'proposed synthetic graph owner registered' );
$connectors = \Sabri\File26\DB::table( 'connectors' );
f26_graph_assert( 1 === (int) $wpdb->update( $connectors,
	array( 'status' => 'active' ), array( 'slug' => $slug ) ), 'source approved only in disposable test DB' );
$registered = sabri_file26_register_connector( $manifest );
f26_graph_assert( ! is_wp_error( $registered ), 'ephemeral approved owner loaded' );
$make = static function ( $id, $title ) use ( $slug ) {
	return array(
		'connector_slug' => $slug, 'domain' => 'education',
		'object_id' => $id, 'object_version' => 1, 'source_event_sequence' => 1,
		'entity_type' => 'article', 'locale' => 'en-US',
		'state' => 'published', 'visibility' => 'public',
		'title' => $title, 'excerpt' => 'Public source educational abstract',
		'search_text' => $title . ' Public source educational abstract',
		'canonical_url' => home_url( '/file26-ci/graph/' . $id ),
	);
};
$a = $make( 'source-article', 'Graph Source Educational Entry' );
$b = $make( 'target-article', 'Graph Target Confidentiality Sentinel' );
$owner_documents[ $a['object_id'] ] = $a;
$owner_documents[ $b['object_id'] ] = $b;
$key_a = sabri_file26_upsert_document( $a );
$key_b = sabri_file26_upsert_document( $b );
f26_graph_assert( is_string( $key_a ) && is_string( $key_b ), 'two real MySQL public graph documents and nodes indexed' );
$edges_table = \Sabri\File26\DB::table( 'edges' );
$edge_uuid = \Sabri\File26\DB::uuid();
$inserted = $wpdb->insert( $edges_table, array(
	'edge_uuid' => $edge_uuid, 'source_key' => $key_a,
	'target_key' => $key_b, 'edge_type' => 'topic-related',
	'provenance' => wp_json_encode( array( 'source' => 'disposable test fixture' ) ),
	'owner_file' => 'File 26 CI', 'evidence_url' => '', 'state' => 'active',
	'visibility' => 'public', 'version' => 1,
	'created_at' => \Sabri\File26\DB::now(), 'updated_at' => \Sabri\File26\DB::now(),
) );
f26_graph_assert( 1 === (int) $inserted, 'real SQL public graph edge materialized' );
$invalid_edge_uuid = \Sabri\File26\DB::uuid();
$invalid_edge = $wpdb->insert( $edges_table, array(
	'edge_uuid' => $invalid_edge_uuid, 'source_key' => $key_a,
	'target_key' => $key_b, 'edge_type' => 'topic-related',
	'provenance' => '{malformed', 'owner_file' => 'File 26 CI',
	'evidence_url' => '', 'state' => 'active', 'visibility' => 'public',
	'version' => 1, 'created_at' => \Sabri\File26\DB::now(),
	'updated_at' => \Sabri\File26\DB::now(),
) );
f26_graph_assert( 1 === (int) $invalid_edge,
	'disposable source-corrupt edge materialized for negative provenance regression' );
$graph = \Sabri\File26\Plugin::instance()->graph();
$before = $graph->query( $key_a, 1, 10 );
f26_graph_assert( ! is_wp_error( $before ) && count( $before['nodes'] ) === 2
	&& count( $before['edges'] ) === 1,
	'fully authorized public source and target yield exactly one edge' );
// Simulate source revocation before File26 has consumed a tombstone:
$owner_documents[ $b['object_id'] ]['visibility'] = 'restricted';
$after = $graph->query( $key_a, 1, 10 );
f26_graph_assert( ! is_wp_error( $after ) && count( $after['nodes'] ) === 1
	&& count( $after['edges'] ) === 0
	&& false === strpos( wp_json_encode( $after ), 'Confidentiality Sentinel' ),
	'stale public graph cache cannot leak revoked target title/edge' );
$owner_documents[ $a['object_id'] ]['state'] = 'deleted';
$hidden_start = $graph->query( $key_a, 1, 10 );
f26_graph_assert( is_wp_error( $hidden_start )
	&& 'file26_graph_node_not_found' === $hidden_start->get_error_code(),
	'deleted source becomes graph-invisible before index/tombstone event arrives' );
$owner_documents[ $a['object_id'] ] = $a;
$owner_documents[ $b['object_id'] ] = $b;
$owner_outage = true;
$outage = $graph->query( $key_a, 1, 10 );
f26_graph_assert( is_wp_error( $outage ) && 'file26_graph_node_not_found' === $outage->get_error_code(),
	'canonical owner ACL outage fails closed, never trusts cached graph labels' );
$owner_outage = false;
$wpdb->delete( $edges_table, array( 'edge_uuid' => $edge_uuid ) );
$wpdb->delete( $edges_table, array( 'edge_uuid' => $invalid_edge_uuid ) );
$wpdb->delete( \Sabri\File26\DB::table( 'nodes' ), array( 'node_key' => $key_a ) );
$wpdb->delete( \Sabri\File26\DB::table( 'nodes' ), array( 'node_key' => $key_b ) );
$wpdb->delete( \Sabri\File26\DB::table( 'documents' ), array( 'canonical_key' => $key_a ) );
$wpdb->delete( \Sabri\File26\DB::table( 'documents' ), array( 'canonical_key' => $key_b ) );
$wpdb->delete( $connectors, array( 'slug' => $slug ) );
echo "PASS: File26 current-native-owner ACL for graph nodes/edges in actual disposable MariaDB\n";
