<?php

use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Ingestion\ChangeEvent;
use Sabri\File26\Ingestion\PurgeRecord;
use Sabri\File26\Ingestion\WordPressChangeEventLedger;
use Sabri\File26\Ingestion\WordPressPurgeLedger;
use Sabri\File26\Jobs\RebuildJob;
use Sabri\File26\Jobs\WordPressJobQueue;
use Sabri\File26\KnowledgeGraph\GraphEdge;
use Sabri\File26\KnowledgeGraph\WordPressGraphStore;
use Sabri\File26\Operations\WordPressDeadLetterOperations;
use Sabri\File26\Recommendations\FeedbackEvent;
use Sabri\File26\Recommendations\WordPressFeedbackStore;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Search\PersistentQuery;
use Sabri\File26\Search\PersistentQueryService;
use Sabri\File26\Search\QueryCursorCodec;
use Sabri\File26\Search\WordPressActiveGenerationRepository;
use Sabri\File26\Storage\SchemaManager;
use Sabri\File26\Storage\WordPressShadowStore;
use Sabri\File26\Taxonomy\TaxonomyTerm;
use Sabri\File26\Taxonomy\WordPressTaxonomyStore;

if (! defined('SABRI_FILE26_INTEGRATION_TESTS') || SABRI_FILE26_INTEGRATION_TESTS !== true) {
    throw new RuntimeException('File 26 integration smoke may run only in an explicitly isolated test installation.');
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
$random = strtolower(bin2hex(random_bytes(6)));
$generation = 'integration-query-' . $random;
$replayGeneration = 'integration-replay-' . $random;
$connectorKey = 'file-21-publications';
$termId = 'topic.integration-' . $random;
$ownerKey = 'itest' . $random;
$canonicalEventKey = $ownerKey . ':item-' . $random;
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$feedbackActor = hash('sha256', 'integration-actor-' . $random);
$feedbackId = hash('sha256', 'integration-feedback-' . $random);
$purgeId = null;
$edgeId = null;

try {
    $assert(defined('SABRI_FILE26_VERSION') && SABRI_FILE26_VERSION === '1.0.0', 'Plugin runtime version must be 1.0.0.');
    $assert(defined('SABRI_FILE26_RUNTIME_STAGE') && SABRI_FILE26_RUNTIME_STAGE === 'complete-runtime', 'Plugin runtime stage must be complete-runtime.');
    $assert(SchemaManager::SCHEMA_VERSION === '1.0.0', 'Schema code version must be 1.0.0.');
    $assert(
        (string) get_option('sabri_file26_schema_version', '') === SchemaManager::SCHEMA_VERSION,
        'Activated plugin must expose the current schema version.'
    );

    $suffixes = SchemaManager::tableSuffixes();
    $assert(count($suffixes) === 19, 'Complete runtime must declare nineteen derivative tables.');
    foreach ($suffixes as $suffix) {
        $table = $prefix . $suffix;
        $actual = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        $assert($actual === $table, 'Required integration table is missing: ' . $suffix);
    }

    foreach (['replay_count', 'last_replayed_at'] as $column) {
        $actual = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$prefix}jobs LIKE %s", $column));
        $assert($actual === $column, 'Required jobs column is missing: ' . $column);
    }
    foreach (['idempotency_key', 'sequence_number', 'payload_hash', 'status'] as $column) {
        $actual = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$prefix}change_events LIKE %s", $column));
        $assert($actual === $column, 'Required change-event column is missing: ' . $column);
    }

    $store = new WordPressShadowStore($wpdb);
    $store->createGeneration($generation, 'full', $now);
    $store->saveCheckpoint($generation, $connectorKey, '<start>', false, $now);

    $document = new SearchDocument(
        $connectorKey,
        'integration-urdu-publication-' . $random,
        '1',
        'ur-PK',
        'published',
        'https://sabrihomeopathy.com/integration/urdu-publication-' . $random,
        [
            'title' => 'ہومیوپیتھی کامیاب کیس',
            'excerpt' => 'حقیقی ورڈپریس اور ماریا ڈی بی انضمامی جانچ',
            'language' => 'ur-PK',
            'content_type' => 'publication',
            'creator_id' => 'integration-doctor',
            'topics' => ['ہومیوپیتھی', 'کامیاب کیس'],
            'authority_score' => 90,
            'quality_score' => 90,
        ],
        VisibilityEnvelope::public(),
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

    $taxonomy = new WordPressTaxonomyStore($wpdb);
    $term = new TaxonomyTerm(
        $termId,
        1,
        ['en' => 'Integration Topic', 'ur' => 'انضمامی موضوع'],
        ['en' => ['integration test'], 'ur' => []],
        [],
        [],
        'Isolated WordPress and MariaDB integration topic.',
        'file26-curated',
        'active',
        null,
        $now
    );
    $taxonomy->save($term);
    $storedTerm = $taxonomy->get($termId);
    $assert($storedTerm->termId() === $termId && $storedTerm->version() === 1, 'Taxonomy term must round-trip through MariaDB.');

    $graph = new WordPressGraphStore($wpdb);
    $edge = GraphEdge::create(
        'taxonomy:' . $termId,
        $document->canonicalKey(),
        'topic-related',
        'file26-curated',
        '1.0.0',
        'https://sabrihomeopathy.com/integration/evidence-' . $random
    );
    $edgeId = $edge->edgeId();
    $graph->save($edge);
    $outgoing = $graph->outgoing('taxonomy:' . $termId, 10);
    $assert(count($outgoing) === 1, 'Knowledge graph edge must persist and hydrate.');
    $assert($outgoing[0]['source_owner'] === 'file26-curated', 'Knowledge graph provenance must survive persistence.');

    $feedbackStore = new WordPressFeedbackStore($wpdb);
    $feedback = new FeedbackEvent(
        $feedbackId,
        $feedbackActor,
        $document->canonicalKey(),
        'hide_item',
        hash('sha256', 'integration-context-' . $random),
        $now
    );
    $storedFeedback = $feedbackStore->record($feedback);
    $assert(! $storedFeedback->reversed(), 'Recommendation feedback must persist as active.');
    $assert(count($feedbackStore->activeForActor($feedbackActor)) === 1, 'Active recommendation feedback must be queryable.');
    $assert($feedbackStore->reverse($feedbackId, $now)->reversed(), 'Recommendation feedback reversal must persist.');

    $eventLedger = new WordPressChangeEventLedger($wpdb);
    $event = ChangeEvent::create(
        'integration-event-' . $random,
        $ownerKey,
        $canonicalEventKey,
        'v1',
        'published',
        $now,
        1,
        ['title' => 'Integration event']
    );
    $assert($eventLedger->append($event), 'Ordered change event must append.');
    $assert(! $eventLedger->append($event), 'Duplicate idempotency key must be ignored.');
    $pendingEvent = $eventLedger->nextPending($now->modify('+1 second'));
    $assert($pendingEvent instanceof ChangeEvent, 'Pending change event must be claimable.');
    $assert($pendingEvent->eventId() === $event->eventId(), 'Claimed change event identity must match.');
    $eventLedger->acknowledge($event->eventId(), $now->modify('+2 seconds'));
    $eventStatus = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$prefix}change_events WHERE event_id=%s",
        $event->eventId()
    ));
    $assert($eventStatus === 'completed', 'Change event acknowledgement must persist.');

    $purgeLedger = new WordPressPurgeLedger($wpdb);
    $purge = PurgeRecord::requested(
        $ownerKey,
        $canonicalEventKey,
        'v2',
        'deleted',
        $now
    );
    $purgeId = $purge->purgeId();
    $purgeLedger->request($purge);
    $purgeLedger->complete($purgeId, $now->modify('+1 second'));
    $purgeLedger->verifyAbsent($purgeId, $now->modify('+2 seconds'));
    $assert($purgeLedger->overdue($now->modify('+1 hour')) === [], 'Verified purge must not remain overdue.');

    if (did_action('rest_api_init') === 0) {
        do_action('rest_api_init');
    }
    $routes = rest_get_server()->get_routes();
    foreach ([
        '/sabri-search/v1/query',
        '/sabri-search/v1/suggest',
        '/sabri-search/v1/facets',
        '/sabri-search/v1/recommendations',
        '/sabri-search/v1/recommendation-feedback',
        '/sabri-search/v1/admin/health',
        '/sabri-search/v1/admin/taxonomy',
        '/sabri-search/v1/admin/graph-edge',
        '/sabri-search/v1/admin/classification',
        '/sabri-search/v1/admin/policy',
        '/sabri-search/v1/admin/evaluation',
        '/sabri-search/v1/admin/export-token',
        '/sabri-search/v1/export',
        '/sabri-search/v1/health',
        '/sabri-search/v1/operations',
    ] as $route) {
        $assert(isset($routes[$route]), 'Required REST route is missing: ' . $route);
    }
    $topicRouteFound = false;
    foreach (array_keys($routes) as $route) {
        if (str_starts_with($route, '/sabri-search/v1/topics/')) {
            $topicRouteFound = true;
            break;
        }
    }
    $assert($topicRouteFound, 'Parameterized topic REST route must be registered.');

    wp_set_current_user(1);
    $healthRequest = new WP_REST_Request('GET', '/sabri-search/v1/health');
    $healthResponse = rest_do_request($healthRequest);
    $assert($healthResponse->get_status() === 200, 'Administrator health route must be available.');
    $healthData = $healthResponse->get_data();
    $assert(($healthData['version'] ?? null) === '1.0.0', 'Health route must report runtime version 1.0.0.');
    $assert(($healthData['schema_version'] ?? null) === '1.0.0', 'Health route must report schema version 1.0.0.');
    $assert(($healthData['status'] ?? null) === 'coded-complete-candidate', 'Health route must truthfully report coded candidate status.');
    $assert(($healthData['staging_accepted'] ?? null) === false, 'Health route must not claim staging acceptance.');
    $assert(($healthData['live_deployed'] ?? null) === false, 'Health route must not claim live deployment.');
    $assert(($healthData['operational'] ?? null) === false, 'Health route must not claim operational completion.');

    $queryRequest = new WP_REST_Request('GET', '/sabri-search/v1/query');
    $queryRequest->set_param('q', 'کامیاب');
    $queryResponse = rest_do_request($queryRequest);
    $assert($queryResponse->get_status() === 200, 'Public Urdu query route must execute against active generation.');
    $queryData = $queryResponse->get_data();
    $assert(($queryData['generation_id'] ?? null) === $generation, 'Public query route must preserve active generation snapshot.');
    $assert(count($queryData['results'] ?? []) === 1, 'Public query route must return the eligible integration result.');
    $assert(($queryData['results'][0]['click_visibility_recheck_required'] ?? null) === true, 'Public query result must require click-time owner revalidation.');

    $topicRequest = new WP_REST_Request('GET', '/sabri-search/v1/topics/' . $termId);
    $topicRequest->set_param('concept', $termId);
    $topicResponse = rest_do_request($topicRequest);
    $assert($topicResponse->get_status() === 200, 'Public topic route must return active controlled taxonomy.');
    $topicData = $topicResponse->get_data();
    $assert(($topicData['click_visibility_recheck_required'] ?? null) === true, 'Topic response must require click-time owner revalidation.');
    $assert(($topicData['generated_medical_claims'] ?? null) === false, 'Topic response must not generate medical claims.');

    fwrite(STDOUT, sprintf("File 26 complete WordPress/MariaDB smoke passed: %d assertions.\n", $assertions));
} finally {
    $wpdb->query('START TRANSACTION');
    try {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$prefix}aliases WHERE generation_id IN (%s, %s)",
            $generation,
            $replayGeneration
        ));
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
        $wpdb->query($wpdb->prepare("DELETE FROM {$prefix}feedback WHERE actor_hash=%s", $feedbackActor));
        if ($edgeId !== null) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$prefix}graph_edges WHERE edge_id=%s", $edgeId));
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$prefix}taxonomy_terms WHERE term_id=%s", $termId));
        $wpdb->query($wpdb->prepare("DELETE FROM {$prefix}change_events WHERE owner_key=%s", $ownerKey));
        $wpdb->query($wpdb->prepare("DELETE FROM {$prefix}owner_sequences WHERE owner_key=%s", $ownerKey));
        if ($purgeId !== null) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$prefix}purge_ledger WHERE purge_id=%s", $purgeId));
        }
        $wpdb->query('COMMIT');
    } catch (Throwable $exception) {
        $wpdb->query('ROLLBACK');
        throw $exception;
    }
}
