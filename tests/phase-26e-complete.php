<?php

declare(strict_types=1);

use Sabri\File26\Classification\ClassificationWorkflow;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Governance\ConfigurationRegistry;
use Sabri\File26\Governance\EvaluationCase;
use Sabri\File26\Governance\EvaluationRegistry;
use Sabri\File26\Governance\TelemetryRedactor;
use Sabri\File26\Governance\VersionedConfiguration;
use Sabri\File26\Ingestion\ChangeEvent;
use Sabri\File26\KnowledgeGraph\GraphEdge;
use Sabri\File26\KnowledgeGraph\KnowledgeGraph;
use Sabri\File26\Query\QueryUnderstandingPipeline;
use Sabri\File26\Recommendations\FeedbackEvent;
use Sabri\File26\Recommendations\InMemoryFeedbackStore;
use Sabri\File26\Recommendations\RecommendationContext;
use Sabri\File26\Recommendations\RecommendationEngine;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Registry\DefaultConnectorRegistrar;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Storage\SchemaManager;
use Sabri\File26\Taxonomy\TaxonomyRegistry;
use Sabri\File26\Taxonomy\TaxonomyTerm;

require_once __DIR__ . '/bootstrap.php';

$assertions = 0;
function complete_assert(bool $condition, string $message): void
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$pipeline = new QueryUnderstandingPipeline();
$query = $pipeline->understand('Jigar ki sozish علاج ۱۲۳');
complete_assert($query->normalizedQuery() === 'jigar ki sozish علاج 123', 'Query normalization must standardize Urdu presentation and digits.');
complete_assert(in_array('جگر', $query->terms(), true), 'Approved transliteration must add Urdu search terms.');
complete_assert($query->sensitivity() === 'public', 'General educational query should remain public.');
$pii = $pipeline->understand('patient@example.com');
complete_assert($pii->sensitivity() === 'pii' && ! $pii->allowsRawTelemetry(), 'PII query must suppress raw telemetry.');

$public = VisibilityEnvelope::public();
$documentA = new SearchDocument(
    'file21',
    'post:1',
    'v1',
    'ur',
    'published',
    'https://sabrihomeopathy.com/post/1',
    [
        'title' => 'جگر کی سوزش کا تعلیمی سبق',
        'content_type' => 'lesson',
        'creator_id' => 'doctor-1',
        'topics' => ['جگر', 'علاج'],
        'authority_score' => 90,
        'quality_score' => 90,
        'popularity_score' => 10,
        'trending_score' => 20,
    ],
    $public,
    new DateTimeImmutable('-1 day')
);
$documentB = new SearchDocument(
    'file10',
    'video:2',
    'v1',
    'en',
    'published',
    'https://sabrihomeopathy.com/video/2',
    [
        'title' => 'Liver Inflammation Educational Video',
        'content_type' => 'video',
        'creator_id' => 'doctor-2',
        'topics' => ['jigar', 'liver'],
        'authority_score' => 80,
        'quality_score' => 80,
        'popularity_score' => 20,
        'trending_score' => 30,
    ],
    $public,
    new DateTimeImmutable('-2 days')
);
$documentC = new SearchDocument(
    'file12',
    'pdf:3',
    'v1',
    'ur',
    'published',
    'https://sabrihomeopathy.com/pdf/3',
    [
        'title' => 'جگر کی کتاب',
        'content_type' => 'pdf',
        'creator_id' => 'doctor-3',
        'topics' => ['جگر'],
        'authority_score' => 70,
        'quality_score' => 75,
    ],
    $public,
    new DateTimeImmutable('-5 days')
);

$ranking = new Sabri\File26\Ranking\RankingEngine();
$ranked = $ranking->rank([$documentB, $documentA, $documentC], $query, 3);
complete_assert(count($ranked) === 3, 'Ranking must return all matching eligible documents.');
complete_assert($ranked[0]->document()->canonicalKey() === 'file21:post:1', 'Exact Urdu relevance and authority should rank first.');
complete_assert(in_array('authoritative-source', $ranked[0]->explanations(), true), 'Ranking must expose an authority explanation.');
complete_assert($ranked[0]->toArray()['click_visibility_recheck_required'] === true, 'Ranked results must require click-time visibility recheck.');

