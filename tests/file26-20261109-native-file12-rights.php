<?php
/** Pinned real File12 rights-aware PDF owner handshake; no synthetic rights proofs. */
if('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')){fwrite(STDERR,"REFUSED\n");exit(2);}
function f26f12_assert($yes,$label){if(!$yes){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$main=WP_PLUGIN_DIR.'/pdf-library-foundation-12/pdf-library-digital-reading.php';
f26f12_assert(is_file($main),'Actual File12 source available on disposable WordPress');
require_once $main;
$plugin=\Sabri\File26\Plugin::instance();
$c=$plugin->connectors()->get('file12-public-pdfs');
f26f12_assert(is_array($c)&&$c['owner_file']==='File 12','Native File12 registers rights-governed File26 public search owner');
f26f12_assert($c['entity_types']===array('pdf','book_pack'),'Canonical PDF/pack entity types declared (pack publication gated)');
f26f12_assert($c['status']==='proposed'&&!$plugin->connectors()->is_index_eligible('file12-public-pdfs'),'File12 never self-publishes PDF metadata without rights/approval');
foreach(array('list_batch','fetch_object','can_view','health') as $callback){f26f12_assert(is_callable($c[$callback]),'Native File12 '.$callback.' callable');}
$health=call_user_func($c['health']);
f26f12_assert(is_array($health)&&$health['state']==='degraded','Missing original encrypted-object/right-state database correctly degrades File12');
$fake=array('connector_slug'=>'file12-public-pdfs','entity_type'=>'pdf','object_id'=>'00000000-0000-4000-a000-000000000001','visibility'=>'public','object_version'=>1);
f26f12_assert(call_user_func($c['can_view'],$fake,array('authenticated'=>false))===false,'No PDF result can bypass missing licensed-public rights');
f26f12_assert(call_user_func($c['fetch_object'],'pdf',$fake['object_id'])===null,'Unknown licensed PDF is never fabricated');
$batch=call_user_func($c['list_batch'],'',10,array('connector'=>'file12-public-pdfs'));
f26f12_assert(is_wp_error($batch),'Unverified rights/encrypted-object migration blocks File26 full snapshot');
f26f12_assert($plugin->owner_contracts()->readiness()['file12']['registered']&&!$plugin->owner_contracts()->readiness()['file12']['production_ready'],'Real File12 handshake but no false release-ready claim');
echo "PASS: native File12 rights-aware public catalog isolation; full lifecycle requires original rights/objects staging\n";
