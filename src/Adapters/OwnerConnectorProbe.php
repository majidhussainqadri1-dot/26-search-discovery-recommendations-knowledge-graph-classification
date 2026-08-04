<?php

declare(strict_types=1);

namespace Sabri\File26\Adapters;

use Sabri\File26\Contracts\ConnectorInterface;
use Sabri\File26\Support\InvariantViolation;

final class OwnerConnectorProbe
{
    /**
     * Execute a bounded, read-only contract probe against a registered owner connector.
     * No records are written to a generation by this harness.
     *
     * @return array{
     *   connector_key:string,pages:int,documents:int,tombstones:int,
     *   checksum:string,terminal:bool
     * }
     */
    public function probe(ConnectorInterface $connector, int $batchLimit = 50, int $maximumPages = 50): array
    {
        if ($batchLimit < 1 || $batchLimit > 200) {
            throw new InvariantViolation('Connector probe batch limits must be between 1 and 200.');
        }
        if ($maximumPages < 1 || $maximumPages > 100) {
            throw new InvariantViolation('Connector probes may inspect between 1 and 100 pages.');
        }

        $key = $connector->key();
        $cursor = null;
        $seenCursors = [];
        $seenKeys = [];
        $checksumRows = [];
        $documents = 0;
        $tombstones = 0;
        $terminal = false;
        $pages = 0;

        for ($page = 0; $page < $maximumPages; ++$page) {
            $batch = $connector->fetchBatch($cursor, $batchLimit);
            ++$pages;

            foreach ($batch->documents() as $document) {
                $canonicalKey = $document->canonicalKey();
                $this->assertOwnedKey($key, $canonicalKey);
                if (isset($seenKeys[$canonicalKey])) {
                    throw new InvariantViolation('Connector probe detected a duplicate canonical identity across pages.');
                }
                $seenKeys[$canonicalKey] = true;
                ++$documents;
                $checksumRows[] = 'D|' . $canonicalKey . '|' . hash('sha256', json_encode($document->toArray(), JSON_THROW_ON_ERROR));
            }

            foreach ($batch->tombstones() as $tombstone) {
                $canonicalKey = $tombstone->canonicalKey();
                $this->assertOwnedKey($key, $canonicalKey);
                if (isset($seenKeys[$canonicalKey])) {
                    throw new InvariantViolation('Connector probe detected a duplicate document/tombstone identity across pages.');
                }
                $seenKeys[$canonicalKey] = true;
                ++$tombstones;
                $checksumRows[] = 'T|' . $canonicalKey . '|' . hash('sha256', json_encode($tombstone->toArray(), JSON_THROW_ON_ERROR));
            }

            if (! $batch->hasMore()) {
                $terminal = true;
                break;
            }

            $nextCursor = $batch->nextCursor();
            if ($nextCursor === null || isset($seenCursors[$nextCursor])) {
                throw new InvariantViolation('Connector probe detected a missing or repeated continuation cursor.');
            }
            $seenCursors[$nextCursor] = true;
            $cursor = $nextCursor;
        }

        if (! $terminal) {
            throw new InvariantViolation('Connector probe did not reach a terminal page within the configured bound.');
        }

        sort($checksumRows);

        return [
            'connector_key' => $key,
            'pages' => $pages,
            'documents' => $documents,
            'tombstones' => $tombstones,
            'checksum' => hash('sha256', implode("\n", $checksumRows)),
            'terminal' => true,
        ];
    }

    private function assertOwnedKey(string $connectorKey, string $canonicalKey): void
    {
        if (! str_starts_with($canonicalKey, $connectorKey . ':')) {
            throw new InvariantViolation('Connector probe detected a cross-owner canonical identity.');
        }
    }
}
