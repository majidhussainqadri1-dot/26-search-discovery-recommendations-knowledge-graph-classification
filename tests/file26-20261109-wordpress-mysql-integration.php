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
$owner_items = array($owner_doc);
$owner_documents = array($doc['object_id']=>$doc);
$owner_batch_mode = 'normal';
$manifest = array(
    'slug'=>'file26_ci_fixture','owner_file'=>'File 26 CI only','contract_version'=>'1.0',
    'entity_types'=>array('article'),'privacy_classes'=>array('public'),
    'visibility_fields'=>array('public'),'deletion_semantics'=>'versioned_tombstone',
    'status'=>'active',
    'list_batch'=>static function($cursor, $limit, $scope) use (&$owner_items, &$owner_batch_mode) {
        if ('bad_done' === $owner_batch_mode) {
            return array('items'=>$owner_items, 'done'=>'false', 'next_cursor'=>'claimed-complete');
        }
        if ('too_many' === $owner_batch_mode) {
            return array('items'=>array_fill(0, $limit+1, $owner_items[0]), 'done'=>true);
        }
        if ('bad_cursor' === $owner_batch_mode) {
            return array('items'=>array(), 'done'=>false, 'next_cursor'=>str_repeat('c',192));
        }
        if ($cursor !== '') { return array('items'=>array(), 'done'=>true); }
        return array('items'=>$owner_items, 'done'=>true);
    },
    'can_view'=>static function($record, $audience) { return true; },
    'fetch_object'=>static function($entity_type,$object_id) use (&$owner_documents,&$owner_items) {
        if(isset($owner_documents[$object_id]) && $owner_documents[$object_id]['entity_type']===$entity_type){return $owner_documents[$object_id];}
        foreach((array)$owner_items as $candidate){if(is_array($candidate)
            && ($candidate['object_id']??'')===$object_id
            && ($candidate['entity_type']??'')===$entity_type){return $candidate;}}
        return null;
    }
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
// The native owner truly publishes v2 before the shadow worker starts.
$owner_documents[$doc['object_id']] = $owner_doc;
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
    $claims['user_id'] = get_current_user_id();
    $claims['approved'] = true; // disposable test identity ONLY
    $claims['eligible'] = true;
    $claims['guardian_verified'] = true;
    $claims['age_context'] = array('known'=>true,'minor'=>false);
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

// Privacy and drift checks run AFTER successful rollback; all data is disposable.
$member_doc = $doc;
$member_doc['object_id'] = 'fixture-private-18';
$member_doc['canonical_url'] = home_url('/file26-ci/fixture-private-18');
$member_doc['title'] = 'Members Only Learning Notes';
$member_doc['visibility'] = 'members';
wp_set_current_user((int)$admin->ID);
$owner_documents[$member_doc['object_id']]=$member_doc;
file26_ci_assert_ok(sabri_file26_upsert_document($member_doc), 'Members-only indexed result stored for the privacy gate');
wp_set_current_user(0);
$guest_private = sabri_file26_search(array('q'=>'Members Only Learning Notes','locale'=>'ur'));
file26_ci_require(!is_wp_error($guest_private) && empty($guest_private['results']), 'Anonymous user cannot discover members-only document');
wp_set_current_user((int)$admin->ID);
$member_private = sabri_file26_search(array('q'=>'Members Only Learning Notes','locale'=>'ur'));
file26_ci_require(!is_wp_error($member_private) && !empty($member_private['results']), 'Authenticated valid member can discover permitted member result');

$drift_uuid = $plugin->indexer()->enqueue_reindex('file26_ci_fixture');
file26_ci_assert_ok($drift_uuid, 'Second generation started for drift negative test');
$plugin->indexer()->process_queue();
$live_change = $doc;
$live_change['object_version'] = 3;
$live_change['source_event_sequence'] = 3;
$live_change['title'] = 'Concurrent Owner Live Update';
file26_ci_assert_ok(sabri_file26_upsert_document($live_change), 'Concurrent live owner update made after snapshot');
$drift_proof = \Sabri\File26\Shadow_Index::validate($drift_uuid);
file26_ci_require(is_wp_error($drift_proof) && $drift_proof->get_error_code()==='file26_shadow_source_drift', 'Real database drift correctly blocks stale candidate promotion');


// A full rebuild must never replace a newer serving owner event with stale data.
$stale_uuid = $plugin->indexer()->enqueue_reindex('file26_ci_fixture');
file26_ci_assert_ok($stale_uuid, 'Stale owner snapshot negative-test generation created');
$plugin->indexer()->process_queue();
$stale_job_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $jobs WHERE job_uuid=%s",$stale_uuid));
file26_ci_require($stale_job_status==='retry', 'Stale owner reindex is retried, never falsely completed');
$stale_proof = \Sabri\File26\Shadow_Index::validate($stale_uuid);
file26_ci_require(is_wp_error($stale_proof) && $stale_proof->get_error_code()==='file26_shadow_incomplete', 'Stale owner batch cannot be promoted');

// Equal total count cannot hide omitted canonical records without deletion proof.
$substitute_a = $doc;
$substitute_a['object_id'] = 'substitute-a';
$substitute_a['canonical_url'] = home_url('/file26-ci/substitute-a');
$substitute_a['title'] = 'Unexpected Substitute A';
$substitute_b = $doc;
$substitute_b['object_id'] = 'substitute-b';
$substitute_b['canonical_url'] = home_url('/file26-ci/substitute-b');
$substitute_b['title'] = 'Unexpected Substitute B';
$owner_items = array($substitute_a, $substitute_b);
$omission_uuid = $plugin->indexer()->enqueue_reindex('file26_ci_fixture');
file26_ci_assert_ok($omission_uuid, 'Equal-count omission negative-test generation created');
$plugin->indexer()->process_queue();
$omission_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $jobs WHERE job_uuid=%s",$omission_uuid));
file26_ci_require($omission_status==='completed', 'Substitution fixture staged without a batch error');
$omission_proof = \Sabri\File26\Shadow_Index::validate($omission_uuid);
file26_ci_require(is_wp_error($omission_proof) && $omission_proof->get_error_code()==='file26_shadow_unrevoked_omission', 'Equal-count omission rejected without canonical tombstone');

// Explicit deletion received from the owner must already be published as a
// versioned live tombstone, otherwise a later stale upsert could resurrect it.
$unsigned_delete = $live_change;
$unsigned_delete['object_version'] = 4;
$unsigned_delete['source_event_sequence'] = 4;
$unsigned_delete['state'] = 'deleted';
$owner_items = array($unsigned_delete);
$unsigned_uuid = $plugin->indexer()->enqueue_reindex('file26_ci_fixture');
file26_ci_assert_ok($unsigned_uuid, 'Unconfirmed deletion negative-test generation created');
$plugin->indexer()->process_queue();
$unsigned_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $jobs WHERE job_uuid=%s",$unsigned_uuid));
file26_ci_require($unsigned_status==='retry', 'Owner deletion with no live tombstone cannot complete generation');
$still_present = $wpdb->get_var($wpdb->prepare("SELECT object_version FROM $documents WHERE canonical_key=%s",$key));
file26_ci_require((int)$still_present===3, 'Unconfirmed deletion never mutates active serving document');


// Simulate an interrupted bookkeeping write after MySQL atomic RENAME succeeds.
// The administrator may reconcile only if both physical sets exactly match
// the pre-rename checksums; recovery itself performs NO RENAME or DROP.
$owner_items = array($live_change, $member_doc);
$recovery_uuid = $plugin->indexer()->enqueue_reindex('file26_ci_fixture');
file26_ci_assert_ok($recovery_uuid, 'Post-rename recovery fixture created with complete current owner data');
$plugin->indexer()->process_queue();
$recovery_job = $wpdb->get_var($wpdb->prepare("SELECT status FROM $jobs WHERE job_uuid=%s",$recovery_uuid));
file26_ci_require($recovery_job==='completed', 'Recovery generation source fully staged');
$recovery_proof = \Sabri\File26\Shadow_Index::validate($recovery_uuid);
file26_ci_assert_ok($recovery_proof, 'Recovery generation validates before cutover');
wp_set_current_user($auditor_id);
file26_ci_assert_ok(\Sabri\File26\Shadow_Index::second_approve($recovery_uuid,'swap'), 'Independent auditor signs recovery fixture checksum');
wp_set_current_user((int)$admin->ID);
$option_name = 'sabri_f26_shadow_' . str_replace('-','',$recovery_uuid);
$reject_swap_receipt = static function($value,$old) {
    return is_array($value) && ($value['state']??'')==='swapped' ? $old : $value;
};
add_filter('pre_update_option_'.$option_name,$reject_swap_receipt,10,2);
$simulated_swap_error = \Sabri\File26\Shadow_Index::swap($recovery_uuid);
remove_filter('pre_update_option_'.$option_name,$reject_swap_receipt,10);
file26_ci_require(is_wp_error($simulated_swap_error) && $simulated_swap_error->get_error_code()==='file26_shadow_post_swap_record', 'Simulated lost post-rename option write is reported, not mislabelled success');
file26_ci_require(get_option($option_name)['state']==='swapping', 'Interrupted swap provenance stays in recoverable transition state');
wp_set_current_user(0);
$unauthorized_recovery = \Sabri\File26\Shadow_Index::recover_interrupted($recovery_uuid);
file26_ci_require(is_wp_error($unauthorized_recovery) && $unauthorized_recovery->get_error_code()==='file26_shadow_recovery_forbidden', 'Anonymous user cannot reconcile interrupted transition');
wp_set_current_user((int)$admin->ID);
$recovered_swap = \Sabri\File26\Shadow_Index::recover_interrupted($recovery_uuid);
file26_ci_require(!is_wp_error($recovered_swap) && $recovered_swap['state']==='swapped' && $recovered_swap['rename_applied']===true, 'Physical checksums repair interrupted successful swap without repeating RENAME');
$repeat_recovery = \Sabri\File26\Shadow_Index::recover_interrupted($recovery_uuid);
file26_ci_require(is_wp_error($repeat_recovery) && $repeat_recovery->get_error_code()==='file26_shadow_recovery_not_pending', 'Recovered transition cannot be replayed');
wp_set_current_user($auditor_id);
file26_ci_assert_ok(\Sabri\File26\Shadow_Index::second_approve($recovery_uuid,'rollback'), 'Distinct auditor signs interrupted rollback fixture');
wp_set_current_user((int)$admin->ID);
$reject_rollback_receipt = static function($value,$old) {
    return is_array($value) && ($value['state']??'')==='rolled_back' ? $old : $value;
};
add_filter('pre_update_option_'.$option_name,$reject_rollback_receipt,10,2);
$simulated_rollback_error = \Sabri\File26\Shadow_Index::rollback($recovery_uuid);
remove_filter('pre_update_option_'.$option_name,$reject_rollback_receipt,10);
file26_ci_require(is_wp_error($simulated_rollback_error) && $simulated_rollback_error->get_error_code()==='file26_shadow_rollback_audit', 'Simulated lost post-rollback option write reports uncertainty');
file26_ci_require(get_option($option_name)['state']==='rolling_back', 'Interrupted rollback retains durable pre-rename checksums');
$recovered_rollback = \Sabri\File26\Shadow_Index::recover_interrupted($recovery_uuid);
file26_ci_require(!is_wp_error($recovered_rollback) && $recovered_rollback['state']==='rolled_back' && $recovered_rollback['rename_applied']===true, 'Exact physical digests restore interrupted rollback provenance without destructive retry');

// Autocomplete PII-regression uses *publicly eligible* decoy titles so an unsafe
// check would really disclose the suggestion. This test is disposable-only.
$pii_email_doc = $doc;
$pii_email_doc['object_id'] = 'pii-email-decoy';
$pii_email_doc['canonical_url'] = home_url('/file26-ci/pii-email-decoy');
$pii_email_doc['title'] = 'learner example com educational reference';
$pii_email_doc['excerpt'] = 'Public-safe synthetic title to reproduce fullwidth-at normalization.';
$pii_phone_doc = $doc;
$pii_phone_doc['object_id'] = 'pii-phone-decoy';
$pii_phone_doc['canonical_url'] = home_url('/file26-ci/pii-phone-decoy');
$pii_phone_doc['title'] = '92 public educational reference';
$pii_phone_doc['excerpt'] = 'Public-safe synthetic title to reproduce spaced-digit normalization.';
wp_set_current_user((int)$admin->ID);
$owner_documents[$pii_email_doc['object_id']]=$pii_email_doc;
file26_ci_assert_ok(sabri_file26_upsert_document($pii_email_doc), 'Synthetic email-shaped autocomplete decoy is indexed as public');
$owner_documents[$pii_phone_doc['object_id']]=$pii_phone_doc;
file26_ci_assert_ok(sabri_file26_upsert_document($pii_phone_doc), 'Synthetic phone-shaped autocomplete decoy is indexed as public');
$pii_at_title = $doc;
$pii_at_title['object_id'] = 'pii-output-at-decoy';
$pii_at_title['canonical_url'] = home_url('/file26-ci/pii-output-at-decoy');
$pii_at_title['title'] = 'learner@example.com tutorial';
$owner_documents[$pii_at_title['object_id']]=$pii_at_title;
file26_ci_assert_ok(sabri_file26_upsert_document($pii_at_title), 'Public email-shaped title indexed as synthetic unsafe output decoy');
$pii_phone_title = $doc;
$pii_phone_title['object_id'] = 'pii-output-phone-decoy';
$pii_phone_title['canonical_url'] = home_url('/file26-ci/pii-output-phone-decoy');
$pii_phone_title['title'] = '92 300 123 4567 educational reference';
$owner_documents[$pii_phone_title['object_id']]=$pii_phone_title;
file26_ci_assert_ok(sabri_file26_upsert_document($pii_phone_title), 'Public phone-shaped title indexed as synthetic unsafe output decoy');
wp_set_current_user(0);
$visible_email_control = $plugin->search()->suggest('learner', 'ur', 8);
file26_ci_require(is_array($visible_email_control) && count($visible_email_control) > 0, 'Safe email-decoy control prefix yields at least one eligible public suggestion');
foreach ($visible_email_control as $suggestion) {
    file26_ci_require(false === strpos($suggestion['label'],'@'), 'Eligible autocomplete must not echo email-shaped public document titles');
}
$visible_phone_control = $plugin->search()->suggest('92', 'ur', 8);
file26_ci_require(is_array($visible_phone_control) && count($visible_phone_control) > 0, 'Safe phone-decoy control prefix yields at least one eligible public suggestion');
foreach ($visible_phone_control as $suggestion) {
    file26_ci_require(false === strpos($suggestion['label'],'300 123 4567'), 'Eligible autocomplete must not echo phone-shaped public document titles');
}
file26_ci_require(array() === $plugin->search()->suggest('learner＠example.com', 'ur', 8), 'Fullwidth-at email-shaped prefix is blocked before SQL suggestion retrieval');
file26_ci_require(array() === $plugin->search()->suggest('+92 300 123 4567', 'ur', 8), 'Formatted phone-like prefix is blocked before SQL suggestion retrieval');

// Invalid native-owner batch attestation must never complete or promote even
// one shadow generation. Production and staging are forbidden by the CI guard.
$before_bad_batch = $wpdb->get_var($wpdb->prepare("SELECT object_version FROM $documents WHERE canonical_key=%s",$key));
foreach (array(
    'bad_done'=>'file26_owner_batch_done_not_boolean',
    'too_many'=>'file26_owner_batch_limit',
    'bad_cursor'=>'file26_owner_batch_cursor'
) as $fault_mode=>$expected_error) {
    $owner_batch_mode = $fault_mode;
    $fault_uuid = $plugin->indexer()->enqueue_reindex('file26_ci_fixture');
    file26_ci_assert_ok($fault_uuid, 'Disposable malformed owner batch job enqueued: '.$fault_mode);
    $plugin->indexer()->process_queue();
    $fault = $wpdb->get_row($wpdb->prepare("SELECT status,error_code FROM $jobs WHERE job_uuid=%s",$fault_uuid),ARRAY_A);
    file26_ci_require(is_array($fault) && $fault['status']==='retry' && $fault['error_code']===$expected_error,
        'Malformed '.$fault_mode.' has a precise retry receipt, never completed');
    $proof = \Sabri\File26\Shadow_Index::validate($fault_uuid);
    file26_ci_require(is_wp_error($proof) && $proof->get_error_code()==='file26_shadow_incomplete',
        'Malformed '.$fault_mode.' cannot pass shadow generation cutover validation');
    $after_fault = $wpdb->get_var($wpdb->prepare("SELECT object_version FROM $documents WHERE canonical_key=%s",$key));
    file26_ci_require((string)$after_fault===(string)$before_bad_batch,
        'Malformed '.$fault_mode.' has not changed active serving version');
}
$owner_batch_mode = 'normal';

// Canonical public owner ACL cannot be inferred merely from a public row.
// The full disposable database includes a real indexed, active connector
// deliberately lacking can_view; the result must be suppressed.
$acl_missing = $manifest;
$acl_missing['slug'] = 'file26_ci_missing_acl';
$acl_missing['owner_file'] = 'File 26 CI missing ACL';
unset($acl_missing['can_view']);
file26_ci_assert_ok(sabri_file26_register_connector($acl_missing), 'Owner missing can_view may register only in proposed lane');
$wpdb->update(\Sabri\File26\DB::table('connectors'), array('status'=>'active'), array('slug'=>'file26_ci_missing_acl'));
file26_ci_assert_ok(sabri_file26_register_connector($acl_missing), 'Disposable legacy active owner absence is observable without production activation');
$unowned = $doc;
$unowned['connector_slug'] = 'file26_ci_missing_acl';
$unowned['object_id'] = 'missing-owner-acl-public';
$unowned['title'] = 'undocumentedaclvisibilitymarker';
$unowned['canonical_url'] = home_url('/file26-ci/missing-owner-acl-public');
wp_set_current_user((int)$admin->ID);
$owner_documents[$unowned['object_id']]=$unowned;
file26_ci_assert_ok(sabri_file26_upsert_document($unowned), 'Synthetic public document indexed for active owner without canonical ACL');
wp_set_current_user(0);
$unowned_results = sabri_file26_search(array('q'=>'undocumentedaclvisibilitymarker','locale'=>'ur'));
file26_ci_require(!is_wp_error($unowned_results) && empty($unowned_results['results']),
    'Public search rejects owner projection with missing request-time can_view, regardless of visibility label');

// FR-002: a valid MySQL unsigned BIGINT above PHP_INT_MAX must NEVER be
// silently clamped before anti-resurrection/version comparison.
$version_overflow = ((string)PHP_INT_MAX)==='9223372036854775807'
    ? '9223372036854775808' : ((string)PHP_INT_MAX).'0';
$huge_doc = $doc;
$huge_doc['object_id'] = 'overflow-version-isolation';
$huge_doc['canonical_url'] = home_url('/file26-ci/overflow-version-isolation');
$huge_doc['object_version'] = $version_overflow;
$huge_doc['source_event_sequence'] = 1;
$overflow_denied = $plugin->indexer()->upsert($huge_doc);
file26_ci_require(is_wp_error($overflow_denied) &&
    $overflow_denied->get_error_code()==='file26_invalid_object_version',
    'Unsigned source version over PHP_INT_MAX rejected before real DB upsert');
$huge_key = \Sabri\File26\Indexer::canonical_key('file26_ci_fixture','test','overflow-version-isolation');
$huge_saved = $wpdb->get_var($wpdb->prepare("SELECT object_version FROM $documents WHERE canonical_key=%s",$huge_key));
file26_ci_require(null===$huge_saved, 'Rejected huge version cannot create a serving document');
$huge_doc['object_version'] = 1;
$huge_doc['source_event_sequence'] = $version_overflow;
$sequence_denied = $plugin->indexer()->upsert($huge_doc);
file26_ci_require(is_wp_error($sequence_denied) &&
    $sequence_denied->get_error_code()==='file26_invalid_source_event_sequence',
    'Unsigned source event sequence over PHP_INT_MAX rejected before DB upsert');

// FR-002/FR-013: malformed source rank/count or non-finite scores must fail
// BEFORE any write into actual disposable WordPress/MariaDB serving tables.
foreach (array(
    array('label'=>'malformed-quality', 'mutation'=>array('quality_score'=>'INF'), 'error'=>'file26_invalid_source_score'),
    array('label'=>'malformed-authority', 'mutation'=>array('authority_score'=>'NaN'), 'error'=>'file26_invalid_source_score'),
    array('label'=>'malformed-popularity', 'mutation'=>array('popularity_score'=>array('very popular')), 'error'=>'file26_invalid_source_score'),
    array('label'=>'saturated-doctor-rank', 'mutation'=>array('payload'=>array('global_doctor_rank'=>'18446744073709551615')), 'error'=>'file26_invalid_payload_integer'),
    array('label'=>'nonfinite-qualification', 'mutation'=>array('payload'=>array('qualification_score'=>'INF')), 'error'=>'file26_invalid_payload_score')
) as $case) {
    $invalid = array_merge($doc,$case['mutation']);
    $invalid['object_id'] = 'numeric-' . $case['label'];
    $invalid['canonical_url'] = home_url('/file26-ci/numeric-'.$case['label']);
    $invalid['source_event_sequence'] = 1;
    $invalid['object_version'] = 1;
    $rejected = sabri_file26_upsert_document($invalid);
    file26_ci_require(is_wp_error($rejected) && $rejected->get_error_code()===$case['error'],
        'Invalid numerical source payload rejected before database write: '.$case['label']);
    $invalid_key = \Sabri\File26\Indexer::canonical_key('file26_ci_fixture','test',$invalid['object_id']);
    $existing_row = $wpdb->get_var($wpdb->prepare("SELECT canonical_key FROM $documents WHERE canonical_key=%s",$invalid_key));
    file26_ci_require(null===$existing_row,'Invalid numerical source payload never entered actual serving documents: '.$case['label']);
}

echo "PASS: disposable WordPress + MariaDB FR-005 end-to-end acceptance\n";
