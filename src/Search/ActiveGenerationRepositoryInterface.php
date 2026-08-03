<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use Sabri\File26\Domain\SearchDocument;

interface ActiveGenerationRepositoryInterface
{
    public function activeGenerationId(): ?string;

    public function isReadableGeneration(string $generationId): bool;

    /**
     * Return a bounded candidate set. Eligibility and final ranking are applied
     * by the query service, never by trusting storage visibility alone.
     *
     * @param list<string> $terms
     * @return list<SearchDocument>
     */
    public function candidates(string $generationId, array $terms, int $maximum): array;
}
