<?php
/**
 * Destructive-on-ephemeral-database WordPress/MariaDB end-to-end smoke test for FR-005.
 * Execute ONLY from CI's disposable WordPress environment, never on production/staging.
 *
 * Run: FILE26_CI_EPHEMERAL_DB=yes wp eval-file <this-file> --path=<ephemeral-wp>
 */
if ('yes' !== getenv('FILE26_CI_EPHEMERAL_DB') || false === strpos(home_url('/'), 'file26-ci.local')) {
    fwrite(STDERR, "REFUSED: this test requires a disposable file26-ci.local database.\n");
    exit(2);
}
if (!defined('SABRI_FILE26_VERSION') || !class_exists('Sabri\\File26\\Shadow_Index')) {
    fwrite(STDERR, "FAIL: File 26 plugin is not active in the disposable WordPress site.\n");
    exit(1);
}
function file26_ci_require($condition, $description) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $description . "\n");
        exit(1);
    }
    echo "PASS: " . $description . "\n";
}
function file26_ci_assert_ok($result, $label) {
    if (is_wp_error($result)) {
        fwrite(STDERR, "FAIL: " . $label . " — " . $result->get_error_code() . ": " . $result->get_error_message() . "\n");
        exit(1);
    }
    file26_ci_require($result !== false, $label);
}
global $wpdb;
$namespace = 'Sabri\\File26\\';
file26_ci_require(\Sabri\File26\DB::schema_physical_ok(), 'Real WordPress schema installed and physically complete');

$doc = array(
    'connector_slug'=>'file26_ci_fixture','domain'=>'test','object_id'=>'fixture-17',
    'object_version'=>1,'source_event_sequence'=>1,
    'entity_type'=>'article','locale'=>'ur','state'=>'published',
    'visibility'=>'public','title'=>'Original Article','excerpt'=>'Initial visible article',
    'canonical_url'=>home_url('/file26-ci/fixture-17')
);
$owner_doc = $doc;
$owner_doc['object_version'] = 2;
$owner_doc['source_event_sequence'] = 2;
$owner_doc['title'] = 'Shadow Updated Article';
$owner_doc['excerpt'] = 'New source content';
$manifest = array(
    'slug'=>'file26_ci_fixture','owner_file'=>'File 26 CI only','contract_version'=>'1.0',
    'entity_types'=>array('article'),'privacy_classes'=>array('public'),
    'visibility_fields'=>array('public'),'deletion_semantics'=>'versioned_tombstone',
    'status'=>'active',
    'list_batch'=>static function($cursor, $limit, $scope) use (&$owner_doc) {
        if ($cursor !== '') { return array('items'=>array(), 'done'=>true); }
        return array('items'=>array($owner_doc), 'done'=>true);
    },
    'can_view'=>static function($record, $audience) { return true; }
);
file26_ci_assert_ok(sabri_file26_register_connector($manifest), 'Fixture connector initial registration');
$wpdb->update(\Sabri\File26\DB::table('connectors'),array('status'=>'active'),array('slug'=>'file26_ci_fixture'));
file26_ci_assert_ok(sabri_file26_register_connector($manifest), 'Fixture owner promoted only inside disposable database');
$plugin = \Sabri\File26\Plugin::instance();
file26_ci_require($plugin->connectors()->is_active('file26_ci_fixture'), 'Fixture owner is active');

file26_ci_assert_ok(sabri_file26_upsert_document($doc), 'Version 1 indexed into real serving tables');
$documents = \Sabri\File26\DB::table('documents');
$nodes = \Sabri\File26\DB::table('nodes');
$key = \Sabri\File26\Indexer::canonical_key('file26_ci_fixture','test','fixture-17');
$live_v1 = $wpdb->get_row($wpdb->prepare("SELECT object_version,title FROM $documents WHERE canonical_key=%s",$key),ARRAY_A);
file26_ci_require(is_array($live_v1) && (int)$live_v1['object_version']===1 && $live_v1['title']==='Original Article', 'Serving index starts with version 1');
\Sabri\File26\DB::update_settings(array('activated'=>true,'telemetry_enabled'=>false));
wp_set_current_user(0);
$search_v1 = sabri_file26_search(array('q'=>'Original Article','locale'=>'ur'));
file26_ci_require(!is_wp_error($search_v1) && !empty($search_v1['results']) && $search_v1['results'][0]['title']==='Original Article', 'Guest search returns original active title through ranking and owner privacy contracts');
$uuid = $plugin->indexer()->enqueue_reindex('file26_ci_fixture');
file26_ci_assert_ok($uuid, 'Owner full shadow generation created with MySQL CREATE TABLE LIKE');
file26_ci_require(is_string($uuid) && preg_match('/^[0-9a-f-]{36}$/',$uuid)===1, 'Owner queue has a generation UUID');
$plugin->indexer()->process_queue();
$jobs = \Sabri\File26\DB::table('jobs');
$job = $wpdb->get_row($wpdb->prepare("SELECT status,counts_json FROM $jobs WHERE job_uuid=%s",$uuid),ARRAY_A);
file26_ci_require(is_array($job) && $job['status']==='completed', 'Real job worker finished with owned lease');
$still_live = $wpdb->get_var($wpdb->prepare("SELECT object_version FROM $documents WHERE canonical_key=%s",$key));
file26_ci_require((int)$still_live===1, 'Reindex has NOT overwritten active document');
$record = get_option('sabri_f26_shadow_'.str_replace('-','',$uuid),false);
file26_ci_require(is_array($record) && $record['state']==='prepared', 'Generation provenance persisted in wp_options');
$validated = \Sabri\File26\Shadow_Index::validate($uuid);
file26_ci_assert_ok($validated, 'Real SQL validates complete shadow projection');
file26_ci_require(!empty($validated['verified']) && (int)$validated['owner_count']===1, 'Candidate source count and checksum validated');

