<?php
/**
 * Exact native File05 owner manifest in real disposable WordPress.
 * File05 depends on File00 and other runtime providers; this test MUST NOT
 * simulate their approval or promote the learning lane.
 */
if ('yes' !== getenv('FILE26_CI_EPHEMERAL_DB') || false === strpos(home_url('/'), 'file26-ci.local')) { fwrite(STDERR,"REFUSED\n"); exit(2); }
function f26f05_assert($yes,$why){if(!$yes){fwrite(STDERR,"FAIL: $why\n");exit(1);}echo "PASS: $why\n";}
$plugin_root=WP_PLUGIN_DIR.'/05-learn-sabri-classical-homeopathy/learn-sabri-classical-homeopathy.php';
f26f05_assert(is_file($plugin_root),'Pinned native File05 owner package installed in isolated WP');
require_once $plugin_root;
$connector=\Sabri\File26\Plugin::instance()->connectors()->get('file05-public-learning');
f26f05_assert(is_array($connector)&&$connector['owner_file']==='File 05','Real File05 owner contract registered into File26');
f26f05_assert($connector['entity_types']===array('lesson','course','book'),'File05 native learning types are canonical');
f26f05_assert($connector['status']==='proposed'&&!\Sabri\File26\Plugin::instance()->connectors()->is_index_eligible('file05-public-learning'),'Missing identity/consent lifecycle does not self-activate File05');
foreach(array('list_batch','can_view','health','fetch_object') as $method){f26f05_assert(is_callable($connector[$method]),'Canonical owner '.$method.' is executable');}
$fake=array('connector_slug'=>'file05-public-learning','entity_type'=>'lesson','object_id'=>'900000','visibility'=>'public');
f26f05_assert(call_user_func($connector['can_view'],$fake,array('authenticated'=>false))===false,'Nonexistent File05 lesson cannot pass public ACL');
f26f05_assert(call_user_func($connector['fetch_object'],'lesson','900000')===null,'Unknown File05 object cannot be fabricated');
$health=call_user_func($connector['health']);
f26f05_assert(is_array($health)&&in_array($health['state'],array('healthy','degraded'),true),'File05 reports real owner runtime state');
$bad=call_user_func($connector['list_batch'],'not-a-valid-cursor',10,array('connector'=>'file05-public-learning'));
f26f05_assert(is_wp_error($bad),'File05 export must reject a missing-runtime or invalid cursor');
$readiness=\Sabri\File26\Plugin::instance()->owner_contracts()->readiness();
f26f05_assert($readiness['file05']['registered']===true&&$readiness['file05']['production_ready']===false,'File26 counts File05 owner handshake, not product completion');
echo "PASS: actual File05 owner-source API handshake, privacy guard and incomplete lifecycle provenance\n";
