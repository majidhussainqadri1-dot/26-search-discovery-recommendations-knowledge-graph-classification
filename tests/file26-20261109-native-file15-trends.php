<?php
/** Real File15 owner public aggregate radar research; NO private studies can leave owner. */
if('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')){fwrite(STDERR,"REFUSED\n");exit(2);}
function f26f15_assert($v,$label){if(!$v){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$main=WP_PLUGIN_DIR.'/15-radar-symptom-and-remedy-analysis-foundation/radar-symptom-remedy-analysis.php';
f26f15_assert(is_file($main),'Original File15 research-owner package present in ephemeral site');
require_once $main;
$plugin=\Sabri\File26\Plugin::instance();
$c=$plugin->connectors()->get('file15-public-research');
f26f15_assert(is_array($c)&&$c['owner_file']==='File 15','Native File15 owner registered with File26');
f26f15_assert($c['entity_types']===array('radar_study','research_signal'),'Owner reports full canonical entity taxonomy, not private-study export');
f26f15_assert($c['status']==='proposed'&&!$plugin->connectors()->is_index_eligible('file15-public-research'),'Radar source cannot self-activate Search');
foreach(array('list_batch','can_view','health','fetch_object') as $name){f26f15_assert(is_callable($c[$name]),'Actual File15 '.$name.' callable');}
$health=call_user_func($c['health']);
f26f15_assert(is_array($health)&&$health['state']==='degraded','Missing owner research/report schema blocks public export');
$batch=call_user_func($c['list_batch'],'',10,array('connector'=>'file15-public-research'));
f26f15_assert(is_wp_error($batch),'Uninstalled native Radar schema cannot be treated as empty completed search source');
$secret=array('connector_slug'=>'file15-public-research','entity_type'=>'radar_study','visibility'=>'members','object_id'=>'00000000-0000-4000-a000-000000000001','object_version'=>1);
f26f15_assert(call_user_func($c['can_view'],$secret,array('authenticated'=>true))===false,'Even authenticated user cannot index owner-private Radar study through public File26');
f26f15_assert(call_user_func($c['fetch_object'],'radar_study',$secret['object_id'])===null,'Private Radar study does not implement public fetch_object');
f26f15_assert($plugin->owner_contracts()->readiness()['file15']['registered']&&!$plugin->owner_contracts()->readiness()['file15']['production_ready'],'File15 native handshake not equivalent to release or active provider');
echo "PASS: File15 real owner source, private-studies ban, published-trend-only fail closed\n";
