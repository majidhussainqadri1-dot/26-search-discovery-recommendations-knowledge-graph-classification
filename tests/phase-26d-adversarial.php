<?php

declare(strict_types=1);

use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Search\PersistentQuery;
use Sabri\File26\Search\QueryCursorCodec;
use Sabri\File26\Search\SearchDocumentHydrator;
use Sabri\File26\Storage\SchemaUpgradeCoordinator;
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

$document = new SearchDocument(
    'file-21-publications',
    'post-strict-time',
    '1',
    'ur-PK',
    'published',
    'https://sabrihomeopathy.com/posts/post-strict-time',
    ['title' => 'وقت کی جانچ'],
    new VisibilityEnvelope(true),
    new DateTimeImmutable('2026-08-03T15:00:00+00:00')
);
$data = $document->toArray();
$data['last_source_event_at'] = 'tomorrow';
$expectInvariant(
    static fn () => (new SearchDocumentHydrator())->hydrate(json_encode($data, JSON_THROW_ON_ERROR)),
    'Relative stored timestamps must be rejected.'
);

$data = $document->toArray();
$data['last_source_event_at'] = '2026-08-03 15:00:00';
$expectInvariant(
    static fn () => (new SearchDocumentHydrator())->hydrate(json_encode($data, JSON_THROW_ON_ERROR)),
    'Non-ISO stored timestamps must be rejected.'
);

$reordered = $document->toArray();
$first = array_shift($reordered);
$reordered['canonical_domain'] = $first;
$expectInvariant(
    static fn () => (new SearchDocumentHydrator())->hydrate(json_encode($reordered, JSON_THROW_ON_ERROR)),
    'Reordered stored payload keys must fail the canonical payload shape.'
);

$extra = $document->toArray();
$extra['unexpected_private_field'] = 'must-not-load';
$expectInvariant(
    static fn () => (new SearchDocumentHydrator())->hydrate(json_encode($extra, JSON_THROW_ON_ERROR)),
    'Unexpected stored payload fields must be rejected.'
);

$expectInvariant(
    static fn () => new PersistentQuery('homeopathy', 20, null, [], ['en-US', 'en_US']),
    'Equivalent locale filters must not survive canonical normalization as duplicates.'
);
$expectInvariant(
    static fn () => new PersistentQuery('homeopathy', 20, null, [], ['EN-us', 'en-US']),
    'Locale filter uniqueness must be case-insensitive after normalization.'
);

$codec = new QueryCursorCodec(str_repeat('x', 64));
$expectInvariant(
    static fn () => $codec->encode('generation-one', 100001, str_repeat('a', 64)),
    'Query cursors must reject offsets above the bounded snapshot range.'
);
$expectInvariant(
    static fn () => $codec->encode('generation-one', 1, 'not-a-sha256'),
    'Query cursors must reject malformed fingerprints.'
);
$expectInvariant(
    static fn () => $codec->decode('not-a-valid-cursor'),
    'Malformed opaque cursor structure must fail closed.'
);

$assert(SchemaUpgradeCoordinator::supportsUpgradeFrom('0.3.0'), 'The immediate 0.3.0 to 0.4.0 upgrade path must be supported.');
$assert(! SchemaUpgradeCoordinator::supportsUpgradeFrom('0.2.0'), 'Skipping the 0.3.0 schema must require an explicit migration path.');
$assert(! SchemaUpgradeCoordinator::supportsUpgradeFrom(''), 'Unknown schema versions must fail closed.');

$cliSource = (string) file_get_contents(dirname(__DIR__) . '/src/Operations/WordPressCliAdapter.php');
$assert(
    ! str_contains($cliSource, 'static fn () => $this'),
    'WP-CLI callbacks must not use an unbound static closure with the runtime instance.'
);

$schedulerSource = (string) file_get_contents(dirname(__DIR__) . '/src/Operations/WordPressWorkerScheduler.php');
$assert(
    str_contains($schedulerSource, "add_action('admin_init', [\$this, 'checkMissedRun'])"),
    'Scheduler registration must include a throttled missed-run recovery check.'
);
$assert(
    str_contains($schedulerSource, 'for ($attempt = 0; $attempt < 100; ++$attempt)'),
    'Cron cleanup must have a finite defensive upper bound.'
);
$assert(
    str_contains($schedulerSource, 'if ($removed !== true)'),
    'Cron cleanup must stop when WordPress cannot remove an event.'
);

if ($failures !== []) {
    fwrite(STDERR, "Phase 26D adversarial tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Phase 26D adversarial tests passed: %d assertions.\n", $assertions));
