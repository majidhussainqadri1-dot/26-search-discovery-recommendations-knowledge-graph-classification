<?php
/** Native File06 owner public encyclopedia contract with exact source, no synthetic approval. */
if('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')){fwrite(STDERR,"REFUSED\n");exit(2);}
function f26f06_assert($v,$label){if(!$v){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$main=WP_PLUGIN_DIR.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php';
f26f06_assert(is_file($main),'Pinned File06 original source present in ephemeral WP');
require_once $main;
$plugin=\Sabri\File26\Plugin::instance();
$c=$plugin->connectors()->get('file06-public-encyclopedia');
f26f06_assert(is_array($c)&&$c['owner_file']==='File 06','Real canonical File06 public owner contract registers');
f26f06_assert($c['status']==='proposed'&&!$plugin->connectors()->is_index_eligible('file06-public-encyclopedia'),'Public concepts cannot self-activate unapproved index');
f26f06_assert($c['entity_types']===array('encyclopedia_entry','concept'),'Canonical dual owner entity types declared');
foreach(array('list_batch','can_view','health','fetch_object') as $method){f26f06_assert(is_callable($c[$method]),'Native File06 '.$method.' callable');}
$status=HE_V2_Schema::runtime_status();
f26f06_assert($status!=='active','Owner schema not silently installed or promoted by cross-file test');
$health=call_user_func($c['health']);
f26f06_assert(is_array($health)&&$health['state']==='degraded','Uninstalled canonical owner schema refuses File26 public export');
$batch=call_user_func($c['list_batch'],'',10,array('connector'=>'file06-public-encyclopedia'));
f26f06_assert(is_wp_error($batch),'No shadow rebuild from missing canonical encyclopedia schema');
$fake=array('connector_slug'=>'file06-public-encyclopedia','entity_type'=>'encyclopedia_entry','visibility'=>'public','object_id'=>'he_fake_unverified','object_version'=>1);
f26f06_assert(call_user_func($c['can_view'],$fake,array())===false,'Unknown/unreviewed concept cannot be made public by File26');
f26f06_assert($plugin->owner_contracts()->readiness()['file06']['registered']&&!$plugin->owner_contracts()->readiness()['file06']['production_ready'],'Real File06 handshake counted without promotion');
echo "PASS: genuine File06 owner source callbacks and fail-closed schema/review boundary\n";
