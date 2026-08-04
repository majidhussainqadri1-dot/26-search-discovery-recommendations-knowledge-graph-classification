<?php

declare(strict_types=1);

use Sabri\File26\Connectors\File10VideosConnector;
use Sabri\File26\Connectors\File21PublicationsConnector;
use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Contracts\ConnectorInterface;
use Sabri\File26\Contracts\ConnectorManifest;
use Sabri\File26\Contracts\SourceBatchProviderInterface;
use Sabri\File26\Domain\IndexTombstone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Search\ShadowIndex;
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

final class ArraySourceProvider implements SourceBatchProviderInterface
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
        return [
            'healthy' => true,
            'contract_version' => '1.0.0',
            'private_endpoint' => 'must-not-be-published-by-registry',
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
    new DateTimeImmutable('2026-08-03T12:00:00+00:00')
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
        new DateTimeImmutable('2026-08-03T12:00:00+00:00')
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
    new DateTimeImmutable('2026-08-03T12:01:00+00:00'),
    new DateTimeImmutable('2026-11-01T00:00:00+00:00')
);
$assert($tombstone->canonicalKey() === 'file-21-publications:post-100', 'Tombstone must retain canonical identity.');

$expectException(
    static fn () => new VisibilityEnvelope(true, [], null, 15),
    'Age-gated records must not be marked public.'
);

$publicationProvider = new ArraySourceProvider([
    'records' => [
        [
            'object_id' => 'post-100',
            'object_version' => '7',
            'locale' => 'ur-PK',
            'state' => 'published',
            'canonical_url' => 'https://sabrihomeopathy.com/posts/post-100',
            'fields' => [
                'title' => 'کامیاب کیس',
                'excerpt' => 'ہومیوپیتھک تعلیمی مطالعہ',
                'topics' => ['homeopathy', 'education'],
                'language' => 'ur-PK',
                'content_type' => 'publication',
            ],
            'source_event_at' => '2026-08-03T12:00:00+00:00',
        ],
        [
            'object_id' => 'post-101',
            'object_version' => '3',
            'locale' => 'en-US',
            'state' => 'published',
            'canonical_url' => 'https://sabrihomeopathy.com/news/source-quality',
            'fields' => [
                'title' => 'Source Quality in Homeopathic Education',
                'excerpt' => 'Evidence-conscious educational publication.',
                'topics' => ['sources', 'education'],
                'language' => 'en-US',
                'content_type' => 'editorial-news',
            ],
            'source_event_at' => '2026-08-03T12:02:00+00:00',
        ],
    ],
    'tombstones' => [],
    'next_cursor' => null,
    'has_more' => false,
]);

$videoProvider = new ArraySourceProvider([
    'records' => [[
        'object_id' => 'video-200',
        'object_version' => '2',
        'locale' => 'en-US',
        'state' => 'published',
        'canonical_url' => 'https://sabrihomeopathy.com/video/video-200',
        'fields' => [
            'title' => 'Homeopathy Foundation Lecture',
            'description' => 'A captioned educational lecture.',
            'channel_name' => 'Learn Sabri Classical Homeopathy',
            'topics' => ['lecture', 'education'],
            'language' => 'en-US',
            'duration_seconds' => 900,
            'captions_available' => true,
            'media_type' => 'recorded-video',
        ],
        'source_event_at' => '2026-08-03T12:03:00+00:00',
    ]],
    'next_cursor' => null,
    'has_more' => false,
]);

$publicationConnector = new File21PublicationsConnector($publicationProvider);
$videoConnector = new File10VideosConnector($videoProvider);
$assert($publicationConnector->manifest()->ownerFile() === '21', 'File 21 connector must preserve canonical owner identity.');
$assert($videoConnector->manifest()->ownerFile() === '10', 'File 10 connector must preserve canonical owner identity.');

$publicationBatch = $publicationConnector->fetchBatch(null, 10);
$videoBatch = $videoConnector->fetchBatch(null, 10);
$assert(count($publicationBatch->documents()) === 2, 'File 21 connector must map two approved public records.');
$assert(count($videoBatch->documents()) === 1, 'File 10 connector must map one approved public video.');

$shadow = new ShadowIndex();
$shadow->applyBatch($publicationBatch);
$shadow->applyBatch($videoBatch);
$assert($shadow->counts()['documents'] === 3, 'Shadow index must contain all accepted public documents.');
$assert(count($shadow->query('کامیاب', AudienceContext::guest())) === 1, 'Urdu public query must find the eligible publication.');
$assert(count($shadow->query('foundation lecture', AudienceContext::guest())) === 1, 'English public query must find the eligible video.');
$assert($shadow->query('education', AudienceContext::guest(), 10)[0] instanceof SearchDocument, 'Shadow query must return derivative SearchDocument objects.');

$deletionProvider = new ArraySourceProvider([
    'records' => [],
    'tombstones' => [[
        'object_id' => 'post-100',
        'last_object_version' => '7',
        'reason' => 'deleted',
        'received_at' => '2026-08-03T12:10:00+00:00',
        'purge_after' => '2026-11-01T00:00:00+00:00',
    ]],
    'next_cursor' => null,
    'has_more' => false,
]);
$shadow->applyBatch((new File21PublicationsConnector($deletionProvider))->fetchBatch(null, 10));
$assert(! $shadow->hasDocument('file-21-publications:post-100'), 'Deletion tombstone must remove the active derivative document.');
$assert(count($shadow->query('کامیاب', AudienceContext::guest())) === 0, 'Deleted content must not leak through query results.');
$assert($shadow->counts()['tombstones'] === 1, 'Shadow index must retain deletion reconciliation evidence.');

