<?php

declare(strict_types=1);

use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Contracts\ConnectorInterface;
use Sabri\File26\Contracts\ConnectorManifest;
use Sabri\File26\Domain\IndexTombstone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Support\InvariantViolation;

require_once __DIR__ . '/bootstrap.php';

final class FoundationTestConnector implements ConnectorInterface
{
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
        unset($cursor, $limit);

        return new ConnectorBatch([], null, false);
    }

    public function health(): array
    {
        return [
            'healthy' => true,
            'contract_version' => '1.0.0',
            'secret' => 'must-not-leak',
            'status' => ['nested-value-must-not-leak'],
        ];
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

$registry = new ConnectorRegistry();
$registry->register(new FoundationTestConnector());
$assert($registry->has('file-21-publications'), 'Registered connector must be discoverable.');
$assert(count($registry->all()) === 1, 'Registry must contain exactly one connector.');

$summary = $registry->publicSummary();
$assert(! isset($summary['file-21-publications']['health']['secret']), 'Health summary must remove unapproved fields.');
$assert(! isset($summary['file-21-publications']['health']['status']), 'Health summary must reject nested values.');
$assert($summary['file-21-publications']['manifest']['owner_file'] === '21', 'Manifest must retain canonical owner file.');

$expectException(
    static fn () => $registry->register(new FoundationTestConnector()),
    'Duplicate connector keys must fail closed.'
);

$expectException(
    static fn () => new ConnectorManifest(
        '21',
        '1.0.0',
        ['publication'],
        ['C9'],
        'cursor',
        'outbox',
        'rebuild',
        'purge',
        'health'
    ),
    'Unknown privacy classes must be rejected.'
);

$publicVisibility = new VisibilityEnvelope(true);
$document = new SearchDocument(
    'file-21-publications',
    'post-100',
    '7',
    'ur-PK',
    'published',
    'https://sabrihomeopathy.com/posts/post-100',
    ['title' => 'کامیاب کیس', 'topics' => ['homeopathy', 'education']],
    $publicVisibility,
    new \DateTimeImmutable('2026-08-03T12:00:00+00:00')
);

$assert($document->canonicalKey() === 'file-21-publications:post-100', 'Canonical key must combine domain and object ID.');
$assert($document->toArray()['visibility']['public'] === true, 'Public visibility must serialize safely.');

$expectException(
    static fn () => new VisibilityEnvelope(true, ['view_private_search']),
    'Public documents must not carry hidden capability requirements.'
);

$expectException(
    static fn () => new SearchDocument(
        'file-21-publications',
        'post-101',
        '1',
        'en-US',
        'published',
        'http://sabrihomeopathy.com/posts/post-101',
        ['title' => 'Unsafe URL'],
        new VisibilityEnvelope(true),
        new \DateTimeImmutable('2026-08-03T12:00:00+00:00')
    ),
    'Canonical URLs must use HTTPS.'
);

$expectException(
    static fn () => new VisibilityEnvelope(false, [], 'Invalid Entitlement'),
    'Invalid entitlement keys must be rejected.'
);

$expectException(
    static fn () => new ConnectorBatch([], null, true),
    'Continuing batches require a next cursor.'
);

$tombstone = new IndexTombstone(
    'file-21-publications',
    'post-100',
    '7',
    'deleted',
    new \DateTimeImmutable('2026-08-03T12:01:00+00:00'),
    new \DateTimeImmutable('2026-11-01T00:00:00+00:00')
);
$assert($tombstone->canonicalKey() === 'file-21-publications:post-100', 'Tombstone must retain canonical identity.');

$expectException(
    static fn () => new VisibilityEnvelope(true, [], null, 15),
    'Age-gated records must not be marked public.'
);

$goldenQueries = json_decode(
    (string) file_get_contents(__DIR__ . '/fixtures/golden-queries.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$assert(is_array($goldenQueries) && count($goldenQueries) >= 4, 'Synthetic golden-query fixture must remain available.');

if ($failures !== []) {
    fwrite(STDERR, "Foundation tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Foundation tests passed: %d assertions.\n", $assertions));
