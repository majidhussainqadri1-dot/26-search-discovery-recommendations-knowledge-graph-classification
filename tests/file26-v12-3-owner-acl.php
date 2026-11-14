<?php
/** FR003/CEN01: a public label is not authority when canonical owner ACL is absent. */
namespace {
    define('ABSPATH',__DIR__.'/');
    class WP_Error { public function __construct($code='denied'){} }
    function sanitize_key($value){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$value));}
}
namespace Sabri\File26 {
    class Security {
        public $fallback_calls=0;
        public $audit_calls=0;
        public function can_view_visibility($visibility,$audience,$payload=array()){
            $this->fallback_calls++;
            return 'public'===$visibility;
        }
        public function audit($event,$context=array()){$this->audit_calls++;return true;}
    }
    require_once dirname(__DIR__).'/includes/class-file26-connectors.php';
}
namespace {
    use Sabri\File26\Connectors;
    use Sabri\File26\Security;
    function verify_owner_acl($ok,$label){
        if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}
        echo "PASS: $label\n";
    }
    $security=new Security();
    $connectors=new Connectors($security);
    $reflection=new \ReflectionClass($connectors);
    $property=$reflection->getProperty('registry');
    $property->setAccessible(true);
    $doc=array('visibility'=>'public','payload'=>array(),'canonical_key'=>str_repeat('a',64));
    $guest=array('authenticated'=>false,'valid'=>true);

    $property->setValue($connectors,array('no_acl'=>array('status'=>'active','slug'=>'no_acl')));
    verify_owner_acl($connectors->can_view('no_acl',$doc,$guest)===false,
        'Public document is never retrievable when active owner lacks canonical can_view');
    verify_owner_acl($security->fallback_calls===0,
        'Generic File26 visibility fallback must not substitute for missing owner ACL');

    $property->setValue($connectors,array('denied'=>array('status'=>'active','slug'=>'denied',
        'can_view'=>static function($row,$who){return false;})));
    verify_owner_acl(!$connectors->can_view('denied',$doc,$guest),
        'Explicit owner denial overrides public projection');

    $property->setValue($connectors,array('bad_type'=>array('status'=>'active','slug'=>'bad_type',
        'can_view'=>static function($row,$who){return 'true';})));
    verify_owner_acl(!$connectors->can_view('bad_type',$doc,$guest),
        'Malformed string true is not a typed owner authorization receipt');

    $property->setValue($connectors,array('yes'=>array('status'=>'active','slug'=>'yes',
        'can_view'=>static function($row,$who){return true;})));
    verify_owner_acl($connectors->can_view('yes',$doc,$guest)===true,
        'Canonical owner explicit PHP boolean true authorizes eligible public result');

    $property->setValue($connectors,array('exception'=>array('status'=>'active','slug'=>'exception',
        'can_view'=>static function($row,$who){throw new \RuntimeException('Synthetic owner outage');})));
    verify_owner_acl($connectors->can_view('exception',$doc,$guest)===false,
        'Canonical owner callback failure denies and does not fabricate success');

    $property->setValue($connectors,array('proposed'=>array('status'=>'proposed','slug'=>'proposed',
        'can_view'=>static function($row,$who){return true;})));
    verify_owner_acl(!$connectors->can_view('proposed',$doc,$guest),
        'Proposed-but-inactive canonical owner cannot serve a result');

    echo "PASS: File26 canonical owner ACL is mandatory, typed and fail closed\n";
}
