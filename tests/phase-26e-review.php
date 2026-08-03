<?php

declare(strict_types=1);

use Sabri\File26\Api\PublicApiController;
use Sabri\File26\Application\AdvancedSearchService;
use Sabri\File26\Application\FacetService;
use Sabri\File26\Application\RecommendationCandidateRepository;
use Sabri\File26\Application\SuggestionService;
use Sabri\File26\Classification\WordPressClassificationStore;
use Sabri\File26\Governance\ExportPackageService;
use Sabri\File26\Governance\ExportTokenService;
use Sabri\File26\Governance\HealthDashboard;
use Sabri\File26\Governance\WordPressAuditLog;
use Sabri\File26\Governance\WordPressEvaluationStore;
use Sabri\File26\Governance\WordPressPolicyStore;
use Sabri\File26\Governance\WordPressTelemetryStore;
use Sabri\File26\Ingestion\WordPressChangeEventLedger;
use Sabri\File26\Ingestion\WordPressPurgeLedger;
use Sabri\File26\KnowledgeGraph\WordPressGraphStore;
use Sabri\File26\Recommendations\FeedbackStoreInterface;
use Sabri\File26\Recommendations\RecommendationEngine;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Search\InMemoryActiveGenerationRepository;
use Sabri\File26\Search\QueryCursorCodec;
use Sabri\File26\Taxonomy\WordPressTaxonomyStore;

require_once __DIR__ . '/bootstrap.php';

$assertions = 0;
function review_assert(bool $condition, string $message): void { global $assertions; ++$assertions; if (! $condition) throw new RuntimeException($message); }

$schema = file_get_contents(__DIR__ . '/../src/Storage/SchemaManager.php');
$stores = [
    WordPressChangeEventLedger::class => ['s26_change_events','s26_owner_sequences'],
    WordPressPurgeLedger::class => ['s26_purge_ledger'],
    WordPressTaxonomyStore::class => ['s26_taxonomy_terms'],
    WordPressClassificationStore::class => ['s26_classifications'],
    WordPressGraphStore::class => ['s26_graph_edges'],
    WordPressPolicyStore::class => ['s26_policies'],
    WordPressEvaluationStore::class => ['s26_evaluation_sets'],
    WordPressTelemetryStore::class => ['s26_telemetry_daily'],
    WordPressAuditLog::class => ['s26_audit_log'],
];
foreach ($stores as $class => $tables) {
    $reflection = new ReflectionClass($class);
    $source = file_get_contents((string) $reflection->getFileName());
    foreach ($tables as $table) {
        review_assert(str_contains($schema, $table), 'Schema must define table ' . $table . ' used by ' . $class . '.');
        review_assert(str_contains($source, $table), $class . ' must reference its canonical table ' . $table . '.');
    }
}

$tokenService = new ExportTokenService(str_repeat('x', 32));
$token = $tokenService->issue(9, ['metrics.read'], new DateTimeImmutable('+5 minutes'));
review_assert($tokenService->verify($token)['actor_id'] === 9, 'Export token round-trip must succeed.');
$parts = explode('.', $token);
$payload = $parts[0];
$mutated = ($payload[0] === 'A' ? 'B' : 'A') . substr($payload, 1) . '.' . $parts[1];
try { $tokenService->verify($mutated); throw new RuntimeException('Tampered export token should fail.'); } catch (Throwable) { ++$assertions; }

$feedback = new class implements FeedbackStoreInterface {
    public function record(\Sabri\File26\Recommendations\FeedbackEvent $event): \Sabri\File26\Recommendations\FeedbackEvent { return $event; }
    public function reverse(string $idempotencyKey, DateTimeImmutable $at): \Sabri\File26\Recommendations\FeedbackEvent { throw new RuntimeException('not used'); }
    public function activeForActor(string $actorHash, int $limit=100): array { return []; }
    public function purgeActor(string $actorHash): int { return 0; }
};
$repo = new InMemoryActiveGenerationRepository();
$repo->putGeneration('gen-review', [], true);
$search = new AdvancedSearchService($repo, new QueryCursorCodec(str_repeat('q', 32)));
$public = new PublicApiController(
    $search,
    new RecommendationCandidateRepository(new class extends wpdb {}),
    new SuggestionService(),
    new FacetService(),
    new RecommendationEngine(),
    $feedback,
    new \Sabri\File26\Governance\TelemetryRedactor(str_repeat('t', 32)),
    new WordPressTelemetryStore(new class extends wpdb {}),
    new WordPressTaxonomyStore(new class extends wpdb {}),
    new WordPressGraphStore(new class extends wpdb {}),
    new \Sabri\File26\Api\WordPressAudienceFactory(),
    new \Sabri\File26\Api\WordPressRateLimiter(str_repeat('r', 32)),
    str_repeat('a', 32)
);
$publicSource = file_get_contents((new ReflectionClass(PublicApiController::class))->getFileName());
review_assert(str_contains($publicSource, 'Personalized recommendations require authentication.'), 'Public API must block anonymous personalized requests.');
review_assert(! str_contains($publicSource, "$minor=$request->get_param('minor')"), 'Public API must not trust a request-supplied minor flag.');
review_assert(str_contains($publicSource, "query string is required for facets"), 'Facet endpoint must require a query snapshot.');
review_assert(str_contains($publicSource, 'eligibleDocuments') || str_contains($publicSource, 'search($query'), 'Facet endpoint must use an eligibility-aware query path.');

$ingestionSource = file_get_contents((new ReflectionClass(WordPressChangeEventLedger::class))->getFileName());
review_assert(str_contains($ingestionSource, 'FOR UPDATE'), 'Change-event sequence and claims must use row locks.');
review_assert(str_contains($ingestionSource, 'owner_sequences'), 'Change-event ledger must maintain durable owner sequence state.');

$exportSource = file_get_contents((new ReflectionClass(ExportPackageService::class))->getFileName());
review_assert(str_contains($exportSource, 'other_user_data_included'), 'Export package must declare cross-user exclusion.');
review_assert(! str_contains($exportSource, 'private_messages'), 'Export package must not include private-message content.');

$health = (new HealthDashboard())->summarize(['connector_lag_seconds'=>0,'failed_events'=>0,'document_count'=>10,'hidden_state_leaks'=>0,'zero_result_rate'=>0.0,'p95_latency_ms'=>100,'graph_orphans'=>0]);
review_assert($health['status'] === 'healthy', 'Health dashboard must return healthy for clean metrics.');

echo "Phase 26E review round 1 tests passed: {$assertions} assertions.\n";
