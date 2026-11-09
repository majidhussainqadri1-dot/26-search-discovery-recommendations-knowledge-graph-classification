<?php
/** Executable canonical owner activation-gate contract; synthetic adapters only. */
namespace {
    define('ABSPATH', __DIR__ . '/');
    function sanitize_key($text) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$text)); }
    function sanitize_text_field($text) { return trim(strip_tags((string)$text)); }
    function apply_filters($name, $value) {
        if ('sabri_file26_cross_file_gate_evidence' === $name) {
            return isset($GLOBALS['file26_evidence']) ? $GLOBALS['file26_evidence'] : $value;
        }
        if ('sabri_file26_owner_connector_adapters' === $name) {
            return isset($GLOBALS['file26_adapters']) ? $GLOBALS['file26_adapters'] : $value;
        }
        return $value;
    }
    function assertion($ok, $message) {
        if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
        echo "PASS: $message\n";
    }
}
namespace Sabri\File26 {
    class Connectors {
        private $registry;
        public function __construct(array $registry) { $this->registry = $registry; }
        public function all() { return $this->registry; }
    }
    require_once dirname(__DIR__) . '/includes/class-file26-owner-contracts.php';
}
namespace {
    $required = (new Sabri\File26\Owner_Contracts(new Sabri\File26\Connectors(array())))->requirements();
    assertion(count($required)===11, 'Exactly eleven independent canonical owner requirements');
    $missing = new Sabri\File26\Owner_Contracts(new Sabri\File26\Connectors(array()));
    assertion(!$missing->all_required_active(), 'No synthetic/missing owner is production ready');

    $GLOBALS['file26_evidence'] = array_fill_keys(array(
        'file00_identity_contract','file20_shell_contract','file24_assurance_contract',
        'file25_visual_contract','staging_acceptance','migration_rehearsal','rollback_rehearsal'
    ), true);
    assertion($missing->activation_gate(true,array('status'=>'healthy'),array('fixture'))===false, 'No activation without all owner manifests even if evidence appears approved');

    $registry = array(); $adapters = array();
    foreach ($required as $key => $owner) {
        $registry[$key] = array(
            'slug' => $key,'owner_file' => $owner['owner_file'],'contract_version'=>'1.0',
            'entity_types'=>$owner['entity_types'],'status'=>'active',
            'list_batch'=>static function() { return array('items'=>array(),'done'=>true); },
            'can_view'=>static function() { return false; },
            'health'=>static function() { return array('state'=>'healthy'); },
        );
        $adapters[$key] = array(
            'entity_types'=>$owner['entity_types'],'contract_version'=>'1.0',
            'list_batch'=>$registry[$key]['list_batch'],
            'can_view'=>$registry[$key]['can_view'],
            'health'=>$registry[$key]['health'],
            'privacy_classes'=>array('public'),
            'visibility_fields'=>array('public'),
            'deletion_semantics'=>'versioned_tombstone'
        );
    }
    $valid = new Sabri\File26\Owner_Contracts(new Sabri\File26\Connectors($registry));
    assertion(count($valid->readiness())===11 && $valid->all_required_active(), 'Required callable owner contract structure passes when all eleven synthetic adapters supplied');
    assertion($valid->activation_gate(true,array('status'=>'healthy'),array('fixture'))===true, 'Explicit unanimous owner and cross-file evidence is required for gate acceptance');

    $GLOBALS['file26_evidence']['file24_assurance_contract'] = false;
    assertion(!$valid->activation_gate(true,array('status'=>'healthy'),array('fixture')), 'Missing File24 assurance evidence denies activation');
    $GLOBALS['file26_evidence']['file24_assurance_contract'] = true;
    assertion(!$valid->activation_gate(false,array('status'=>'healthy'),array('fixture')), 'No activation without independent explicit Founder approval');
    assertion(!$valid->activation_gate(true,array('status'=>'degraded'),array('fixture')), 'Degraded overall health refuses activation');

    $bad = $registry; $bad['file07']['status']='shadow';
    $badGate = new Sabri\File26\Owner_Contracts(new Sabri\File26\Connectors($bad));
    assertion(!$badGate->all_required_active(), 'File07 in shadow state fails real-owner activation gate');
    $bad = $registry; unset($bad['file21']['list_batch']);
    $badGate = new Sabri\File26\Owner_Contracts(new Sabri\File26\Connectors($bad));
    assertion(!$badGate->all_required_active(), 'File21 missing batch callback fails gate');
    $bad = $registry; $bad['file08']['owner_file']='File 03';
    $badGate = new Sabri\File26\Owner_Contracts(new Sabri\File26\Connectors($bad));
    assertion(!$badGate->all_required_active(), 'Impersonated wrong owner_file cannot satisfy canonical File08 readiness');

    $GLOBALS['file26_adapters']=$adapters;
    $collect = new Sabri\File26\Owner_Contracts(new Sabri\File26\Connectors(array()));
    $manifests = $collect->collect(array());
    assertion(count($manifests)===11, 'All eleven correctly formed synthetic native adapter contracts collected');
    unset($GLOBALS['file26_adapters']['file06']['list_batch']);
    $manifests = $collect->collect(array());
    assertion(count($manifests)===10, 'Absent list_batch prevents File06 source owner from registering');

    echo "PASS: eleven-owner release gate runtime matrix; no real adapter presence implied\n";
}
