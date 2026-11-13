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
f26_file10_assert(class_exists('VWLB_File26_Connector'),'Real File10 adapter class loaded');
$registry=$plugin->connectors();
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
f26_file10_assert(call_user_func($native['can_view'],$public,array('authenticated'=>false))===true,'Native click-time public video authorization');
f26_file10_assert(call_user_func($native['fetch_object'],'video',$pub_video_id)['object_version']===3,'Direct native fetch retains versioned canonical video identity');
$wpdb->update($videos,array('visibility'=>'private','version'=>4),array('id'=>$video_pk));
f26_file10_assert(call_user_func($native['can_view'],$public,array('authenticated'=>false))===false,'Privacy change immediately invalidates stale File26 click reference');
f26_file10_assert(call_user_func($native['fetch_object'],'video',$pub_video_id)===null,'Private video fetch denies content after owner privacy change');
$wpdb->update($lives,array('kill_switch'=>1,'version'=>4),array('id'=>$live_pk));
f26_file10_assert(call_user_func($native['can_view'],$collected['live_video:'.$live_id],array('authenticated'=>false))===false,'Native broadcast emergency kill switch revokes search authorization');
update_option('vwlb_safe_mode',1);
f26_file10_assert(call_user_func($native['can_view'],$collected['channel:'.$channel_id],array('authenticated'=>false))===false,'Safe mode fails closed even for previously public channel');
f26_file10_assert(call_user_func($native['health'])['state']==='degraded','Safe mode marks owner health degraded');
delete_option('vwlb_safe_mode');
echo "PASS: actual File10 public owner projection and immediate privacy/kill-switch revocation in real MariaDB; status remains proposed\n";
