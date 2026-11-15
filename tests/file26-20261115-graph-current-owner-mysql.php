<?php
/**
 * F26-CEN-01: real disposable WP/MariaDB public graph read must recheck native
 * can_view for BOTH source/target nodes. Fails closed if owner revoked or
 * File26 graph title/version departs from derivative serving document.
 */
if ( 'yes' !== getenv( 'FILE26_CI_EPHEMERAL_DB' ) ||
	false === strpos( home_url( '/' ), 'file26-ci.local' ) ) {
	fwrite( STDERR, "REFUSED: no real/staging/production site may run graph ACL test.\n" );
	exit( 2 );
}
function f26_graph_acl_require( $condition, $label ) {
	if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $label . "\n" ); exit( 1 ); }
	echo 'PASS: ' . $label . "\n";
}
global $wpdb;
$slug = 'file26_ci_public_graph_acl';
$allow = array( 'graph-source-1' => true, 'graph-target-2' => true );
$native = array();
$manifest = array(
	'slug' => $slug,
	'owner_file' => 'File 26 CI current native graph ACL',
	'contract_version' => '1.0',
	'entity_types' => array( 'article' ),
	'privacy_classes' => array( 'public' ),
	'visibility_fields' => array( 'public' ),
	'deletion_semantics' => 'versioned_tombstone',
	'status' => 'proposed',
	'list_batch' => static function () { return array( 'items' => array(), 'done' => true ); },
	'health' => static function () { return array( 'state' => 'healthy' ); },
	'fetch_object' => static function ( $entity, $id ) use ( &$native ) {
		return isset( $native[$id] ) && $entity === $native[$id]['entity_type'] ? $native[$id] : null;
	},
	'can_view' => static function ( $row, $audience ) use ( &$allow ) {
		return is_array( $row ) &&
			isset( $row['object_id'], $allow[$row['object_id']] ) &&
			true === $allow[$row['object_id']] &&
			empty( $audience['authenticated'] );
	},
);
f26_graph_acl_require( true === sabri_file26_register_connector( $manifest ), 'Disposable graph native owner registered' );
$table = \Sabri\File26\DB::table( 'connectors' );
f26_graph_acl_require( 1 === (int) $wpdb->update( $table, array( 'status' => 'active' ), array( 'slug' => $slug ) ), 'Only synthetic graph native owner activated' );
f26_graph_acl_require( true === sabri_file26_register_connector( $manifest ), 'Synthetic owner runtime manifest active' );

$docs = \Sabri\File26\DB::table( 'documents' );
$nodes = \Sabri\File26\DB::table( 'nodes' );
$edges = \Sabri\File26\DB::table( 'edges' );
$keys = array();
foreach ( array( 'graph-source-1', 'graph-target-2' ) as $id ) {
	$doc = array(
		'connector_slug' => $slug, 'domain' => 'public_articles',
		'object_id' => $id, 'object_version' => 1,
		'source_event_sequence' => 1, 'entity_type' => 'article',
		'locale' => 'en-US', 'state' => 'published', 'visibility' => 'public',
		'title' => 'Public research graph ' . $id,
		'excerpt' => 'No private person, profile or clinical information',
		'canonical_url' => home_url( '/articles/' . $id ),
	);
	$native[$id] = $doc;
	$key = sabri_file26_upsert_document( $doc );
	f26_graph_acl_require( is_string( $key ) && strlen( $key ) === 64, 'Valid canonical owner publishes graph document ' . $id );
	$keys[$id] = $key;
}
$uuid = wp_generate_uuid4();
$now = \Sabri\File26\DB::now();
f26_graph_acl_require( 1 === (int) $wpdb->insert( $edges, array(
	'edge_uuid' => $uuid,
	'source_key' => $keys['graph-source-1'],
	'target_key' => $keys['graph-target-2'],
	'edge_type' => 'topic-related',
	'provenance' => wp_json_encode( array( 'source' => 'native current CI public research' ) ),
	'owner_file' => $manifest['owner_file'],
	'evidence_url' => '',
	'state' => 'active', 'visibility' => 'public', 'version' => 2,
	'created_at' => $now, 'updated_at' => $now,
) ), 'Synthetic native-approved public edge inserted only in disposable database' );
$graph = \Sabri\File26\Plugin::instance()->graph();
$visible = $graph->query( $keys['graph-source-1'], 2, 10 );
f26_graph_acl_require( ! is_wp_error( $visible ) && 2 === count( $visible['nodes'] ) &&
	1 === count( $visible['edges'] ), 'Authorized current owner permits two nodes and one edge' );

$allow['graph-target-2'] = false;
$revoked = $graph->query( $keys['graph-source-1'], 2, 10 );
f26_graph_acl_require( ! is_wp_error( $revoked ) && 1 === count( $revoked['nodes'] ) &&
	0 === count( $revoked['edges'] ) && $revoked['nodes'][0]['node_key'] === $keys['graph-source-1'],
	'Revoked native target immediately disappears from public graph without waiting for tombstone' );
f26_graph_acl_require( false === strpos( wp_json_encode( $revoked ), 'graph-target-2' ),
	'Removed public target identity/title never appears in graph JSON or edge provenance' );
$allow['graph-target-2'] = true;
$allow['graph-source-1'] = false;
$source_revoked = $graph->query( $keys['graph-source-1'], 2, 10 );
f26_graph_acl_require( is_wp_error( $source_revoked ) &&
	'file26_graph_node_not_found' === $source_revoked->get_error_code(),
	'Revoked native start node never exposes graph title or neighbor edge' );
$allow['graph-source-1'] = true;
$wpdb->update( $nodes, array( 'version' => 99 ), array( 'node_key' => $keys['graph-target-2'] ) );
$stale_graph = $graph->query( $keys['graph-source-1'], 2, 10 );
f26_graph_acl_require( ! is_wp_error( $stale_graph ) && 0 === count( $stale_graph['edges'] ),
	'Stale independently changed node version cannot pass graph owner-and-document parity' );
$wpdb->update( $nodes, array( 'version' => 1 ), array( 'node_key' => $keys['graph-target-2'] ) );
$wpdb->update( $nodes, array( 'title' => 'Old unauthorized research title' ), array( 'node_key' => $keys['graph-target-2'] ) );
$title_stale = $graph->query( $keys['graph-source-1'], 2, 10 );
f26_graph_acl_require( ! is_wp_error( $title_stale ) && 0 === count( $title_stale['edges'] ),
	'Graph cannot disclose a node title inconsistent with its current derivative search document' );

// Do not damage any real dataset: all writes above were guarded ephemeral-only.
$wpdb->delete( $edges, array( 'edge_uuid' => $uuid ) );
foreach ( $keys as $key ) {
	$wpdb->delete( $nodes, array( 'node_key' => $key ) );
	$wpdb->delete( $docs, array( 'canonical_key' => $key ) );
}
$wpdb->delete( $table, array( 'slug' => $slug ) );
echo "PASS: genuine MariaDB File26 public Graph native owner ACL and derivative parity regression.\n";
