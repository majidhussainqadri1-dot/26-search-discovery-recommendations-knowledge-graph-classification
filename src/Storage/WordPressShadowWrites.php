<?php

declare(strict_types=1);

namespace Sabri\File26\Storage;

use DateTimeImmutable;
use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Support\InvariantViolation;
use Throwable;

trait WordPressShadowWrites
{
    public function applyBatch(string $generationId, string $connectorKey, ConnectorBatch $batch): void
    {
        $this->assertConnectorKey($connectorKey);
        $this->db->query('START TRANSACTION');
        try {
            $this->lockBuildingGeneration($generationId);
            $checkpointExists = $this->db->get_var($this->db->prepare(
                "SELECT connector_key FROM {$this->checkpointsTable} WHERE generation_id = %s AND connector_key = %s FOR UPDATE",
                $generationId,
                $connectorKey
            ));
            if (! is_string($checkpointExists)) {
                throw new InvariantViolation('A connector must be registered in the generation checkpoint set before writing.');
            }

            foreach ($batch->tombstones() as $tombstone) {
                $key = $tombstone->canonicalKey();
                $this->assertConnectorOwnsKey($connectorKey, $key);
                $receivedAt = $this->utc($tombstone->receivedAt());

                $documentTime = $this->db->get_var($this->db->prepare(
                    "SELECT source_event_at FROM {$this->documentsTable} WHERE generation_id = %s AND canonical_key = %s FOR UPDATE",
                    $generationId,
                    $key
                ));
                if (is_string($documentTime) && $documentTime > $receivedAt) {
                    continue;
                }

                $existingTime = $this->db->get_var($this->db->prepare(
                    "SELECT received_at FROM {$this->tombstonesTable} WHERE generation_id = %s AND canonical_key = %s FOR UPDATE",
                    $generationId,
                    $key
                ));
                if (is_string($existingTime) && $existingTime > $receivedAt) {
                    continue;
                }

                $this->db->query($this->db->prepare(
                    "DELETE FROM {$this->documentsTable} WHERE generation_id = %s AND canonical_key = %s",
                    $generationId,
                    $key
                ));

                $payload = $this->encode($tombstone->toArray());
                $this->replace($this->tombstonesTable, [
                    'generation_id' => $generationId,
                    'canonical_key' => $key,
                    'connector_key' => $connectorKey,
                    'received_at' => $receivedAt,
                    'payload' => $payload,
                    'payload_hash' => hash('sha256', $payload),
                ]);
            }

            foreach ($batch->documents() as $document) {
                $key = $document->canonicalKey();
                $this->assertConnectorOwnsKey($connectorKey, $key);
                $sourceEventAt = $this->utc($document->lastSourceEventAt());

                $tombstoneTime = $this->db->get_var($this->db->prepare(
                    "SELECT received_at FROM {$this->tombstonesTable} WHERE generation_id = %s AND canonical_key = %s FOR UPDATE",
                    $generationId,
                    $key
                ));
                if (is_string($tombstoneTime) && $tombstoneTime >= $sourceEventAt) {
                    continue;
                }

                $existingTime = $this->db->get_var($this->db->prepare(
                    "SELECT source_event_at FROM {$this->documentsTable} WHERE generation_id = %s AND canonical_key = %s FOR UPDATE",
                    $generationId,
                    $key
                ));
                if (is_string($existingTime) && $existingTime > $sourceEventAt) {
                    continue;
                }

                $this->db->query($this->db->prepare(
                    "DELETE FROM {$this->tombstonesTable} WHERE generation_id = %s AND canonical_key = %s AND received_at < %s",
                    $generationId,
                    $key,
                    $sourceEventAt
                ));

                $payload = $this->encode($document->toArray());
                $this->replace($this->documentsTable, [
                    'generation_id' => $generationId,
                    'canonical_key' => $key,
                    'connector_key' => $connectorKey,
                    'source_event_at' => $sourceEventAt,
                    'payload' => $payload,
                    'payload_hash' => hash('sha256', $payload),
                ]);
            }

            $this->refreshCounts($generationId);
            $this->db->query('COMMIT');
        } catch (Throwable $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        }
    }

    public function saveCheckpoint(
        string $generationId,
        string $connectorKey,
        ?string $cursor,
        bool $complete,
        DateTimeImmutable $updatedAt
    ): void {
        $this->assertConnectorKey($connectorKey);
        if ($complete && $cursor !== null) {
            throw new InvariantViolation('A completed checkpoint must not retain a cursor.');
        }
        if (! $complete && ($cursor === null || trim($cursor) === '' || strlen($cursor) > 512)) {
            throw new InvariantViolation('An incomplete checkpoint requires a bounded cursor.');
        }

        $this->db->query('START TRANSACTION');
        try {
            $this->lockBuildingGeneration($generationId);
            $existing = $this->db->get_row($this->db->prepare(
                "SELECT is_complete, updated_at FROM {$this->checkpointsTable} WHERE generation_id = %s AND connector_key = %s FOR UPDATE",
                $generationId,
                $connectorKey
            ), ARRAY_A);

            $timestamp = $this->utc($updatedAt);
            if (is_array($existing)) {
                if ((string) $existing['updated_at'] > $timestamp) {
                    throw new InvariantViolation('A stale checkpoint may not overwrite a newer checkpoint.');
                }
                if ((int) $existing['is_complete'] === 1 && ! $complete) {
                    throw new InvariantViolation('A completed checkpoint may not regress to incomplete.');
                }
            }

            $this->replace($this->checkpointsTable, [
                'generation_id' => $generationId,
                'connector_key' => $connectorKey,
                'cursor_value' => $cursor,
                'is_complete' => $complete ? 1 : 0,
                'updated_at' => $timestamp,
            ]);
            $this->db->query('COMMIT');
        } catch (Throwable $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        }
    }

    public function checkpoint(string $generationId, string $connectorKey): ?array
    {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT cursor_value, is_complete, updated_at FROM {$this->checkpointsTable} WHERE generation_id = %s AND connector_key = %s",
            $generationId,
            $connectorKey
        ), ARRAY_A);

        if (! is_array($row)) {
            return null;
        }

        return [
            'cursor' => $row['cursor_value'] === null ? null : (string) $row['cursor_value'],
            'complete' => (int) $row['is_complete'] === 1,
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}
