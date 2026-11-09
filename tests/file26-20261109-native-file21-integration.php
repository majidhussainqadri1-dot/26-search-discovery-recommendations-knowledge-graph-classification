<?php
/** Native File21 + File26 actual public-source plugin integration, disposable WordPress ONLY. */
if('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')){
    fwrite(STDERR, "REFUSED: native File21 integration requires disposable WP/MariaDB.\n");exit(2);
}
function f26native($condition,$description){
    if(!$condition){fwrite(STDERR,"FAIL: ".$description."\n");exit(1);}
    echo "PASS: ".$description."\n";
}
f26native(defined('SABRI_FILE26_VERSION')&&defined('SABRI_HNF_PACKAGE_VERSION'),'Both exact-source plugins are loaded in the same WordPress');
f26native(is_plugin_active('sabri-file26-search-discovery/file-26-search-discovery.php'),'File26 plugin active');
f26native(is_plugin_active('sabri-complete-home-news-feed/sabri-complete-home-news-feed.php'),'Native File21 plugin active');
$registry=\Sabri\File26\Plugin::instance()->connectors();
$native=$registry->get('file21-publication');
f26native(is_array($native),'Native File21 owner registered its real File26 connector');
f26native(($native['owner_file']??'')==='File 21'&&($native['contract_version']??'')==='1.0','Canonical File21 owner and contract version match');
f26native(($native['status']??'')==='proposed','Native owner remains proposed, cannot self-activate global search');
f26native(isset($native['list_batch'],$native['can_view'],$native['health']),'Native owner exports batch, visibility and health callbacks');
foreach(array('list_batch','can_view','health') as $callback){
    f26native(is_callable($native[$callback]),'Native owner '.$callback.' callback callable');
}
f26native($native['list_batch'][0]==='Sabri\\HomeNewsFeed\\SearchProviderRegistry' ||
    (is_array($native['list_batch']) && is_string($native['list_batch'][0]) && strpos($native['list_batch'][0],'SearchProviderRegistry')!==false),
    'Native batch handler comes from canonical File21 class, not a test stub');
$health=call_user_func($native['health']);
f26native(is_array($health)&&($health['connector']??'')==='file21-publication'&&($health['global_search_owner']??'')==='26','Real File21 health names File26 as search owner');
$batch=call_user_func($native['list_batch'],'',10,array('connector'=>'file21-publication'));
f26native(is_array($batch)&&isset($batch['items'])&&is_array($batch['items'])&&array_key_exists('done',$batch),'Real owner WordPress batch returns valid bounded shape');
f26native(count($batch['items'])<=10,'Real owner respects explicit source batch size');
$fake=array('object_id'=>'0','entity_type'=>'post','visibility'=>'public');
f26native(call_user_func($native['can_view'],$fake,array('authenticated'=>false))===false,'Native owner denies fake public reference at click time');
echo "PASS: exact pinned File21 real-plugin runtime connector handshake on disposable WordPress\n";
