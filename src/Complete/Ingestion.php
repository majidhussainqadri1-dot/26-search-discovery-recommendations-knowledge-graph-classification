<?php

declare(strict_types=1);

namespace Sabri\File26\Ingestion;

use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class ChangeEvent
{
    public const ACTIONS = ['created', 'updated', 'deleted', 'restricted', 'suspended', 'corrected', 'restored'];

    /** @param array<string,mixed> $payload */
    public function __construct(
        private readonly string $eventId,
        private readonly string $connectorKey,
        private readonly int $sequence,
        private readonly string $canonicalKey,
        private readonly string $objectVersion,
        private readonly string $action,
        private readonly array $payload,
        private readonly string $payloadHash,
        private readonly DateTimeImmutable $occurredAt
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/', $eventId)
            || ! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $connectorKey)
            || $sequence < 1
            || trim($canonicalKey) === '' || strlen($canonicalKey) > 292
            || trim($objectVersion) === '' || strlen($objectVersion) > 100
            || ! in_array($action, self::ACTIONS, true)
            || ! preg_match('/^[a-f0-9]{64}$/', $payloadHash)) {
            throw new InvariantViolation('Change event metadata is invalid.');
        }
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($encoded) > 1000000 || ! hash_equals($payloadHash, hash('sha256', $encoded))) {
            throw new InvariantViolation('Change event payload integrity failed.');
        }
    }

    /** @param array<string,mixed> $payload */
    public static function create(string $connectorKey, int $sequence, string $canonicalKey, string $objectVersion, string $action, array $payload, DateTimeImmutable $occurredAt): self
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $encoded);
        $id = hash('sha256', implode("\0", [$connectorKey, (string) $sequence, $canonicalKey, $objectVersion, $action, $hash]));
        return new self($id, $connectorKey, $sequence, $canonicalKey, $objectVersion, $action, $payload, $hash, $occurredAt);
    }

    public function eventId(): string { return $this->eventId; }
    public function connectorKey(): string { return $this->connectorKey; }
    public function sequence(): int { return $this->sequence; }
    public function canonicalKey(): string { return $this->canonicalKey; }
    public function objectVersion(): string { return $this->objectVersion; }
    public function action(): string { return $this->action; }
    /** @return array<string,mixed> */ public function payload(): array { return $this->payload; }
    public function payloadHash(): string { return $this->payloadHash; }
    public function occurredAt(): DateTimeImmutable { return $this->occurredAt; }
}

interface ChangeEventLedgerInterface
{
    /** @return array{duplicate:bool,gap:bool,expected_sequence:int,status:string} */
    public function receive(ChangeEvent $event): array;
    public function markProcessed(string $eventId): void;
    public function markFailed(string $eventId, string $errorCode): void;
    public function lastSequence(string $connectorKey): int;
}

final class ChangeIngestionService
{
    /** @param callable(ChangeEvent):void $handler */
    public function __construct(private readonly ChangeEventLedgerInterface $ledger, private readonly mixed $handler)
    {
        if (! is_callable($handler)) { throw new InvariantViolation('Change ingestion handler must be callable.'); }
    }

    /** @return array{duplicate:bool,gap:bool,expected_sequence:int,status:string} */
    public function ingest(ChangeEvent $event): array
    {
        $receipt = $this->ledger->receive($event);
        if ($receipt['duplicate']) { return $receipt; }
        if ($receipt['gap']) {
            $this->ledger->markFailed($event->eventId(), 'sequence-gap-detected');
            throw new InvariantViolation('Change event sequence gap requires replay or rebuild before processing.');
        }
        try {
            ($this->handler)($event);
            $this->ledger->markProcessed($event->eventId());
            $receipt['status'] = 'processed';
            return $receipt;
        } catch (\Throwable $exception) {
            $this->ledger->markFailed($event->eventId(), 'change-handler-failed');
            throw $exception;
        }
    }
}

final class InMemoryChangeEventLedger implements ChangeEventLedgerInterface
{
    /** @var array<string,array{event:ChangeEvent,status:string,error:?string}> */
    private array $events = [];
    /** @var array<string,int> */
    private array $last = [];

    public function receive(ChangeEvent $event): array
    {
        if (isset($this->events[$event->eventId()])) {
            return ['duplicate' => true, 'gap' => false, 'expected_sequence' => $this->lastSequence($event->connectorKey()) + 1, 'status' => $this->events[$event->eventId()]['status']];
        }
        $expected = $this->lastSequence($event->connectorKey()) + 1;
        $gap = $event->sequence() > $expected;
        if ($event->sequence() < $expected) { throw new InvariantViolation('Out-of-order old change event is not a valid new event.'); }
        $this->events[$event->eventId()] = ['event' => $event, 'status' => $gap ? 'gap_detected' : 'received', 'error' => null];
        return ['duplicate' => false, 'gap' => $gap, 'expected_sequence' => $expected, 'status' => $gap ? 'gap_detected' : 'received'];
    }

