<?php

declare(strict_types=1);

use Sabri\File26\Api\PublicApiController;
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
use Sabri\File26\Ranking\RankedResult;
use Sabri\File26\Recommendations\RecommendationResult;
use Sabri\File26\Storage\SchemaManager;
use Sabri\File26\Taxonomy\WordPressTaxonomyStore;

require_once __DIR__ . '/bootstrap.php';

$assertions = 0;
function review_assert(bool $condition, string $message): void
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$schemaSuffixes = SchemaManager::tableSuffixes();
$stores = [
    WordPressChangeEventLedger::class => ['change_events', 'owner_sequences'],
    WordPressPurgeLedger::class => ['purge_ledger'],
    WordPressTaxonomyStore::class => ['taxonomy_terms'],
    WordPressClassificationStore::class => ['classifications'],
    WordPressGraphStore::class => ['graph_edges'],
    WordPressPolicyStore::class => ['policies'],
    WordPressEvaluationStore::class => ['evaluation_sets'],
    WordPressTelemetryStore::class => ['telemetry_daily'],
    WordPressAuditLog::class => ['audit_log'],
];
foreach ($stores as $class => $suffixes) {
    $reflection = new ReflectionClass($class);
    $source = (string) file_get_contents((string) $reflection->getFileName());
    foreach ($suffixes as $suffix) {
        review_assert(in_array($suffix, $schemaSuffixes, true), 'Schema registry must own table suffix ' . $suffix . ' used by ' . $class . '.');
        review_assert(str_contains($source, 's26_' . $suffix), $class . ' must reference its canonical table s26_' . $suffix . '.');
    }
}
review_assert(SchemaManager::SCHEMA_VERSION === '1.0.0', 'Complete schema version must be 1.0.0.');
review_assert(count($schemaSuffixes) === 19, 'Complete schema must enumerate nineteen owner-scoped derivative tables.');
review_assert(count($schemaSuffixes) === count(array_unique($schemaSuffixes, SORT_STRING)), 'Schema table registry must not contain duplicate suffixes.');

$tokenService = new ExportTokenService(str_repeat('x', 32));
$token = $tokenService->issue(9, ['metrics.read'], new DateTimeImmutable('+5 minutes'));
review_assert($tokenService->verify($token)['actor_id'] === 9, 'Export token round-trip must succeed.');
$parts = explode('.', $token);
$payload = $parts[0];
$mutated = ($payload[0] === 'A' ? 'B' : 'A') . substr($payload, 1) . '.' . $parts[1];
try {
    $tokenService->verify($mutated);
    throw new RuntimeException('Tampered export token should fail.');
} catch (Sabri\File26\Support\InvariantViolation) {
    ++$assertions;
}

$publicSource = (string) file_get_contents((new ReflectionClass(PublicApiController::class))->getFileName());
review_assert(str_contains($publicSource, 'Personalized recommendations require authentication.'), 'Public API must block anonymous personalized requests.');
review_assert(! str_contains($publicSource, "get_param('minor')"), 'Public API must not trust a request-supplied minor flag.');
review_assert(str_contains($publicSource, 'query string is required for facets'), 'Facet endpoint must require a query snapshot.');
review_assert(str_contains($publicSource, 'eligibleDocuments') || str_contains($publicSource, 'search($query'), 'Facet endpoint must use an eligibility-aware query path.');
review_assert(str_contains($publicSource, 'clinical_message_payment_signals_used'), 'Recommendation response must declare prohibited signals unused.');

$rankingSource = (string) file_get_contents((new ReflectionClass(RankedResult::class))->getFileName());
$recommendationSource = (string) file_get_contents((new ReflectionClass(RecommendationResult::class))->getFileName());
$searchPageSource = (string) file_get_contents(__DIR__ . '/../src/Runtime/ApplicationSearch.php');
review_assert(str_contains($rankingSource, 'click_visibility_recheck_required'), 'Ranked-result serialization must require click-time owner revalidation.');
review_assert(str_contains($recommendationSource, 'click_visibility_recheck_required'), 'Recommendation serialization must require click-time owner revalidation.');
review_assert(str_contains($searchPageSource, 'RankedResult $result') && str_contains($searchPageSource, '$result->toArray()'), 'Search page must serialize canonical RankedResult values without stripping revalidation metadata.');

$ingestionSource = (string) file_get_contents((new ReflectionClass(WordPressChangeEventLedger::class))->getFileName());
review_assert(str_contains($ingestionSource, 'FOR UPDATE'), 'Change-event sequence and claims must use row locks.');
review_assert(str_contains($ingestionSource, 'owner_sequences'), 'Change-event ledger must maintain durable owner sequence state.');
review_assert(str_contains($ingestionSource, 'idempotency_key'), 'Change-event ingestion must enforce idempotency.');

$exportSource = (string) file_get_contents((new ReflectionClass(ExportPackageService::class))->getFileName());
review_assert(str_contains($exportSource, 'other_user_data_included'), 'Export package must declare cross-user exclusion.');
review_assert(! str_contains($exportSource, 'private_messages'), 'Export package must not include private-message content.');
review_assert(! str_contains($exportSource, 'clinical_records'), 'Export package must not include clinical-record content.');

$pluginSource = (string) file_get_contents(__DIR__ . '/../src/Plugin.php');
review_assert(str_contains($pluginSource, 'DefaultConnectorRegistrar'), 'Plugin must register the approved owner adapter set.');
review_assert(str_contains($pluginSource, 'WordPressApplication::boot'), 'Plugin must compose the complete public/admin application runtime.');
review_assert(str_contains($pluginSource, "'staging_accepted' => false"), 'Health response must not falsely claim staging acceptance.');
review_assert(str_contains($pluginSource, "'live_deployed' => false"), 'Health response must not falsely claim live deployment.');
review_assert(str_contains($pluginSource, "'operational' => false"), 'Health response must not falsely claim operational completion.');

$autoloaderSource = (string) file_get_contents(__DIR__ . '/../src/Autoloader.php');
foreach (['Runtime/ApiPublic.php', 'Runtime/ApplicationRuntime.php', 'Runtime/Taxonomy.php', 'Runtime/KnowledgeGraph.php', 'Runtime/OwnerIntegration.php'] as $bundle) {
    review_assert(str_contains($autoloaderSource, $bundle), 'Autoloader must map complete runtime bundle: ' . $bundle);
}

$composer = json_decode((string) file_get_contents(__DIR__ . '/../composer.json'), true, 32, JSON_THROW_ON_ERROR);
$testCommand = (string) ($composer['scripts']['test'] ?? '');
foreach (['phase-26e-complete.php', 'phase-26e-review.php', 'phase-26e-adversarial.php'] as $suite) {
    review_assert(str_contains($testCommand, $suite), 'Composer test gate must include ' . $suite . '.');
}

$health = (new HealthDashboard())->summarize([
    'connector_lag_seconds' => 0,
    'failed_events' => 0,
    'document_count' => 10,
    'hidden_state_leaks' => 0,
    'zero_result_rate' => 0.0,
    'p95_latency_ms' => 100,
    'graph_orphans' => 0,
]);
review_assert($health['status'] === 'healthy', 'Health dashboard must return healthy for clean metrics.');

echo "Phase 26E review round 1 tests passed: {$assertions} assertions.\n";
