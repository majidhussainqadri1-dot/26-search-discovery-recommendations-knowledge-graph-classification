<?php

declare(strict_types=1);

use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Search\InMemoryActiveGenerationRepository;
use Sabri\File26\Search\PersistentQuery;
use Sabri\File26\Search\PersistentQueryService;
use Sabri\File26\Search\QueryCursorCodec;
use Sabri\File26\Search\SearchDocumentHydrator;
use Sabri\File26\Support\InvariantViolation;

require_once __DIR__ . '/bootstrap.php';

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

$document = static function (
    string $domain,
    string $id,
    string $locale,
    string $title,
    string $excerpt,
    VisibilityEnvelope $visibility,
    string $state = 'published'
): SearchDocument {
    return new SearchDocument(
        $domain,
        $id,
        '1',
        $locale,
        $state,
        'https://sabrihomeopathy.com/search/' . rawurlencode($id),
        ['title' => $title, 'excerpt' => $excerpt, 'language' => $locale],
        $visibility,
        new DateTimeImmutable('2026-08-03T15:00:00+00:00')
    );
};

$codec = new QueryCursorCodec(str_repeat('s', 64));
$encoded = $codec->encode('generation-one', 20, str_repeat('a', 64));
$decoded = $codec->decode($encoded);
$assert($decoded['generation'] === 'generation-one', 'Cursor must preserve generation identity.');
$assert($decoded['offset'] === 20, 'Cursor must preserve result offset.');
$assert($decoded['fingerprint'] === str_repeat('a', 64), 'Cursor must preserve query fingerprint.');
$expectInvariant(static fn () => new QueryCursorCodec('short'), 'Short cursor secrets must fail closed.');
$expectInvariant(static fn () => $codec->decode(substr($encoded, 0, -1) . '0'), 'Tampered cursor signatures must be rejected.');

$public = new VisibilityEnvelope(true);
$restricted = new VisibilityEnvelope(false, ['view_advanced_lessons'], 'education.pro', 15, true);
$urdu = $document('file-21-publications', 'post-urdu', 'ur-PK', 'ہومیوپیتھی کامیاب کیس', 'تعلیمی مطالعہ', $public);
$englishOne = $document('file-21-publications', 'post-en-1', 'en-US', 'Homeopathy Education One', 'Foundation learning', $public);
$englishTwo = $document('file-10-videos', 'video-en-2', 'en-US', 'Homeopathy Education Two', 'Video lecture', $public);
$private = $document('file-05-lessons', 'lesson-private', 'en-US', 'Advanced Homeopathy Education', 'Private course', $restricted, 'restricted');

$payload = json_encode($urdu->toArray(), JSON_THROW_ON_ERROR);
$hydrated = (new SearchDocumentHydrator())->hydrate($payload);
$assert($hydrated->canonicalKey() === $urdu->canonicalKey(), 'Hydrator must preserve canonical identity.');
$assert($hydrated->fields()['title'] === 'ہومیوپیتھی کامیاب کیس', 'Hydrator must preserve Unicode search fields.');
$badPayload = json_encode(['canonical_domain' => 'file-21-publications'], JSON_THROW_ON_ERROR);
$expectInvariant(static fn () => (new SearchDocumentHydrator())->hydrate($badPayload), 'Incomplete stored payloads must be rejected.');

$repository = new InMemoryActiveGenerationRepository();
$repository->addGeneration('generation-one', 'active', [$urdu, $englishOne, $englishTwo, $private], true);
$service = new PersistentQueryService($repository, $codec);

$firstPage = $service->search(new PersistentQuery('homeopathy education', 1), AudienceContext::guest());
$assert($firstPage->generationId() === 'generation-one', 'Fresh queries must bind to the current active generation.');
$assert(count($firstPage->documents()) === 1, 'Query limit must bound the first page.');
$assert($firstPage->nextCursor() !== null, 'A multi-result query must return an opaque next cursor.');
$assert($firstPage->documents()[0]->visibility()->isPublic(), 'Guest pages must contain public documents only.');