    public function markProcessed(string $eventId): void
    {
        $record = $this->events[$eventId] ?? null;
        if (! is_array($record) || $record['status'] !== 'received') { throw new InvariantViolation('Only received change events may be processed.'); }
        $event = $record['event'];
        $this->events[$eventId]['status'] = 'processed';
        $this->last[$event->connectorKey()] = $event->sequence();
    }

    public function markFailed(string $eventId, string $errorCode): void
    {
        if (! isset($this->events[$eventId]) || ! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $errorCode)) { throw new InvariantViolation('Change-event failure transition is invalid.'); }
        $this->events[$eventId]['status'] = 'failed';
        $this->events[$eventId]['error'] = $errorCode;
    }

    public function lastSequence(string $connectorKey): int { return $this->last[$connectorKey] ?? 0; }
}

final class PurgeRecord
{
    public const STAGES = ['search', 'autocomplete', 'recommendations', 'graph', 'cache', 'click_denial'];

    /** @param array<string,bool> $stages */
    public function __construct(
        private readonly string $purgeId,
        private readonly string $canonicalKey,
        private readonly string $sourceVersion,
        private readonly array $stages,
        private readonly DateTimeImmutable $requestedAt,
        private readonly ?DateTimeImmutable $completedAt,
        private readonly ?string $errorCode,
        private readonly string $traceId
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/', $purgeId) || trim($canonicalKey) === '' || strlen($canonicalKey) > 292
            || trim($sourceVersion) === '' || strlen($sourceVersion) > 100 || ! preg_match('/^[a-f0-9]{32,64}$/', $traceId)) {
            throw new InvariantViolation('Purge record metadata is invalid.');
        }
        if (array_keys($stages) !== self::STAGES) { throw new InvariantViolation('Purge record stages are incomplete or unordered.'); }
        foreach ($stages as $complete) { if (! is_bool($complete)) { throw new InvariantViolation('Purge stage values must be booleans.'); } }
        if ($completedAt !== null && in_array(false, $stages, true)) { throw new InvariantViolation('Incomplete purge stages cannot be marked completed.'); }
        if ($errorCode !== null && ! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $errorCode)) { throw new InvariantViolation('Purge error code is invalid.'); }
    }

    public static function requested(string $canonicalKey, string $sourceVersion, DateTimeImmutable $at, string $traceId): self
    {
        $id = hash('sha256', implode("\0", [$canonicalKey, $sourceVersion, $at->format(DATE_ATOM), $traceId]));
        return new self($id, $canonicalKey, $sourceVersion, array_fill_keys(self::STAGES, false), $at, null, null, $traceId);
    }

    public function completeStage(string $stage, DateTimeImmutable $at): self
    {
        if (! in_array($stage, self::STAGES, true)) { throw new InvariantViolation('Unknown purge stage.'); }
        $stages = $this->stages;
        $stages[$stage] = true;
        $completedAt = in_array(false, $stages, true) ? null : $at;
        return new self($this->purgeId, $this->canonicalKey, $this->sourceVersion, $stages, $this->requestedAt, $completedAt, null, $this->traceId);
    }

    public function purgeId(): string { return $this->purgeId; }
    public function canonicalKey(): string { return $this->canonicalKey; }
    public function sourceVersion(): string { return $this->sourceVersion; }
    /** @return array<string,bool> */ public function stages(): array { return $this->stages; }
    public function requestedAt(): DateTimeImmutable { return $this->requestedAt; }
    public function completedAt(): ?DateTimeImmutable { return $this->completedAt; }
    public function traceId(): string { return $this->traceId; }
}

use DateTimeZone;
use wpdb;

final class WordPressChangeEventLedger implements ChangeEventLedgerInterface
{
    private readonly string $table;
    public function __construct(private readonly wpdb $db) { $this->table = $db->prefix . 's26_change_events'; }

