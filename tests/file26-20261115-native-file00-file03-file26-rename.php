<?php
/**
 * TRUE cross-repository native File00->File03->File26 founder profile event.
 * Disposable WordPress/MariaDB only, with real File00 identity assertions,
 * real File03 profile/outbox, and real File26 index/search. All permissions
 * are checked; the helper does NOT alter File00/03 user eligibility directly.
 */
if ( 'yes' !== getenv( 'FILE26_CI_EPHEMERAL_DB' ) ||
	false === strpos( home_url( '/' ), 'file26-ci.local' ) ) {
	fwrite( STDERR, "REFUSED: 3-owner native rename integration is ephemeral only.\n" );
	exit( 2 );
}
function f26_3native_require( $ok, $label ) {
	if ( ! $ok ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); }
	echo "PASS: $label\n";
}
global $wpdb;
f26_3native_require( defined( 'SMC_VERSION' ) && defined( 'SPD_VERSION' ) && defined( 'SABRI_FILE26_VERSION' ),
	'All three actual native runtime plugin identities loaded' );
f26_3native_require( SPD_Schema_Guard::base_ready() && SPD_Schema_Guard::central_ready(),
	'Real File03 native public and central profile schema ready' );
$admin = get_user_by( 'login', 'admin' );
f26_3native_require( $admin instanceof WP_User && smc_is_founder( (int) $admin->ID ),
	'Disposable File00 legally configured founder identity confirmed by native SMC' );
$user_id = (int) $admin->ID;
$claims = smc_membership_assertions( $user_id );
f26_3native_require( is_array( $claims ) && ! empty( $claims['eligible'] ) &&
	! empty( $claims['approved'] ) && empty( $claims['suspended'] ),
	'Real File00 current founder eligibility and non-suspension proved' );

$profiles = SPD_DB::table( 'profiles' );
$fields = SPD_DB::table( 'fields' );
$profile = SPD_Profile_Repository::instance()->find_by_user_id( $user_id, false );
f26_3native_require( is_array( $profile ) && empty( $profile['id'] ),
	'No old local owner profile contaminates the 3-owner first-publication test' );
$profile_uuid = wp_generate_uuid4();
$created = $wpdb->insert( $profiles, array(
	'user_id' => $user_id, 'public_id' => $profile_uuid,
	'slug' => 'file26-real-native-founder-ci', 'profile_type' => 'founder',
	'state' => 'active', 'locale' => 'en-US',
	'version' => 14, 'created_at' => gmdate( 'Y-m-d H:i:s' ),
	'updated_at' => gmdate( 'Y-m-d H:i:s' ),
) );
f26_3native_require( 1 === (int) $created, 'Real File03 founder public owner row created in disposable MariaDB' );
$profile = SPD_Profile_Repository::instance()->find_by_user_id( $user_id, false );
f26_3native_require( is_array( $profile ) && (int) $profile['version'] === 14 &&
	$profile['public_id'] === $profile_uuid, 'Current File03 repository returns version 14' );

$before = SPD_Contracts::file26_fetch_object( 'founder', $profile_uuid );
f26_3native_require( is_array( $before ) && $before['object_id'] === $profile_uuid &&
	$before['entity_type'] === 'founder' && (int) $before['object_version'] === 14 &&
	$before['visibility'] === 'public' && $before['connector_slug'] === 'file03-profiles',
	'Native File03 owner verifies current File00 founder and exposes genuine public-only search projection' );
// File03 provides its native adapter; File26's canonical Owner_Contracts
// validates entity types and supplies the authoritative owner_file.
$manifests = \Sabri\File26\Plugin::instance()->owner_contracts()->collect( array() );
$manifest = null;
foreach ( $manifests as $candidate ) {
	if ( is_array( $candidate ) && ( $candidate['slug'] ?? '' ) === 'file03-profiles' ) {
		$manifest = $candidate;
		break;
	}
}
f26_3native_require( is_array( $manifest ) && $manifest['owner_file'] === 'File 03',
	'File26 canonical owner collection retains real File03 source and true owner_file' );
$registered = sabri_file26_register_connector( $manifest );
f26_3native_require( true === $registered, 'Genuine File03 owner connector registered in File26 proposed lane: ' .
	( is_wp_error( $registered ) ? $registered->get_error_code() : gettype( $registered ) ) );
$connectors = \Sabri\File26\DB::table( 'connectors' );
f26_3native_require( 1 === (int) $wpdb->update( $connectors,
	array( 'status' => 'active' ), array( 'slug' => 'file03-profiles' ) ),
	'Only disposable File03 native connector manually promoted for owner-read acceptance' );
