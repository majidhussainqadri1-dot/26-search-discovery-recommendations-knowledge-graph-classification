<?php
/** Disposable MariaDB acceptance of the real File04 -> File26 asynchronous reindex contract. */
if ('yes' !== getenv('FILE26_CI_EPHEMERAL_DB') || false === strpos(home_url('/'),'file26-ci.local')) {
    fwrite(STDERR, "REFUSED: only disposable file26-ci.local database accepted.\n");
    exit(2);
}
if (!defined('SABRI_FILE26_VERSION')) { fwrite(STDERR,"FAIL: File26 inactive\n"); exit(1); }
function f26f04_assert($cond,$label) {
    if (!$cond) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
global $wpdb;
$manifest=array(
    'slug'=>'file21-publication','owner_file'=>'File 21','contract_version'=>'1.0',
    'entity_types'=>array('article','news','post'),
    'privacy_classes'=>array('public'),'visibility_fields'=>array('public'),
    'deletion_semantics'=>'versioned_tombstone','status'=>'active',
    'list_batch'=>static function($cursor,$limit,$scope) { return array('items'=>array(),'done'=>true); },
    'can_view'=>static function($doc,$audience) { return false; },
    'health'=>static function() { return array('state'=>'healthy'); }
);
$plugin=\Sabri\File26\Plugin::instance();
$first=sabri_file26_register_connector($manifest);
f26f04_assert(!is_wp_error($first) && $first===true,'Synthetic File21 connector registered only on disposable database');
$wpdb->update(\Sabri\File26\DB::table('connectors'),array('status'=>'active'),array('slug'=>'file21-publication'));
$second=sabri_file26_register_connector($manifest);
f26f04_assert(!is_wp_error($second) && $plugin->connectors()->is_index_eligible('file21-publication'),'Synthetic File21 connector eligible in isolated WordPress');
$ctx=array(
    'canonical_owner'=>'File 21',
    'source_signature'=>str_repeat('1',64),
    'reconciliation_checksum'=>str_repeat('2',64),
    'request_digest'=>str_repeat('3',64)
);
$before=$plugin->file04_verify_reindex(array('verified'=>false),$ctx);
f26f04_assert(empty($before['verified']),'Missing File04 receipt is rejected');
$wrong_owner=$ctx; $wrong_owner['canonical_owner']='File 03';
$plugin->file04_request_reindex($wrong_owner);
f26f04_assert(empty($plugin->file04_verify_reindex(array('verified'=>false),$wrong_owner)['verified']),'Noncanonical File04 owner denied');
$plugin->file04_request_reindex($ctx);
$receipts=get_option('sabri_file26_file04_cutover_receipts_v1',array());
$row=$receipts[$ctx['request_digest']]??null;
f26f04_assert(is_array($row)&&!empty($row['job_uuid']),'Real full-shadow job created and bound to File04 context');
$jobs=\Sabri\File26\DB::table('jobs');
$job=$wpdb->get_row($wpdb->prepare("SELECT job_type,status,scope_json FROM $jobs WHERE job_uuid=%s",$row['job_uuid']),ARRAY_A);
$scope=is_array($job)?json_decode($job['scope_json'],true):null;
f26f04_assert(is_array($job)&&$job['job_type']==='shadow_reindex'&&$job['status']==='pending','File04 request enqueues exactly one real shadow owner job');
f26f04_assert($scope===array('connector'=>'file21-publication'),'File04 metadata no longer masquerades as partial Indexer scope');
$receipt=$plugin->file04_verify_reindex(array('verified'=>false),$ctx);
f26f04_assert(!empty($receipt['verified'])&&$receipt['status']==='accepted'&&$receipt['phase']==='queued_not_promoted','File04 receives honest asynchronous acceptance evidence, never completion');
$plugin->file04_request_reindex($ctx);
$repeat=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $jobs WHERE job_uuid=%s",$row['job_uuid']));
$receipt2=get_option('sabri_file26_file04_cutover_receipts_v1',array())[$ctx['request_digest']]??null;
f26f04_assert((int)$repeat===1&&$receipt2['job_uuid']===$row['job_uuid'],'Same cutover request is idempotent and reuses its actual job');
$tampered=$ctx; $tampered['source_signature']=str_repeat('a',64);
f26f04_assert(empty($plugin->file04_verify_reindex(array('verified'=>false),$tampered)['verified']),'Receipt cannot be recycled for different reconciliation source');
$wpdb->update($jobs,array('status'=>'dead_letter'),array('job_uuid'=>$row['job_uuid']));
f26f04_assert(empty($plugin->file04_verify_reindex(array('verified'=>false),$ctx)['verified']),'Dead-letter owner job invalidates stored File04 acceptance proof');
$plugin->file04_request_reindex($ctx);
$persisted=get_option('sabri_file26_file04_cutover_receipts_v1',array())[$ctx['request_digest']]??null;
f26f04_assert($persisted['job_uuid']===$row['job_uuid'],'Failed same-digest request cannot silently mint a new generation');
echo "PASS: File04/26 asynchronous owner-queue contract on real MariaDB\n";