    public function receive(ChangeEvent $event): array
    {
        $lockName = 'sabri_file26_event_' . substr(hash('sha256', $event->connectorKey()), 0, 32);
        $acquired = $this->db->get_var($this->db->prepare('SELECT GET_LOCK(%s, %d)', $lockName, 3));
        if ((string) $acquired !== '1') { throw new InvariantViolation('Change-event connector lock could not be acquired.'); }
        $this->db->query('START TRANSACTION');
        try {
            $existing = $this->db->get_row($this->db->prepare("SELECT status FROM {$this->table} WHERE event_id=%s FOR UPDATE", $event->eventId()), ARRAY_A);
            $expected = $this->lastSequenceForUpdate($event->connectorKey()) + 1;
            if (is_array($existing)) {
                $this->db->query('COMMIT');
                return ['duplicate' => true, 'gap' => false, 'expected_sequence' => $expected, 'status' => (string) $existing['status']];
            }
            if ($event->sequence() < $expected) { throw new InvariantViolation('Out-of-order old change event is not a valid new event.'); }
            $gap = $event->sequence() > $expected;
            $payload = json_encode($event->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            $written = $this->db->query($this->db->prepare(
                "INSERT INTO {$this->table} (event_id,connector_key,sequence_no,canonical_key,object_version,action_key,payload,payload_hash,occurred_at,status,error_code,received_at,processed_at) VALUES (%s,%s,%d,%s,%s,%s,%s,%s,%s,%s,NULL,%s,NULL)",
                $event->eventId(), $event->connectorKey(), $event->sequence(), $event->canonicalKey(), $event->objectVersion(), $event->action(), $payload, $event->payloadHash(), $event->occurredAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'), $gap ? 'gap_detected' : 'received', $now
            ));
            if ($written !== 1) { throw new InvariantViolation('Change event persistence failed.'); }
            $this->db->query('COMMIT');
            return ['duplicate' => false, 'gap' => $gap, 'expected_sequence' => $expected, 'status' => $gap ? 'gap_detected' : 'received'];
        } catch (\Throwable $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        } finally {
            $this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }

    public function markProcessed(string $eventId): void
    {
        $updated = $this->db->query($this->db->prepare("UPDATE {$this->table} SET status='processed',processed_at=%s,error_code=NULL WHERE event_id=%s AND status='received'", (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'), $eventId));
        if ($updated !== 1) { throw new InvariantViolation('Only received change events may be processed.'); }
    }

    public function markFailed(string $eventId, string $errorCode): void
    {
        if (! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $errorCode)) { throw new InvariantViolation('Invalid change-event error code.'); }
        $updated = $this->db->query($this->db->prepare("UPDATE {$this->table} SET status='failed',error_code=%s WHERE event_id=%s AND status IN ('received','gap_detected')", $errorCode, $eventId));
        if ($updated !== 1) { throw new InvariantViolation('Change-event failure transition failed.'); }
    }

    public function lastSequence(string $connectorKey): int
    {
        $value = $this->db->get_var($this->db->prepare("SELECT MAX(sequence_no) FROM {$this->table} WHERE connector_key=%s AND status='processed'", $connectorKey));
        return $value === null ? 0 : (int) $value;
    }

    private function lastSequenceForUpdate(string $connectorKey): int
    {
        $value = $this->db->get_var($this->db->prepare("SELECT sequence_no FROM {$this->table} WHERE connector_key=%s AND status='processed' ORDER BY sequence_no DESC LIMIT 1 FOR UPDATE", $connectorKey));
        return $value === null ? 0 : (int) $value;
    }
}

final class WordPressPurgeLedger
{
    private readonly string $table;
    public function __construct(private readonly wpdb $db) { $this->table = $db->prefix . 's26_purge_ledger'; }

    public function save(PurgeRecord $record): void
    {
        $stages = json_encode($record->stages(), JSON_THROW_ON_ERROR);
        $written = $this->db->query($this->db->prepare(
            "INSERT INTO {$this->table} (purge_id,canonical_key,source_version,stages_payload,requested_at,completed_at,error_code,trace_id,updated_at) VALUES (%s,%s,%s,%s,%s,%s,NULL,%s,%s) ON DUPLICATE KEY UPDATE stages_payload=VALUES(stages_payload),completed_at=VALUES(completed_at),error_code=NULL,updated_at=VALUES(updated_at)",
            $record->purgeId(), $record->canonicalKey(), $record->sourceVersion(), $stages, $this->utc($record->requestedAt()), $record->completedAt() === null ? null : $this->utc($record->completedAt()), $record->traceId(), (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u')
        ));
        if ($written === false) { throw new InvariantViolation('Purge ledger write failed.'); }
    }

    /** @return list<array<string,mixed>> */
    public function overdue(DateTimeImmutable $before, int $limit = 100): array
    {
        if ($limit < 1 || $limit > 500) { throw new InvariantViolation('Purge ledger read limit is invalid.'); }
        $rows = $this->db->get_results($this->db->prepare("SELECT purge_id,canonical_key,source_version,stages_payload,requested_at,error_code,trace_id FROM {$this->table} WHERE completed_at IS NULL AND requested_at < %s ORDER BY requested_at ASC LIMIT %d", $this->utc($before), $limit), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    private function utc(DateTimeImmutable $at): string { return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'); }
}
