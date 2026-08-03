<?php

declare(strict_types=1);

namespace Sabri\File26\Jobs;

use DateTimeImmutable;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Storage\ShadowStoreInterface;
use Sabri\File26\Support\InvariantViolation;

final class RebuildCoordinator
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
        private readonly ShadowStoreInterface $store,
        private readonly JobQueueInterface $queue
    ) {
    }

    /** @param list<string> $connectorKeys */
    public function start(
        string $generationId,
        array $connectorKeys,
        string $mode,
        DateTimeImmutable $now
    ): void {
        if ($connectorKeys === [] || ! array_is_list($connectorKeys) || count($connectorKeys) !== count(array_unique($connectorKeys))) {
            throw new InvariantViolation('Rebuild connector keys must be a non-empty unique list.');
        }

        sort($connectorKeys);
        foreach ($connectorKeys as $connectorKey) {
            $this->registry->get($connectorKey);
        }

        $this->store->createGeneration($generationId, $mode, $now);

        foreach ($connectorKeys as $connectorKey) {
            $this->store->saveCheckpoint($generationId, $connectorKey, '<start>', false, $now);
            $this->queue->enqueue(RebuildJob::create($generationId, $connectorKey, null, $mode, 0, $now));
        }
    }

    /** @param list<string> $connectorKeys @return array{generation_id:string,state:string,documents:int,tombstones:int,checksum:string,active:bool} */
    public function validateAndPromote(
        string $generationId,
        array $connectorKeys,
        DateTimeImmutable $now
    ): array {
        $this->store->validateGeneration($generationId, $connectorKeys, $now);
        $this->store->promote($generationId, $now);

        return $this->store->generationSummary($generationId);
    }

    public function rollback(DateTimeImmutable $now): string
    {
        return $this->store->rollback($now);
    }
}
