<?php
/**
 * Real disposable WordPress/MariaDB File03 identity consumer lifecycle using
 * a SYNTHETIC current File03 owner callback. The native File00+File03 rename
 * is separately exercised by File03 candidate PR #48. Neither is Live.
 */
if ( 'yes' !== getenv( 'FILE26_CI_EPHEMERAL_DB' ) ||
	false === strpos( home_url( '/' ), 'file26-ci.local' ) ) {
	fwrite( STDERR, "REFUSED: must not run File03 identity consumer integration on non-disposable DB.\n" );
	exit( 2 );
}
function f26_f03_delivery_check( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $message . "\n" ); exit( 1 ); }
	echo 'PASS: ' . $message . "\n";
}
global $wpdb;
$uuid = 'f2123456-1234-4234-8234-123456789abc';
$source = array(
	'connector_slug' => 'file03-profiles', 'domain' => 'profiles',
	'object_id' => $uuid, 'object_version' => 14,
	'source_event_sequence' => 14, 'entity_type' => 'member_profile',
	'locale' => 'en-US', 'state' => 'active', 'visibility' => 'public',
	'title' => 'Old approved File00 public name',
	'excerpt' => 'Public-safe research profile',
	'canonical_url' => home_url( '/profiles/' . $uuid ),
	'safety_class' => 'professional_profile',
);
$now_source = $source;
$manifest = array(
	'slug' => 'file03-profiles',
	'owner_file' => 'File 03',
	'contract_version' => 'file03-disposable-owner-1',
	'entity_types' => array( 'member_profile', 'doctor', 'founder' ),
	'privacy_classes' => array( 'public' ),
	'visibility_fields' => array( 'public' ),
	'deletion_semantics' => 'versioned_tombstone',
	'status' => 'proposed',
	'fetch_object' => static function ( $entity, $id ) use ( &$now_source, $uuid ) {
		if ( ! is_array( $now_source ) || $id !== $uuid ||
			! isset( $now_source['entity_type'] ) || $entity !== $now_source['entity_type'] ) {
			return null;
		}
		return $now_source;
	},
	'list_batch' => static function () { return array( 'items' => array(), 'done' => true ); },
	'health' => static function () { return array( 'state' => 'healthy' ); },
	'can_view' => static function ( $row, $audience ) use ( &$now_source, $uuid ) {
		return is_array( $now_source ) && isset( $row['object_id'], $row['object_version'] ) &&
			$row['object_id'] === $uuid &&
			(int) $row['object_version'] === (int) $now_source['object_version'] &&
			! empty( $now_source['visibility'] ) && 'public' === $now_source['visibility'];
	},
);
$registry = \Sabri\File26\Plugin::instance()->connectors();
f26_f03_delivery_check( true === sabri_file26_register_connector( $manifest ), 'Disposable native File03 owner manifest registered proposed' );
$con = \Sabri\File26\DB::table( 'connectors' );
f26_f03_delivery_check( 1 === (int) $wpdb->update( $con, array( 'status' => 'active' ), array( 'slug' => 'file03-profiles' ) ),
	'Only synthetic File03 owner promoted in disposable WordPress' );
f26_f03_delivery_check( true === sabri_file26_register_connector( $manifest ) && $registry->is_active( 'file03-profiles' ),
	'Current native identity owner must be active before File26 may ingest' );
\Sabri\File26\DB::update_settings( array( 'activated' => true ) );
$key = sabri_file26_upsert_document( $source );
f26_f03_delivery_check( is_string( $key ) && 64 === strlen( $key ),
	'Real MariaDB serving table starts with current approved File03 v14' );
$rows = \Sabri\File26\DB::table( 'documents' );
$probe = static function () use ( $wpdb, $rows, $key ) {
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT object_version,title FROM $rows WHERE canonical_key=%s", $key
	), ARRAY_A );
};
$first = $probe();
f26_f03_delivery_check( is_array( $first ) && 14 === (int) $first['object_version'], 'Real SQL source v14 baseline' );
$consumer = new \Sabri\File26\File03_Identity_Consumer(
	$registry, \Sabri\File26\Plugin::instance()->indexer(), new \Sabri\File26\Security()
);
$now_source['object_version'] = 15;
$now_source['source_event_sequence'] = 15;
$now_source['title'] = 'New currently approved File00 name';
$consumer->on_profile_changed( $uuid, 15 );
$changed = $probe();
f26_f03_delivery_check( is_array( $changed ) && 15 === (int) $changed['object_version'] &&
	$changed['title'] === $now_source['title'], 'Immediate native File03 event commits new name/version to actual SQL' );
$consumer->on_file03_outbox( 'PublicProfileUpdated.v1',
	array( 'version' => 15, 'changed_fields' => array( 'file00_public_display_name' ) ),
	array( 'aggregate_id' => $uuid )
);
f26_f03_delivery_check( 15 === (int) $probe()['object_version'],
	'Repeated committed outbox v15 is idempotent and cannot create a second source version' );
$must_retry = false;
try {
	$consumer->on_file03_outbox( 'PublicProfileUpdated.v1',
		array( 'version' => 16, 'changed_fields' => array( 'file00_public_display_name' ) ),
		array( 'aggregate_id' => $uuid )
	);
} catch ( \RuntimeException $e ) { $must_retry = true; }
f26_f03_delivery_check( $must_retry && 15 === (int) $probe()['object_version'],
	'Outbox v16 cannot report success while File03 current owner still has v15' );
$now_source['object_version'] = 16;
$now_source['source_event_sequence'] = 16;
$now_source['title'] = 'Second real approved public revision';
$consumer->on_file03_outbox( 'PublicProfileUpdated.v1',
	array( 'version' => 16, 'changed_fields' => array( 'file00_public_display_name' ) ),
	array( 'aggregate_id' => $uuid )
);
$restored = $probe();
f26_f03_delivery_check( 16 === (int) $restored['object_version'] &&
	$restored['title'] === $now_source['title'], 'Outbox recovery advances actual SQL only after canonical File03 current version' );
$now_source = null;
$unavailable = $consumer->refresh( $uuid, 16 );
f26_f03_delivery_check( is_wp_error( $unavailable ) &&
	'file26_file03_public_projection_unavailable' === $unavailable->get_error_code(),
	'Current native owner unavailable cannot forge public approved index data' );
f26_f03_delivery_check( 16 === (int) $probe()['object_version'],
	'Native owner outage does not mutate previously stored projection' );
$wpdb->delete( \Sabri\File26\DB::table( 'nodes' ), array( 'node_key' => $key ) );
$wpdb->delete( $rows, array( 'canonical_key' => $key ) );
$wpdb->delete( $con, array( 'slug' => 'file03-profiles' ) );
delete_option( 'sabri_file26_file03_identity_refresh_error' );
echo "PASS: native File03 synthetic current-owner revision, source replay and real MariaDB receipt (not Live).\n";
