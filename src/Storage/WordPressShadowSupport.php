<?php

declare(strict_types=1);

namespace Sabri\File26\Storage;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Sabri\File26\Support\InvariantViolation;

trait WordPressShadowSupport
{
    private function refreshCounts(string $generationId): void
    {
        $documents = (int) $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->documentsTable} WHERE generation_id = %s",
            $generationId
        ));
        $tombstones = (int) $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->tombstonesTable} WHERE generation_id = %s",
            $generationId
        ));
        $this->db->query($this->db->prepare(
            "UPDATE {$this->generationsTable} SET document_count = %d, tombstone_count = %d WHERE generation_id = %s",
            $documents,
            $tombstones,
            $generationId
        ));
    }

    private function calculateChecksum(string $generationId): string
    {
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT canonical_key, payload_hash, 'D' AS kind FROM {$this->documentsTable} WHERE generation_id = %s
             UNION ALL
             SELECT canonical_key, payload_hash, 'T' AS kind FROM {$this->tombstonesTable} WHERE generation_id = %s
             ORDER BY canonical_key, kind",
            $generationId,
            $generationId
        ), ARRAY_A);

        $parts = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $parts[] = (string) $row['kind'] . '|' . (string) $row['canonical_key'] . '|' . (string) $row['payload_hash'];
        }

        return hash('sha256', implode("\n", $parts));
    }

    private function lockBuildingGeneration(string $generationId): void
    {
        $this->assertGenerationId($generationId);
        $state = $this->db->get_var($this->db->prepare(
            "SELECT state FROM {$this->generationsTable} WHERE generation_id = %s FOR UPDATE",
            $generationId
        ));
        if (! is_string($state) || $state !== 'building') {
            throw new InvariantViolation('Only a building generation may be mutated.');
        }
    }

    /** @param list<string> $keys @return list<string> */
    private function validateExpectedConnectorKeys(array $keys): array
    {
        if ($keys === [] || ! array_is_list($keys) || count($keys) > 100 || count($keys) !== count(array_unique($keys))) {
            throw new InvariantViolation('Expected connector keys must be a non-empty bounded unique list.');
        }
        foreach ($keys as $key) {
            $this->assertConnectorKey($key);
        }
        sort($keys);

        return $keys;
    }

    /** @param array<string,mixed> $data */
    private function replace(string $table, array $data): void
    {
        $result = $this->db->replace($table, $data);
        if ($result === false) {
            throw new InvariantViolation('Persistent shadow storage write failed.');
        }
    }

    /** @param array<string,mixed> $payload */
    private function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            unset($exception);
            throw new InvariantViolation('Shadow payload JSON encoding failed.');
        }
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private function assertGenerationId(string $generationId): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $generationId)) {
            throw new InvariantViolation('Generation IDs must be stable bounded lowercase keys.');
        }
    }

    private function assertConnectorKey(string $connectorKey): void
    {
        if (! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $connectorKey)) {
            throw new InvariantViolation('Connector keys must be stable bounded lowercase keys.');
        }
    }

    private function assertConnectorOwnsKey(string $connectorKey, string $canonicalKey): void
    {
        if (! str_starts_with($canonicalKey, $connectorKey . ':')) {
            throw new InvariantViolation('A connector may write only its own canonical domain.');
        }
    }
}
