<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Support\InvariantViolation;

final class PersistentQueryService
{
    private const MAXIMUM_CANDIDATES = 2000;

    public function __construct(
        private readonly ActiveGenerationRepositoryInterface $repository,
        private readonly QueryCursorCodec $cursorCodec,
        private readonly EligibilityEvaluator $eligibility = new EligibilityEvaluator()
    ) {
    }

    public function search(PersistentQuery $query, AudienceContext $audience): QueryPage
    {
        $fingerprint = $query->fingerprint();
        $cursor = $query->cursor();

        if ($cursor === null) {
            $generationId = $this->repository->activeGenerationId();
            if ($generationId === null) {
                throw new InvariantViolation('No active search generation is available.');
            }
            $offset = 0;
        } else {
            $decoded = $this->cursorCodec->decode($cursor);
            if (! hash_equals($fingerprint, $decoded['fingerprint'])) {
                throw new InvariantViolation('Query cursor does not belong to the current query and filters.');
            }
            $generationId = $decoded['generation'];
            $offset = $decoded['offset'];
        }

        if (! $this->repository->isReadableGeneration($generationId)) {
            throw new InvariantViolation('The cursor generation is no longer readable.');
        }

        $candidates = $this->repository->candidates($generationId, $query->terms(), self::MAXIMUM_CANDIDATES);
        $scored = [];
        foreach ($candidates as $document) {
            if (! $query->allows($document) || ! $this->eligibility->canView($document, $audience)) {
                continue;
            }

            $score = $this->score($document, $query->terms());
            if ($score > 0) {
                $scored[] = [
                    'key' => $document->canonicalKey(),
                    'score' => $score,
                    'document' => $document,
                ];
            }
        }

        usort(
            $scored,
            static fn (array $left, array $right): int => ($right['score'] <=> $left['score']) ?: ($left['key'] <=> $right['key'])
        );

        if ($offset > count($scored)) {
            throw new InvariantViolation('Query cursor offset is outside the current snapshot result range.');
        }

        $pageItems = array_slice($scored, $offset, $query->limit());
        $documents = array_values(array_map(
            static fn (array $item): SearchDocument => $item['document'],
            $pageItems
        ));
        $nextOffset = $offset + count($documents);
        $hasMore = $nextOffset < count($scored);
        $nextCursor = $hasMore
            ? $this->cursorCodec->encode($generationId, $nextOffset, $fingerprint)
            : null;

        return new QueryPage(
            $generationId,
            $documents,
            $nextCursor,
            count($candidates) === self::MAXIMUM_CANDIDATES
        );
    }

    /** @param list<string> $terms */
    private function score(SearchDocument $document, array $terms): int
    {
        $fields = $document->fields();
        $title = isset($fields['title']) && is_string($fields['title']) ? $this->normalize($fields['title']) : '';
        $parts = [];

        foreach ($fields as $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $parts[] = (string) $value;
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $parts[] = $item;
                }
            }
        }

        $haystack = $this->normalize(implode(' ', $parts));
        $score = 0;
        foreach ($terms as $term) {
            $term = $this->normalize($term);
            if ($title !== '' && str_contains($title, $term)) {
                $score += 8;
            }
            if (str_contains($haystack, $term)) {
                $score += 2;
            }
        }

        if ($title !== '' && $title === implode(' ', array_map([$this, 'normalize'], $terms))) {
            $score += 12;
        }

        return $score;
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
