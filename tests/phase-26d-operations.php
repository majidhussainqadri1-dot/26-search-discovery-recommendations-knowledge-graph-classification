<?php

declare(strict_types=1);

use Sabri\File26\Adapters\OwnerConnectorProbe;
use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Contracts\ConnectorInterface;
use Sabri\File26\Contracts\ConnectorManifest;
use Sabri\File26\Domain\IndexTombstone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Jobs\InMemoryJobQueue;
use Sabri\File26\Jobs\InMemoryLeaseLock;
use Sabri\File26\Jobs\RebuildCoordinator;
use Sabri\File26\Jobs\RebuildWorker;
use Sabri\File26\Operations\InMemoryDeadLetterOperations;
use Sabri\File26\Operations\MissedRunDetector;
use Sabri\File26\Operations\WorkerLoop;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Storage\InMemoryShadowStore;
use Sabri\File26\Support\InvariantViolation;

require_once __DIR__ . '/bootstrap.php';

final class Phase26DProbeConnector implements ConnectorInterface
{
    public function __construct(
        private readonly bool $repeatCursor = false,
        private readonly bool $crossOwner = false,
        private readonly bool $neverTerminal = false
    ) {
    }

    public function key(): string
    {
        return 'file-21-publications';
    }

    public function manifest(): ConnectorManifest
    {
        return new ConnectorManifest(
            '21',
            '1.0.0',
            ['publication'],
            ['C1'],
            'opaque-cursor',
            'owner-outbox',
            'full-rebuild-and-delta',
            'restrict-then-tombstone-then-purge',
            'bounded-owner-health-contract'
        );
    }

    public function fetchBatch(?string $cursor, int $limit): ConnectorBatch
    {
        unset($limit);
        $domain = $this->crossOwner ? 'file-10-videos' : $this->key();
        $suffix = $cursor === null ? 'one' : 'two';
        $document = new SearchDocument(
            $domain,
            'post-' . $suffix,
            '1',
            'en-US',
            'published',
            'https://sabrihomeopathy.com/posts/post-' . $suffix,
            ['title' => 'Probe publication ' . $suffix],
            new VisibilityEnvelope(true),
            new DateTimeImmutable('2026-08-03T15:00:00+00:00')
        );

        if ($this->neverTerminal) {
            return new ConnectorBatch([$document], 'cursor-' . $suffix, true);
        }

        if ($cursor === null) {
            return new ConnectorBatch([$document], 'cursor-two', true);
        }

        $next = $this->repeatCursor ? 'cursor-two' : null;
        $hasMore = $this->repeatCursor;
        $tombstone = new IndexTombstone(
            $domain,
            'post-retired',
            '1',
            'deleted',
            new DateTimeImmutable('2026-08-03T15:01:00+00:00')
        );

        return new ConnectorBatch([$document], $next, $hasMore, [$tombstone]);
    }

    public function health(): array
    {
        return ['healthy' => true, 'contract_version' => '1.0.0'];
    }
}

$failures = [];
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$failures, &$assertions): void {
    ++$assertions;
    if (! $condition) {
        $failures[] = $message;
    }
};
$expectInvariant = static function (callable $callback, string $message) use ($assert): void {
    try {
        $callback();
        $assert(false, $message);
    } catch (InvariantViolation) {
        $assert(true, $message);
    }
};

$detector = new MissedRunDetector();
$now = new DateTimeImmutable('2026-08-03T16:00:00+00:00');
$idle = $detector->inspect(null, $now, 0);
$assert($idle['status'] === 'idle' && ! $idle['missed'], 'No pending jobs must produce idle scheduler health.');
$never = $detector->inspect(null, $now, 2);
$assert($never['status'] === 'never-ran' && $never['missed'], 'Pending jobs without a prior run must require recovery.');
$onTime = $detector->inspect(new DateTimeImmutable('2026-08-03T15:50:00+00:00'), $now, 2);
$assert($onTime['status'] === 'on-time' && ! $onTime['missed'], 'Recent worker execution must remain on time.');
$overdue = $detector->inspect(new DateTimeImmutable('2026-08-03T15:00:00+00:00'), $now, 2);
$assert($overdue['status'] === 'overdue' && $overdue['lag_seconds'] === 3600, 'Old worker execution must be reported as overdue.');
$expectInvariant(
    static fn () => $detector->inspect(new DateTimeImmutable('2026-08-03T17:00:00+00:00'), $now, 1),
    'Future last-run timestamps must fail closed.'
);

