<?php
/** Native File08 canonical public clinic source and appointment-data non-exposure. */
if('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')){fwrite(STDERR,"REFUSED\n");exit(2);}
function f26f08_assert($yes,$label){if(!$yes){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$main=WP_PLUGIN_DIR.'/worldwide-clinic/worldwide-clinic.php';
f26f08_assert(is_file($main),'Real File08 clinic plugin present in isolated WordPress');
require_once $main;
$plugin=\Sabri\File26\Plugin::instance();
$c=$plugin->connectors()->get('file08-public-clinics');
f26f08_assert(is_array($c)&&$c['owner_file']==='File 08','Real File08 clinic owner registered');
f26f08_assert($c['entity_types']===array('clinic','appointment_availability'),'Clinic and appointment availability types recognized, only clinic public DTO exported');
f26f08_assert($c['status']==='proposed'&&!$plugin->connectors()->is_index_eligible('file08-public-clinics'),'No automatic activation on unknown identity or migration');
foreach(array('list_batch','can_view','fetch_object','health') as $cb){f26f08_assert(is_callable($c[$cb]),'Original File08 '.$cb.' callback exists');}
$health=call_user_func($c['health']);
f26f08_assert(is_array($health)&&$health['state']==='degraded','Missing File00/03/09 or native clinic schema degrades owner search');
$empty=call_user_func($c['list_batch'],'',10,array('connector'=>'file08-public-clinics'));
f26f08_assert(is_wp_error($empty),'Unverified identity/schema cannot stage empty successful clinic generation');
$fake=array('connector_slug'=>'file08-public-clinics','object_id'=>'00000000-0000-4000-a000-000000000001','entity_type'=>'clinic','visibility'=>'public','object_version'=>1);
f26f08_assert(call_user_func($c['can_view'],$fake,array('authenticated'=>false))===false,'No unverified doctor/clinic is public through File26');
f26f08_assert(call_user_func($c['fetch_object'],'appointment_availability',$fake['object_id'])===null,'No patient slots or native appointment details can be inferred');
f26f08_assert($plugin->owner_contracts()->readiness()['file08']['registered']&&!$plugin->owner_contracts()->readiness()['file08']['production_ready'],'File08 native integration recognized, production not approved');
echo "PASS: File08 original clinic public projection, authoritative eligibility and safe non-export of patient appointments\n";
