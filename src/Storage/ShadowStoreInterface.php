<?php

declare(strict_types=1);

namespace Sabri\File26\Storage;

use DateTimeImmutable;
use Sabri\File26\Contracts\ConnectorBatch;

interface ShadowStoreInterface
{
    public function createGeneration(string $generationId, string $mode, DateTimeImmutable $createdAt): void;

    public function applyBatch(string $generationId, string $connectorKey, ConnectorBatch $batch): void;

    public function saveCheckpoint(
        string $generationId,
        string $connectorKey,
        ?string $cursor,
        bool $complete,
        DateTimeImmutable $updatedAt
    ): void;

    /** @return array{cursor:?string,complete:bool,updated_at:string}|null */
    public function checkpoint(string $generationId, string $connectorKey): ?array;

    /**
     * @param list<string> $expectedConnectorKeys
     * @return array{generation_id:string,state:string,documents:int,tombstones:int,checksum:string,active:bool}
     */
    public function validateGeneration(
        string $generationId,
        array $expectedConnectorKeys,
        DateTimeImmutable $validatedAt,
        ?GenerationValidationPolicy $policy = null
    ): array;

    public function promote(string $generationId, DateTimeImmutable $promotedAt): void;

    public function rollback(DateTimeImmutable $rolledBackAt): string;

    public function activeGenerationId(): ?string;

    /** @return array{generation_id:string,state:string,documents:int,tombstones:int,checksum:string,active:bool} */
    public function generationSummary(string $generationId): array;
}
