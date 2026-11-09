<?php
/**
 * Multilingual and adversarial Search/Future-provider acceptance on disposable
 * WordPress/MariaDB. Uses real plugin SQL + auth, but synthetic owner records.
 * This tests safety and lexical retrieval, NOT a deployed semantic model.
 */
if ('yes' !== getenv('FILE26_CI_EPHEMERAL_DB') || false === strpos(home_url('/'), 'file26-ci.local')) {
    fwrite(STDERR, "REFUSED: isolated file26-ci.local database required.\n"); exit(2);
}
function file26_lang_assert($condition,$message) {
    if (!$condition) { fwrite(STDERR,"FAIL: $message\n");exit(1); }
    echo "PASS: $message\n";
}
$slug='file26_ci_languages';
$manifest=array(
    'slug'=>$slug, 'owner_file'=>'File 26 CI Only','contract_version'=>'1.0',
    'entity_types'=>array('article'),'privacy_classes'=>array('public','members'),
    'visibility_fields'=>array('public','members'),'deletion_semantics'=>'versioned_tombstone',
    'status'=>'proposed',
    'list_batch'=>static function(){return array('items'=>array(),'done'=>true);},
    'can_view'=>static function(){return true;},
    'health'=>static function(){return array('state'=>'healthy');}
);
$registered=sabri_file26_register_connector($manifest);
file26_lang_assert(!is_wp_error($registered),'Language fixture owner registered');
global $wpdb;
$wpdb->update(\Sabri\File26\DB::table('connectors'),array('status'=>'active'),array('slug'=>$slug));
$registered=sabri_file26_register_connector($manifest);
file26_lang_assert(!is_wp_error($registered)&&\Sabri\File26\Plugin::instance()->connectors()->is_active($slug),'Language fixture index eligibility active only in ephemeral environment');
$cases=array(
    array('id'=>'urdu','locale'=>'ur','title'=>'کلاسیکی ہومیوپیتھی کی تعلیم','query'=>'ہومیوپیتھی'),
    array('id'=>'arabic','locale'=>'ar','title'=>'مقدمة المعالجة المثلية','query'=>'المعالجة'),
    array('id'=>'english','locale'=>'en-US','title'=>'Classical homeopathy fundamentals','query'=>'homeopathy'),
    array('id'=>'roman','locale'=>'ur-Latn','title'=>'jigar ki soozish','query'=>'soozish'),
    array('id'=>'farsi','locale'=>'fa','title'=>'آموزش طب همسان','query'=>'آموزش'),
    array('id'=>'bengali','locale'=>'bn','title'=>'হোমিওপ্যাথি পরিচিতি','query'=>'হোমিওপ্যাথি'),
    array('id'=>'hindi','locale'=>'hi','title'=>'होम्योपैथी परिचय','query'=>'होम्योपैथी')
);
foreach($cases as $case){
    $doc=array(
        'connector_slug'=>$slug,'domain'=>'education','object_id'=>$case['id'],
        'object_version'=>1,'source_event_sequence'=>1,'entity_type'=>'article',
        'locale'=>$case['locale'],'state'=>'published','visibility'=>'public',
        'title'=>$case['title'],'excerpt'=>'Public bounded language test',
        'canonical_url'=>home_url('/file26-ci/language/'.$case['id']),
        'search_text'=>$case['title']
    );
    $res=sabri_file26_upsert_document($doc);
    file26_lang_assert(!is_wp_error($res),'Real SQL stores '.$case['locale'].' public fixture');
}
wp_set_current_user(0);
foreach($cases as $case){
    $result=sabri_file26_search(array('q'=>$case['query'],'locale'=>$case['locale'],'limit'=>10));
    $found=false;
    if(!is_wp_error($result)){
        foreach((array)($result['results']??array()) as $row){
            if(($row['title']??'')===$case['title']){$found=true;break;}
        }
    }
    file26_lang_assert($found,'Public SQL search retrieves canonical '.$case['locale'].' script without inventing semantic translation');
}
$private=array(
    'connector_slug'=>$slug,'domain'=>'education','object_id'=>'secret',
    'object_version'=>1,'source_event_sequence'=>1,'entity_type'=>'article',
    'locale'=>'en-US','state'=>'published','visibility'=>'members',
    'title'=>'Private Members Only Teaching Record','excerpt'=>'Not public',
    'canonical_url'=>home_url('/file26-ci/language/secret')
);
file26_lang_assert(!is_wp_error(sabri_file26_upsert_document($private)),'Members-only fixture indexed for privacy-negative search');
$attacks=array(
    'Private Members Only Teaching Record',
    "Private' OR 1=1 --",
    '<script>Private Members Only Teaching Record</script>',
    'Private%' ,
    'Private\\_'
);
foreach($attacks as $attack){
    $result=sabri_file26_search(array('q'=>$attack,'locale'=>'en-US','limit'=>30));
    $leaked=false;
    if(!is_wp_error($result)){
        foreach((array)($result['results']??array()) as $row){
            if(($row['title']??'')==='Private Members Only Teaching Record'){$leaked=true;}
        }
    }
    file26_lang_assert(!$leaked,'Unauthenticated SQL injection / text query cannot expose members-only record');
}
$normalizer=new \Sabri\File26\Normalizer();
file26_lang_assert($normalizer->normalize('كِتاب')===$normalizer->normalize('کتاب'),'Arabic/Urdu codepoints and diacritics share controlled normalized form');
file26_lang_assert($normalizer->normalize('۱۲۳۴')==='1234','Urdu-Indic numerals normalized consistently');
file26_lang_assert($normalizer->token_similarity('jigar','jiger')>=0.7,'Roman Urdu bounded typo tolerance');
file26_lang_assert(!$normalizer->prefix_is_safe('03001234567'),'Telephone-like autocomplete prefixes refused');
file26_lang_assert(count($normalizer->expansions('jigar'))<=12,'Cross-language lexical expansions are strictly bounded');
$plugin=\Sabri\File26\Plugin::instance();
file26_lang_assert(class_exists('Sabri\\File26\\Future_Intelligence'),'Future provider boundary class available');
$future=new \Sabri\File26\Future_Intelligence($plugin->search(),$plugin->recommendations(),$normalizer,new \Sabri\File26\Security(),$plugin->central_plan(),$plugin->health());
$request=new WP_REST_Request('GET','/sabri-search/v1/semantic-rerank');
$request->set_param('q','Classical homeopathy fundamentals');
$request->set_param('locale','en-US');
$semantic=$future->semantic_rerank($request);
file26_lang_assert(!is_wp_error($semantic)&&empty($semantic['provider_available']),'Semantic model correctly reports unavailable without a connected actual model');
$externalCalls=0;
$provider=function($scores,$query,$candidates,$policy)use(&$externalCalls){
    $externalCalls++;
    $out=array(str_repeat('f',64)=>999999999); // Never import an unrelated key.
    foreach($candidates as $candidate){
        if(!empty($candidate['key'])){$out[$candidate['key']]=0.85;}
    }
    return $out;
};
add_filter('sabri_file26_semantic_reranker',$provider,10,4);
$semanticWithStub=$future->semantic_rerank($request);
file26_lang_assert(!is_wp_error($semanticWithStub)&&!empty($semanticWithStub['provider_available'])&&$externalCalls===1,'Approved fixture reranker receives only pre-authorized candidate keys');
$beforeKeys=array_column($semantic['results'],'key');
$afterKeys=array_column($semanticWithStub['results'],'key');
sort($beforeKeys);sort($afterKeys);
file26_lang_assert($beforeKeys===$afterKeys,'Reranker cannot add unindexed/unauthorized search results');
file26_lang_assert(!in_array(str_repeat('f',64),$afterKeys,true),'Reranker ignores unrecognized malicious result key');
$clinical=new WP_REST_Request('GET','/sabri-search/v1/semantic-rerank');
$clinical->set_param('q','diagnose patient and prescribe potency');
$beforeCalls=$externalCalls;
$clinicalResult=$future->semantic_rerank($clinical);
file26_lang_assert(!is_wp_error($clinicalResult)&&!empty($clinicalResult['provider_bypassed_for_sensitive_or_clinical'])&&$externalCalls===$beforeCalls,'Clinical/sensitive queries never call external semantic provider');
remove_filter('sabri_file26_semantic_reranker',$provider,10);
$image=new WP_REST_Request('POST','/sabri-search/v1/multimodal');
$image->set_param('asset',array('kind'=>'patient_image','owner'=>'file10','object_id'=>'private-1'));
$medicalImage=$future->multimodal_search($image);
file26_lang_assert(is_wp_error($medicalImage)&&$medicalImage->get_error_code()==='file26_multimodal_clinical_diagnosis_prohibited','Patient images refused from public multimodal diagnosis service');
echo "PASS: seven-script lexical SQL, adversarial privacy, and fail-closed semantic/clinical provider boundaries\n";