$deadLetters = new InMemoryDeadLetterOperations();
$jobId = hash('sha256', 'dead-letter-one');
$deadLetters->seed(
    $jobId,
    'generation-build',
    'file-21-publications',
    5,
    'connector-execution-failed',
    new DateTimeImmutable('2026-08-03T15:00:00+00:00')
);
$assert(count($deadLetters->deadLetters()) === 1, 'Dead-letter diagnostics must list seeded failed work.');
$expectInvariant(
    static fn () => $deadLetters->replay($jobId, 'wrong-error', $now),
    'Dead-letter replay must require exact current error-code confirmation.'
);
$replay = $deadLetters->replay($jobId, 'connector-execution-failed', $now);
$assert($replay['status'] === 'queued' && $replay['replay_count'] === 1, 'Confirmed dead-letter replay must queue exactly one guarded replay.');
$expectInvariant(
    static fn () => $deadLetters->replay($jobId, 'connector-execution-failed', $now),
    'A job that is no longer dead-lettered must not be replayed again.'
);

$activeJobId = hash('sha256', 'dead-letter-active-generation');
$deadLetters->seed(
    $activeJobId,
    'generation-active',
    'file-21-publications',
    5,
    'connector-execution-failed',
    $now,
    'active'
);
$expectInvariant(
    static fn () => $deadLetters->replay($activeJobId, 'connector-execution-failed', $now),
    'Dead letters belonging to active generations must not be replayed.'
);

$completeJobId = hash('sha256', 'dead-letter-complete-checkpoint');
$deadLetters->seed(
    $completeJobId,
    'generation-build-two',
    'file-21-publications',
    5,
    'connector-execution-failed',
    $now,
    'building',
    true
);
$expectInvariant(
    static fn () => $deadLetters->replay($completeJobId, 'connector-execution-failed', $now),
    'Completed connector checkpoints must block stale dead-letter replay.'
);

$probe = new OwnerConnectorProbe();
$report = $probe->probe(new Phase26DProbeConnector(), 10, 10);
$assert($report['terminal'], 'Healthy owner connector probes must reach a terminal page.');
$assert($report['pages'] === 2, 'Owner connector probe must count bounded cursor pages.');
$assert($report['documents'] === 2 && $report['tombstones'] === 1, 'Owner connector probe must count documents and tombstones separately.');
$assert(preg_match('/^[a-f0-9]{64}$/', $report['checksum']) === 1, 'Owner connector probe must emit a deterministic checksum.');
$reportAgain = $probe->probe(new Phase26DProbeConnector(), 10, 10);
$assert($reportAgain['checksum'] === $report['checksum'], 'Repeated owner probes over stable pages must produce the same checksum.');
$expectInvariant(
    static fn () => $probe->probe(new Phase26DProbeConnector(true), 10, 10),
    'Repeated continuation cursors must fail the owner contract probe.'
);
$expectInvariant(
    static fn () => $probe->probe(new Phase26DProbeConnector(false, true), 10, 10),
    'Cross-owner canonical identities must fail the owner contract probe.'
);
$expectInvariant(
    static fn () => $probe->probe(new Phase26DProbeConnector(false, false, true), 10, 2),
    'Owner connector probes must fail when no terminal page appears within the bound.'
);

$registry = new ConnectorRegistry();
$registry->register(new Phase26DProbeConnector());
$store = new InMemoryShadowStore();
$queue = new InMemoryJobQueue();
$locks = new InMemoryLeaseLock();
$coordinator = new RebuildCoordinator($registry, $store, $queue);
$coordinator->start('generation-loop', ['file-21-publications'], 'full', $now);
$loop = new WorkerLoop(new RebuildWorker($registry, $store, $queue, $locks));
$loopResult = $loop->run($now, 5, 10);
$assert($loopResult['processed'] === 2, 'Worker loop must process both bounded connector pages.');
$assert($loopResult['idle'], 'Worker loop must stop after observing an idle queue.');
$checkpoint = $store->checkpoint('generation-loop', 'file-21-publications');
$assert(is_array($checkpoint) && $checkpoint['complete'], 'Worker loop must leave the connector checkpoint complete.');
$expectInvariant(static fn () => $loop->run($now, 51, 10), 'Worker loops above 50 jobs must be rejected.');
$expectInvariant(static fn () => $probe->probe(new Phase26DProbeConnector(), 201, 10), 'Owner probes above the batch ceiling must be rejected.');

if ($failures !== []) {
    fwrite(STDERR, "Phase 26D operations tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Phase 26D operations tests passed: %d assertions.\n", $assertions));
