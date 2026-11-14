<?php
/**
 * File26 × File03 V12.3 D20 owner lifecycle payload contract.
 *
 * File03 emits payload.professional_lifecycle for active/retired/legacy
 * professionals. File26 must not drop that field while indexing, because
 * File03 then compares it to the current owner state at click time to prevent
 * a stale doctor-badge/search document after retirement.
 *
 * The real private Indexer payload sanitizer is invoked; no copied logic.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__ . '/');
class WP_Error {
    private $code;
    public function __construct($code,$message=''){$this->code=(string)$code;}
    public function get_error_code(){return $this->code;}
}
function is_wp_error($value){return $value instanceof WP_Error;}
function sanitize_key($value){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$value));}
function sanitize_text_field($value){return trim(strip_tags((string)$value));}
require __DIR__ . '/../includes/class-file26-indexer.php';

$reflection = new ReflectionClass(\Sabri\File26\Indexer::class);
$indexer = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('sanitize_payload');
$method->setAccessible(true);
function check($test,$message) {
    if(!$test){fwrite(STDERR,'FAIL File26 lifecycle: '.$message."\n");exit(1);}
}
foreach(['active','retired','legacy'] as $lifecycle){
    $result = $method->invoke($indexer,array(
        'verified_doctor'=>false,
        'professional_lifecycle'=>$lifecycle,
    ),'file03-profiles','profiles');
    check(is_array($result),'sanitizer failed for permitted '.$lifecycle);
    check(isset($result['professional_lifecycle'])
        && $result['professional_lifecycle']===$lifecycle,
        'indexer stripped or modified File03 '.$lifecycle.' owner state');
    check(isset($result['verified_doctor'])
        && $result['verified_doctor']===false,
        'verified-doctor boolean was not preserved');
}
foreach(['unknown','active,legacy','',array('active')] as $invalid){
    $result=$method->invoke($indexer,array('professional_lifecycle'=>$invalid),'file03-profiles','profiles');
    check(is_wp_error($result) &&
        $result->get_error_code()==='file26_invalid_professional_lifecycle',
        'invalid lifecycle was accepted instead of rejected');
}
$without=$method->invoke($indexer,array('verified_doctor'=>true),'other-connector','other-domain');
check(is_array($without) && !array_key_exists('professional_lifecycle',$without),
    'unrelated connectors require a File03-only lifecycle field');
$unowned=$method->invoke($indexer,array('professional_lifecycle'=>'active'),'other-connector','other-domain');
check(is_wp_error($unowned)&&$unowned->get_error_code()==='file26_invalid_professional_lifecycle_owner',
    'unrelated connector spoofed File03 professional lifecycle');
echo "PASS File26 strict File03 active/retired/legacy lifecycle payload preservation\n";
