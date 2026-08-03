<?php

declare(strict_types=1);

namespace Sabri\File26\Governance;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Query\QueryPlan;
use Sabri\File26\Query\SensitiveQueryClassifier;
use Sabri\File26\Support\InvariantViolation;

final class VersionedConfiguration
{
    public const STATES=['draft','validated','staged','active','rolled_back','retired'];
    public function __construct(private readonly string $policyKey,private readonly string $version,private readonly string $state,private readonly bool $highRisk,private readonly array $payload,private readonly string $payloadHash,private readonly string $author,private readonly array $approvers,private readonly DateTimeImmutable $updatedAt,private readonly ?string $previousVersion=null)
    {
        if(!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$policyKey)||!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/',$version)||!in_array($state,self::STATES,true)||!preg_match('/^[a-f0-9]{64}$/',$payloadHash)||!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$author)||!array_is_list($approvers)||count($approvers)>10||count($approvers)!==count(array_unique($approvers)))throw new InvariantViolation('Versioned configuration metadata is invalid.');
        $encoded=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!hash_equals($payloadHash,hash('sha256',$encoded))||strlen($encoded)>100000)throw new InvariantViolation('Versioned configuration payload integrity failed.');
        foreach($approvers as $approver)if(!is_string($approver)||!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$approver)||$approver===$author)throw new InvariantViolation('Configuration approvals require independent actor keys.');
        if($state==='active'&&$highRisk&&count($approvers)<2)throw new InvariantViolation('High-risk configuration activation requires two approvers.');
    }
    public static function draft(string $policyKey,string $version,bool $highRisk,array $payload,string $author,DateTimeImmutable $at):self{$encoded=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);return new self($policyKey,$version,'draft',$highRisk,$payload,hash('sha256',$encoded),$author,[],$at);}
    public function approve(string $actor,DateTimeImmutable $at):self{if($actor===$this->author)throw new InvariantViolation('Configuration author cannot self-approve.');$approvers=array_values(array_unique([...$this->approvers,$actor]));return new self($this->policyKey,$this->version,'validated',$this->highRisk,$this->payload,$this->payloadHash,$this->author,$approvers,$at,$this->previousVersion);}
    public function activate(?string $previousVersion,DateTimeImmutable $at):self{return new self($this->policyKey,$this->version,'active',$this->highRisk,$this->payload,$this->payloadHash,$this->author,$this->approvers,$at,$previousVersion);}
    public function rollback(DateTimeImmutable $at):self{if($this->state!=='active')throw new InvariantViolation('Only active configuration can be rolled back.');return new self($this->policyKey,$this->version,'rolled_back',$this->highRisk,$this->payload,$this->payloadHash,$this->author,$this->approvers,$at,$this->previousVersion);}
    public function policyKey():string{return $this->policyKey;} public function version():string{return $this->version;} public function state():string{return $this->state;} public function highRisk():bool{return $this->highRisk;} public function payload():array{return $this->payload;} public function payloadHash():string{return $this->payloadHash;} public function author():string{return $this->author;} public function approvers():array{return $this->approvers;} public function updatedAt():DateTimeImmutable{return $this->updatedAt;} public function previousVersion():?string{return $this->previousVersion;}
}

final class ConfigurationRegistry
{
    private array $records=[]; private array $active=[];
    public function put(VersionedConfiguration $configuration):void{$key=$configuration->policyKey();if(isset($this->records[$key][$configuration->version()]))throw new InvariantViolation('Configuration policy versions are immutable.');$this->records[$key][$configuration->version()]=$configuration;if($configuration->state()==='active')$this->activate($configuration);}
    public function activate(VersionedConfiguration $configuration):void{if($configuration->state()!=='active')throw new InvariantViolation('Only active configuration records may become current.');$key=$configuration->policyKey();$current=$this->active[$key]??null;if($configuration->previousVersion()!==$current)throw new InvariantViolation('Configuration activation previous-version check failed.');$this->records[$key][$configuration->version()]=$configuration;$this->active[$key]=$configuration->version();}
    public function current(string $policyKey):VersionedConfiguration{$version=$this->active[$policyKey]??null;$record=$version===null?null:($this->records[$policyKey][$version]??null);if(!$record instanceof VersionedConfiguration)throw new InvariantViolation('No active configuration exists for this policy.');return $record;}
    public function rollback(string $policyKey):VersionedConfiguration{$current=$this->current($policyKey);$previous=$current->previousVersion();if($previous===null)throw new InvariantViolation('Configuration has no rollback predecessor.');$record=$this->records[$policyKey][$previous]??null;if(!$record instanceof VersionedConfiguration)throw new InvariantViolation('Configuration rollback predecessor is missing.');$this->active[$policyKey]=$previous;return $record;}
}

final class DegradedMode
{
    public const MODES=['normal','keyword_only','partial_domains','non_personalized','read_only','unavailable'];
    public function __construct(private readonly string $mode,private readonly array $unavailableDomains,private readonly string $traceId,private readonly bool $accessBroadening=false){if(!in_array($mode,self::MODES,true)||!array_is_list($unavailableDomains)||count($unavailableDomains)>50||!preg_match('/^[a-f0-9]{32,64}$/',$traceId)||$accessBroadening)throw new InvariantViolation('Degraded mode is invalid or would broaden access.');}
    public function toArray():array{return ['mode'=>$this->mode,'partial'=>$this->mode!=='normal','unavailable_domains'=>$this->unavailableDomains,'trace_id'=>$this->traceId,'access_broadening'=>false];}
}

final class TelemetryAggregate
{
    public function __construct(private readonly string $metricKey,private readonly string $dimensionHash,private readonly array $dimensions,private readonly int $count,private readonly DateTimeImmutable $day){if(!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$metricKey)||!preg_match('/^[a-f0-9]{64}$/',$dimensionHash)||count($dimensions)>20||$count<1||$count>1000000000)throw new InvariantViolation('Telemetry aggregate is invalid or unbounded.');foreach($dimensions as $key=>$value){if(!is_string($key)||!preg_match('/^[a-z][a-z0-9._-]{1,63}$/',$key)||(!is_string($value)&&!is_int($value)&&!is_bool($value)&&$value!==null)||(is_string($value)&&strlen($value)>100))throw new InvariantViolation('Telemetry dimensions must be bounded non-sensitive scalars.');}}
    public function metricKey():string{return $this->metricKey;} public function dimensionHash():string{return $this->dimensionHash;} public function dimensions():array{return $this->dimensions;} public function count():int{return $this->count;} public function day():DateTimeImmutable{return $this->day;}
}

final class TelemetryRedactor
{
    public function __construct(private readonly string $secret){if(strlen($secret)<32)throw new InvariantViolation('Telemetry hashing secret is too weak.');}
    public function queryMetric(QueryPlan $plan,string $metricKey,array $dimensions=[]):TelemetryAggregate{$dimensions['sensitivity']=$plan->sensitivity();if($plan->sensitivity()===SensitiveQueryClassifier::PUBLIC)$dimensions['query_bucket']=substr(hash_hmac('sha256',$plan->normalizedQuery(),$this->secret),0,16);else unset($dimensions['query_bucket']);ksort($dimensions);$serialized=json_encode($dimensions,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);return new TelemetryAggregate($metricKey,hash_hmac('sha256',$serialized,$this->secret),$dimensions,1,new DateTimeImmutable('today',new DateTimeZone('UTC')));}
}
