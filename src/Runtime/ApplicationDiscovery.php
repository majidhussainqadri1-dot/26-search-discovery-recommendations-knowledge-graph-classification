<?php

declare(strict_types=1);

namespace Sabri\File26\Application;

use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Query\UnicodeNormalizer;
use Sabri\File26\Search\AudienceContext;
use Sabri\File26\Search\EligibilityEvaluator;
use Sabri\File26\Search\SearchDocumentHydrator;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class SuggestionService
{
    public function __construct(
        private readonly UnicodeNormalizer $normalizer = new UnicodeNormalizer(),
        private readonly EligibilityEvaluator $eligibility = new EligibilityEvaluator()
    ) {
    }

    public function suggest(string $prefix, array $documents, AudienceContext $audience, int $limit = 10): array
    {
        if ($limit < 1 || $limit > 20 || ! array_is_list($documents) || count($documents) > 5000) {
            throw new InvariantViolation('Suggestion input is invalid or unbounded.');
        }

        $prefix = $this->normalizer->normalizeForSearch($prefix);
        if ($prefix === '' || (function_exists('mb_strlen') ? mb_strlen($prefix, 'UTF-8') : strlen($prefix)) < 2) {
            return [];
        }

        $suggestions = [];
        foreach ($documents as $document) {
            if (! $document instanceof SearchDocument || ! $this->eligibility->canView($document, $audience)) {
                continue;
            }
            $title = $document->fields()['title'] ?? null;
            if (! is_string($title) || trim($title) === '') {
                continue;
            }
            $normalized = $this->normalizer->normalizeForSearch($title);
            if (! str_starts_with($normalized, $prefix)) {
                continue;
            }
            $key = $document->canonicalKey();
            $suggestions[$key] = [
                'canonical_key' => $key,
                'label' => $title,
                'domain' => $document->toArray()['canonical_domain'],
                'destination_url' => $document->canonicalUrl(),
                'click_visibility_recheck_required' => true,
            ];
            if (count($suggestions) >= $limit) {
                break;
            }
        }

        return array_values($suggestions);
    }
}

final class FacetService
{
    public function __construct(private readonly EligibilityEvaluator $eligibility = new EligibilityEvaluator())
    {
    }

    public function counts(array $documents, AudienceContext $audience): array
    {
        if (! array_is_list($documents) || count($documents) > 5000) {
            throw new InvariantViolation('Facet documents must be a bounded list.');
        }

        $counts = ['domain' => [], 'locale' => [], 'state' => [], 'content_type' => []];
        foreach ($documents as $document) {
            if (! $document instanceof SearchDocument || ! $this->eligibility->canView($document, $audience)) {
                continue;
            }
            $array = $document->toArray();
            $values = [
                'domain' => (string) $array['canonical_domain'],
                'locale' => (string) $array['locale'],
                'state' => (string) $array['state'],
                'content_type' => is_string($document->fields()['content_type'] ?? null)
                    ? (string) $document->fields()['content_type']
                    : (string) $array['canonical_domain'],
            ];
            foreach ($values as $facet => $value) {
                $counts[$facet][$value] = ($counts[$facet][$value] ?? 0) + 1;
            }
        }

        foreach ($counts as &$facet) {
            arsort($facet);
        }
        unset($facet);

        return $counts;
    }
}

final class RecommendationCandidateRepository
{
    private readonly string $documentsTable;
    private readonly string $aliasesTable;

    public function __construct(
        private readonly wpdb $db,
        private readonly SearchDocumentHydrator $hydrator = new SearchDocumentHydrator()
    ) {
        $this->documentsTable = $db->prefix . 's26_documents';
        $this->aliasesTable = $db->prefix . 's26_aliases';
    }

    public function recent(int $limit = 1000): array
    {
        if ($limit < 1 || $limit > 5000) {
            throw new InvariantViolation('Recommendation candidate limit is invalid.');
        }

        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT d.payload,d.payload_hash
                 FROM {$this->documentsTable} d
                 INNER JOIN {$this->aliasesTable} a
                    ON a.alias_key='active' AND a.generation_id=d.generation_id
                 ORDER BY d.source_event_at DESC,d.canonical_key ASC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $documents = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $payload = isset($row['payload']) && is_string($row['payload']) ? $row['payload'] : '';
            $payloadHash = isset($row['payload_hash']) && is_string($row['payload_hash']) ? $row['payload_hash'] : '';
            if ($payload === '' || preg_match('/^[a-f0-9]{64}$/', $payloadHash) !== 1) {
                throw new InvariantViolation('Recommendation candidate payload metadata is invalid.');
            }
            if (! hash_equals($payloadHash, hash('sha256', $payload))) {
                throw new InvariantViolation('Recommendation candidate payload integrity failed.');
            }
            $document = $this->hydrator->hydrate($payload);
            if (isset($documents[$document->canonicalKey()])) {
                throw new InvariantViolation('Recommendation candidates contain duplicate canonical identities.');
            }
            $documents[$document->canonicalKey()] = $document;
        }

        return array_values($documents);
    }
}