// Simulate two distinct authorized human identities ONLY in the disposable CI instance.
add_filter('sabri_file26_membership_assertions',static function($claims) {
    $claims['contract_version'] = '1.0';
    $claims['suspended'] = false;
    return $claims;
},10,1);
add_filter('sabri_file26_step_up_authorized','__return_true',10,2);
add_filter('sabri_file26_shadow_release_approved','__return_true',10,4);
$admin = get_user_by('login','admin');
file26_ci_require($admin instanceof WP_User, 'Ephemeral Founder simulation account exists');
$auditor_id = wp_create_user('file26_ci_auditor',wp_generate_password(24),'file26-ci-auditor@example.test');
file26_ci_require(!is_wp_error($auditor_id) && $auditor_id!==(int)$admin->ID, 'Separate auditor identity created');
$auditor = new WP_User($auditor_id);
$auditor->set_role('administrator');
$auditor->add_cap('audit_sabri_search',true);
wp_set_current_user($auditor_id);
file26_ci_assert_ok(\Sabri\File26\Shadow_Index::second_approve($uuid,'swap'), 'Separate auditor approves candidate checksum');
wp_set_current_user(0);
$denied = \Sabri\File26\Shadow_Index::swap($uuid);
file26_ci_require(is_wp_error($denied), 'Anonymous cutover rejected');
wp_set_current_user((int)$admin->ID);
file26_ci_assert_ok(\Sabri\File26\Shadow_Index::swap($uuid), 'Founder-authorized MySQL multi-table atomic rename');
$live_v2 = $wpdb->get_row($wpdb->prepare("SELECT object_version,title FROM $documents WHERE canonical_key=%s",$key),ARRAY_A);
file26_ci_require(is_array($live_v2) && (int)$live_v2['object_version']===2 && $live_v2['title']==='Shadow Updated Article', 'Updated candidate is now active');
wp_set_current_user(0);
$search_v2 = sabri_file26_search(array('q'=>'Shadow Updated Article','locale'=>'ur'));
file26_ci_require(!is_wp_error($search_v2) && !empty($search_v2['results']) && $search_v2['results'][0]['title']==='Shadow Updated Article', 'Guest search reads promoted generation, not previous generation');
wp_set_current_user((int)$admin->ID);
$node_version = $wpdb->get_var($wpdb->prepare("SELECT version FROM $nodes WHERE node_key=%s",$key));
file26_ci_require((int)$node_version===2, 'Knowledge node and serving document promoted together');
$stale = \Sabri\File26\Shadow_Index::stage($uuid,array(), 'expired-lease');
file26_ci_require(is_wp_error($stale), 'Post-cutover stale queue lease rejected');
wp_set_current_user($auditor_id);
file26_ci_assert_ok(\Sabri\File26\Shadow_Index::second_approve($uuid,'rollback'), 'Separate auditor approves rollback at current checksum');
wp_set_current_user((int)$admin->ID);
file26_ci_assert_ok(\Sabri\File26\Shadow_Index::rollback($uuid), 'Founder-authorized atomic rollback to original generation');
$restored = $wpdb->get_row($wpdb->prepare("SELECT object_version,title FROM $documents WHERE canonical_key=%s",$key),ARRAY_A);
file26_ci_require(is_array($restored) && (int)$restored['object_version']===1 && $restored['title']==='Original Article', 'Rollback restored exact serving document version');
wp_set_current_user(0);
$search_rollback = sabri_file26_search(array('q'=>'Original Article','locale'=>'ur'));
file26_ci_require(!is_wp_error($search_rollback) && !empty($search_rollback['results']) && $search_rollback['results'][0]['title']==='Original Article', 'Guest search reflects rollback immediately');
file26_ci_require((int)$wpdb->get_var($wpdb->prepare("SELECT version FROM $nodes WHERE node_key=%s",$key))===1, 'Rollback restored exact graph node version');
file26_ci_require(get_option('sabri_f26_shadow_'.str_replace('-','',$uuid))['state']==='rolled_back', 'Rollback provenance recorded');
echo "PASS: disposable WordPress + MariaDB FR-005 end-to-end acceptance\n";
