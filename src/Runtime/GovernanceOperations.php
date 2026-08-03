<?php

declare(strict_types=1);

namespace Sabri\File26\Governance;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class WordPressAuditLog
{
    private readonly string $table; public function __construct(private readonly wpdb $db){$this->table=$db->prefix.'s26_audit_log';}
    public function append(string $action,int $actorId,string $objectKey,string $reasonCode,?string $policyVersion,array $metadata=[]):string
    {
        if(!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$action)||$actorId<1||trim($objectKey)===''||strlen($objectKey)>292||!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$reasonCode)||($policyVersion!==null&&strlen($policyVersion)>32)||count($metadata)>30)throw new InvariantViolation('Audit record metadata is invalid.');
        $json=json_encode($metadata,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(strlen($json)>10000)throw new InvariantViolation('Audit metadata is too large.');$traceId=bin2hex(random_bytes(16));$auditId=hash('sha256',implode('|',[$action,(string)$actorId,$objectKey,$reasonCode,$traceId]));$written=$this->db->query($this->db->prepare("INSERT INTO {$this->table} (audit_id,actor_id,action_key,object_key,reason_code,policy_version,payload_hash,trace_id,created_at) VALUES (%s,%d,%s,%s,%s,%s,%s,%s,%s)",$auditId,$actorId,$action,$objectKey,$reasonCode,$policyVersion,hash('sha256',$json),$traceId,(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u')));if($written!==1)throw new InvariantViolation('Audit record persistence failed.');return $auditId;
    }
}

final class WordPressTelemetryStore
{
    private readonly string $table; public function __construct(private readonly wpdb $db){$this->table=$db->prefix.'s26_telemetry_daily';}
    public function increment(TelemetryAggregate $aggregate):void{$payload=json_encode($aggregate->dimensions(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$written=$this->db->query($this->db->prepare("INSERT INTO {$this->table} (metric_day,metric_key,dimension_hash,dimensions_payload,total,updated_at) VALUES (%s,%s,%s,%s,%d,%s) ON DUPLICATE KEY UPDATE total=total+VALUES(total),updated_at=VALUES(updated_at)",$aggregate->day()->format('Y-m-d'),$aggregate->metricKey(),$aggregate->dimensionHash(),$payload,$aggregate->count(),(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u')));if($written===false)throw new InvariantViolation('Telemetry aggregate write failed.');}
    public function purgeBefore(DateTimeImmutable $cutoff):int{$deleted=$this->db->query($this->db->prepare("DELETE FROM {$this->table} WHERE metric_day < %s",$cutoff->format('Y-m-d')));if($deleted===false)throw new InvariantViolation('Telemetry retention purge failed.');return(int)$deleted;}
    public function summary(int $days=30,int $limit=200):array{if($days<1||$days>400||$limit<1||$limit>1000)throw new InvariantViolation('Telemetry summary bounds are invalid.');$cutoff=(new DateTimeImmutable('today',new DateTimeZone('UTC')))->modify('-'.$days.' days')->format('Y-m-d');$rows=$this->db->get_results($this->db->prepare("SELECT metric_day,metric_key,dimensions_payload,total FROM {$this->table} WHERE metric_day >= %s ORDER BY metric_day DESC,total DESC LIMIT %d",$cutoff,$limit),ARRAY_A);return is_array($rows)?$rows:[];}
}

final class WordPressPolicyStore
{
    private readonly string $table; public function __construct(private readonly wpdb $db){$this->table=$db->prefix.'s26_policies';}
    public function save(VersionedConfiguration $configuration):void{$payload=json_encode($configuration->payload(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$approvers=json_encode($configuration->approvers(),JSON_THROW_ON_ERROR);$updated=$configuration->updatedAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');$effective=$configuration->state()==='active'?$updated:null;$written=$this->db->query($this->db->prepare("INSERT INTO {$this->table} (policy_key,version,state,high_risk,author_key,approvers_payload,previous_version,payload,payload_hash,effective_at,updated_at) VALUES (%s,%s,%s,%d,%s,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE state=VALUES(state),high_risk=VALUES(high_risk),approvers_payload=VALUES(approvers_payload),previous_version=VALUES(previous_version),payload=VALUES(payload),payload_hash=VALUES(payload_hash),effective_at=VALUES(effective_at),updated_at=VALUES(updated_at)",$configuration->policyKey(),$configuration->version(),$configuration->state(),$configuration->highRisk()?1:0,$configuration->author(),$approvers,$configuration->previousVersion(),$payload,$configuration->payloadHash(),$effective,$updated));if($written===false)throw new InvariantViolation('Policy configuration persistence failed.');}
    public function history(string $key,int $limit=50):array{if(!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$key)||$limit<1||$limit>200)throw new InvariantViolation('Policy history query is invalid.');$rows=$this->db->get_results($this->db->prepare("SELECT policy_key,version,state,high_risk,author_key,approvers_payload,previous_version,payload_hash,effective_at,updated_at FROM {$this->table} WHERE policy_key=%s ORDER BY updated_at DESC LIMIT %d",$key,$limit),ARRAY_A);return is_array($rows)?$rows:[];}
}
