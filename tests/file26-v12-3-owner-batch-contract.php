<?php
/** FR-004/005: malformed owner batch MUST NOT promote an incomplete shadow. Pure PHP. */
namespace {
    define('ABSPATH',__DIR__.'/');
    class WP_Error {
        private $code;
        public function __construct($code,$message=''){ $this->code=$code; }
        public function get_error_code(){ return $this->code; }
    }
    function is_wp_error($v){ return $v instanceof WP_Error; }
    function sanitize_text_field($v){return trim(strip_tags((string)$v));}
}
namespace Sabri\File26 {
    require_once dirname(__DIR__).'/includes/class-file26-indexer.php';
}
namespace {
    function f26_batch_require($ok,$message){
        if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
        echo "PASS: $message\n";
    }
    $indexer = (new \ReflectionClass('Sabri\File26\Indexer'))->newInstanceWithoutConstructor();
    $method = new \ReflectionMethod($indexer,'validate_owner_batch');
    $method->setAccessible(true);
    $run = static function($batch,$cursor='start',$limit=100) use($method,$indexer){
        return $method->invoke($indexer,$batch,$cursor,$limit);
    };
    $valid = $run(array('items'=>array(array('object_id'=>'a')),'done'=>false,'next_cursor'=>'next'));
    f26_batch_require(!is_wp_error($valid) && $valid['done']===false && $valid['next_cursor']==='next','Typed nonfinal page advances original owner cursor');

    $complete = $run(array('items'=>array(),'done'=>true));
    f26_batch_require(!is_wp_error($complete) && $complete['done']===true,'Typed final empty batch allowed for true empty source, subject to later source-completeness proof');

    foreach(array('false',1,'0',null) as $bad){
        $result = $run(array('items'=>array(array('object_id'=>'a')),'done'=>$bad,'next_cursor'=>'next'));
        f26_batch_require(is_wp_error($result) && $result->get_error_code()==='file26_owner_batch_done_not_boolean','Non-boolean done cannot prematurely complete snapshot');
    }
    $missing = $run(array('items'=>array(),'next_cursor'=>'next'));
    f26_batch_require(is_wp_error($missing),'Missing done marker cannot implicitly complete or proceed');

    $oversized=$run(array('items'=>array_fill(0,101,array('object_id'=>'a')),'done'=>true));
    f26_batch_require(is_wp_error($oversized)&&$oversized->get_error_code()==='file26_owner_batch_limit','Owner cannot violate requested batch bound');

    $badItem=$run(array('items'=>array(array('object_id'=>'a'),'corrupt'),'done'=>true));
    f26_batch_require(is_wp_error($badItem),'Non-document owner item cannot be processed');

    $same=$run(array('items'=>array(),'done'=>false,'next_cursor'=>'start'));
    f26_batch_require(is_wp_error($same)&&$same->get_error_code()==='file26_owner_batch_cursor','Nonfinal cursor must advance');

    $missingCursor=$run(array('items'=>array(),'done'=>false));
    f26_batch_require(is_wp_error($missingCursor),'Nonfinal missing cursor refused');

    $oversizedCursor=$run(array('items'=>array(),'done'=>false,'next_cursor'=>str_repeat('x',192)));
    f26_batch_require(is_wp_error($oversizedCursor),'Oversize opaque source cursor cannot be silently truncated');

    $htmlCursor=$run(array('items'=>array(),'done'=>false,'next_cursor'=>'<script>x</script>'));
    f26_batch_require(is_wp_error($htmlCursor),'Cursor data changed by sanitization is not a valid owner checkpoint');

    $nonscalar=$run(array('items'=>array(),'done'=>false,'next_cursor'=>array('a')));
    f26_batch_require(is_wp_error($nonscalar),'Array cursor fails closed without PHP warnings');

    $emptyPage=$run(array('items'=>array(),'done'=>false,'next_cursor'=>'skip-2'));
    f26_batch_require(!is_wp_error($emptyPage),'Empty nonfinal page with advancing cursor is valid');

    echo "PASS: File26 typed, bounded, version-safe owner batch shape integrity\n";
}
