<?php
/** Native File03 exact source adapter with fail-closed owner outage and checksum invariant. */
if('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')){fwrite(STDERR,"REFUSED\n");exit(2);}
function f26f03_assert($yes,$label){if(!$yes){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$main=WP_PLUGIN_DIR.'/sabri-profiles-doctors/sabri-profiles-doctors.php';
f26f03_assert(is_file($main),'Real File03 source installed beside File26 in disposable WordPress');
require_once $main;
$plugin=\Sabri\File26\Plugin::instance();
$manifests=$plugin->owner_contracts()->collect(array());
$found=null;
foreach($manifests as $manifest){if(is_array($manifest)&&($manifest['slug']??'')==='file03-profiles'){$found=$manifest;break;}}
f26f03_assert(is_array($found)&&$found['owner_file']==='File 03','Existing native File03 owner adapter collected rather than duplicated');
$added=$plugin->connectors()->register($found);
f26f03_assert($added===true,'File03 owner adapter registered in File26 actual SQL registry');
$c=$plugin->connectors()->get('file03-profiles');
f26f03_assert(is_array($c)&&$c['status']==='proposed'&&$c['entity_types']===array('founder','doctor','member_profile'),'Canonical File03 types remain governed/proposed');
foreach(array('list_batch','can_view','health','fetch_object') as $method){f26f03_assert(is_callable($c[$method]),'File03 '.$method.' callable');}
$batch=call_user_func($c['list_batch'],'',10,array('connector'=>'file03-profiles'));
f26f03_assert(is_wp_error($batch)&&$batch->get_error_code()==='spd_file26_owner_schema_unavailable','Missing canonical source schema returns WP_Error, never completed empty snapshot');
$fake=array('connector_slug'=>'file03-profiles','object_id'=>'00000000-0000-4000-8000-000000000001','object_version'=>1,'visibility'=>'public');
f26f03_assert(call_user_func($c['can_view'],$fake,array('authenticated'=>false))===false,'No private or unauthorised founder/doctor/member profile can be inferred');
$bad=call_user_func($c['list_batch'],'x',10,array('connector'=>'file03-profiles'));
f26f03_assert(is_wp_error($bad),'Missing schema/invalid cursor fails closed');
$source=(string)file_get_contents(WP_PLUGIN_DIR.'/sabri-profiles-doctors/includes/class-spd-contracts.php');
f26f03_assert(strpos($source,"'freshness_at' => sanitize_text_field( (string) ( \\$projection['generated_at']")===false,'Volatile request-time source data no longer changes a stable version checksum');
f26f03_assert($plugin->owner_contracts()->readiness()['file03']['registered']&& !$plugin->owner_contracts()->readiness()['file03']['production_ready'],'Native File03 discovered but release gate remains denied');
echo "PASS: genuine File03 owner outage and checksum-source regressions\n";
