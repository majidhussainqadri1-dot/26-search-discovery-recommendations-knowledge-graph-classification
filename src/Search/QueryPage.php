<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Support\InvariantViolation;

final class QueryPage
{
    /** @param list<SearchDocument> $documents */
    public function __construct(
        private readonly string $generationId,
        private readonly array $documents,
        private readonly ?string $nextCursor,
        private readonly bool $candidateSetTruncated
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $generationId) !== 1) {
            throw new InvariantViolation('Query page generation identifiers are invalid.');
        }
        if ($nextCursor !== null && ($nextCursor === '' || strlen($nextCursor) > 1024)) {
            throw new InvariantViolation('Query page cursors must be null or bounded opaque values.');
        }

        $seen = [];
        foreach ($documents as $document) {
            if (! $document instanceof SearchDocument) {
                throw new InvariantViolation('Query pages may contain SearchDocument objects only.');
            }
            if (isset($seen[$document->canonicalKey()])) {
                throw new InvariantViolation('Query pages may not contain duplicate canonical identities.');
            }
            $seen[$document->canonicalKey()] = true;
        }
    }

    public function generationId(): string
    {
        return $this->generationId;
    }

    /** @return list<SearchDocument> */
    public function documents(): array
    {
        return $this->documents;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function candidateSetTruncated(): bool
    {
        return $this->candidateSetTruncated;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'generation_id' => $this->generationId,
            'results' => array_map(static fn (SearchDocument $document): array => $document->toArray(), $this->documents),
            'next_cursor' => $this->nextCursor,
            'candidate_set_truncated' => $this->candidateSetTruncated,
        ];
    }
}
