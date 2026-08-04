<?php

declare(strict_types=1);

namespace Sabri\File26\Storage;

use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class WordPressShadowStore implements ShadowStoreInterface
{
    use WordPressShadowWrites;
    use WordPressShadowLifecycle;
    use WordPressShadowSupport;

    private readonly string $generationsTable;
    private readonly string $aliasesTable;
    private readonly string $documentsTable;
    private readonly string $tombstonesTable;
    private readonly string $checkpointsTable;

    public function __construct(private readonly wpdb $db)
    {
        $prefix = $db->prefix . 's26_';
        $this->generationsTable = $prefix . 'generations';
        $this->aliasesTable = $prefix . 'aliases';
        $this->documentsTable = $prefix . 'documents';
        $this->tombstonesTable = $prefix . 'tombstones';
        $this->checkpointsTable = $prefix . 'checkpoints';
    }

    public function createGeneration(string $generationId, string $mode, DateTimeImmutable $createdAt): void
    {
        $this->assertGenerationId($generationId);
        if (! in_array($mode, ['full', 'partial', 'delta'], true)) {
            throw new InvariantViolation('Generation mode must be full, partial or delta.');
        }

        $inserted = $this->db->query($this->db->prepare(
            "INSERT INTO {$this->generationsTable}
                (generation_id, mode, state, created_at, document_count, tombstone_count, checksum)
             VALUES (%s, %s, 'building', %s, 0, 0, '')",
            $generationId,
            $mode,
            $this->utc($createdAt)
        ));

        if ($inserted !== 1) {
            throw new InvariantViolation('Generation creation failed or the identifier already exists.');
        }
    }

    public function activeGenerationId(): ?string
    {
        $value = $this->db->get_var("SELECT generation_id FROM {$this->aliasesTable} WHERE alias_key = 'active'");

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function generationSummary(string $generationId): array
    {
        $this->assertGenerationId($generationId);
        $row = $this->db->get_row($this->db->prepare(
            "SELECT generation_id, state, document_count, tombstone_count, checksum FROM {$this->generationsTable} WHERE generation_id = %s",
            $generationId
        ), ARRAY_A);
        if (! is_array($row)) {
            throw new InvariantViolation('Unknown shadow generation.');
        }

        return [
            'generation_id' => (string) $row['generation_id'],
            'state' => (string) $row['state'],
            'documents' => (int) $row['document_count'],
            'tombstones' => (int) $row['tombstone_count'],
            'checksum' => (string) $row['checksum'],
            'active' => $this->activeGenerationId() === $generationId,
        ];
    }
}
