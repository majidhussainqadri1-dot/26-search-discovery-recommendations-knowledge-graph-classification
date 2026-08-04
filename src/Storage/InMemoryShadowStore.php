<?php

declare(strict_types=1);

namespace Sabri\File26\Storage;

use DateTimeImmutable;
use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Domain\IndexTombstone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Support\InvariantViolation;

final class InMemoryShadowStore implements ShadowStoreInterface
{
    /**
     * @var array<string,array{
     *   mode:string,
     *   state:string,
     *   created_at:DateTimeImmutable,
     *   validated_at:?DateTimeImmutable,
     *   promoted_at:?DateTimeImmutable,
     *   previous_generation_id:?string,
     *   documents:array<string,SearchDocument>,
     *   tombstones:array<string,IndexTombstone>,
     *   checkpoints:array<string,array{cursor:?string,complete:bool,updated_at:DateTimeImmutable}>,
     *   checksum:string
     * }>
     */
    private array $generations = [];

    private ?string $activeGenerationId = null;

    public function createGeneration(string $generationId, string $mode, DateTimeImmutable $createdAt): void
    {
        $this->assertGenerationId($generationId);
        if (! in_array($mode, ['full', 'partial', 'delta'], true)) {
            throw new InvariantViolation('Generation mode must be full, partial or delta.');
        }

        if (isset($this->generations[$generationId])) {
            throw new InvariantViolation('Generation identifiers are immutable and may not be reused.');
        }

        $this->generations[$generationId] = [
            'mode' => $mode,
            'state' => 'building',
            'created_at' => $createdAt,
            'validated_at' => null,
            'promoted_at' => null,
            'previous_generation_id' => null,
            'documents' => [],
            'tombstones' => [],
            'checkpoints' => [],
            'checksum' => '',
        ];
    }

    public function applyBatch(string $generationId, string $connectorKey, ConnectorBatch $batch): void
    {
        $generation = &$this->generationForWrite($generationId);
        $this->assertConnectorKey($connectorKey);

        if ($generation['state'] !== 'building') {
            throw new InvariantViolation('Only a building generation may accept connector batches.');
        }
        if (! isset($generation['checkpoints'][$connectorKey])) {
            throw new InvariantViolation('A connector must be registered in the generation checkpoint set before writing.');
        }

        foreach ($batch->tombstones() as $tombstone) {
            $key = $tombstone->canonicalKey();
            $this->assertConnectorOwnsKey($connectorKey, $key);
            $existingDocument = $generation['documents'][$key] ?? null;

            if ($existingDocument !== null && $existingDocument->lastSourceEventAt() > $tombstone->receivedAt()) {
                continue;
            }

            unset($generation['documents'][$key]);
            $existingTombstone = $generation['tombstones'][$key] ?? null;
            if ($existingTombstone === null || $existingTombstone->receivedAt() <= $tombstone->receivedAt()) {
                $generation['tombstones'][$key] = $tombstone;
            }
        }

        foreach ($batch->documents() as $document) {
            $key = $document->canonicalKey();
            $this->assertConnectorOwnsKey($connectorKey, $key);
            $tombstone = $generation['tombstones'][$key] ?? null;

            if ($tombstone !== null && $tombstone->receivedAt() >= $document->lastSourceEventAt()) {
                continue;
            }

            $existingDocument = $generation['documents'][$key] ?? null;
            if ($existingDocument !== null && $existingDocument->lastSourceEventAt() > $document->lastSourceEventAt()) {
                continue;
            }

            unset($generation['tombstones'][$key]);
            $generation['documents'][$key] = $document;
        }
    }

