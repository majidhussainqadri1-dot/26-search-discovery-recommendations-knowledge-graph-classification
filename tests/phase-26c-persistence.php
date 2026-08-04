<?php

declare(strict_types=1);

use Sabri\File26\Connectors\File21PublicationsConnector;
use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Contracts\SourceBatchProviderInterface;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Jobs\InMemoryJobQueue;
use Sabri\File26\Jobs\InMemoryLeaseLock;
use Sabri\File26\Jobs\RebuildCoordinator;
use Sabri\File26\Jobs\RebuildWorker;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Storage\InMemoryShadowStore;
use Sabri\File26\Support\InvariantViolation;

require_once __DIR__ . '/bootstrap.php';

final class Phase26CScriptedProvider implements SourceBatchProviderInterface
{
    /** @var array<string,array<string,mixed>> */
    public array $pages = [];
    public int $failuresRemaining = 0;

    public function fetch(?string $cursor, int $limit): array
    {
        unset($limit);
        if ($this->failuresRemaining > 0) {
            --$this->failuresRemaining;
            throw new RuntimeException('synthetic provider failure');
        }

        $key = $cursor ?? '<start>';
        if (! isset($this->pages[$key])) {
            throw new RuntimeException('missing scripted page');
        }

        return $this->pages[$key];
    }

    public function health(): array
    {
        return ['healthy' => true, 'contract_version' => '1.0.0'];
    }
}

/** @return array<string,mixed> */
function phase26cPublication(string $id, string $title, string $eventAt): array
{
    return [
        'object_id' => $id,
        'object_version' => '1',
        'locale' => 'en-US',
        'state' => 'published',
        'canonical_url' => 'https://sabrihomeopathy.com/posts/' . $id,
        'fields' => [
            'title' => $title,
            'excerpt' => 'Public educational content.',
            'topics' => ['education'],
            'language' => 'en-US',
            'content_type' => 'publication',
        ],
        'source_event_at' => $eventAt,
    ];
}

/** @var list<string> $failures */
$failures = [];
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$failures, &$assertions): void {
    ++$assertions;
    if (! $condition) {
        $failures[] = $message;
    }
};
$expectException = static function (callable $callback, string $message) use (&$assert): void {
    try {
        $callback();
        $assert(false, $message);
    } catch (InvariantViolation) {
        $assert(true, $message);
    }
};

$provider = new Phase26CScriptedProvider();
$provider->pages = [
    '<start>' => [
        'records' => [phase26cPublication('post-c-1', 'Persistent Shadow One', '2026-08-03T13:00:00+00:00')],
        'tombstones' => [],
        'next_cursor' => 'page-2',
        'has_more' => true,
    ],
    'page-2' => [
        'records' => [phase26cPublication('post-c-2', 'Persistent Shadow Two', '2026-08-03T13:01:00+00:00')],
        'tombstones' => [],
        'next_cursor' => null,
        'has_more' => false,
    ],
];

$registry = new ConnectorRegistry();
$registry->register(new File21PublicationsConnector($provider));
$store = new InMemoryShadowStore();
$queue = new InMemoryJobQueue();
$locks = new InMemoryLeaseLock();
$coordinator = new RebuildCoordinator($registry, $store, $queue);
$worker = new RebuildWorker($registry, $store, $queue, $locks);
$start = new DateTimeImmutable('2026-08-03T13:10:00+00:00');

$coordinator->start('gen-001', ['file-21-publications'], 'full', $start);
$assert($queue->stats()['queued'] === 1, 'Starting a generation must enqueue one initial connector job.');
$assert($store->activeGenerationId() === null, 'A building generation must not become active.');
$assert($store->checkpoint('gen-001', 'file-21-publications')['cursor'] === '<start>', 'Initial checkpoint must explicitly record the start boundary.');

$first = $worker->runOne($start, 1);
$assert($first['status'] === 'checkpointed', 'A continuing owner page must create a durable checkpoint.');
$assert($store->checkpoint('gen-001', 'file-21-publications')['cursor'] === 'page-2', 'Checkpoint must persist the opaque next cursor.');
$assert($store->generationSummary('gen-001')['documents'] === 1, 'First rebuild slice must persist its accepted document.');

