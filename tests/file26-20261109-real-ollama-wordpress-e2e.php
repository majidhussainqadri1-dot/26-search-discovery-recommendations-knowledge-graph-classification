<?php
/**
 * True Ollama model + real File26 WordPress guest search integration.
 * Executed ONLY on ephemeral WordPress in the isolated GitHub model runner.
 * Unmocked Ollama HTTP path is required; no clinical or private source data.
 */
if ('yes'!==getenv('FILE26_CI_EPHEMERAL_DB')||false===strpos(home_url('/'),'file26-ci.local')) {
    fwrite(STDERR,"REFUSED: an ephemeral WordPress+Ollama CI environment is required.\n");exit(2);
}
function f26_real_model_assert($cond,$what) {
    if(!$cond){fwrite(STDERR,"FAIL: $what\n");exit(1);}
    echo "PASS: $what\n";
}
define('SABRI_FILE26_SEMANTIC_OLLAMA_ENABLED',true);
define('SABRI_FILE26_SEMANTIC_OLLAMA_MODEL','qwen3-embedding:0.6b');
wp_set_current_user(0);
$plugin=\Sabri\File26\Plugin::instance();
$slug='file26_ci_model_demo';
$manifest=array(
    'slug'=>$slug,'owner_file'=>'File26 CI Only','contract_version'=>'1.0',
    'entity_types'=>array('article'),'privacy_classes'=>array('public','members'),
    'visibility_fields'=>array('public','members'),
    'deletion_semantics'=>'versioned_tombstone','status'=>'proposed',
    'list_batch'=>static function(){return array('items'=>array(),'done'=>true);},
    'can_view'=>static function(){return true;},
    'health'=>static function(){return array('state'=>'healthy');}
);
$reg=sabri_file26_register_connector($manifest);
f26_real_model_assert(!is_wp_error($reg),'Ephemeral public-only owner registered');
global $wpdb;
$wpdb->update(\Sabri\File26\DB::table('connectors'),array('status'=>'active'),array('slug'=>$slug));
$reg=sabri_file26_register_connector($manifest);
f26_real_model_assert(!is_wp_error($reg)&&$plugin->connectors()->is_active($slug),'Only disposable owner promoted for real-model query');
foreach(array(
    array('id'=>'history','title'=>'History of classical homeopathy','excerpt'=>'Samuel Hahnemann, the Organon, and the history of classical homeopathic teaching.','visibility'=>'public'),
    array('id'=>'supplies','title'=>'Homeopathy learning supplies','excerpt'=>'Printed notebooks, pencils, educational materials, and stationery for students.','visibility'=>'public'),
    array('id'=>'private','title'=>'Private homeopathy patient lesson','excerpt'=>'Not for public results or model access.','visibility'=>'members')
) as $item) {
    $doc=array(
        'connector_slug'=>$slug,'domain'=>'education','object_id'=>$item['id'],
        'object_version'=>1,'source_event_sequence'=>1,
        'entity_type'=>'article','locale'=>'en-US','state'=>'published',
        'visibility'=>$item['visibility'],'title'=>$item['title'],
        'excerpt'=>$item['excerpt'],'search_text'=>$item['title'].' '.$item['excerpt'],
        'canonical_url'=>home_url('/file26-ci/model/'.$item['id'])
    );
    $res=sabri_file26_upsert_document($doc);
    f26_real_model_assert(!is_wp_error($res),'Source indexed: '.$item['id']);
}
$request=new WP_REST_Request('GET','/sabri-search/v1/semantic-rerank');
$request->set_param('q','homeopathy');
$request->set_param('locale','en-US');
$future=new \Sabri\File26\Future_Intelligence($plugin->search(),$plugin->recommendations(),new \Sabri\File26\Normalizer(),new \Sabri\File26\Security(),$plugin->central_plan(),$plugin->health());
$baseline=$plugin->search()->run(array('q'=>'homeopathy','locale'=>'en-US','limit'=>30));
f26_real_model_assert(!is_wp_error($baseline)&&count($baseline['results'])>=2,'Actual lexical guest search supplies multiple authorized candidates');
$result=$future->semantic_rerank($request);
f26_real_model_assert(!is_wp_error($result)&&!empty($result['provider_available']),'Real unmocked Qwen3 Ollama service produces semantic ranking inside WordPress');
$names=array_column($result['results'],'title');
f26_real_model_assert(in_array('History of classical homeopathy',$names,true)&&in_array('Homeopathy learning supplies',$names,true),'Both authorized educational candidates survive reranking');
f26_real_model_assert(!in_array('Private homeopathy patient lesson',$names,true),'Private/member document never reaches candidate model or guest results');
$scores=array_column($result['results'],'semantic_rerank_score');
f26_real_model_assert(count($scores)>0&&count(array_filter($scores,'is_numeric'))===count($scores),'Real embedding cosine values are finite');
$medical=new WP_REST_Request('GET','/sabri-search/v1/semantic-rerank');
$medical->set_param('q','diagnose me patient');
$clinical=$future->semantic_rerank($medical);
f26_real_model_assert(!is_wp_error($clinical)&&!empty($clinical['provider_bypassed_for_sensitive_or_clinical'])&&!$clinical['provider_available'],'Sensitive clinical intent does not invoke model');
echo "PASS: true Ollama embedding inference wired end-to-end into real WordPress guest File26 search, not deployed Live\n";
