<?php

declare(strict_types=1);

namespace Sabri\File26\Operations;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Adapters\OwnerConnectorProbe;
use Sabri\File26\Jobs\RebuildWorker;
use Sabri\File26\Jobs\WordPressJobQueue;
use Sabri\File26\Jobs\WordPressLeaseLock;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Search\PersistentQueryService;
use Sabri\File26\Search\QueryCursorCodec;
use Sabri\File26\Search\WordPressActiveGenerationRepository;
use Sabri\File26\Storage\WordPressShadowStore;
use wpdb;

final class WordPressRuntime
{
    private readonly WordPressShadowStore $store;
    private readonly WordPressJobQueue $queue;
    private readonly WordPressWorkerScheduler $scheduler;
    private readonly WordPressDeadLetterOperations $deadLetters;
    private readonly PersistentQueryService $queryService;
    private readonly OwnerConnectorProbe $connectorProbe;

    public function __construct(
        wpdb $db,
        private readonly ConnectorRegistry $registry,
        string $cursorSecret
    ) {
        $this->store = new WordPressShadowStore($db);
        $this->queue = new WordPressJobQueue($db);
        $locks = new WordPressLeaseLock($db);
        $worker = new RebuildWorker($registry, $this->store, $this->queue, $locks);
        $this->scheduler = new WordPressWorkerScheduler(new WorkerLoop($worker), $this->queue);
        $this->deadLetters = new WordPressDeadLetterOperations($db);
        $this->queryService = new PersistentQueryService(
            new WordPressActiveGenerationRepository($db),
            new QueryCursorCodec($cursorSecret)
        );
        $this->connectorProbe = new OwnerConnectorProbe();
    }

    public function register(): void
    {
        $this->scheduler->register();
    }

    public function queryService(): PersistentQueryService
    {
        return $this->queryService;
    }

    /** @return array<string,mixed> */
    public function diagnostics(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return [
            'active_generation_id' => $this->store->activeGenerationId(),
            'queue' => $this->queue->stats(),
            'dead_letters' => $this->deadLetters->deadLetters(20),
            'scheduler' => $this->scheduler->diagnostics($now),
            'connectors_registered' => count($this->registry->all()),
        ];
    }

    /** @return array{job_id:string,status:string,replay_count:int,replayed_at:string} */
    public function replayDeadLetter(string $jobId, string $expectedErrorCode): array
    {
        return $this->deadLetters->replay(
            $jobId,
            $expectedErrorCode,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    }

    /** @return array{processed:int,idle:bool,status_counts:array<string,int>,last_error_code:?string} */
    public function runRealCron(int $maximumJobs = 20, int $batchLimit = 100): array
    {
        return $this->scheduler->runFromRealCron($maximumJobs, $batchLimit);
    }

    /** @return array{scheduled:bool,inspection:array{status:string,missed:bool,lag_seconds:int,pending_jobs:int}} */
    public function recoverMissedRun(): array
    {
        return $this->scheduler->recoverMissedRun(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    /** @return array{connector_key:string,pages:int,documents:int,tombstones:int,checksum:string,terminal:bool} */
    public function probeConnector(string $connectorKey, int $batchLimit = 50, int $maximumPages = 50): array
    {
        return $this->connectorProbe->probe(
            $this->registry->get($connectorKey),
            $batchLimit,
            $maximumPages
        );
    }
}