    public function saveCheckpoint(
        string $generationId,
        string $connectorKey,
        ?string $cursor,
        bool $complete,
        DateTimeImmutable $updatedAt
    ): void {
        $generation = &$this->generationForWrite($generationId);
        $this->assertConnectorKey($connectorKey);

        if ($generation['state'] !== 'building') {
            throw new InvariantViolation('Checkpoints may be written only while a generation is building.');
        }

        if ($complete && $cursor !== null) {
            throw new InvariantViolation('A completed connector checkpoint must not retain a cursor.');
        }

        if (! $complete && ($cursor === null || trim($cursor) === '' || strlen($cursor) > 512)) {
            throw new InvariantViolation('An incomplete connector checkpoint requires a bounded cursor.');
        }

        $existing = $generation['checkpoints'][$connectorKey] ?? null;
        if ($existing !== null && $existing['updated_at'] > $updatedAt) {
            throw new InvariantViolation('A stale checkpoint may not overwrite a newer checkpoint.');
        }

        if ($existing !== null && $existing['complete'] && ! $complete) {
            throw new InvariantViolation('A completed checkpoint may not regress to incomplete.');
        }

        $generation['checkpoints'][$connectorKey] = [
            'cursor' => $cursor,
            'complete' => $complete,
            'updated_at' => $updatedAt,
        ];
    }

    public function checkpoint(string $generationId, string $connectorKey): ?array
    {
        $generation = $this->generation($generationId);
        $checkpoint = $generation['checkpoints'][$connectorKey] ?? null;

        if ($checkpoint === null) {
            return null;
        }

        return [
            'cursor' => $checkpoint['cursor'],
            'complete' => $checkpoint['complete'],
            'updated_at' => $checkpoint['updated_at']->format(DATE_ATOM),
        ];
    }

    public function validateGeneration(
        string $generationId,
        array $expectedConnectorKeys,
        DateTimeImmutable $validatedAt
    ): array {
        $generation = &$this->generationForWrite($generationId);
        if ($generation['state'] !== 'building') {
            throw new InvariantViolation('Only a building generation may be validated.');
        }

        $expectedConnectorKeys = $this->validateExpectedConnectorKeys($expectedConnectorKeys);
        $actualConnectorKeys = array_keys($generation['checkpoints']);
        sort($actualConnectorKeys);
        if ($actualConnectorKeys !== $expectedConnectorKeys) {
            throw new InvariantViolation('Validation connector set must exactly match the generation checkpoint set.');
        }

        foreach ($expectedConnectorKeys as $connectorKey) {
            $checkpoint = $generation['checkpoints'][$connectorKey] ?? null;
            if ($checkpoint === null || ! $checkpoint['complete']) {
                throw new InvariantViolation('Every expected connector must have a completed checkpoint before validation.');
            }
        }

        $generation['checksum'] = $this->calculateChecksum($generation['documents'], $generation['tombstones']);
        $generation['validated_at'] = $validatedAt;
        $generation['state'] = 'validated';

        return $this->generationSummary($generationId);
    }

    public function promote(string $generationId, DateTimeImmutable $promotedAt): void
    {
        $generation = &$this->generationForWrite($generationId);
        if ($generation['state'] !== 'validated') {
            throw new InvariantViolation('Only a validated generation may be promoted.');
        }

        if ($generation['checksum'] === '') {
            throw new InvariantViolation('A generation without a validation checksum may not be promoted.');
        }

        $previous = $this->activeGenerationId;
        if ($previous !== null && $previous !== $generationId) {
            $this->generations[$previous]['state'] = 'superseded';
        }

        $generation['previous_generation_id'] = $previous;
        $generation['promoted_at'] = $promotedAt;
        $generation['state'] = 'active';
        $this->activeGenerationId = $generationId;
    }

    public function rollback(DateTimeImmutable $rolledBackAt): string
    {
        unset($rolledBackAt);

        if ($this->activeGenerationId === null) {
            throw new InvariantViolation('No active generation exists to roll back.');
        }

        $active = &$this->generations[$this->activeGenerationId];
        $previous = $active['previous_generation_id'];
        if ($previous === null || ! isset($this->generations[$previous])) {
            throw new InvariantViolation('The active generation has no valid rollback predecessor.');
        }

        $active['state'] = 'rolled_back';
        $this->generations[$previous]['state'] = 'active';
        $this->activeGenerationId = $previous;

        return $previous;
    }

    public function activeGenerationId(): ?string
    {
        return $this->activeGenerationId;
    }

