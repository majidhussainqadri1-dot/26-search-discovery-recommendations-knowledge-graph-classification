<?php
/** Real File07 source plugin handshake, no simulated File00/03/09 verification. */
if('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')){fwrite(STDERR,"REFUSED\n");exit(2);}
function f26f07_assert($value,$label){if(!$value){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$path=WP_PLUGIN_DIR.'/doctors-directory/doctors-directory.php';
f26f07_assert(is_file($path),'Native File07 source available in isolated WordPress');
require_once $path;
$plugin=\Sabri\File26\Plugin::instance();
$c=$plugin->connectors()->get('file07-verified-doctors');
f26f07_assert(is_array($c)&&($c['owner_file']??'')==='File 07','Original File07 register handshake reaches File26');
f26f07_assert(($c['entity_types']??array())===array('doctor_directory_projection'),'Doctor directory canonical entity type');
f26f07_assert($c['status']==='proposed'&&!$plugin->connectors()->is_index_eligible('file07-verified-doctors'),'No automatic clinical/doctor search activation');
foreach(array('list_batch','can_view','health','fetch_object') as $fn){f26f07_assert(is_callable($c[$fn]),'Original File07 owner '.$fn.' callable');}
$identity=DDD_Contracts::dependency_health();
f26f07_assert(is_array($identity)&&empty($identity['ready']),'File00, File03, File09 unresolved in disposable test, no invented professional verification');
$health=call_user_func($c['health']);
f26f07_assert(is_array($health)&&$health['state']==='degraded','Owner health expressly degraded without canonical provider proofs');
$sample=array('connector_slug'=>'file07-verified-doctors','visibility'=>'public','entity_type'=>'doctor_directory_projection','object_id'=>'00000000-0000-4000-a000-000000000001','object_version'=>1);
f26f07_assert(call_user_func($c['can_view'],$sample,array('authenticated'=>false))===false,'No doctor appears public without current verified File00/03/09 identity');
f26f07_assert(call_user_func($c['fetch_object'],'doctor_directory_projection',$sample['object_id'])===null,'Unknown founder/doctor identity cannot be manufactured');
$batch=call_user_func($c['list_batch'],'',10,array('connector'=>'file07-verified-doctors'));
f26f07_assert(is_wp_error($batch),'Unready owner source refuses full search snapshot rather than leaking stale doctor profiles');
f26f07_assert($plugin->owner_contracts()->readiness()['file07']['registered']&& !$plugin->owner_contracts()->readiness()['file07']['production_ready'],'File26 counts real owner integration but not production acceptance');
echo "PASS: real File07 doctor owner plugin registered, provenance missing properly fails closed\n";
