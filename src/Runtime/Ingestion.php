<?php

declare(strict_types=1);

namespace Sabri\File26\Ingestion;

use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class ChangeEvent
{
    public const TYPES = ['created','updated','published','unpublished','restricted','deleted','merged','corrected','retracted'];

    public function __construct(
        private readonly string $eventId,
        private readonly string $idempotencyKey,
        private readonly string $ownerKey,
        private readonly string $canonicalKey,
        private readonly string $objectVersion,
        private readonly string $eventType,
        private readonly DateTimeImmutable $occurredAt,
        private readonly int $sequenceNumber,
        private readonly ?array $payload = null
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/', $eventId)
            || trim($idempotencyKey) === '' || strlen($idempotencyKey) > 191
            || ! preg_match('/^[a-z][a-z0-9._-]{2,99}$/', $ownerKey)
            || trim($canonicalKey) === '' || strlen($canonicalKey) > 292
            || trim($objectVersion) === '' || strlen($objectVersion) > 64
            || ! in_array($eventType, self::TYPES, true)
            || $sequenceNumber < 1) {
            throw new InvariantViolation('Change event identity, ownership, version or sequence is invalid.');
        }
        if (! str_starts_with($canonicalKey, $ownerKey . ':')) {
            throw new InvariantViolation('Change event canonical key must remain in the owner namespace.');
        }
        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($encoded) > 100000) { throw new InvariantViolation('Change event payload is too large.'); }
        }
        if (in_array($eventType, ['created','updated','published','corrected'], true) && $payload === null) {
            throw new InvariantViolation('State-carrying change events require a payload.');
        }
        if (in_array($eventType, ['deleted','retracted','unpublished','restricted'], true) && $payload !== null) {
            throw new InvariantViolation('Purge and visibility change events must not retain source content payloads.');
        }
    }

    public static function create(string $idempotencyKey,string $ownerKey,string $canonicalKey,string $objectVersion,string $eventType,DateTimeImmutable $occurredAt,int $sequenceNumber,?array $payload=null):self
    {
        $eventId=hash('sha256',implode("\0",[$ownerKey,$canonicalKey,$objectVersion,$eventType,$occurredAt->format(DATE_ATOM),(string)$sequenceNumber]));
        return new self($eventId,$idempotencyKey,$ownerKey,$canonicalKey,$objectVersion,$eventType,$occurredAt,$sequenceNumber,$payload);
    }
    public function eventId():string{return $this->eventId;} public function idempotencyKey():string{return $this->idempotencyKey;} public function ownerKey():string{return $this->ownerKey;} public function canonicalKey():string{return $this->canonicalKey;} public function objectVersion():string{return $this->objectVersion;} public function eventType():string{return $this->eventType;} public function occurredAt():DateTimeImmutable{return $this->occurredAt;} public function sequenceNumber():int{return $this->sequenceNumber;} public function payload():?array{return $this->payload;}
}

interface ChangeEventLedgerInterface
{
    public function append(ChangeEvent $event): bool;
    public function nextPending(DateTimeImmutable $now): ?ChangeEvent;
    public function acknowledge(string $eventId, DateTimeImmutable $at): void;
    public function fail(string $eventId, string $errorCode, DateTimeImmutable $retryAt): void;
}

use DateTimeZone;
use Throwable;
use wpdb;

