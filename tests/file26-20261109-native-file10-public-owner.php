<?php
/**
 * Native File10 owner public video/live/channel -> File26 contract on an isolated
 * WordPress/MariaDB install. Uses the real File10 classes and owner tables.
 */
if ('yes' !== getenv('FILE26_CI_EPHEMERAL_DB') || false === strpos(home_url('/'), 'file26-ci.local')) {
    fwrite(STDERR, "REFUSED: File10 owner acceptance requires disposable file26-ci.local database.\n"); exit(2);
}
function f26_file10_assert($check, $label) {
    if (!$check) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
global $wpdb;
$plugin=\Sabri\File26\Plugin::instance();
// The modern File10 source intentionally ships its unapproved connector dormant.
// This explicit load/register is allowed ONLY after this file's disposable CI guard.
$owner_class_file=WP_PLUGIN_DIR.'/video-wall-and-live-broadcasting/includes/class-vwlb-file26-connector.php';
f26_file10_assert(is_readable($owner_class_file),'Immutable modern File10 class is present on disposable WordPress');
if(!class_exists('VWLB_File26_Connector',false))require_once $owner_class_file;
f26_file10_assert(class_exists('VWLB_File26_Connector',false),'Real modern File10 adapter class loaded for disposable test only');
$registry=$plugin->connectors();
$registered=$registry->register(VWLB_File26_Connector::manifest());
f26_file10_assert(true===$registered,'CI-local proposed File10 registration accepted, with no production activation');
$native=$registry->get('file10-public-media');
f26_file10_assert(is_array($native)&&$native['owner_file']==='File 10','Canonical owner registration found in File26');
f26_file10_assert($native['entity_types']===array('video','live_video','channel'),'All three File10 search entity types supplied');
f26_file10_assert($native['status']==='proposed'&&!$registry->is_index_eligible('file10-public-media'),'Owner cannot silently enter production indexing');
foreach(array('list_batch','can_view','health','fetch_object') as $callback) {
    f26_file10_assert(is_callable($native[$callback]),'Actual File10 '.$callback.' callback callable');
}
f26_file10_assert($plugin->owner_contracts()->readiness()['file10']['callbacks_complete'],'File26 catalog recognizes File10 native callbacks');
$health=call_user_func($native['health']);
f26_file10_assert(is_array($health)&&$health['state']==='healthy','Native media SQL/schema healthy on disposable WordPress');
$now=gmdate('Y-m-d H:i:s');
$channels=VWLB_Helpers::table('channels');
$videos=VWLB_Helpers::table('videos');
$lives=VWLB_Helpers::table('live_events');
$channel_id='chn_demo20261109';
$pub_video_id='vid_demo20261109';
$private_video_id='vid_private20261109';
$live_id='live_demo20261109';
foreach (array($channel_id, $pub_video_id, $private_video_id, $live_id) as $owner_public_id) {
    f26_file10_assert((bool) preg_match('/^[a-z][a-z0-9]*_[a-z0-9]+$/D', $owner_public_id), 'Fixture satisfies modern File10 public-ID grammar: '.$owner_public_id);
    if (method_exists('VWLB_Helpers', 'is_public_id')) {
        f26_file10_assert(VWLB_Helpers::is_public_id($owner_public_id), 'Modern owner validates fixture ID: '.$owner_public_id);
    }
}
$inserted=$wpdb->insert($channels,array(
    'public_id'=>$channel_id,'title'=>'File10 Public Channel',
    'slug'=>'f26-public-channel-20261109','description'=>'Public educational channel',
    'status'=>'active','visibility'=>'public','version'=>2,
    'created_at'=>$now,'updated_at'=>$now
));
f26_file10_assert($inserted===1,'Original File10 channel persisted in source DB');
$channel_pk=(int)$wpdb->insert_id;
$inserted=$wpdb->insert($videos,array(
    'public_id'=>$pub_video_id,'channel_id'=>$channel_pk,
    'title'=>'Public Safe Educational Video','slug'=>'f26-public-video-20261109',
    'excerpt'=>'Educational content without patient information',
    'language'=>'ur','status'=>'published','visibility'=>'public',
    'consent_status'=>'not_patient_case','rights_status'=>'declared',
    'version'=>3,'published_at'=>$now,'created_at'=>$now,'updated_at'=>$now
));
f26_file10_assert($inserted===1,'Original File10 publicly eligible video persisted');
$video_pk=(int)$wpdb->insert_id;
$inserted=$wpdb->insert($videos,array(
    'public_id'=>$private_video_id,'channel_id'=>$channel_pk,
    'title'=>'Private Video Must Never Appear','slug'=>'f26-private-video-20261109',
    'excerpt'=>'Private', 'language'=>'en-US',
    'status'=>'published','visibility'=>'private',
    'consent_status'=>'not_patient_case','rights_status'=>'declared',
    'version'=>1,'published_at'=>$now,'created_at'=>$now,'updated_at'=>$now
));
f26_file10_assert($inserted===1,'Private native owner video saved as exclusion fixture');
$inserted=$wpdb->insert($lives,array(
    'public_id'=>$live_id,'channel_id'=>$channel_pk,
    'title'=>'File10 Public Scheduled Live','slug'=>'f26-live-20261109',
    'language'=>'ar','status'=>'scheduled','visibility'=>'public',
    'kill_switch'=>0,'version'=>3,'created_at'=>$now,'updated_at'=>$now
));
f26_file10_assert($inserted===1,'Native public scheduled live persisted');
$live_pk=(int)$wpdb->insert_id;

$cursor='';$collected=array();$done=false;
for($i=0;$i<40;$i++){
    $batch=call_user_func($native['list_batch'],$cursor,1,array('connector'=>'file10-public-media'));
    f26_file10_assert(is_array($batch)&&isset($batch['items'])&&is_array($batch['items']),'Native bounded source page '.$i.' is valid');
    foreach($batch['items'] as $doc){
        f26_file10_assert($doc['visibility']==='public'&&$doc['source_event_sequence']===$doc['object_version'],'Versioned public-only source document');
        f26_file10_assert($doc['object_id']!==$private_video_id,'Private source row never becomes a document');
        $collected[$doc['entity_type'].':'.$doc['object_id']]=$doc;
    }
    if(!empty($batch['done'])){$done=true;break;}
    $next=(string)($batch['next_cursor']??'');
    f26_file10_assert($next!==''&&$next!==$cursor,'Native cursor advances without a partial snapshot deadlock');
    $cursor=$next;
}
f26_file10_assert($done,'Canonical owner source completes its bounded paginated scan');
f26_file10_assert(isset($collected['video:'.$pub_video_id],$collected['live_video:'.$live_id],$collected['channel:'.$channel_id]),'Real owner projects video, live and channel');
f26_file10_assert(count($collected)===3,'No unpublished/private owner records in candidate source');
$public=$collected['video:'.$pub_video_id];
$direct=VWLB_File26_Connector::fetch_object('video',$pub_video_id);
f26_file10_assert(is_array($direct)&&($direct['object_version']??null)===3,
    'Canonical direct File10 fetch returns current public version, not raw row');
f26_file10_assert(VWLB_File26_Connector::fetch_object('video','vid_nonexistent20261113')===null,
    'Missing canonical video remains legitimate null');

$test_video_event=array(
    'public_id'=>'evt_f10f26video20261113','event_name'=>'VideoPublished.v1',
    'object_id'=>$video_pk,'object_type'=>'video'
);
$source_proposal=VWLB_File26_Connector::propose_outbox_reconciliation(
    $test_video_event,array('public_id'=>$pub_video_id)
);
f26_file10_assert(is_array($source_proposal)&&$source_proposal['action']==='upsert',
    'Real File10 current publication yields source-only upsert proposal');
f26_file10_assert($source_proposal['object_version']===3&&
    $source_proposal['document']['object_id']===$pub_video_id,
    'File26 proposal source identity/version matches actual canonical MariaDB row');
f26_file10_assert($source_proposal['status']==='proposed'&&
    $source_proposal['delivery_state']==='not_dispatched',
    'No consumer delivery/ack is falsely claimed');
f26_file10_assert(strpos(wp_json_encode($source_proposal),'"object_id":'.$video_pk)===false,
    'No internal numeric SQL source ID escapes into File26 proposal');

f26_file10_assert(call_user_func($native['can_view'],$public,array('authenticated'=>false))===true,'Native click-time public video authorization');
f26_file10_assert(call_user_func($native['fetch_object'],'video',$pub_video_id)['object_version']===3,'Direct native fetch retains versioned canonical video identity');
$wpdb->update($videos,array('visibility'=>'private','version'=>4),array('id'=>$video_pk));

$private_proposal=VWLB_File26_Connector::propose_outbox_reconciliation(
    $test_video_event,array('public_id'=>$pub_video_id)
);
f26_file10_assert(is_array($private_proposal)&&
    $private_proposal['action']==='tombstone'&&
    $private_proposal['object_version']===4&&
    !isset($private_proposal['document']),
    'Native File10 privacy version emits content-free tombstone proposal');

f26_file10_assert(call_user_func($native['can_view'],$public,array('authenticated'=>false))===false,'Privacy change immediately invalidates stale File26 click reference');
f26_file10_assert(call_user_func($native['fetch_object'],'video',$pub_video_id)===null,'Private video fetch denies content after owner privacy change');
$wpdb->update($lives,array('kill_switch'=>1,'version'=>4),array('id'=>$live_pk));

$test_live_event=array('public_id'=>'evt_f10f26live20261113',
    'event_name'=>'LiveBroadcastEnded.v1','object_id'=>$live_pk,'object_type'=>'live');
$live_proposal=VWLB_File26_Connector::propose_outbox_reconciliation(
    $test_live_event,array('public_id'=>$live_id)
);
f26_file10_assert(is_array($live_proposal)&&$live_proposal['action']==='tombstone'&&
    $live_proposal['object_version']===4,'Real kill-switch causes versioned Live tombstone proposal');
$no_source=VWLB_File26_Connector::propose_outbox_reconciliation(
    $test_video_event,array('public_id'=>'vid_absent20261113')
);
f26_file10_assert(is_wp_error($no_source),
    'Hard-missing native owner version does not invent a deletion tombstone');

f26_file10_assert(call_user_func($native['can_view'],$collected['live_video:'.$live_id],array('authenticated'=>false))===false,'Native broadcast emergency kill switch revokes search authorization');
update_option('vwlb_safe_mode',1);
f26_file10_assert(call_user_func($native['can_view'],$collected['channel:'.$channel_id],array('authenticated'=>false))===false,'Safe mode fails closed even for previously public channel');
f26_file10_assert(call_user_func($native['health'])['state']==='degraded','Safe mode marks owner health degraded');
$unavailable=VWLB_File26_Connector::fetch_object('video',$pub_video_id);
f26_file10_assert(is_wp_error($unavailable)&&$unavailable->get_error_code()==='file10_search_owner_unavailable',
    'Safe Mode direct fetch is owner-unavailable, not false deletion');
delete_option('vwlb_safe_mode');

// Fault injections run only within the disposable CI WordPress database.
function f26_file10_temporarily_hide_owner_table($table,$read) {
    global $wpdb;
    $alternate=$table.'_f26_ci_hidden';
    if (!preg_match('/^[a-zA-Z0-9_]+$/D',$table) || !preg_match('/^[a-zA-Z0-9_]+$/D',$alternate)) {
        throw new RuntimeException('Unsafe disposable table name for test');
    }
    $quote=chr(96);
    $moved=$wpdb->query("RENAME TABLE ".$quote.$table.$quote." TO ".$quote.$alternate.$quote);
    if (false===$moved) { throw new RuntimeException('Could not create isolated source-read outage'); }
    try {
        return $read();
    } finally {
        $restored=$wpdb->query("RENAME TABLE ".$quote.$alternate.$quote." TO ".$quote.$table.$quote);
        if(false===$restored) { throw new RuntimeException('Disposable source table restore FAILED'); }
    }
}

$db_error=f26_file10_temporarily_hide_owner_table($videos,
    static function() use ($pub_video_id) {
        return VWLB_File26_Connector::fetch_object('video',$pub_video_id);
    });
f26_file10_assert(is_wp_error($db_error)&&$db_error->get_error_code()==='file10_search_owner_read_failed',
    'Real MariaDB video-table read outage returns operational error, never null');
$restored_video=$wpdb->get_var($wpdb->prepare(
    "SELECT public_id FROM ".$videos." WHERE id=%d",$video_pk));
f26_file10_assert($restored_video===$pub_video_id,'Fault-injected canonical video table was restored');

// A consent-table outage must not be classified as verified withdrawal.
$public_again=$wpdb->update($videos,array('visibility'=>'public','version'=>5),array('id'=>$video_pk));
f26_file10_assert(1===$public_again,'Disposable video re-publicized only for consent failure injection');
$consent_table=VWLB_Helpers::table('consent_links');
$consent_error=f26_file10_temporarily_hide_owner_table($consent_table,
    static function() use ($pub_video_id) {
        return VWLB_File26_Connector::fetch_object('video',$pub_video_id);
    });
f26_file10_assert(is_wp_error($consent_error)&&$consent_error->get_error_code()==='file10_search_owner_policy_failed',
    'Real consent-table outage yields typed policy error, never false deletion');

$batch_error=f26_file10_temporarily_hide_owner_table($consent_table,
    static function() {
        return VWLB_File26_Connector::list_batch('',50,array('connector'=>'file10-public-media'));
    });
f26_file10_assert(is_wp_error($batch_error)&&
    $batch_error->get_error_code()==='file10_search_snapshot_policy_unavailable',
    'Real missing consent SQL denies entire native File10 snapshot, never skips a public row');

// WordPress REST starts a fresh owner-policy request and clears the
// previous request-scoped R109 read-failure flag; canonical SQL is restored.
VWLB_R109_Rights_Consent_Replay_Guard::rest_before(
    null,array(),new WP_REST_Request('GET','/video-wall-live-broadcasting/v1/videos')
);
$batch_after_repair=VWLB_File26_Connector::list_batch('',50,array('connector'=>'file10-public-media'));
f26_file10_assert(is_array($batch_after_repair)&&isset($batch_after_repair['items']),
    'After disposable consent table restoration the owner snapshot succeeds');
$restored_public=false;
foreach($batch_after_repair['items'] as $candidate) {
    if(($candidate['object_id']??null)===$pub_video_id) $restored_public=true;
}
f26_file10_assert($restored_public,
    'Recovered authoritative owner snapshot still includes current public video');

echo "PASS: actual File10 public owner projection and immediate privacy/kill-switch revocation in real MariaDB; status remains proposed\n";
