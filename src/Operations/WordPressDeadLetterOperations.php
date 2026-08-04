<?php

declare(strict_types=1);

namespace Sabri\File26\Operations;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Support\InvariantViolation;
use Throwable;
use wpdb;

final class WordPressDeadLetterOperations implements DeadLetterOperationsInterface
{
    private readonly string $jobsTable;
    private readonly string $generationsTable;
    private readonly string $checkpointsTable;

    public function __construct(private readonly wpdb $db)
    {
        $prefix = $db->prefix . 's26_';
        $this->jobsTable = $prefix . 'jobs';
        $this->generationsTable = $prefix . 'generations';
        $this->checkpointsTable = $prefix . 'checkpoints';
    }

    public function deadLetters(int $limit = 20): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvariantViolation('Dead-letter list limits must be between 1 and 100.');
        }

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT job_id, generation_id, connector_key, attempt, error_code, replay_count, updated_at
             FROM {$this->jobsTable}
             WHERE status = 'dead_letter'
             ORDER BY updated_at DESC, job_id ASC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        $result = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $errorCode = isset($row['error_code']) && is_string($row['error_code']) ? $row['error_code'] : '';
            if (preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $errorCode) !== 1) {
                $errorCode = 'unknown-error';
            }
            $result[] = [
                'job_id' => (string) $row['job_id'],
                'generation_id' => (string) $row['generation_id'],
                'connector_key' => (string) $row['connector_key'],
                'attempt' => (int) $row['attempt'],
                'error_code' => $errorCode,
                'replay_count' => (int) $row['replay_count'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }

        return $result;
    }

    public function replay(string $jobId, string $expectedErrorCode, DateTimeImmutable $replayedAt): array
    {
        $this->assertJobId($jobId);
        $this->assertErrorCode($expectedErrorCode);
        $timestamp = $this->utc($replayedAt);

        $this->db->query('START TRANSACTION');
        try {
            $row = $this->db->get_row($this->db->prepare(
                "SELECT j.job_id, j.generation_id, j.connector_key, j.error_code, j.replay_count,
                        g.state AS generation_state, c.is_complete
                 FROM {$this->jobsTable} j
                 INNER JOIN {$this->generationsTable} g ON g.generation_id = j.generation_id
                 LEFT JOIN {$this->checkpointsTable} c
                   ON c.generation_id = j.generation_id AND c.connector_key = j.connector_key
                 WHERE j.job_id = %s AND j.status = 'dead_letter'
                 FOR UPDATE",
                $jobId
            ), ARRAY_A);

            if (! is_array($row)) {
                throw new InvariantViolation('Dead-letter job was not found or is no longer replayable.');
            }
            if (! is_string($row['error_code']) || ! hash_equals($row['error_code'], $expectedErrorCode)) {
                throw new InvariantViolation('Dead-letter replay error-code confirmation failed.');
            }
            if ((string) $row['generation_state'] !== 'building') {
                throw new InvariantViolation('Dead-letter replay requires a building generation.');
            }
            if ($row['is_complete'] === null) {
                throw new InvariantViolation('Dead-letter replay requires an existing connector checkpoint.');
            }
            if ((int) $row['is_complete'] === 1) {
                throw new InvariantViolation('Dead-letter replay is forbidden after connector completion.');
            }

            $replayCount = (int) $row['replay_count'];
            if ($replayCount >= 10) {
                throw new InvariantViolation('Dead-letter replay limit has been reached.');
            }
            ++$replayCount;

            $updated = $this->db->query($this->db->prepare(
                "UPDATE {$this->jobsTable}
                 SET status = 'queued', available_at = %s, lease_expires_at = NULL,
                     replay_count = %d, last_replayed_at = %s, updated_at = %s
                 WHERE job_id = %s AND status = 'dead_letter' AND error_code = %s",
                $timestamp,
                $replayCount,
                $timestamp,
                $timestamp,
                $jobId,
                $expectedErrorCode
            ));
            if ($updated !== 1) {
                throw new InvariantViolation('Dead-letter replay transition failed.');
            }

            $this->db->query('COMMIT');

            return [
                'job_id' => $jobId,
                'status' => 'queued',
                'replay_count' => $replayCount,
                'replayed_at' => $replayedAt->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            ];
        } catch (Throwable $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        }
    }

    private function assertJobId(string $jobId): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $jobId) !== 1) {
            throw new InvariantViolation('Dead-letter job identifiers must be SHA-256 hex values.');
        }
    }

    private function assertErrorCode(string $errorCode): void
    {
        if (preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $errorCode) !== 1) {
            throw new InvariantViolation('Dead-letter error codes are invalid.');
        }
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