final class WordPressChangeEventLedger implements ChangeEventLedgerInterface
{
    private readonly string $eventsTable; private readonly string $sequencesTable;
    public function __construct(private readonly wpdb $db){$this->eventsTable=$db->prefix.'s26_change_events';$this->sequencesTable=$db->prefix.'s26_owner_sequences';}
    public function append(ChangeEvent $event):bool
    {
        $now=$this->utc(new DateTimeImmutable('now',new DateTimeZone('UTC')));$payload=$event->payload()===null?null:json_encode($event->payload(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$payloadHash=$payload===null?null:hash('sha256',$payload);
        $this->db->query('START TRANSACTION');try{
            $duplicate=$this->db->get_var($this->db->prepare("SELECT event_id FROM {$this->eventsTable} WHERE idempotency_key=%s FOR UPDATE",$event->idempotencyKey()));if(is_string($duplicate)&&$duplicate!==''){$this->db->query('COMMIT');return false;}
            $sequence=$this->db->get_row($this->db->prepare("SELECT last_sequence,last_event_id FROM {$this->sequencesTable} WHERE owner_key=%s FOR UPDATE",$event->ownerKey()),ARRAY_A);$last=is_array($sequence)?(int)$sequence['last_sequence']:0;if($event->sequenceNumber()!==$last+1)throw new InvariantViolation('Owner change event sequence contains a gap, duplicate or reordering.');
            $inserted=$this->db->query($this->db->prepare("INSERT INTO {$this->eventsTable} (event_id,idempotency_key,owner_key,canonical_key,object_version,event_type,occurred_at,sequence_number,payload,payload_hash,status,attempts,error_code,processed_at,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,%s,%s,%d,%s,%s,'pending',0,NULL,NULL,%s,%s)",$event->eventId(),$event->idempotencyKey(),$event->ownerKey(),$event->canonicalKey(),$event->objectVersion(),$event->eventType(),$this->utc($event->occurredAt()),$event->sequenceNumber(),$payload,$payloadHash,$now,$now));if($inserted!==1)throw new InvariantViolation('Change event persistence failed.');
            $advanced=$this->db->query($this->db->prepare("INSERT INTO {$this->sequencesTable} (owner_key,last_sequence,last_event_id,updated_at) VALUES (%s,%d,%s,%s) ON DUPLICATE KEY UPDATE last_sequence=VALUES(last_sequence),last_event_id=VALUES(last_event_id),updated_at=VALUES(updated_at)",$event->ownerKey(),$event->sequenceNumber(),$event->eventId(),$now));if($advanced===false)throw new InvariantViolation('Owner sequence advancement failed.');$this->db->query('COMMIT');return true;
        }catch(Throwable $exception){$this->db->query('ROLLBACK');throw $exception;}
    }
    public function nextPending(DateTimeImmutable $now):?ChangeEvent
    {
        $this->db->query('START TRANSACTION');try{$row=$this->db->get_row($this->db->prepare("SELECT * FROM {$this->eventsTable} WHERE status IN ('pending','retry') AND updated_at<=%s ORDER BY created_at,event_id LIMIT 1 FOR UPDATE",$this->utc($now)),ARRAY_A);if(!is_array($row)){$this->db->query('COMMIT');return null;}$updated=$this->db->query($this->db->prepare("UPDATE {$this->eventsTable} SET status='processing',attempts=attempts+1,updated_at=%s WHERE event_id=%s AND status IN ('pending','retry')",$this->utc($now),(string)$row['event_id']));if($updated!==1)throw new InvariantViolation('Change event claim failed.');$this->db->query('COMMIT');return $this->hydrate($row);}catch(Throwable $exception){$this->db->query('ROLLBACK');throw $exception;}
    }
    public function acknowledge(string $eventId,DateTimeImmutable $at):void{$updated=$this->db->query($this->db->prepare("UPDATE {$this->eventsTable} SET status='completed',processed_at=%s,error_code=NULL,updated_at=%s WHERE event_id=%s AND status='processing'",$this->utc($at),$this->utc($at),$eventId));if($updated!==1)throw new InvariantViolation('Processing change event acknowledgement failed.');}
    public function fail(string $eventId,string $errorCode,DateTimeImmutable $retryAt):void{if(!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$errorCode))throw new InvariantViolation('Change event error code is invalid.');$attempts=$this->db->get_var($this->db->prepare("SELECT attempts FROM {$this->eventsTable} WHERE event_id=%s AND status='processing'",$eventId));if($attempts===null)throw new InvariantViolation('Processing change event was not found.');$status=(int)$attempts>=10?'failed':'retry';$updated=$this->db->query($this->db->prepare("UPDATE {$this->eventsTable} SET status=%s,error_code=%s,updated_at=%s WHERE event_id=%s AND status='processing'",$status,$errorCode,$this->utc($retryAt),$eventId));if($updated!==1)throw new InvariantViolation('Change event failure transition failed.');}
    private function hydrate(array $row):ChangeEvent{$payload=null;if($row['payload']!==null){$encoded=(string)$row['payload'];if(!hash_equals((string)$row['payload_hash'],hash('sha256',$encoded)))throw new InvariantViolation('Change event payload integrity failed.');$payload=json_decode($encoded,true,64,JSON_THROW_ON_ERROR);if(!is_array($payload))throw new InvariantViolation('Change event payload is not an object.');}return new ChangeEvent((string)$row['event_id'],(string)$row['idempotency_key'],(string)$row['owner_key'],(string)$row['canonical_key'],(string)$row['object_version'],(string)$row['event_type'],new DateTimeImmutable((string)$row['occurred_at'],new DateTimeZone('UTC')),(int)$row['sequence_number'],$payload);}
    private function utc(DateTimeImmutable $at):string{return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
}

final class PurgeRecord
{
    public function __construct(private readonly string $purgeId,private readonly string $ownerKey,private readonly string $canonicalKey,private readonly string $objectVersion,private readonly string $reasonCode,private readonly DateTimeImmutable $requestedAt,private readonly string $traceId,private readonly ?DateTimeImmutable $completedAt=null,private readonly ?DateTimeImmutable $verifiedAbsentAt=null)
    {if(!preg_match('/^[a-f0-9]{64}$/',$purgeId)||!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$ownerKey)||trim($canonicalKey)===''||strlen($canonicalKey)>292||trim($objectVersion)===''||strlen($objectVersion)>64||!preg_match('/^[a-z][a-z0-9._-]{2,99}$/',$reasonCode)||!preg_match('/^[a-f0-9]{32}$/',$traceId))throw new InvariantViolation('Purge record is invalid.');}
    public static function requested(string $ownerKey,string $canonicalKey,string $objectVersion,string $reasonCode,DateTimeImmutable $at):self{$traceId=bin2hex(random_bytes(16));$purgeId=hash('sha256',implode("\0",[$ownerKey,$canonicalKey,$objectVersion,$reasonCode,$at->format(DATE_ATOM),$traceId]));return new self($purgeId,$ownerKey,$canonicalKey,$objectVersion,$reasonCode,$at,$traceId);}
    public function purgeId():string{return $this->purgeId;}public function ownerKey():string{return $this->ownerKey;}public function canonicalKey():string{return $this->canonicalKey;}public function objectVersion():string{return $this->objectVersion;}public function reasonCode():string{return $this->reasonCode;}public function requestedAt():DateTimeImmutable{return $this->requestedAt;}public function traceId():string{return $this->traceId;}public function completedAt():?DateTimeImmutable{return $this->completedAt;}public function verifiedAbsentAt():?DateTimeImmutable{return $this->verifiedAbsentAt;}
}

final class WordPressPurgeLedger
{
    private readonly string $table;public function __construct(private readonly wpdb $db){$this->table=$db->prefix.'s26_purge_ledger';}
    public function request(PurgeRecord $record):void{$inserted=$this->db->query($this->db->prepare("INSERT IGNORE INTO {$this->table} (purge_id,owner_key,canonical_key,object_version,reason_code,requested_at,completed_at,verified_absent_at,trace_id) VALUES (%s,%s,%s,%s,%s,%s,NULL,NULL,%s)",$record->purgeId(),$record->ownerKey(),$record->canonicalKey(),$record->objectVersion(),$record->reasonCode(),$this->utc($record->requestedAt()),$record->traceId()));if($inserted===false)throw new InvariantViolation('Purge ledger write failed.');}
    public function complete(string $purgeId,DateTimeImmutable $at):void{$updated=$this->db->query($this->db->prepare("UPDATE {$this->table} SET completed_at=%s WHERE purge_id=%s AND completed_at IS NULL",$this->utc($at),$purgeId));if($updated!==1)throw new InvariantViolation('Purge ledger completion failed.');}
    public function verifyAbsent(string $purgeId,DateTimeImmutable $at):void{$updated=$this->db->query($this->db->prepare("UPDATE {$this->table} SET verified_absent_at=%s WHERE purge_id=%s AND completed_at IS NOT NULL AND verified_absent_at IS NULL",$this->utc($at),$purgeId));if($updated!==1)throw new InvariantViolation('Purge absence verification failed.');}
    public function overdue(DateTimeImmutable $now,int $maximumLagSeconds=900):array{if($maximumLagSeconds<60||$maximumLagSeconds>86400)throw new InvariantViolation('Purge SLO is outside safe bounds.');$cutoff=$now->modify('-'.$maximumLagSeconds.' seconds');$rows=$this->db->get_results($this->db->prepare("SELECT purge_id,owner_key,canonical_key,object_version,reason_code,requested_at,completed_at,verified_absent_at,trace_id FROM {$this->table} WHERE verified_absent_at IS NULL AND requested_at<%s ORDER BY requested_at LIMIT 500",$this->utc($cutoff)),ARRAY_A);return is_array($rows)?$rows:[];}
    private function utc(DateTimeImmutable $at):string{return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
}