    public function generationSummary(string $generationId): array
    {
        $generation = $this->generation($generationId);

        return [
            'generation_id' => $generationId,
            'state' => $generation['state'],
            'documents' => count($generation['documents']),
            'tombstones' => count($generation['tombstones']),
            'checksum' => $generation['checksum'],
            'active' => $this->activeGenerationId === $generationId,
        ];
    }

    /** @return array<string,SearchDocument> */
    public function documents(string $generationId): array
    {
        return $this->generation($generationId)['documents'];
    }

    /**
     * @return array{
     *   mode:string,state:string,created_at:DateTimeImmutable,validated_at:?DateTimeImmutable,
     *   promoted_at:?DateTimeImmutable,previous_generation_id:?string,
     *   documents:array<string,SearchDocument>,tombstones:array<string,IndexTombstone>,
     *   checkpoints:array<string,array{cursor:?string,complete:bool,updated_at:DateTimeImmutable}>,checksum:string
     * }
     */
    private function generation(string $generationId): array
    {
        $this->assertGenerationId($generationId);
        if (! isset($this->generations[$generationId])) {
            throw new InvariantViolation('Unknown shadow generation.');
        }

        return $this->generations[$generationId];
    }

    /**
     * @return array{
     *   mode:string,state:string,created_at:DateTimeImmutable,validated_at:?DateTimeImmutable,
     *   promoted_at:?DateTimeImmutable,previous_generation_id:?string,
     *   documents:array<string,SearchDocument>,tombstones:array<string,IndexTombstone>,
     *   checkpoints:array<string,array{cursor:?string,complete:bool,updated_at:DateTimeImmutable}>,checksum:string
     * }
     */
    private function &generationForWrite(string $generationId): array
    {
        $this->assertGenerationId($generationId);
        if (! isset($this->generations[$generationId])) {
            throw new InvariantViolation('Unknown shadow generation.');
        }

        return $this->generations[$generationId];
    }

    /** @param list<string> $expectedConnectorKeys @return list<string> */
    private function validateExpectedConnectorKeys(array $expectedConnectorKeys): array
    {
        if ($expectedConnectorKeys === [] || count($expectedConnectorKeys) > 100) {
            throw new InvariantViolation('Expected connector sets must contain 1 to 100 keys.');
        }

        if (! array_is_list($expectedConnectorKeys) || count($expectedConnectorKeys) !== count(array_unique($expectedConnectorKeys))) {
            throw new InvariantViolation('Expected connector sets must be unique lists.');
        }

        foreach ($expectedConnectorKeys as $connectorKey) {
            $this->assertConnectorKey($connectorKey);
        }

        sort($expectedConnectorKeys);

        return $expectedConnectorKeys;
    }

    /** @param array<string,SearchDocument> $documents @param array<string,IndexTombstone> $tombstones */
    private function calculateChecksum(array $documents, array $tombstones): string
    {
        $rows = [];
        ksort($documents);
        ksort($tombstones);

        foreach ($documents as $key => $document) {
            $rows[] = 'D|' . $key . '|' . hash('sha256', json_encode($document->toArray(), JSON_THROW_ON_ERROR));
        }

        foreach ($tombstones as $key => $tombstone) {
            $rows[] = 'T|' . $key . '|' . hash('sha256', json_encode($tombstone->toArray(), JSON_THROW_ON_ERROR));
        }

        return hash('sha256', implode("\n", $rows));
    }

    private function assertGenerationId(string $generationId): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $generationId)) {
            throw new InvariantViolation('Generation IDs must be stable bounded lowercase keys.');
        }
    }

    private function assertConnectorKey(string $connectorKey): void
    {
        if (! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $connectorKey)) {
            throw new InvariantViolation('Connector keys must be stable bounded lowercase keys.');
        }
    }

    private function assertConnectorOwnsKey(string $connectorKey, string $canonicalKey): void
    {
        if (! str_starts_with($canonicalKey, $connectorKey . ':')) {
            throw new InvariantViolation('A connector may write only its own canonical domain.');
        }
    }
}