$second = $worker->runOne($start->modify('+1 second'), 1);
$assert($second['status'] === 'completed', 'Terminal owner page must complete the connector checkpoint.');
$assert($store->checkpoint('gen-001', 'file-21-publications')['complete'] === true, 'Terminal page must mark the connector complete.');
$assert($store->generationSummary('gen-001')['documents'] === 2, 'All bounded rebuild slices must accumulate in one generation.');

$promoted = $coordinator->validateAndPromote('gen-001', ['file-21-publications'], $start->modify('+2 seconds'));
$assert($promoted['active'] === true && $promoted['state'] === 'active', 'Validated generation must promote atomically.');
$assert(strlen($promoted['checksum']) === 64, 'Validated generation must carry a deterministic SHA-256 checksum.');
$assert($store->activeGenerationId() === 'gen-001', 'Active alias must point to the promoted generation.');

$expectException(
    static fn () => $store->createGeneration('gen-001', 'full', $start),
    'Generation identifiers must be immutable and non-reusable.'
);

$store->createGeneration('gen-incomplete', 'full', $start);
$store->saveCheckpoint('gen-incomplete', 'file-21-publications', '<start>', false, $start);
$expectException(
    static fn () => $store->validateGeneration('gen-incomplete', ['file-21-publications'], $start),
    'Incomplete connector checkpoints must block validation.'
);
$expectException(
    static fn () => $store->promote('gen-incomplete', $start),
    'Unvalidated generations must not be promoted.'
);

$provider->pages = [
    '<start>' => [
        'records' => [phase26cPublication('post-c-3', 'Retry Safe Generation', '2026-08-03T13:20:00+00:00')],
        'tombstones' => [],
        'next_cursor' => null,
        'has_more' => false,
    ],
];
$provider->failuresRemaining = 1;
$coordinator->start('gen-002', ['file-21-publications'], 'full', $start->modify('+10 seconds'));
$retry = $worker->runOne($start->modify('+10 seconds'));
$assert($retry['status'] === 'retry', 'Transient provider failure must enter bounded retry rather than corrupting the generation.');
$assert($store->activeGenerationId() === 'gen-001', 'Failed candidate generation must leave the active alias unchanged.');
$assert($worker->runOne($start->modify('+30 seconds'))['status'] === 'idle', 'Retry must not run before its scheduled availability.');
$retrySuccess = $worker->runOne($start->modify('+70 seconds'));
$assert($retrySuccess['status'] === 'completed', 'Retry must resume from the same checkpoint and complete idempotently.');
$coordinator->validateAndPromote('gen-002', ['file-21-publications'], $start->modify('+71 seconds'));
$assert($store->activeGenerationId() === 'gen-002', 'A validated replacement must atomically supersede the former generation.');
$assert($store->generationSummary('gen-001')['state'] === 'superseded', 'Former active generation must remain as rollback evidence.');
$assert($coordinator->rollback($start->modify('+72 seconds')) === 'gen-001', 'Rollback must restore the recorded predecessor.');
$assert($store->activeGenerationId() === 'gen-001', 'Rollback must restore the former active alias.');

$provider->failuresRemaining = 0;
$coordinator->start('gen-lock', ['file-21-publications'], 'full', $start->modify('+80 seconds'));
$externalToken = hash('sha256', 'external-lock');
$locks->acquire('file26:rebuild:gen-lock:file-21-publications', $externalToken, $start->modify('+80 seconds'), 600);
$locked = $worker->runOne($start->modify('+80 seconds'));
$assert($locked['status'] === 'rescheduled' && $locked['error_code'] === 'lease-busy', 'Competing worker lease must prevent duplicate connector execution.');
$locks->release('file26:rebuild:gen-lock:file-21-publications', $externalToken);
$assert($worker->runOne($start->modify('+96 seconds'))['status'] === 'completed', 'Lease-rescheduled work must remain executable without incrementing failure attempts.');

