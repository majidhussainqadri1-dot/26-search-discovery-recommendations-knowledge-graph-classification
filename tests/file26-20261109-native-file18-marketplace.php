<?php
/** Native File18 owner listing contract; buyer, seller, offer and recall proofs not simulated. */
if('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')){fwrite(STDERR,"REFUSED\n");exit(2);}
function f26f18_assert($yes,$why){if(!$yes){fwrite(STDERR,"FAIL: $why\n");exit(1);}echo "PASS: $why\n";}
$main=WP_PLUGIN_DIR.'/18-marketplace/marketplace.php';
f26f18_assert(is_file($main),'Actual File18 marketplace source installed in ephemeral WordPress');
require_once $main;
$plugin=\Sabri\File26\Plugin::instance();
$c=$plugin->connectors()->get('file18-public-listings');
f26f18_assert(is_array($c)&&$c['owner_file']==='File 18','Real File18 owner registered its public search adapter');
f26f18_assert($c['entity_types']===array('marketplace_listing'),'Marketplace listing canonical type');
f26f18_assert($c['status']==='proposed'&&!$plugin->connectors()->is_index_eligible('file18-public-listings'),'No unreviewed seller/listing auto activation');
foreach(array('list_batch','can_view','fetch_object','health') as $fn){f26f18_assert(is_callable($c[$fn]),'Real File18 '.$fn.' callback');}
$health=call_user_func($c['health']);
f26f18_assert(is_array($health)&&$health['state']==='degraded','Absent original seller/recall/commerce schema is visible as degraded');
$batch=call_user_func($c['list_batch'],'',20,array('connector'=>'file18-public-listings'));
f26f18_assert(is_wp_error($batch),'Incomplete Marketplace schema never yields a completed empty search generation');
$fake=array('connector_slug'=>'file18-public-listings','entity_type'=>'marketplace_listing','object_id'=>'00000000-0000-4000-a000-000000000001','object_version'=>1,'visibility'=>'public');
f26f18_assert(call_user_func($c['can_view'],$fake,array('authenticated'=>false))===false,'Unverified or recalled marketplace object never passes search click-time visibility');
f26f18_assert(call_user_func($c['fetch_object'],'marketplace_listing',$fake['object_id'])===null,'No fabricated products or private buyer/seller records');
f26f18_assert($plugin->owner_contracts()->readiness()['file18']['registered']&&!$plugin->owner_contracts()->readiness()['file18']['production_ready'],'Source registered, operational permission still missing');
echo "PASS: File18 actual public listing owner with seller/recall/expiry safety controls (proposed only)\n";
