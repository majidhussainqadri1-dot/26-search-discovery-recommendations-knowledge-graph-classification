<?php
/**
 * Real, disposable WordPress/MariaDB test for File26 direct doctor ranking.
 *
 * NEVER run on Staging or Live. The fixture uses synthetic records only and
 * requires the dedicated CI hostname and explicit disposable DB flag.
 * File07 is represented by a versioned native-owner callback fixture here,
 * not by a verified real doctor or an accepted deployed File07 connector.
 */
if ( 'yes' !== getenv( 'FILE26_CI_EPHEMERAL_DB' ) ||
	false === strpos( home_url( '/' ), 'file26-ci.local' ) ) {
	fwrite( STDERR, "REFUSED: File26 doctor test requires ephemeral CI WordPress.\n" );
	exit( 2 );
}
if ( ! class_exists( 'Sabri\\File26\\Doctor_Ranking' ) ||
	! class_exists( 'Sabri\\File26\\DB' ) ) {
	fwrite( STDERR, "FAIL: the actual File26 plugin must be loaded.\n" );
	exit( 1 );
}
function f26doctor_sql_assert( $condition, $reason ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $reason . "\n" );
		exit( 1 );
	}
	echo 'PASS: ' . $reason . "\n";
}
global $wpdb;
$plugin = \Sabri\File26\Plugin::instance();
$slug = 'file26_ci_doctor_acl';
$mode = 'allow';
$fixture_current = null;
$manifest = array(
	'slug' => $slug,
	'owner_file' => 'File 07',
	'contract_version' => 'file26-ci-owner-1',
	'entity_types' => array( 'doctor_directory_projection' ),
	'privacy_classes' => array( 'public' ),
	'visibility_fields' => array( 'public' ),
	'deletion_semantics' => 'versioned_tombstone',
	'status' => 'proposed',
	'list_batch' => static function () {
		return array( 'items' => array(), 'done' => true );
	},
	'fetch_object' => static function ( $entity_type, $object_id ) use ( &$fixture_current ) {
		if ( ! is_array( $fixture_current ) || $entity_type !== $fixture_current['entity_type'] ||
			$object_id !== $fixture_current['object_id'] ) { return null; }
		return $fixture_current;
	},
	'health' => static function () {
		return array( 'state' => 'ok', 'source' => 'synthetic-file07-ci' );
	},
	'can_view' => static function ( $document, $audience ) use ( &$mode ) {
		if ( 'error' === $mode ) { throw new \RuntimeException( 'Synthetic owner read failure' ); }
		if ( 'rejected' === $mode ) { return false; }
		if ( 'string' === $mode ) { return 'true'; }
		if ( 'newer' === $mode ) { return 2 === (int) $document['object_version']; }
		return isset( $document['payload']['verified_doctor'] ) &&
			true === $document['payload']['verified_doctor'] &&
			'public' === (string) $document['visibility'];
	},
);
$first = sabri_file26_register_connector( $manifest );
f26doctor_sql_assert( ! is_wp_error( $first ), 'fixture doctor connector registered as proposed' );
f26doctor_sql_assert(
	1 === $wpdb->update( \Sabri\File26\DB::table( 'connectors' ),
		array( 'status' => 'active' ), array( 'slug' => $slug ) ),
	'only disposable synthetic owner connector promoted for SQL test'
);
$second = sabri_file26_register_connector( $manifest );
f26doctor_sql_assert( ! is_wp_error( $second ) &&
	$plugin->connectors()->is_active( $slug ),
	'synthetic File07 owner is active in disposable database'
);
$doc = array(
	'connector_slug' => $slug,
	'domain' => 'doctors',
	'object_id' => 'file26-ci-public-doctor-1',
	'object_version' => 1,
	'source_event_sequence' => 1,
	'entity_type' => 'doctor_directory_projection',
	'locale' => 'en_US',
	'state' => 'published',
	'visibility' => 'public',
	'title' => 'Synthetic Example Doctor',
	'excerpt' => 'Synthetic public test fixture, not a real practitioner',
	'canonical_url' => home_url( '/doctors/file26-ci-public-doctor-1' ),
	'payload' => array( 'verified_doctor' => true ),
);
$fixture_current = $doc;
$inserted = sabri_file26_upsert_document( $doc );
f26doctor_sql_assert( ! is_wp_error( $inserted ) && is_string( $inserted ),
	'actual indexer writes synthetic doctor through real WordPress/MariaDB'
);
$key = \Sabri\File26\Indexer::canonical_key( $slug, 'doctors', $doc['object_id'] );
$table = \Sabri\File26\DB::table( 'documents' );
f26doctor_sql_assert( 1 === (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM $table WHERE canonical_key=%s AND visibility='public'", $key
) ), 'actual SQL contains the stale-able public doctor projection' );
$reader = new \ReflectionMethod( $plugin->doctor_ranking(), 'eligible_rows' );
$reader->setAccessible( true );
$read = static function () use ( $reader, $plugin ) {
	return $reader->invoke( $plugin->doctor_ranking() );
};
$mode = 'allow';
$allowed = $read();
f26doctor_sql_assert( is_array( $allowed ) && 1 === count( $allowed ) &&
	$allowed[0]['canonical_key'] === $key, 'owner-approved doctor appears only after current authorization' );
foreach ( array( 'rejected', 'string', 'error', 'newer' ) as $case ) {
	$mode = $case;
	$result = $read();
	f26doctor_sql_assert( is_wp_error( $result ) &&
		'file26_doctor_owner_not_current' === $result->get_error_code(),
		'synthetic owner rejection, malformed permission or read outage blocks all ranks: ' . $case );
}
$mode = 'allow';
f26doctor_sql_assert( 1 === (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM $table WHERE canonical_key=%s", $key
) ), 'source/SQL record is still present; blocking used current owner rather than deletion' );

// Malformed search projection must not silently turn into valid doctor merit.
$before = $wpdb->get_var( $wpdb->prepare(
	"SELECT payload FROM $table WHERE canonical_key=%s", $key
) );
f26doctor_sql_assert( 1 === $wpdb->update( $table,
	array( 'payload' => '{invalid-json' ), array( 'canonical_key' => $key ) ),
	'synthetic indexed payload deliberately corrupted for negative SQL test' );
$bad = $read();
f26doctor_sql_assert( is_wp_error( $bad ) &&
	'file26_doctor_owner_projection_invalid' === $bad->get_error_code(),
	'actual SQL invalid payload fails before ranking output' );
$wpdb->update( $table, array( 'payload' => $before ), array( 'canonical_key' => $key ) );

f26doctor_sql_assert( 1 === $wpdb->delete( $table,
	array( 'canonical_key' => $key ) ), 'remove synthetic doctor document after test' );
$wpdb->delete( \Sabri\File26\DB::table( 'nodes' ), array( 'node_key' => $key ) );
$wpdb->delete( \Sabri\File26\DB::table( 'connectors' ), array( 'slug' => $slug ) );
echo "PASS: genuine disposable WordPress/MariaDB doctor-ranking owner authorization matrix; NO Live test.\n";
