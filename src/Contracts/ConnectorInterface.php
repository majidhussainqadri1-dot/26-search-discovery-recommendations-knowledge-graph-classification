<?php

declare(strict_types=1);

namespace Sabri\File26\Contracts;

interface ConnectorInterface
{
    /**
     * A globally unique connector key such as "file-21-publications".
     */
    public function key(): string;

    public function manifest(): ConnectorManifest;

    /**
     * Return a bounded, cursor-based batch. Implementations must not return
     * records that are outside the manifest's approved visibility classes.
     */
    public function fetchBatch(?string $cursor, int $limit): ConnectorBatch;

    /**
     * Public-safe health data only. Secrets, private URLs, credentials and raw
     * record values are forbidden.
     *
     * @return array<string, bool|int|float|string|null>
     */
    public function health(): array;
}
