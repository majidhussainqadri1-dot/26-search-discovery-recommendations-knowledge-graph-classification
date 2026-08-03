<?php

declare(strict_types=1);

use Sabri\File26\Connectors\File21PublicationsConnector;
use Sabri\File26\Contracts\SourceBatchProviderInterface;
use Sabri\File26\Jobs\InMemoryJobQueue;
use Sabri\File26\Jobs\InMemoryLeaseLock;
use Sabri\File26\Jobs\RebuildCoordinator;
use Sabri\File26\Jobs\RebuildJob;
use Sabri\File26\Jobs\RebuildWorker;
use Sabri\File26\Jobs\RetryPolicy;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Storage\GenerationValidationPolicy;
use Sabri\File26\Storage\InMemoryShadowStore;
use Sabri\File26\Support\InvariantViolation;

require_once __DIR__ . '/bootstrap.php';

final class Phase26CAdversarialProvider implements SourceBatchProviderInterface
{
    public int $calls = 0;

    /** @param array<string,mixed> $page */
    public function __construct(private readonly array $page)
    {
    }

    public function fetch(?string $cursor, int $limit): array
    {
        unset($cursor, $limit);
        ++$this->calls;

        return $this->page;
    }

    public function health(): array
    {
        return ['healthy' => true, 'contract_version' => '1.0.0'];
    }
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

$now = new DateTimeImmutable('2026-08-03T15:00:00+00:00');
$emptyProvider = new Phase26CAdversarialProvider([
    'records' => [],
    'tombstones' => [],
    'next_cursor' => null,
    'has_more' => false,
]);
$registry = new ConnectorRegistry();
$registry->register(new File21PublicationsConnector($emptyProvider));
$store = new InMemoryShadowStore();
$queue = new InMemoryJobQueue();
$locks = new InMemoryLeaseLock();
$coordinator = new RebuildCoordinator($registry, $store, $queue);
$worker = new RebuildWorker($registry, $store, $queue, $locks);

$coordinator->start('gen-empty', ['file-21-publications'], 'full', $now);
$assert($worker->runOne($now)['status'] === 'completed', 'An empty owner page may complete ingestion but must not imply promotion eligibility.');
$expectException(
    static fn () => $coordinator->validateAndPromote('gen-empty', ['file-21-publications'], $now),
    'Default validation policy must block an unexpectedly empty generation.'
);
$assert($store->activeGenerationId() === null, 'Rejected empty generation must not change the active alias.');

$expectException(
    static fn () => new GenerationValidationPolicy(-1),
    'Negative minimum document policies must fail closed.'
);
$expectException(
    static fn () => new GenerationValidationPolicy(0, 10, 1.1),
    'Divergence ratio above one must fail closed.'
);
$expectException(
    static fn () => new GenerationValidationPolicy(0, null, 0.1, -1),
    'Negative tombstone ceilings must fail closed.'
);

$allowEmpty = new GenerationValidationPolicy(0, 0, 0.0, 0);
$promotedEmpty = $coordinator->validateAndPromote('gen-empty', ['file-21-publications'], $now->modify('+1 second'), $allowEmpty);
$assert($promotedEmpty['active'] === true, 'An explicitly approved zero-record policy may promote a genuinely empty owner domain.');

$unknownStore = new InMemoryShadowStore();
$unknownCoordinator = new RebuildCoordinator($registry, $unknownStore, new InMemoryJobQueue());
$expectException(
    static fn () => $unknownCoordinator->start('gen-unknown', ['file-21-publications', 'unknown-connector'], 'full', $now),
    'All connector identities must resolve before generation creation.'
);
$expectException(
    static fn () => $unknownStore->generationSummary('gen-unknown'),
    'Failed connector preflight must not leave a partial generation.'
);
$expectException(
    static fn () => $unknownCoordinator->start('gen-duplicate', ['file-21-publications', 'file-21-publications'], 'full', $now),
    'Duplicate connector sets must fail before persistent mutation.'
);

$missingCheckpointStore = new InMemoryShadowStore();
$missingCheckpointStore->createGeneration('gen-missing-checkpoint', 'full', $now);
$missingCheckpointQueue = new InMemoryJobQueue();
$missingJob = RebuildJob::create('gen-missing-checkpoint', 'file-21-publications', null, 'full', 0, $now);
$missingCheckpointQueue->enqueue($missingJob);
$missingWorker = new RebuildWorker($registry, $missingCheckpointStore, $missingCheckpointQueue, new InMemoryLeaseLock());
$missingResult = $missingWorker->runOne($now);
$assert($missingResult['status'] === 'dead-letter' && $missingResult['error_code'] === 'checkpoint-missing', 'Missing checkpoint jobs must enter dead letter without connector execution.');
$assert($emptyProvider->calls === 1, 'Checkpoint failure must be detected before another owner-provider call.');

$staleStore = new InMemoryShadowStore();
$staleStore->createGeneration('gen-stale', 'full', $now);
$staleStore->saveCheckpoint('gen-stale', 'file-21-publications', 'page-2', false, $now);
$staleQueue = new InMemoryJobQueue();
$staleQueue->enqueue(RebuildJob::create('gen-stale', 'file-21-publications', null, 'full', 0, $now));
$staleWorker = new RebuildWorker($registry, $staleStore, $staleQueue, new InMemoryLeaseLock());
$assert($staleWorker->runOne($now)['status'] === 'stale-skipped', 'A job behind the durable checkpoint must be acknowledged without replay.');
$assert($emptyProvider->calls === 1, 'Stale jobs must not call the owner provider.');

$idempotentQueue = new InMemoryJobQueue();
$idempotentJob = RebuildJob::create('gen-idempotent', 'file-21-publications', null, 'full', 0, $now);
$idempotentQueue->enqueue($idempotentJob);
$idempotentQueue->enqueue($idempotentJob);
$assert($idempotentQueue->stats()['queued'] === 1, 'Deterministic duplicate enqueue must remain one queue record.');

$lock = new InMemoryLeaseLock();
$tokenA = hash('sha256', 'token-a');
$tokenB = hash('sha256', 'token-b');
$assert($lock->acquire('file26:test-lock', $tokenA, $now, 10), 'First valid lease acquisition must succeed.');
$assert(! $lock->acquire('file26:test-lock', $tokenB, $now->modify('+5 seconds'), 10), 'A live lease must reject a competing token.');
$assert($lock->acquire('file26:test-lock', $tokenB, $now->modify('+11 seconds'), 10), 'An expired lease may be safely taken over.');
$lock->release('file26:test-lock', $tokenA);
$assert($lock->renew('file26:test-lock', $tokenB, $now->modify('+12 seconds'), 10), 'A stale token release must not remove the current lease owner.');

$expectException(
    static fn () => new RetryPolicy([60, 30]),
    'Retry delays must not decrease.'
);
$expectException(
    static fn () => new RetryPolicy([604801]),
    'Retry delays beyond the bounded ceiling must fail closed.'
);

$expectException(
    static fn () => $coordinator->rollback($now->modify('+2 seconds')),
    'An active generation without a predecessor must not fabricate a rollback target.'
);

if ($failures !== []) {
    fwrite(STDERR, "Phase 26C adversarial tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Phase 26C adversarial tests passed: %d assertions.\n", $assertions));
