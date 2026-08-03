<?php

declare(strict_types=1);

namespace Sabri\File26\Contracts;

use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Support\InvariantViolation;

final class ConnectorBatch
{
    /**
     * @param list<SearchDocument> $documents
     */
    public function __construct(
        private readonly array $documents,
        private readonly ?string $nextCursor,
        private readonly bool $hasMore
    ) {
        foreach ($documents as $document) {
            if (! $document instanceof SearchDocument) {
                throw new InvariantViolation('Connector batches may contain SearchDocument objects only.');
            }
        }

        if ($hasMore && ($nextCursor === null || $nextCursor === '')) {
            throw new InvariantViolation('A continuing batch requires a non-empty next cursor.');
        }
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

    public function hasMore(): bool
    {
        return $this->hasMore;
    }
}
