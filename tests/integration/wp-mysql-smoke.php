<?php

declare(strict_types=1);

use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Jobs\RebuildJob;
use Sabri\File26\Jobs\WordPressJobQueue;
use Sabri\File26\Operations\WordPressDeadLetterOperations;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Search\PersistentQuery;
use Sabri\File26\Search\PersistentQueryService;
use Sabri\File26\Search\QueryCursorCodec;
use Sabri\File26\Search\WordPressActiveGenerationRepository;
use Sabri\File26\Storage\SchemaManager;
use Sabri\File26\Storage\WordPressShadowStore;

if (! defined('SABRI_FILE26_INTEGRATION_TESTS') || SABRI_FILE26_INTEGRATION_TESTS !== true) {
    throw new RuntimeException('Phase 26D integration smoke may run only in an explicitly isolated test installation.');
}

global $wpdb;
if (! $wpdb instanceof \wpdb) {
    throw new RuntimeException('WordPress database access is unavailable.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$prefix = $wpdb->prefix . 's26_';
$generation = 'integration-query-' . strtolower(bin2hex(random_bytes(6)));
$replayGeneration = 'integration-replay-' . strtolower(bin2hex(random_bytes(6)));
$connectorKey = 'file-21-publications';
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

try {
    $assert(
        (string) get_option('sabri_file26_schema_version', '') === SchemaManager::SCHEMA_VERSION,
        'Activated plugin must expose the current schema version.'
    );

    foreach (['generations', 'aliases', 'documents', 'tombstones', 'checkpoints', 'jobs', 'locks'] as $suffix) {
        $table = $prefix . $suffix;
        $actual = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        $assert($actual === $table, 'Required integration table is missing: ' . $suffix);
    }

    foreach (['replay_count', 'last_replayed_at'] as $column) {
        $actual = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$prefix}jobs LIKE %s", $column));
        $assert($actual === $column, 'Required jobs upgrade column is missing: ' . $column);
    }

    $store = new WordPressShadowStore($wpdb);
    $store->createGeneration($generation, 'full', $now);
    $store->saveCheckpoint($generation, $connectorKey, '<start>', false, $now);

    $document = new SearchDocument(
        $connectorKey,
        'integration-urdu-publication',
        '1',
        'ur-PK',
        'published',
        'https://sabrihomeopathy.com/integration/urdu-publication',
        [
            'title' => 'ہومیوپیتھی کامیاب کیس',
            'excerpt' => 'حقیقی ورڈپریس اور ماریا ڈی بی انضمامی جانچ',
            'language' => 'ur-PK',
        ],
        new VisibilityEnvelope(true),
        $now
    );
    $store->applyBatch($generation, $connectorKey, new ConnectorBatch([$document], null, false));
    $store->saveCheckpoint($generation, $connectorKey, null, true, $now);
    $validated = $store->validateGeneration($generation, [$connectorKey], $now);
    $assert($validated['state'] === 'validated', 'Persistent integration generation must validate.');
    $assert($validated['documents'] === 1, 'Persistent integration generation must contain one document.');
    $assert(preg_match('/^[a-f0-9]{64}$/', $validated['checksum']) === 1, 'Persistent integration checksum must be SHA-256.');

    $store->promote($generation, $now);
    $assert($store->activeGenerationId() === $generation, 'Promoted integration generation must become active.');

    $queryService = new PersistentQueryService(
        new WordPressActiveGenerationRepository($wpdb),
        new QueryCursorCodec(str_repeat('i', 64))
    );
    $page = $queryService->search(new PersistentQuery('کامیاب', 20), AudienceContext::guest());
    $assert($page->generationId() === $generation, 'Persistent query must read the active integration generation.');
    $assert(count($page->documents()) === 1, 'Persistent Urdu query must return the stored public document.');
    $assert($page->documents()[0]->canonicalKey() === $document->canonicalKey(), 'Persistent query must preserve canonical identity.');

    $store->createGeneration($replayGeneration, 'full', $now);
    $store->saveCheckpoint($replayGeneration, $connectorKey, '<start>', false, $now);
    $queue = new WordPressJobQueue($wpdb);
    $job = RebuildJob::create($replayGeneration, $connectorKey, null, 'full', 0, $now);
    $queue->enqueue($job);
    $claimed = $queue->claim($now);
    $assert($claimed instanceof RebuildJob, 'Persistent integration queue must claim an eligible job.');
    $queue->deadLetter($claimed, 'connector-execution-failed', $now);
    $assert($queue->stats()['dead_letter'] >= 1, 'Persistent integration queue must record dead-letter work.');

    $deadLetters = new WordPressDeadLetterOperations($wpdb);
    $replayed = $deadLetters->replay($claimed->jobId(), 'connector-execution-failed', $now);
    $assert($replayed['status'] === 'queued', 'Guarded persistent replay must return the dead-letter job to queued state.');
    $assert($replayed['replay_count'] === 1, 'Guarded persistent replay must increment the audit counter exactly once.');

    fwrite(STDOUT, sprintf("Phase 26D WordPress/MariaDB smoke passed: %d assertions.\n", $assertions));
} finally {
    $wpdb->query('START TRANSACTION');
    try {
        $wpdb->query($wpdb->prepare("DELETE FROM {$prefix}aliases WHERE generation_id IN (%s, %s)", $generation, $replayGeneration));
        foreach (['documents', 'tombstones', 'checkpoints', 'jobs'] as $suffix) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$prefix}{$suffix} WHERE generation_id IN (%s, %s)",
                $generation,
                $replayGeneration
            ));
        }
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$prefix}generations WHERE generation_id IN (%s, %s)",
            $generation,
            $replayGeneration
        ));
        $wpdb->query('COMMIT');
    } catch (Throwable $exception) {
        $wpdb->query('ROLLBACK');
        throw $exception;
    }
}