$secondPage = $service->search(
    new PersistentQuery('homeopathy education', 1, $firstPage->nextCursor()),
    AudienceContext::guest()
);
$assert(count($secondPage->documents()) === 1, 'Signed cursor must retrieve the next result page.');
$assert($secondPage->documents()[0]->canonicalKey() !== $firstPage->documents()[0]->canonicalKey(), 'Cursor pages must not repeat the preceding result.');

$expectInvariant(
    static fn () => $service->search(new PersistentQuery('different query', 1, $firstPage->nextCursor()), AudienceContext::guest()),
    'Cursors must not be reusable across different queries.'
);

$guestPrivate = $service->search(new PersistentQuery('advanced private'), AudienceContext::guest());
$assert(count($guestPrivate->documents()) === 0, 'Guest queries must not reveal restricted results.');
$authorized = new AudienceContext(true, ['view_advanced_lessons'], ['education.pro'], 16, true);
$authorizedPrivate = $service->search(new PersistentQuery('advanced private'), $authorized);
$assert(count($authorizedPrivate->documents()) === 1, 'Complete audience assertions must unlock an eligible restricted result.');
$underAge = new AudienceContext(true, ['view_advanced_lessons'], ['education.pro'], 14, true);
$assert(count($service->search(new PersistentQuery('advanced private'), $underAge)->documents()) === 0, 'Age constraints must be enforced after persistent hydration.');

$urduOnly = $service->search(new PersistentQuery('کامیاب', 20, null, [], ['ur-PK']), AudienceContext::guest());
$assert(count($urduOnly->documents()) === 1, 'Urdu locale filtering must preserve the matching Urdu result.');
$wrongLocale = $service->search(new PersistentQuery('کامیاب', 20, null, [], ['en-US']), AudienceContext::guest());
$assert(count($wrongLocale->documents()) === 0, 'Locale filters must reject non-matching records.');
$videoOnly = $service->search(new PersistentQuery('homeopathy education', 20, null, ['file-10-videos']), AudienceContext::guest());
$assert(count($videoOnly->documents()) === 1, 'Domain filters must restrict results to approved canonical domains.');

$oldCursor = $firstPage->nextCursor();
$newDocument = $document('file-21-publications', 'post-new', 'en-US', 'Homeopathy Education New', 'New generation', $public);
$repository->addGeneration('generation-two', 'active', [$newDocument], true);
$continuedOldSnapshot = $service->search(new PersistentQuery('homeopathy education', 1, $oldCursor), AudienceContext::guest());
$assert($continuedOldSnapshot->generationId() === 'generation-one', 'Existing cursors must remain generation-bound after an active alias swap.');
$freshNewSnapshot = $service->search(new PersistentQuery('homeopathy education'), AudienceContext::guest());
$assert($freshNewSnapshot->generationId() === 'generation-two', 'New queries must use the newly active generation.');

$expectInvariant(static fn () => new PersistentQuery('', 20), 'Empty persistent queries must be rejected.');
$expectInvariant(static fn () => new PersistentQuery('valid', 51), 'Persistent query limits above 50 must be rejected.');
$expectInvariant(static fn () => new PersistentQuery('valid', 20, null, ['Bad Domain']), 'Malformed domain filters must be rejected.');
$expectInvariant(static fn () => new PersistentQuery("bad\0query"), 'Control characters must be rejected from persistent queries.');

$emptyRepository = new InMemoryActiveGenerationRepository();
$emptyService = new PersistentQueryService($emptyRepository, $codec);
$expectInvariant(static fn () => $emptyService->search(new PersistentQuery('homeopathy'), AudienceContext::guest()), 'Queries must fail closed when no active generation exists.');

if ($failures !== []) {
    fwrite(STDERR, "Phase 26D query tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Phase 26D query tests passed: %d assertions.\n", $assertions));