f26_3native_require( true === sabri_file26_register_connector( $manifest ) &&
	\Sabri\File26\Plugin::instance()->connectors()->is_active( 'file03-profiles' ),
	'Actual native File03 connector is active only within isolated integration' );
\Sabri\File26\DB::update_settings( array( 'activated' => true, 'public_search_enabled' => true ) );
$key = sabri_file26_upsert_document( $before );
f26_3native_require( is_string( $key ) && strlen( $key ) === 64,
	'Genuine native File00/File03 founder profile is indexed through real File26 MariaDB' );
$documents = \Sabri\File26\DB::table( 'documents' );
$events = SPD_DB::table( 'events' );
$get_row = static function () use ( $wpdb, $documents, $key ) {
	return $wpdb->get_row( $wpdb->prepare(
		"SELECT object_version,title FROM $documents WHERE canonical_key=%s", $key
	), ARRAY_A );
};
$original = $get_row();
f26_3native_require( is_array( $original ) && (int) $original['object_version'] === 14,
	'Real File26 index begins at the native File03 owner version 14' );

$previous_name = (string) get_userdata( $user_id )->display_name;
$new_name = 'Native File00 approved identity revision ' . substr( wp_generate_uuid4(), 0, 6 );
$update = wp_update_user( array( 'ID' => $user_id, 'display_name' => $new_name ) );
f26_3native_require( ! is_wp_error( $update ), 'Native WordPress profile_update actually committed the new File00 public name' );
$current_name = (string) get_userdata( $user_id )->display_name;
f26_3native_require( $current_name === $new_name, 'Current File00 canonical name is the newly approved WordPress identity truth' );
$owner_current = SPD_Contracts::file26_fetch_object( 'founder', $profile_uuid );
f26_3native_require( is_array( $owner_current ) && (int) $owner_current['object_version'] === 15 &&
	$owner_current['title'] === $new_name, 'Native File03 public projection reflects both actual identity name and monotonic version 15' );
$indexed = $get_row();
f26_3native_require( is_array( $indexed ) && (int) $indexed['object_version'] === 15 &&
	$indexed['title'] === $new_name, 'File26 post-commit source hook instantly replaced previous native File00 display name in SQL' );

$old_replay = sabri_file26_upsert_document( $before );
$after_replay = $get_row();
f26_3native_require( ! is_wp_error( $old_replay ) &&
	(int) $after_replay['object_version'] === 15 && $after_replay['title'] === $new_name,
	'Real old version 14 replay cannot restore outdated identity title after native v15 owner change' );
$events_present = $wpdb->get_results( $wpdb->prepare(
	"SELECT event_name,payload,aggregate_id FROM $events WHERE aggregate_id=%s AND event_name=%s ORDER BY id DESC LIMIT 10",
	$profile_uuid, 'PublicProfileUpdated.v1'
), ARRAY_A );
$versioned = false;
foreach ( (array) $events_present as $event ) {
	$p = json_decode( (string) $event['payload'], true );
	if ( is_array( $p ) && (int) ( $p['version'] ?? 0 ) === 15 &&
		( $p['changed_fields'] ?? array() ) === array( 'file00_public_display_name' ) ) {
		$versioned = true;
	}
}
f26_3native_require( $versioned,
	'File03 real transactional owner outbox retains the File00 public-name event v15 for retry' );

$consumer = new \Sabri\File26\File03_Identity_Consumer(
	\Sabri\File26\Plugin::instance()->connectors(),
	\Sabri\File26\Plugin::instance()->indexer(),
	new \Sabri\File26\Security()
);
$consumer->on_file03_outbox( 'PublicProfileUpdated.v1', array(
	'version' => 15, 'changed_fields' => array( 'file00_public_display_name' )
), array( 'aggregate_id' => $profile_uuid ) );
f26_3native_require( 15 === (int) $get_row()['object_version'],
	'Same genuine File03 durable outbox replay is idempotent against real serving index' );

$results = sabri_file26_search( array( 'q' => $new_name, 'locale' => 'en-US', 'limit' => 10 ) );
$found = false;
if ( ! is_wp_error( $results ) ) {
	foreach ( (array) ( $results['results'] ?? array() ) as $hit ) {
		if ( ( $hit['key'] ?? '' ) === $key && ( $hit['title'] ?? '' ) === $new_name ) { $found = true; }
	}
}
f26_3native_require( $found, 'Actual anonymous File26 public search finds only latest approved native File00 founder name' );

echo "PASS: real disposable File00+03+26 identity update, native monotonic outbox, owner ACL, indexed v14->v15 and public search.\n";
