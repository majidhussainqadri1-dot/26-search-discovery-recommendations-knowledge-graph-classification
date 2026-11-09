<?php
/** Real File11 (non-main RC21) registration alongside File26, without File10 media. */
if('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')){
    fwrite(STDERR,"REFUSED: File11 source contract only on disposable WordPress.\n");exit(2);
}
function file11_x26_assert($condition,$message){
    if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";
}
if(!function_exists('is_plugin_active')){require_once ABSPATH.'wp-admin/includes/plugin.php';}
file11_x26_assert(is_plugin_active('11-reels-foundation/11-reels-foundation.php'),'Unmerged exact-HEAD File11 plugin activated only in disposable site');
file11_x26_assert(class_exists('RSV_File26_Connector'),'Real native File11 source adapter class present');
$plugin=\Sabri\File26\Plugin::instance();
$owners=$plugin->owner_contracts()->readiness();
file11_x26_assert(!empty($owners['file11']['registered']),'File26 detected File11 actual adapter from owner hook');
file11_x26_assert(!empty($owners['file11']['callbacks_complete']),'File11 native list_batch/can_view/health all callable');
file11_x26_assert($owners['file11']['status']==='proposed'&&empty($owners['file11']['production_ready']),'Unmerged Reel source cannot self-promote search connector');
$connector=$plugin->connectors()->get('file11-reels');
file11_x26_assert(is_array($connector)&&($connector['owner_file']??'')==='File 11','File11 native entity owner preserved');
file11_x26_assert(($connector['entity_types']??array())===array('reel'),'File11 source exports only canonical reel entity type');
$health=call_user_func($connector['health']);
file11_x26_assert(is_array($health)&&in_array($health['state'],array('healthy','degraded'),true),'Native media-conditional File11 health is explicit');
file11_x26_assert(call_user_func($connector['can_view'],array('object_id'=>'999999999'),array('authenticated'=>false))===false,'Native Reel owner refuses nonexistent asset in cross-file search');
if('degraded'===$health['state']){
    file11_x26_assert(!$plugin->connectors()->is_index_eligible('file11-reels'),'File11 media outage remains unindexed by File26');
}
echo "PASS: unmerged real File11 owner initial handshake (NOT production-ready or actual File10 media playback)\n";
