<?php

declare(strict_types=1);

namespace Sabri\File26\Governance;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class EvaluationCase
{
    public function __construct(private readonly string $caseId,private readonly string $query,private readonly string $locale,private readonly string $domain,private readonly array $expectedKeys,private readonly array $forbiddenKeys,private readonly bool $safetyCritical)
    {
        if(!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$caseId)||trim($query)===''||strlen($query)>500||!preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/',$locale)||!preg_match('/^[a-z][a-z0-9._-]{1,79}$/',$domain))throw new InvariantViolation('Evaluation case metadata is invalid.');
        foreach([$expectedKeys,$forbiddenKeys] as $values){if(!array_is_list($values)||count($values)>100||count($values)!==count(array_unique($values)))throw new InvariantViolation('Evaluation result keys must be bounded and unique.');foreach($values as $key)if(!is_string($key)||trim($key)===''||strlen($key)>292)throw new InvariantViolation('Evaluation result key is invalid.');}
        if(array_intersect($expectedKeys,$forbiddenKeys)!==[])throw new InvariantViolation('Evaluation expected and forbidden keys cannot overlap.');
    }
    public function caseId():string{return $this->caseId;} public function query():string{return $this->query;} public function locale():string{return $this->locale;} public function domain():string{return $this->domain;} public function expectedKeys():array{return $this->expectedKeys;} public function forbiddenKeys():array{return $this->forbiddenKeys;} public function safetyCritical():bool{return $this->safetyCritical;}
}

final class EvaluationRegistry
{
    private array $cases=[];
    public function __construct(private readonly string $version,private readonly string $reviewerKey){if(!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/',$version)||!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$reviewerKey))throw new InvariantViolation('Evaluation registry version or reviewer is invalid.');}
    public function add(EvaluationCase $case):void{if(isset($this->cases[$case->caseId()]))throw new InvariantViolation('Duplicate evaluation case ID.');$this->cases[$case->caseId()]=$case;}
    public function evaluate(array $actualByCase):array
    {
        $totalExpected=0;$foundExpected=0;$forbiddenHits=0;$criticalFailures=[];$reports=[];
        foreach($this->cases as $id=>$case){$actual=$actualByCase[$id]??[];if(!array_is_list($actual)||count($actual)>500)throw new InvariantViolation('Evaluation actual results are invalid.');$found=array_values(array_intersect($case->expectedKeys(),$actual));$forbidden=array_values(array_intersect($case->forbiddenKeys(),$actual));$totalExpected+=count($case->expectedKeys());$foundExpected+=count($found);$forbiddenHits+=count($forbidden);if($case->safetyCritical()&&($forbidden!==[]||count($found)<count($case->expectedKeys())))$criticalFailures[]=$id;$reports[$id]=['expected_found'=>$found,'expected_missing'=>array_values(array_diff($case->expectedKeys(),$actual)),'forbidden_found'=>$forbidden];}
        return ['registry_version'=>$this->version,'reviewer'=>$this->reviewerKey,'case_count'=>count($this->cases),'recall'=>$totalExpected===0?1.0:$foundExpected/$totalExpected,'forbidden_hits'=>$forbiddenHits,'critical_failures'=>$criticalFailures,'release_pass'=>$forbiddenHits===0&&$criticalFailures===[],'cases'=>$reports];
    }
}

final class WordPressEvaluationStore
{
    private readonly string $table; public function __construct(private readonly wpdb $db){$this->table=$db->prefix.'s26_evaluation_sets';}
    public function save(string $setKey,string $version,array $cases,string $reviewerKey):void
    {
        if(!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$setKey)||!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/',$version)||!array_is_list($cases)||$cases===[]||count($cases)>10000||!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$reviewerKey))throw new InvariantViolation('Evaluation set metadata is invalid.');
        $payload=[];foreach($cases as $case){if(!$case instanceof EvaluationCase)throw new InvariantViolation('Evaluation sets may contain EvaluationCase values only.');$payload[]=['case_id'=>$case->caseId(),'query'=>$case->query(),'locale'=>$case->locale(),'domain'=>$case->domain(),'expected_keys'=>$case->expectedKeys(),'forbidden_keys'=>$case->forbiddenKeys(),'safety_critical'=>$case->safetyCritical()];}
        $json=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$updated=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');$written=$this->db->query($this->db->prepare("INSERT INTO {$this->table} (set_key,version,state,reviewer_key,payload,payload_hash,updated_at) VALUES (%s,%s,'approved',%s,%s,%s,%s) ON DUPLICATE KEY UPDATE state='approved',reviewer_key=VALUES(reviewer_key),payload=VALUES(payload),payload_hash=VALUES(payload_hash),updated_at=VALUES(updated_at)",$setKey,$version,$reviewerKey,$json,hash('sha256',$json),$updated));if($written===false)throw new InvariantViolation('Evaluation set persistence failed.');
    }
}

final class HealthDashboard
{
    public function summarize(array $metrics):array
    {
        foreach(['connector_lag_seconds','failed_events','document_count','hidden_state_leaks','zero_result_rate','p95_latency_ms','graph_orphans'] as $key)if(!array_key_exists($key,$metrics))throw new InvariantViolation('Health dashboard metric is missing: '.$key);
        $alerts=[];if((int)$metrics['connector_lag_seconds']>900)$alerts[]='connector-lag-high';if((int)$metrics['failed_events']>0)$alerts[]='failed-events-present';if((int)$metrics['hidden_state_leaks']>0)$alerts[]='visibility-leak-blocker';if((float)$metrics['zero_result_rate']>0.25)$alerts[]='zero-result-rate-high';if((int)$metrics['p95_latency_ms']>1000)$alerts[]='latency-slo-breach';if((int)$metrics['graph_orphans']>0)$alerts[]='graph-integrity-failure';return ['status'=>in_array('visibility-leak-blocker',$alerts,true)||in_array('graph-integrity-failure',$alerts,true)?'critical':($alerts===[]?'healthy':'degraded'),'alerts'=>$alerts,'metrics'=>$metrics,'runbook'=>$alerts===[]?null:'docs/operations/reconciliation-runbook.md'];
    }
}