$store->createGeneration('gen-checkpoint', 'full', $start);
$store->saveCheckpoint('gen-checkpoint', 'file-21-publications', 'page-2', false, $start->modify('+5 seconds'));
$expectException(
    static fn () => $store->saveCheckpoint('gen-checkpoint', 'file-21-publications', 'page-1', false, $start),
    'Stale checkpoint writes must not overwrite newer progress.'
);
$store->saveCheckpoint('gen-checkpoint', 'file-21-publications', null, true, $start->modify('+6 seconds'));
$expectException(
    static fn () => $store->saveCheckpoint('gen-checkpoint', 'file-21-publications', 'page-3', false, $start->modify('+7 seconds')),
    'Completed checkpoints must not regress to incomplete.'
);

$foreignDocument = new SearchDocument(
    'file-10-videos',
    'video-foreign',
    '1',
    'en-US',
    'published',
    'https://sabrihomeopathy.com/video/video-foreign',
    ['title' => 'Foreign Domain'],
    new VisibilityEnvelope(true),
    new DateTimeImmutable('2026-08-03T13:30:00+00:00')
);
$store->createGeneration('gen-foreign', 'full', $start);
$store->saveCheckpoint('gen-foreign', 'file-21-publications', '<start>', false, $start);
$expectException(
    static fn () => $store->applyBatch('gen-foreign', 'file-21-publications', new ConnectorBatch([$foreignDocument], null, false)),
    'Persistent generation storage must reject cross-owner canonical writes.'
);

$provider->failuresRemaining = 99;
$coordinator->start('gen-dead', ['file-21-publications'], 'full', $start->modify('+100 seconds'));
$attemptTimes = [100, 160, 460, 2260, 9460, 52660];
$lastStatus = null;
foreach ($attemptTimes as $seconds) {
    $lastStatus = $worker->runOne($start->modify('+' . $seconds . ' seconds'))['status'];
}
$assert($lastStatus === 'dead-letter', 'Exhausted retries must end in dead letter.');
$assert($queue->stats()['dead_letter'] === 1, 'Dead-letter queue must preserve failed work for operator reconciliation.');
$assert($store->activeGenerationId() === 'gen-001', 'Dead-letter failure must never alter the active generation alias.');

$storeA = new InMemoryShadowStore();
$storeB = new InMemoryShadowStore();
$docA = new SearchDocument(
    'file-21-publications', 'a', '1', 'en-US', 'published', 'https://sabrihomeopathy.com/posts/a',
    ['title' => 'A'], new VisibilityEnvelope(true), new DateTimeImmutable('2026-08-03T14:00:00+00:00')
);
$docB = new SearchDocument(
    'file-21-publications', 'b', '1', 'en-US', 'published', 'https://sabrihomeopathy.com/posts/b',
    ['title' => 'B'], new VisibilityEnvelope(true), new DateTimeImmutable('2026-08-03T14:01:00+00:00')
);
foreach ([[$storeA, [$docA, $docB]], [$storeB, [$docB, $docA]]] as [$targetStore, $documents]) {
    $targetStore->createGeneration('gen-checksum', 'full', $start);
    $targetStore->saveCheckpoint('gen-checksum', 'file-21-publications', '<start>', false, $start);
    $targetStore->applyBatch('gen-checksum', 'file-21-publications', new ConnectorBatch($documents, null, false));
    $targetStore->saveCheckpoint('gen-checksum', 'file-21-publications', null, true, $start);
    $targetStore->validateGeneration('gen-checksum', ['file-21-publications'], $start);
}
$assert(
    $storeA->generationSummary('gen-checksum')['checksum'] === $storeB->generationSummary('gen-checksum')['checksum'],
    'Generation checksum must be deterministic regardless of ingestion order.'
);

if ($failures !== []) {
    fwrite(STDERR, "Phase 26C persistence tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Phase 26C persistence tests passed: %d assertions.\n", $assertions));