$recommendations = (new RecommendationEngine())->recommend(
    [$documentA, $documentB, $documentC],
    new RecommendationContext(true, false, false, ['جگر'], ['doctor-1'], [], [], [], [], [], 3)
);
complete_assert(count($recommendations) === 3, 'Recommendation engine should produce a bounded candidate list.');
complete_assert(in_array('matches-declared-interest', $recommendations[0]->reasons(), true), 'Recommendation reasons must explain personalization.');
$nonPersonal = (new RecommendationEngine())->recommend([$documentA, $documentB], RecommendationContext::coldStart(2));
complete_assert(in_array('cold-start-curated', $nonPersonal[0]->reasons(), true), 'Opt-out recommendations must use cold-start curation.');

$feedbackStore = new InMemoryFeedbackStore();
$feedback = new FeedbackEvent(
    hash('sha256', 'fb-1'),
    hash('sha256', 'actor-1'),
    'file21:post:1',
    'hide_item',
    hash('sha256', 'ctx'),
    new DateTimeImmutable('now')
);
complete_assert($feedbackStore->record($feedback)->reversed() === false, 'Feedback record should be active.');
complete_assert($feedbackStore->record($feedback)->idempotencyKey() === $feedback->idempotencyKey(), 'Feedback must be idempotent.');
complete_assert($feedbackStore->reverse($feedback->idempotencyKey(), new DateTimeImmutable('now'))->reversed(), 'Feedback reversal should be explicit.');
complete_assert($feedbackStore->activeForActor($feedback->actorHash()) === [], 'Reversed feedback must leave the active set.');

$taxonomy = new TaxonomyRegistry();
$root = new TaxonomyTerm(
    'topic.health',
    1,
    ['en' => 'Health', 'ur' => 'صحت'],
    ['en' => ['wellness'], 'ur' => ['تندرستی']],
    [],
    [],
    'Controlled health root.',
    'file26-curated',
    'active'
);
$taxonomy->register($root);
$liver = new TaxonomyTerm(
    'topic.liver',
    1,
    ['en' => 'Liver', 'ur' => 'جگر'],
    ['en' => ['hepatic'], 'ur' => []],
    ['topic.health'],
    [],
    'Controlled liver topic.',
    'file26-curated',
    'active'
);
$taxonomy->register($liver);
complete_assert(count($taxonomy->all()) === 2, 'Taxonomy registry should hold versioned terms.');
try {
    $taxonomy->register(new TaxonomyTerm('topic.health', 3, ['en' => 'Health'], [], [], [], 'Bad version.', 'file26-curated', 'active'));
    throw new RuntimeException('Skipped taxonomy version should fail.');
} catch (Sabri\File26\Support\InvariantViolation) {
    ++$assertions;
}

$workflow = new ClassificationWorkflow($taxonomy, 0.95);
$suggestion = $workflow->suggest('file21:post:1', 'topic.liver', 0.80, true, 'classifier-ai', 'model-1', 'request-1');
complete_assert($suggestion->state() === 'review_pending', 'High-impact classification must require review.');
try {
    $workflow->review($suggestion->suggestionId(), 'approved', 'classifier-ai', 'approve');
    throw new RuntimeException('High-impact self-review should fail.');
} catch (Sabri\File26\Support\InvariantViolation) {
    ++$assertions;
}
$approved = $workflow->review($suggestion->suggestionId(), 'approved', 'taxonomy-reviewer', 'evidence-confirmed');
complete_assert($approved->state() === 'approved', 'Independent reviewer should approve classification.');
complete_assert($workflow->appeal($approved->suggestionId(), 'doctor-1', 'incorrect-topic')->state() === 'appealed', 'Approved classification must remain appealable.');

$graph = new KnowledgeGraph();
$graph->putNode($documentA);
$graph->putNode($documentB);
$graph->putEdge(GraphEdge::create('file21:post:1', 'file10:video:2', 'post-references', 'file21', 'v1', 'https://sabrihomeopathy.com/post/1'));
$traversal = $graph->traverse('file21:post:1', AudienceContext::guest(), ['post-references'], 2, 10, 20)->toArray();
complete_assert(count($traversal['nodes']) === 2 && count($traversal['edges']) === 1, 'Graph traversal should return visible owner-sourced nodes and edges.');
complete_assert($traversal['truncated'] === false, 'Small traversal should not be truncated.');

