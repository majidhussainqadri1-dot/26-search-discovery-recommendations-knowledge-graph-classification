<?php
/**
 * Real WordPress HTTP transport contract for optional loopback Ollama adapter.
 * No Ollama service or external request is made by this test. Validates the
 * integration/SSRF boundary; NOT semantic model relevance or deployment.
 */
if ('yes' !== getenv('FILE26_CI_EPHEMERAL_DB') || false === strpos(home_url('/'), 'file26-ci.local')) {
    fwrite(STDERR, "REFUSED: Ollama contract test must use disposable WP CI.\n"); exit(2);
}
function f26_emb_assert($condition,$message) {
    if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";
}
f26_emb_assert(class_exists('Sabri\\File26\\Semantic_Ollama'),'Actual local Ollama HTTP adapter loaded in File26 bootstrap');
define('SABRI_FILE26_SEMANTIC_OLLAMA_ENABLED',true);
define('SABRI_FILE26_SEMANTIC_OLLAMA_MODEL','bge-m3');
wp_set_current_user(0);
$GLOBALS['f26_embed_calls']=0;
$GLOBALS['f26_embed_mode']='valid';
$http_mock=static function($pre,$args,$url){
    if($url!=='http://127.0.0.1:11434/api/embed'){return new WP_Error('f26_test_nonlocal_network','No outbound HTTP allowed in this isolated test');}
    $GLOBALS['f26_embed_calls']++;
    $input=json_decode((string)($args['body']??''),true);
    if(!is_array($input)||($input['model']??'')!=='bge-m3'||!is_array($input['input']??null)||count($input['input'])!==3||
        (int)($args['redirection']??-1)!==0 ||(int)($args['limit_response_size']??0)>250000) {
        return new WP_Error('f26_test_bad_request','Unexpected transport payload or unsafe redirect/response size');
    }
    if('outage'===$GLOBALS['f26_embed_mode']){
        return array('headers'=>array(),'body'=>'{"error":"unavailable"}','response'=>array('code'=>503,'message'=>'Unavailable'),'cookies'=>array(),'filename'=>null);
    }
    $vectors=array();
    foreach(array(array(1,0),array(0.9,0.1),array(0,1)) as $axes){
        $vector=array_fill(0,128,0.0);$vector[0]=$axes[0];$vector[1]=$axes[1];$vectors[]=$vector;
    }
    if('bad_dimensions'===$GLOBALS['f26_embed_mode']){$vectors[2]=array(1.0,0.0);}
    if('nonfinite'===$GLOBALS['f26_embed_mode']){$vectors[1][4]='NaN';}
    if('missing'===$GLOBALS['f26_embed_mode']){array_pop($vectors);}
    return array('headers'=>array(),'body'=>wp_json_encode(array('embeddings'=>$vectors)),'response'=>array('code'=>200,'message'=>'OK'),'cookies'=>array(),'filename'=>null);
};
add_filter('pre_http_request',$http_mock,10,3);
$docA=array('key'=>str_repeat('a',64),'title'=>'Classical homeopathy principles','excerpt'=>'Public lesson');
$docB=array('key'=>str_repeat('b',64),'title'=>'Organic gardening','excerpt'=>'Public plants');
$policy=array('safety_gates_immutable'=>true,'visibility_gates_immutable'=>true);
$scores=apply_filters('sabri_file26_semantic_reranker',array(),'Homeopathy lesson',array($docA,$docB),$policy);
f26_emb_assert(is_array($scores)&&count($scores)===2&&$scores[$docA['key']]>$scores[$docB['key']],'Real WordPress HTTP path validates dimensions and cosine rerank of authorized candidate set');
f26_emb_assert($GLOBALS['f26_embed_calls']===1,'Only one batch HTTP call and no model key leaks');
$old=$GLOBALS['f26_embed_calls'];
$private=apply_filters('sabri_file26_semantic_reranker',array(),'Patient CNIC details',array($docA,$docB),$policy);
f26_emb_assert(!$private&&$GLOBALS['f26_embed_calls']===$old,'Sensitive personal query bypasses any model HTTP call');
wp_set_current_user(1);
$authed=apply_filters('sabri_file26_semantic_reranker',array(),'Homeopathy lesson',array($docA,$docB),$policy);
f26_emb_assert(!$authed&&$GLOBALS['f26_embed_calls']===$old,'Logged-in member data can never leave the owner indexing boundary for embeddings');
wp_set_current_user(0);
foreach(array('outage','bad_dimensions','nonfinite','missing') as $mode){
    $GLOBALS['f26_embed_mode']=$mode;
    $answer=apply_filters('sabri_file26_semantic_reranker',array(),'Homeopathy lesson',array($docA,$docB),$policy);
    f26_emb_assert($answer===array(),'Ollama '.$mode.' fails closed with no injected search score');
}
$GLOBALS['f26_embed_mode']='valid';
$forged=apply_filters('sabri_file26_semantic_reranker',array(),'Homeopathy lesson',array_merge(array($docA,$docB),array_fill(0,29,$docA)),$policy);
f26_emb_assert(!$forged,'Oversized 31-candidate input refuses expensive inference before any network request');
remove_filter('pre_http_request',$http_mock,10);
echo "PASS: self-hosted Ollama HTTP adapter; strictly opt-in, local-only, constrained and fail-closed; real model remains unverified\n";