$staleDocument = new SearchDocument(
    'file-21-publications',
    'post-100',
    '7',
    'ur-PK',
    'published',
    'https://sabrihomeopathy.com/posts/post-100',
    ['title' => 'کامیاب کیس'],
    new VisibilityEnvelope(true),
    new DateTimeImmutable('2026-08-03T12:05:00+00:00')
);
$shadow->applyBatch(new ConnectorBatch([$staleDocument], null, false));
$assert(! $shadow->hasDocument('file-21-publications:post-100'), 'A stale document must not resurrect after a newer tombstone.');

$newerCorrection = new SearchDocument(
    'file-21-publications',
    'post-100',
    '8',
    'ur-PK',
    'corrected',
    'https://sabrihomeopathy.com/posts/post-100',
    ['title' => 'کامیاب کیس کی مصحح اشاعت'],
    new VisibilityEnvelope(true),
    new DateTimeImmutable('2026-08-03T12:11:00+00:00')
);
$shadow->applyBatch(new ConnectorBatch([$newerCorrection], null, false));
$assert($shadow->hasDocument('file-21-publications:post-100'), 'A newer owner correction may supersede an older tombstone.');
$assert($shadow->counts()['tombstones'] === 0, 'Superseded tombstone must leave the active shadow projection.');

$restrictedDocument = new SearchDocument(
    'file-05-lessons',
    'lesson-private-1',
    '1',
    'en-US',
    'restricted',
    'https://sabrihomeopathy.com/learn/private-lesson',
    ['title' => 'Advanced Private Lesson'],
    new VisibilityEnvelope(false, ['view_advanced_lessons'], 'education.pro', 15, true),
    new DateTimeImmutable('2026-08-03T12:20:00+00:00')
);
$shadow->applyBatch(new ConnectorBatch([$restrictedDocument], null, false));
$assert(count($shadow->query('advanced private', AudienceContext::guest())) === 0, 'Guest query must not reveal restricted documents.');
$authorizedAudience = new AudienceContext(true, ['view_advanced_lessons'], ['education.pro'], 16, true);
$assert(count($shadow->query('advanced private', $authorizedAudience)) === 1, 'Complete audience assertions must unlock an eligible restricted result.');
$assert(count($shadow->query('advanced private', new AudienceContext(true, ['view_advanced_lessons'], ['education.pro'], 14, true))) === 0, 'Age policy must be enforced at query time.');
$assert(count($shadow->query('advanced private', new AudienceContext(true, ['view_advanced_lessons'], ['education.pro'], 16, false))) === 0, 'Guardian policy must be enforced at query time.');

$parity = $shadow->reconcileExpectedKeys([
    'file-21-publications:post-100',
    'file-21-publications:post-101',
    'file-10-videos:video-200',
    'file-05-lessons:missing-owner-record',
]);
$assert($parity['missing'] === ['file-05-lessons:missing-owner-record'], 'Parity report must identify missing owner records deterministically.');
$assert($parity['orphaned'] === ['file-05-lessons:lesson-private-1'], 'Parity report must identify orphaned shadow records deterministically.');

$expectException(
    static fn () => (new File21PublicationsConnector(new ArraySourceProvider([
        'records' => [[
            'object_id' => 'post-bad-field',
            'object_version' => '1',
            'locale' => 'en-US',
            'state' => 'published',
            'canonical_url' => 'https://sabrihomeopathy.com/posts/post-bad-field',
            'fields' => ['title' => 'Bad', 'patient_phone' => '+000000000'],
            'source_event_at' => '2026-08-03T12:00:00+00:00',
        ]],
        'next_cursor' => null,
        'has_more' => false,
    ])))->fetchBatch(null, 10),
    'Connector field allowlists must reject unexpected sensitive fields.'
);

$expectException(
    static fn () => (new File10VideosConnector(new ArraySourceProvider([
        'records' => [[
            'object_id' => 'video-external',
            'object_version' => '1',
            'locale' => 'en-US',
            'state' => 'published',
            'canonical_url' => 'https://example.com/video-external',
            'fields' => ['title' => 'External phishing destination'],
            'source_event_at' => '2026-08-03T12:00:00+00:00',
        ]],
        'next_cursor' => null,
        'has_more' => false,
    ])))->fetchBatch(null, 10),
    'Public connector destinations must remain on approved platform hosts.'
);

$expectException(
    static fn () => new AudienceContext(false, ['view_private_search']),
    'Anonymous audience context must not carry authenticated capabilities.'
);

$expectException(
    static fn () => new AudienceContext(true, ['duplicate_key', 'duplicate_key']),
    'Audience assertions must reject duplicates.'
);

$expectException(
    static fn () => new ConnectorBatch([$document], null, false, [$tombstone]),
    'A batch must not contain a document and tombstone for the same identity.'
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

fwrite(STDOUT, sprintf("Foundation and shadow-index tests passed: %d assertions.\n", $assertions));
