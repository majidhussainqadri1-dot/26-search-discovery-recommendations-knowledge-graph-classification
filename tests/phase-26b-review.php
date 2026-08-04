<?php

declare(strict_types=1);

use Sabri\File26\Connectors\File21PublicationsConnector;
use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Contracts\SourceBatchProviderInterface;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Search\ShadowIndex;
use Sabri\File26\Support\InvariantViolation;

require_once __DIR__ . '/bootstrap.php';

final class ReviewSourceProvider implements SourceBatchProviderInterface
{
    /** @param array<string,mixed> $payload */
    public function __construct(private readonly array $payload)
    {
    }

    public function fetch(?string $cursor, int $limit): array
    {
        unset($cursor, $limit);

        return $this->payload;
    }

    public function health(): array
    {
        return ['healthy' => true];
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

$validRecord = static fn (array $overrides = []): array => array_replace_recursive([
    'object_id' => 'post-review-1',
    'object_version' => '1',
    'locale' => 'en-US',
    'state' => 'published',
    'canonical_url' => 'https://staging.sabrihomeopathy.com/posts/post-review-1',
    'fields' => [
        'title' => 'Review-safe publication',
        'excerpt' => 'Synthetic public connector record.',
    ],
    'source_event_at' => '2026-08-03T12:30:00+00:00',
], $overrides);

$payload = static fn (array $records, array $tombstones = [], ?string $nextCursor = null, bool $hasMore = false): array => [
    'records' => $records,
    'tombstones' => $tombstones,
    'next_cursor' => $nextCursor,
    'has_more' => $hasMore,
];

$connector = new File21PublicationsConnector(new ReviewSourceProvider($payload([$validRecord()])));
$batch = $connector->fetchBatch(null, 10);
$assert(count($batch->documents()) === 1, 'Approved Sabri subdomain destinations must be accepted.');

$expectInvariant(
    static fn () => new ConnectorBatch([], 'unexpected-terminal-cursor', false),
    'Terminal connector batches must reject ambiguous next cursors.'
);

$continuingBatch = new ConnectorBatch([], 'opaque-page-2', true);
$assert($continuingBatch->hasMore() && $continuingBatch->nextCursor() === 'opaque-page-2', 'Continuing batches must retain a bounded opaque cursor.');

$expectInvariant(
    static fn () => (new File21PublicationsConnector(new ReviewSourceProvider($payload([
        $validRecord(['object_id' => ['array-is-not-an-id']]),
    ]))))->fetchBatch(null, 10),
    'Owner object identity must reject non-string values instead of coercing them.'
);

$expectInvariant(
    static fn () => (new File21PublicationsConnector(new ReviewSourceProvider($payload([
        $validRecord(['source_event_at' => ['not-a-date']]),
    ]))))->fetchBatch(null, 10),
    'Owner source event time must reject non-string values.'
);

$expectInvariant(
    static fn () => (new File21PublicationsConnector(new ReviewSourceProvider($payload([
        $validRecord(['canonical_url' => 'https://sabrihomeopathy.com.evil.example/post-review-1']),
    ]))))->fetchBatch(null, 10),
    'Lookalike external hosts must fail the canonical-host gate.'
);

$expectInvariant(
    static fn () => (new File21PublicationsConnector(new ReviewSourceProvider($payload([], [[
        'object_id' => 'post-review-1',
        'last_object_version' => '1',
        'reason' => 123,
        'received_at' => '2026-08-03T12:31:00+00:00',
    ]]))))->fetchBatch(null, 10),
    'Tombstone reason must reject scalar coercion.'
);

$expectInvariant(
    static fn () => (new File21PublicationsConnector(new ReviewSourceProvider($payload([
        $validRecord(['object_id' => 'one']),
        $validRecord(['object_id' => 'two']),
    ]))))->fetchBatch(null, 1),
    'Owner providers must not exceed the caller batch limit.'
);

$expectInvariant(
    static fn () => (new File21PublicationsConnector(new ReviewSourceProvider([
        'records' => [$validRecord()],
        'next_cursor' => null,
        'has_more' => false,
        'undocumented' => 'reject-me',
    ])))->fetchBatch(null, 10),
    'Undocumented provider payload fields must fail closed.'
);

$restricted = new SearchDocument(
    'file-05-lessons',
    'restricted-review',
    '1',
    'en-US',
    'restricted',
    'https://sabrihomeopathy.com/learn/restricted-review',
    ['title' => 'Restricted Review Lesson'],
    new VisibilityEnvelope(false, ['view_advanced_lessons'], 'education.pro', 15, true),
    new DateTimeImmutable('2026-08-03T12:32:00+00:00')
);
$shadow = new ShadowIndex();
$shadow->applyBatch(new ConnectorBatch([$restricted], null, false));
$assert($shadow->query('restricted review', AudienceContext::guest()) === [], 'Anonymous queries must not reveal restricted-result existence.');
$assert($shadow->query('restricted review', new AudienceContext(true, ['view_advanced_lessons'], ['education.pro'], 16, true)) !== [], 'All current audience assertions must permit the restricted result.');
$assert($shadow->query('restricted review', new AudienceContext(true, ['view_advanced_lessons'], [], 16, true)) === [], 'Missing entitlement must fail closed at query time.');

$expectInvariant(
    static fn () => $shadow->reconcileExpectedKeys(['file-21-publications:duplicate', 'file-21-publications:duplicate']),
    'Reconciliation must reject duplicate expected keys.'
);

if ($failures !== []) {
    fwrite(STDERR, "Phase 26B review tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Phase 26B review tests passed: %d assertions.\n", $assertions));
