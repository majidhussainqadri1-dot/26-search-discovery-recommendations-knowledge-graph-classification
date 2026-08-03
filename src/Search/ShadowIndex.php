<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Domain\IndexTombstone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Support\InvariantViolation;

final class ShadowIndex
{
    /** @var array<string,SearchDocument> */
    private array $documents = [];

    /** @var array<string,IndexTombstone> */
    private array $tombstones = [];

    public function __construct(private readonly EligibilityEvaluator $eligibility = new EligibilityEvaluator())
    {
    }

    public function applyBatch(ConnectorBatch $batch): void
    {
        foreach ($batch->tombstones() as $tombstone) {
            $key = $tombstone->canonicalKey();
            $existingDocument = $this->documents[$key] ?? null;

            if ($existingDocument !== null && $existingDocument->lastSourceEventAt() > $tombstone->receivedAt()) {
                continue;
            }

            unset($this->documents[$key]);
            $existingTombstone = $this->tombstones[$key] ?? null;
            if ($existingTombstone === null || $existingTombstone->receivedAt() <= $tombstone->receivedAt()) {
                $this->tombstones[$key] = $tombstone;
            }
        }

        foreach ($batch->documents() as $document) {
            $key = $document->canonicalKey();
            $tombstone = $this->tombstones[$key] ?? null;

            if ($tombstone !== null && $tombstone->receivedAt() >= $document->lastSourceEventAt()) {
                continue;
            }

            $existingDocument = $this->documents[$key] ?? null;
            if ($existingDocument !== null && $existingDocument->lastSourceEventAt() > $document->lastSourceEventAt()) {
                continue;
            }

            unset($this->tombstones[$key]);
            $this->documents[$key] = $document;
        }
    }

    /**
     * @return list<SearchDocument>
     */
    public function query(string $query, AudienceContext $audience, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '' || $this->stringLength($query) > 200) {
            throw new InvariantViolation('Shadow query must be non-empty and at most 200 characters.');
        }

        if ($limit < 1 || $limit > 50) {
            throw new InvariantViolation('Shadow query limit must be between 1 and 50.');
        }

        $terms = $this->tokenize($query);
        if ($terms === []) {
            throw new InvariantViolation('Shadow query must contain at least one searchable term.');
        }

        $scored = [];
        foreach ($this->documents as $key => $document) {
            if (! $this->eligibility->canView($document, $audience)) {
                continue;
            }

            $score = $this->score($document, $terms);
            if ($score > 0) {
                $scored[] = ['key' => $key, 'score' => $score, 'document' => $document];
            }
        }

        usort(
            $scored,
            static fn (array $left, array $right): int => ($right['score'] <=> $left['score']) ?: ($left['key'] <=> $right['key'])
        );

        return array_values(array_map(
            static fn (array $item): SearchDocument => $item['document'],
            array_slice($scored, 0, $limit)
        ));
    }

    /**
     * Compare the shadow projection with a bounded canonical-owner key set.
     *
     * @param list<string> $expectedCanonicalKeys
     * @return array{missing:list<string>,orphaned:list<string>}
     */
    public function reconcileExpectedKeys(array $expectedCanonicalKeys): array
    {
        if (count($expectedCanonicalKeys) > 10000) {
            throw new InvariantViolation('Reconciliation key sets must be bounded.');
        }

        if (count($expectedCanonicalKeys) !== count(array_unique($expectedCanonicalKeys))) {
            throw new InvariantViolation('Reconciliation key sets must not contain duplicates.');
        }

        foreach ($expectedCanonicalKeys as $key) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9._-]{1,79}:.{1,191}$/', $key)) {
                throw new InvariantViolation('Reconciliation keys must use canonical domain:object identity.');
            }
        }

        $actualKeys = array_keys($this->documents);
        $missing = array_values(array_diff($expectedCanonicalKeys, $actualKeys));
        $orphaned = array_values(array_diff($actualKeys, $expectedCanonicalKeys));
        sort($missing);
        sort($orphaned);

        return ['missing' => $missing, 'orphaned' => $orphaned];
    }

    /** @return array{documents:int,tombstones:int} */
    public function counts(): array
    {
        return ['documents' => count($this->documents), 'tombstones' => count($this->tombstones)];
    }

    public function hasDocument(string $canonicalKey): bool
    {
        return isset($this->documents[$canonicalKey]);
    }

    /** @param list<string> $terms */
    private function score(SearchDocument $document, array $terms): int
    {
        $fields = $document->fields();
        $title = isset($fields['title']) && is_string($fields['title']) ? $this->normalize($fields['title']) : '';
        $searchableParts = [];

        foreach ($fields as $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $searchableParts[] = (string) $value;
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $searchableParts[] = $item;
                }
            }
        }

        $haystack = $this->normalize(implode(' ', $searchableParts));
        $score = 0;
        foreach ($terms as $term) {
            if ($title !== '' && str_contains($title, $term)) {
                $score += 8;
            }
            if (str_contains($haystack, $term)) {
                $score += 2;
            }
        }

        if ($this->normalize((string) ($fields['title'] ?? '')) === implode(' ', $terms)) {
            $score += 12;
        }

        return $score;
    }

    /** @return list<string> */
    private function tokenize(string $query): array
    {
        $parts = preg_split('/[\s\p{P}\p{S}]+/u', $this->normalize($query), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            return [];
        }

        return array_slice(array_values(array_unique($parts)), 0, 16);
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function stringLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
