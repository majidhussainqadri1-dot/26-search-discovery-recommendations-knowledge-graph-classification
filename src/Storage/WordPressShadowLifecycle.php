<?php

declare(strict_types=1);

namespace Sabri\File26\Storage;

use DateTimeImmutable;
use Sabri\File26\Jobs\WordPressLeaseLock;
use Sabri\File26\Support\InvariantViolation;
use Throwable;

trait WordPressShadowLifecycle
{
    public function validateGeneration(
        string $generationId,
        array $expectedConnectorKeys,
        DateTimeImmutable $validatedAt
    ): array {
        $expectedConnectorKeys = $this->validateExpectedConnectorKeys($expectedConnectorKeys);
        $this->db->query('START TRANSACTION');
        try {
            $this->lockBuildingGeneration($generationId);
            $rows = $this->db->get_results($this->db->prepare(
                "SELECT connector_key, cursor_value, is_complete FROM {$this->checkpointsTable} WHERE generation_id = %s ORDER BY connector_key FOR UPDATE",
                $generationId
            ), ARRAY_A);
            $rows = is_array($rows) ? $rows : [];
            $actualConnectorKeys = array_map(static fn (array $row): string => (string) $row['connector_key'], $rows);
            if ($actualConnectorKeys !== $expectedConnectorKeys) {
                throw new InvariantViolation('Validation connector set must exactly match the generation checkpoint set.');
            }
            foreach ($rows as $row) {
                if ((int) $row['is_complete'] !== 1 || $row['cursor_value'] !== null) {
                    throw new InvariantViolation('Every expected connector must complete before validation.');
                }
            }

            $checksum = $this->calculateChecksum($generationId);
            $updated = $this->db->query($this->db->prepare(
                "UPDATE {$this->generationsTable}
                 SET state = 'validated', validated_at = %s, checksum = %s
                 WHERE generation_id = %s AND state = 'building'",
                $this->utc($validatedAt),
                $checksum,
                $generationId
            ));
            if ($updated !== 1) {
                throw new InvariantViolation('Generation validation state transition failed.');
            }
            $this->db->query('COMMIT');
        } catch (Throwable $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        }

        return $this->generationSummary($generationId);
    }

    public function promote(string $generationId, DateTimeImmutable $promotedAt): void
    {
        $lock = new WordPressLeaseLock($this->db);
        $token = hash('sha256', 'promote|' . $generationId . '|' . $promotedAt->format('U.u'));
        if (! $lock->acquire('file26:generation-promotion', $token, $promotedAt, 60)) {
            throw new InvariantViolation('Another generation promotion or rollback is already in progress.');
        }

        try {
            $this->db->query('START TRANSACTION');
            $row = $this->db->get_row($this->db->prepare(
                "SELECT state, checksum FROM {$this->generationsTable} WHERE generation_id = %s FOR UPDATE",
                $generationId
            ), ARRAY_A);
            if (! is_array($row) || (string) $row['state'] !== 'validated' || (string) $row['checksum'] === '') {
                throw new InvariantViolation('Only a validated checksummed generation may be promoted.');
            }

            $alias = $this->db->get_row("SELECT generation_id FROM {$this->aliasesTable} WHERE alias_key = 'active' FOR UPDATE", ARRAY_A);
            $previous = is_array($alias) ? (string) $alias['generation_id'] : null;
            if ($previous === '') {
                $previous = null;
            }

            if ($previous !== null && $previous !== $generationId) {
                $superseded = $this->db->query($this->db->prepare(
                    "UPDATE {$this->generationsTable} SET state = 'superseded' WHERE generation_id = %s AND state = 'active'",
                    $previous
                ));
                if ($superseded !== 1) {
                    throw new InvariantViolation('Former active generation could not be superseded atomically.');
                }
            }

            $updated = $this->db->query($this->db->prepare(
                "UPDATE {$this->generationsTable}
                 SET state = 'active', promoted_at = %s, previous_generation_id = %s
                 WHERE generation_id = %s AND state = 'validated'",
                $this->utc($promotedAt),
                $previous,
                $generationId
            ));
            if ($updated !== 1) {
                throw new InvariantViolation('Atomic generation promotion failed.');
            }

            $this->replace($this->aliasesTable, [
                'alias_key' => 'active',
                'generation_id' => $generationId,
                'previous_generation_id' => $previous,
                'updated_at' => $this->utc($promotedAt),
            ]);
            $this->db->query('COMMIT');
        } catch (Throwable $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        } finally {
            $lock->release('file26:generation-promotion', $token);
        }
    }

    public function rollback(DateTimeImmutable $rolledBackAt): string
    {
        $lock = new WordPressLeaseLock($this->db);
        $token = hash('sha256', 'rollback|' . $rolledBackAt->format('U.u'));
        if (! $lock->acquire('file26:generation-promotion', $token, $rolledBackAt, 60)) {
            throw new InvariantViolation('Another generation promotion or rollback is already in progress.');
        }

        try {
            $this->db->query('START TRANSACTION');
            $alias = $this->db->get_row("SELECT generation_id, previous_generation_id FROM {$this->aliasesTable} WHERE alias_key = 'active' FOR UPDATE", ARRAY_A);
            if (! is_array($alias)) {
                throw new InvariantViolation('No active generation exists to roll back.');
            }
            $active = (string) $alias['generation_id'];
            $previous = $alias['previous_generation_id'] === null ? null : (string) $alias['previous_generation_id'];
            if ($previous === null || $previous === '') {
                throw new InvariantViolation('The active generation has no rollback predecessor.');
            }

            $rolledBack = $this->db->query($this->db->prepare(
                "UPDATE {$this->generationsTable} SET state = 'rolled_back' WHERE generation_id = %s AND state = 'active'",
                $active
            ));
            if ($rolledBack !== 1) {
                throw new InvariantViolation('Active generation rollback state transition failed.');
            }

            $restored = $this->db->query($this->db->prepare(
                "UPDATE {$this->generationsTable} SET state = 'active' WHERE generation_id = %s AND state IN ('superseded','validated')",
                $previous
            ));
            if ($restored !== 1) {
                throw new InvariantViolation('Rollback predecessor restoration failed.');
            }

            $predecessor = $this->db->get_var($this->db->prepare(
                "SELECT previous_generation_id FROM {$this->generationsTable} WHERE generation_id = %s",
                $previous
            ));
            $this->replace($this->aliasesTable, [
                'alias_key' => 'active',
                'generation_id' => $previous,
                'previous_generation_id' => is_string($predecessor) && $predecessor !== '' ? $predecessor : null,
                'updated_at' => $this->utc($rolledBackAt),
            ]);
            $this->db->query('COMMIT');

            return $previous;
        } catch (Throwable $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        } finally {
            $lock->release('file26:generation-promotion', $token);
        }
    }
}