$registry = new ConnectorRegistry();
(new DefaultConnectorRegistrar())->registerInto($registry);
$connectors = $registry->all();
$summary = $registry->publicSummary();
complete_assert(count($connectors) === 9 && count($summary) === 9, 'Default registry must contain nine approved public owner adapters.');
complete_assert(isset($summary['file-09-doctors'], $summary['file-21-publications'], $summary['file-18-marketplace']), 'Doctor, publication and marketplace connectors must be registered.');
foreach ($summary as $row) {
    complete_assert(($row['manifest']['contract_version'] ?? null) === '1.0.0', 'Every default connector contract must be frozen at 1.0.0.');
    complete_assert(($row['manifest']['privacy_classes'] ?? []) === ['C1'], 'Default connectors must expose public C1 projections only.');
}

$configRegistry = new ConfigurationRegistry();
$base = VersionedConfiguration::draft('ranking-policy', '1.0.0', true, ['weight' => 10], 'author-a', new DateTimeImmutable('now'));
$base = $base->approve('reviewer-a', new DateTimeImmutable('now'))->approve('reviewer-b', new DateTimeImmutable('now'))->activate(null, new DateTimeImmutable('now'));
$configRegistry->put($base);
complete_assert($configRegistry->current('ranking-policy')->version() === '1.0.0', 'Approved high-risk configuration should become active.');
$next = VersionedConfiguration::draft('ranking-policy', '1.1.0', true, ['weight' => 12], 'author-a', new DateTimeImmutable('now'));
$next = $next->approve('reviewer-a', new DateTimeImmutable('now'))->approve('reviewer-b', new DateTimeImmutable('now'))->activate('1.0.0', new DateTimeImmutable('now'));
$configRegistry->put($next);
complete_assert($configRegistry->rollback('ranking-policy')->version() === '1.0.0', 'Configuration rollback should restore predecessor.');

$evaluation = new EvaluationRegistry('1.0.0', 'reviewer-a');
$evaluation->add(new EvaluationCase('ur-liver', 'جگر', 'ur', 'lesson', ['file21:post:1'], ['restricted:1'], true));
$evaluationResult = $evaluation->evaluate(['ur-liver' => ['file21:post:1']]);
complete_assert($evaluationResult['release_pass'] === true && $evaluationResult['recall'] === 1.0, 'Evaluation registry should pass a correct safety-critical query.');
$evaluationBlocked = $evaluation->evaluate(['ur-liver' => ['restricted:1']]);
complete_assert($evaluationBlocked['release_pass'] === false && $evaluationBlocked['forbidden_hits'] === 1, 'Forbidden result must block release.');

$telemetry = (new TelemetryRedactor(str_repeat('s', 32)))->queryMetric($query, 'search.query', ['locale' => 'ur']);
complete_assert(! array_key_exists('raw_query', $telemetry->dimensions()), 'Telemetry must not retain raw query text.');
complete_assert(strlen($telemetry->dimensionHash()) === 64, 'Telemetry dimensions must have a stable hash.');

$event = ChangeEvent::create('event-key-1', 'file21', 'file21:post:1', 'v2', 'updated', new DateTimeImmutable('now'), 2, ['title' => 'Changed']);
complete_assert($event->sequenceNumber() === 2 && $event->payload() !== null, 'Change event should retain bounded state payload.');
try {
    ChangeEvent::create('event-key-2', 'file21', 'file21:post:1', 'v3', 'deleted', new DateTimeImmutable('now'), 3, ['title' => 'leak']);
    throw new RuntimeException('Deletion payload should fail.');
} catch (Sabri\File26\Support\InvariantViolation) {
    ++$assertions;
}

$pluginSource = (string) file_get_contents(__DIR__ . '/../sabri-search-discovery.php');
complete_assert(str_contains($pluginSource, "Version:     1.0.0"), 'Plugin runtime identity must be 1.0.0.');
complete_assert(str_contains($pluginSource, "SABRI_FILE26_RUNTIME_STAGE', 'complete-runtime'"), 'Runtime stage must truthfully identify complete code scope.');
complete_assert(SchemaManager::SCHEMA_VERSION === '1.0.0', 'Schema identity must be 1.0.0.');
complete_assert(count(SchemaManager::tableSuffixes()) === 19, 'Complete runtime schema must declare nineteen derivative tables.');

echo "Phase 26E complete runtime tests passed: {$assertions} assertions.\n";
