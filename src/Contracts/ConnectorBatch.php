<?php

declare(strict_types=1);

namespace Sabri\File26\Contracts;

use Sabri\File26\Domain\IndexTombstone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Support\InvariantViolation;

final class ConnectorBatch
{
    /**
     * @param list<SearchDocument> $documents
     * @param list<IndexTombstone> $tombstones
     */
    public function __construct(
        private readonly array $documents,
        private readonly ?string $nextCursor,
        private readonly bool $hasMore,
        private readonly array $tombstones = []
    ) {
        $seen = [];

        foreach ($documents as $document) {
            if (! $document instanceof SearchDocument) {
                throw new InvariantViolation('Connector batches may contain SearchDocument objects only.');
            }

            $key = $document->canonicalKey();
            if (isset($seen[$key])) {
                throw new InvariantViolation('Connector batches may not contain duplicate canonical identities.');
            }
            $seen[$key] = true;
        }

        foreach ($tombstones as $tombstone) {
            if (! $tombstone instanceof IndexTombstone) {
                throw new InvariantViolation('Connector batch tombstones must contain IndexTombstone objects only.');
            }

            $key = $tombstone->canonicalKey();
            if (isset($seen[$key])) {
                throw new InvariantViolation('A connector batch cannot contain both a document and tombstone for the same identity.');
            }
            $seen[$key] = true;
        }

        if ($hasMore && ($nextCursor === null || trim($nextCursor) === '')) {
            throw new InvariantViolation('A continuing batch requires a non-empty next cursor.');
        }

        if ($nextCursor !== null && strlen($nextCursor) > 512) {
            throw new InvariantViolation('Connector batch cursor must be bounded.');
        }
    }

    /** @return list<SearchDocument> */
    public function documents(): array
    {
        return $this->documents;
    }

    /** @return list<IndexTombstone> */
    public function tombstones(): array
    {
        return $this->tombstones;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }
}
