<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Support\InvariantViolation;

final class InMemoryActiveGenerationRepository implements ActiveGenerationRepositoryInterface
{
    /** @var array<string,array{state:string,documents:array<string,SearchDocument>}> */
    private array $generations = [];

    private ?string $activeGenerationId = null;

    /** @param list<SearchDocument> $documents */
    public function addGeneration(string $generationId, string $state, array $documents, bool $active = false): void
    {
        $this->assertGenerationId($generationId);
        if (! in_array($state, ['active', 'superseded'], true)) {
            throw new InvariantViolation('In-memory readable generations must be active or superseded.');
        }

        $mapped = [];
        foreach ($documents as $document) {
            if (! $document instanceof SearchDocument) {
                throw new InvariantViolation('In-memory query generations accept SearchDocument objects only.');
            }
            if (isset($mapped[$document->canonicalKey()])) {
                throw new InvariantViolation('In-memory query generations may not contain duplicate canonical keys.');
            }
            $mapped[$document->canonicalKey()] = $document;
        }
        ksort($mapped);

        if ($active) {
            if ($state !== 'active') {
                throw new InvariantViolation('The active in-memory generation must have active state.');
            }
            if ($this->activeGenerationId !== null && isset($this->generations[$this->activeGenerationId])) {
                $this->generations[$this->activeGenerationId]['state'] = 'superseded';
            }
            $this->activeGenerationId = $generationId;
        }

        $this->generations[$generationId] = ['state' => $state, 'documents' => $mapped];
    }

    public function activeGenerationId(): ?string
    {
        return $this->activeGenerationId;
    }

    public function isReadableGeneration(string $generationId): bool
    {
        $this->assertGenerationId($generationId);
        $state = $this->generations[$generationId]['state'] ?? null;

        return $state === 'active' || $state === 'superseded';
    }

    public function candidates(string $generationId, array $terms, int $maximum): array
    {
        if (! $this->isReadableGeneration($generationId)) {
            throw new InvariantViolation('Requested query generation is not readable.');
        }
        $this->assertTermsAndMaximum($terms, $maximum);

        $results = [];
        foreach ($this->generations[$generationId]['documents'] as $document) {
            $haystack = $this->normalize(json_encode(
                $document->fields(),
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ));
            foreach ($terms as $term) {
                if (str_contains($haystack, $this->normalize($term))) {
                    $results[] = $document;
                    break;
                }
            }
            if (count($results) === $maximum) {
                break;
            }
        }

        return $results;
    }

    /** @param list<string> $terms */
    private function assertTermsAndMaximum(array $terms, int $maximum): void
    {
        if (! array_is_list($terms) || $terms === [] || count($terms) > 16 || count($terms) !== count(array_unique($terms))) {
            throw new InvariantViolation('Candidate terms must be a non-empty bounded unique list.');
        }
        foreach ($terms as $term) {
            if (! is_string($term) || $term === '' || strlen($term) > 256) {
                throw new InvariantViolation('Candidate terms must contain bounded strings only.');
            }
        }
        if ($maximum < 1 || $maximum > 2000) {
            throw new InvariantViolation('Candidate read limits must be between 1 and 2000.');
        }
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function assertGenerationId(string $generationId): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $generationId) !== 1) {
            throw new InvariantViolation('Generation identifiers must be stable bounded lowercase keys.');
        }
    }
}
