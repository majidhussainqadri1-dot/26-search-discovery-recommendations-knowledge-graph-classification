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

// R2.1 HOT path: a serving row already exists at v3. A fabricated native
// v4 event is not authoritative merely because 4 > 3.
$fabricated_v4 = $reapproved;
$fabricated_v4['object_version'] = 4;
$fabricated_v4['source_event_sequence'] = 4;
$fabricated_v4['title'] = 'Injected disallowed professional claim';
$owner_current = null;
$revoked_hot = sabri_file26_upsert_document( $fabricated_v4 );
f26cold_db_check( is_wp_error( $revoked_hot ) &&
	'file26_cold_owner_attestation_denied' === $revoked_hot->get_error_code(),
	'Serving v3 cannot be overwritten by a fabricated higher v4 during native owner revocation' );
$owner_current = $reapproved;
$false_newer = sabri_file26_upsert_document( $fabricated_v4 );
f26cold_db_check( is_wp_error( $false_newer ) &&
	'file26_cold_owner_version_conflict' === $false_newer->get_error_code(),
	'Serving v3 rejects higher v4 that current owner has not issued' );
f26cold_db_check( 3 === (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT object_version FROM $docs WHERE canonical_key=%s", $key ) ),
	'Failed hot replay preserved v3 active serving version' );
$owner_current = $fabricated_v4;
$owner_current['title'] = 'Actually current approved professional education';
$spoofed = sabri_file26_upsert_document( $fabricated_v4 );
f26cold_db_check( is_wp_error( $spoofed ) &&
	'file26_cold_owner_projection_conflict' === $spoofed->get_error_code(),
	'Serving v3 cannot accept same-v4 but counterfeit title' );
$valid_native_v4 = $owner_current;
$approved_v4 = sabri_file26_upsert_document( $valid_native_v4 );
f26cold_db_check( is_string( $approved_v4 ) && $approved_v4 === $key,
	'Genuinely current native v4 can safely advance an existing serving record' );
f26cold_db_check( 4 === (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT object_version FROM $docs WHERE canonical_key=%s", $key ) ),
	'Actual SQL serving index advanced only after current owner approval' );

// A stale or forged event may keep the exact owner version, title and URL,
// but change *another* indexed public field. R3 proves full normalized parity,
// not a superficial version/title-only attestation, in actual MariaDB.
$parity_truth = $doc;
$parity_truth['object_id'] = 'field-parity-fresh-102';
$parity_truth['canonical_url'] = home_url( '/articles/field-parity-fresh-102' );
$parity_truth['object_version'] = 9;
$parity_truth['source_event_sequence'] = 9;
$parity_truth['search_text'] = 'Owner-approved public education content';
$parity_truth['country'] = 'Pakistan';
$parity_truth['location'] = 'Gujrat';
$parity_truth['author_key'] = 'owner:approved-public';
$parity_truth['safety_class'] = 'general';
$parity_truth['topic_ids'] = array( 'remedy-history' );
$parity_truth['quality_score'] = 0.7;
$parity_truth['payload'] = array( 'verified_doctor' => false, 'content_type_label' => 'Education' );
$owner_current = $parity_truth;
foreach ( array(
	'body' => array( 'search_text' => 'stale personal patient data' ),
	'city' => array( 'location' => 'Hidden private clinic' ),
	'country' => array( 'country' => 'Unapproved private address' ),
	'owner' => array( 'author_key' => 'unverified:other-owner' ),
	'topic' => array( 'topic_ids' => array( 'restricted-patient-topic' ) ),
	'quality' => array( 'quality_score' => 0.99 ),
	'trust' => array( 'payload' => array( 'verified_doctor' => true, 'content_type_label' => 'Education' ) ),
	'label' => array( 'payload' => array( 'verified_doctor' => false, 'content_type_label' => 'Unapproved medical advice' ) ),
	'safety' => array( 'safety_class' => 'verified_professional_directory' ),
) as $name => $changes ) {
	$tampered = array_merge( $parity_truth, $changes );
	$denied = sabri_file26_upsert_document( $tampered );
	f26cold_db_check( is_wp_error( $denied ) &&
		'file26_native_projection_mismatch' === $denied->get_error_code(),
		'current same-version owner refuses stale or forged public index field: ' . $name );
}
$parity_key = \Sabri\File26\Indexer::canonical_key( $slug, 'articles', $parity_truth['object_id'] );
f26cold_db_check( 0 === (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM $docs WHERE canonical_key=%s", $parity_key ) ),
	'No partial tampered index row entered real MariaDB after nine malicious field variants' );
$parity_saved = sabri_file26_upsert_document( $parity_truth );
f26cold_db_check( is_string( $parity_saved ) && $parity_saved === $parity_key,
	'Exact complete native owner public-safe search projection remains indexable' );
$wpdb->delete( $docs, array( 'canonical_key' => $parity_key ) );
$wpdb->delete( $nodes, array( 'node_key' => $parity_key ) );

// No content is used outside the ephemeral CI instance.
$wpdb->delete( $docs, array( 'canonical_key' => $key ) );
$wpdb->delete( $nodes, array( 'node_key' => $key ) );
$wpdb->delete( $connectors, array( 'slug' => $slug ) );
echo "PASS: real MariaDB FR006 expired-tombstone denial and legitimate v3 reapproval; no Hostinger claims.\n";
