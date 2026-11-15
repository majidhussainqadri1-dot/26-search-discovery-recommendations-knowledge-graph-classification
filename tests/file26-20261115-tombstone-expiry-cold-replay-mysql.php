<?php
/**
 * FR006 R1/R2: actual disposable WordPress/MariaDB tombstone-expiry replay.
 * This uses synthetic in-memory owner callbacks; it does not certify real
 * File03/05/06/07/08/10/11/12/15/18/21 contracts or Hostinger.
 */
if ( 'yes' !== getenv( 'FILE26_CI_EPHEMERAL_DB' ) ||
	false === strpos( home_url( '/' ), 'file26-ci.local' ) ) {
	fwrite( STDERR, "REFUSED: FR006 test requires an isolated disposable database.\n" );
	exit( 2 );
}
function f26cold_db_check( $condition, $label ) {
	if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $label . "\n" ); exit( 1 ); }
	echo 'PASS: ' . $label . "\n";
}
global $wpdb;
$slug = 'file26_ci_cold_replay';
$doc = array(
	'connector_slug' => $slug, 'domain' => 'articles',
	'object_id' => 'first-published-100', 'object_version' => 1,
	'source_event_sequence' => 1, 'entity_type' => 'article',
	'locale' => 'en-US', 'state' => 'published', 'visibility' => 'public',
	'title' => 'Published before revocation',
	'excerpt' => 'Synthetic permitted abstract',
	'canonical_url' => home_url( '/articles/first-published-100' ),
);
$owner_current = $doc;
$owner_outage = false;
$manifest = array(
	'slug' => $slug, 'owner_file' => 'File 26 disposable CI owner',
	'contract_version' => 'test.1', 'entity_types' => array( 'article' ),
	'privacy_classes' => array( 'public' ), 'visibility_fields' => array( 'public' ),
	'deletion_semantics' => 'versioned_tombstone',
	'status' => 'proposed',
	'list_batch' => static function () { return array( 'items' => array(), 'done' => true ); },
	'health' => static function () { return array( 'state' => 'ok' ); },
	'can_view' => static function () use ( &$owner_current ) { return is_array( $owner_current ); },
	'fetch_object' => static function ( $entity, $object ) use ( &$owner_current, &$owner_outage ) {
		if ( $owner_outage ) { throw new \RuntimeException( 'owner SQL transport unavailable' ); }
		if ( ! is_array( $owner_current ) || $entity !== $owner_current['entity_type'] ||
			$object !== $owner_current['object_id'] ) { return null; }
		return $owner_current;
	},
);
$registered = sabri_file26_register_connector( $manifest );
f26cold_db_check( ! is_wp_error( $registered ), 'synthetic canonical owner registered' );
$connectors = \Sabri\File26\DB::table( 'connectors' );
f26cold_db_check( 1 === (int) $wpdb->update( $connectors,
	array( 'status' => 'active' ), array( 'slug' => $slug ) ), 'only disposable owner approved' );
$registered = sabri_file26_register_connector( $manifest );
f26cold_db_check( ! is_wp_error( $registered ), 'synthetic active owner loaded in current registry' );

$docs = \Sabri\File26\DB::table( 'documents' );
$nodes = \Sabri\File26\DB::table( 'nodes' );
$tombs = \Sabri\File26\DB::table( 'tombstones' );
$key = \Sabri\File26\Indexer::canonical_key( $slug, 'articles', $doc['object_id'] );
$initial = sabri_file26_upsert_document( $doc );
f26cold_db_check( is_string( $initial ) && $initial === $key, 'first cold publication receives fresh canonical owner attestation' );
f26cold_db_check( 1 === (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM $docs WHERE canonical_key=%s", $key ) ), 'v1 published in actual MySQL serving index' );

// The source owner now deletes the object at v2.
$owner_current = null;
$removed = sabri_file26_tombstone_document( $slug, 'articles', $doc['object_id'], 2, 'deleted' );
f26cold_db_check( ! is_wp_error( $removed ), 'native delete v2 removes public record' );
f26cold_db_check( 0 === (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM $docs WHERE canonical_key=%s", $key ) ), 'v1 document absent after v2 delete' );
f26cold_db_check( 2 === (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT object_version FROM $tombs WHERE canonical_key=%s", $key ) ), 'bounded tombstone records v2' );

// Expire/delete the tombstone, reproducing the prior unsafe no-row/no-marker case.
f26cold_db_check( 1 === (int) $wpdb->update( $tombs,
	array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ),
	array( 'canonical_key' => $key ) ), 'force expiry exclusively inside disposable MariaDB' );
\Sabri\File26\Plugin::instance()->indexer()->retention();
f26cold_db_check( null === $wpdb->get_var(
	$wpdb->prepare( "SELECT object_version FROM $tombs WHERE canonical_key=%s", $key ) ),
	'bounded tombstone really was purged' );
$replayed = sabri_file26_upsert_document( $doc );
f26cold_db_check( is_wp_error( $replayed ) &&
	'file26_cold_owner_attestation_denied' === $replayed->get_error_code(),
	'expired-tombstone stale v1 replay fails closed against deleted native owner' );
f26cold_db_check( 0 === (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM $docs WHERE canonical_key=%s", $key ) ),
	'stale event did not resurrect a document in MariaDB' );
f26cold_db_check( 0 === (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM $nodes WHERE node_key=%s", $key ) ),
	'stale event did not resurrect the knowledge node' );

$owner_outage = true;
$outage = sabri_file26_upsert_document( $doc );
f26cold_db_check( is_wp_error( $outage ) &&
	'file26_cold_owner_fetch_failed' === $outage->get_error_code(),
	'owner outage cannot transform expired revocation into a new public record' );
$owner_outage = false;

// Only a strictly newer and current native publication can be re-admitted.
$reapproved = $doc;
$reapproved['object_version'] = 3;
$reapproved['source_event_sequence'] = 3;
$reapproved['title'] = 'Freshly republished with version 3';
$owner_current = $reapproved;
$still_old = sabri_file26_upsert_document( $doc );
f26cold_db_check( is_wp_error( $still_old ) &&
	'file26_cold_owner_version_conflict' === $still_old->get_error_code(),
	'old v1 replay rejected even if the same owner has genuinely reapproved v3' );
$accepted = sabri_file26_upsert_document( $reapproved );
f26cold_db_check( is_string( $accepted ) && $accepted === $key, 'owner-confirmed higher v3 restores public projection' );
f26cold_db_check( 3 === (int) $wpdb->get_var(
	$wpdb->prepare( "SELECT object_version FROM $docs WHERE canonical_key=%s", $key ) ),
	'MariaDB contains only fresh native v3, not stale v1' );

// No content is used outside the ephemeral CI instance.
$wpdb->delete( $docs, array( 'canonical_key' => $key ) );
$wpdb->delete( $nodes, array( 'node_key' => $key ) );
$wpdb->delete( $connectors, array( 'slug' => $slug ) );
echo "PASS: real MariaDB FR006 expired-tombstone denial and legitimate v3 reapproval; no Hostinger claims.\n";
