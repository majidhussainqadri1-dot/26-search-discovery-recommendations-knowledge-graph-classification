<?php

declare(strict_types=1);

namespace Sabri\File26\Contracts;

interface SourceBatchProviderInterface
{
    /**
     * Return one bounded owner-sourced change page.
     *
     * The provider remains the canonical owner's read adapter. File 26 accepts
     * only the documented public-safe shape and rejects unknown or malformed
     * values before constructing derivative search documents.
     *
     * @return array{
     *   records:list<array<string,mixed>>,
     *   tombstones?:list<array<string,mixed>>,
     *   next_cursor:?string,
     *   has_more:bool
     * }
     */
    public function fetch(?string $cursor, int $limit): array;

    /**
     * Public-safe, bounded health facts only.
     *
     * @return array<string, bool|int|float|string|null>
     */
    public function health(): array;
}
