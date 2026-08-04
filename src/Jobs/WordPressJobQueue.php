<?php

declare(strict_types=1);

namespace Sabri\File26\Jobs;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Support\InvariantViolation;
use Throwable;
use wpdb;

final class WordPressJobQueue implements JobQueueInterface
{
    private readonly string $table;

    public function __construct(private readonly wpdb $db)
    {
        $this->table = $db->prefix . 's26_jobs';
    }

    public function enqueue(RebuildJob $job): void
    {
        $timestamp = $this->utc(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        if ($job->cursor() === null) {
            $sql = $this->db->prepare(
                "INSERT IGNORE INTO {$this->table}
                    (job_id, generation_id, connector_key, cursor_value, mode, attempt, available_at, status, created_at, updated_at)
                 VALUES (%s, %s, %s, NULL, %s, %d, %s, 'queued', %s, %s)",
                $job->jobId(),
                $job->generationId(),
                $job->connectorKey(),
                $job->mode(),
                $job->attempt(),
                $this->utc($job->availableAt()),
                $timestamp,
                $timestamp
            );
        } else {
            $sql = $this->db->prepare(
                "INSERT IGNORE INTO {$this->table}
                    (job_id, generation_id, connector_key, cursor_value, mode, attempt, available_at, status, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, %d, %s, 'queued', %s, %s)",
                $job->jobId(),
                $job->generationId(),
                $job->connectorKey(),
                $job->cursor(),
                $job->mode(),
                $job->attempt(),
                $this->utc($job->availableAt()),
                $timestamp,
                $timestamp
            );
        }

        $inserted = $this->db->query($sql);
        if ($inserted === false) {
            throw new InvariantViolation('Persistent job enqueue failed.');
        }
    }

    public function claim(DateTimeImmutable $now): ?RebuildJob
    {
        $nowValue = $this->utc($now);
        $leaseExpiry = $this->utc($now->add(new DateInterval('PT10M')));
        $this->db->query('START TRANSACTION');
        try {
            $row = $this->db->get_row($this->db->prepare(
                "SELECT * FROM {$this->table}
                 WHERE (status = 'queued' AND available_at <= %s)
                    OR (status = 'running' AND lease_expires_at <= %s)
                 ORDER BY available_at ASC, job_id ASC
                 LIMIT 1 FOR UPDATE",
                $nowValue,
                $nowValue
            ), ARRAY_A);

            if (! is_array($row)) {
                $this->db->query('COMMIT');
                return null;
            }

            $updated = $this->db->query($this->db->prepare(
                "UPDATE {$this->table} SET status = 'running', lease_expires_at = %s, updated_at = %s WHERE job_id = %s",
                $leaseExpiry,
                $nowValue,
                (string) $row['job_id']
            ));
            if ($updated !== 1) {
                throw new InvariantViolation('Persistent job claim failed.');
            }
            $this->db->query('COMMIT');

            $storedCursor = $row['cursor_value'] ?? null;
            $cursor = $storedCursor === null || $storedCursor === '' ? null : (string) $storedCursor;

            return new RebuildJob(
                (string) $row['job_id'],
                (string) $row['generation_id'],
                (string) $row['connector_key'],
                $cursor,
                (string) $row['mode'],
                (int) $row['attempt'],
                new DateTimeImmutable((string) $row['available_at'], new DateTimeZone('UTC'))
            );
        } catch (Throwable $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        }
    }

    public function acknowledge(string $jobId, DateTimeImmutable $completedAt): void
    {
        $updated = $this->db->query($this->db->prepare(
            "UPDATE {$this->table}
             SET status = 'completed', lease_expires_at = NULL, error_code = NULL, updated_at = %s
             WHERE job_id = %s AND status = 'running'",
            $this->utc($completedAt),
            $jobId
        ));
        if ($updated !== 1) {
            throw new InvariantViolation('Persistent running job acknowledgement failed.');
        }
    }

    public function reschedule(RebuildJob $job, string $errorCode): void
    {
        $this->assertErrorCode($errorCode);
        $this->db->query('START TRANSACTION');
        try {
            if ($job->cursor() === null) {
                $current = $this->db->get_var($this->db->prepare(
                    "SELECT job_id FROM {$this->table}
                     WHERE generation_id = %s AND connector_key = %s AND mode = %s
                       AND (cursor_value IS NULL OR cursor_value = '') AND status = 'running'
                     ORDER BY attempt DESC LIMIT 1 FOR UPDATE",
                    $job->generationId(),
                    $job->connectorKey(),
                    $job->mode()
                ));
            } else {
                $current = $this->db->get_var($this->db->prepare(
                    "SELECT job_id FROM {$this->table}
                     WHERE generation_id = %s AND connector_key = %s AND mode = %s
                       AND cursor_value = %s AND status = 'running'
                     ORDER BY attempt DESC LIMIT 1 FOR UPDATE",
                    $job->generationId(),
                    $job->connectorKey(),
                    $job->mode(),
                    $job->cursor()
                ));
            }
            if (! is_string($current) || $current === '') {
                throw new InvariantViolation('No matching persistent running job exists for reschedule.');
            }

            $this->db->query($this->db->prepare(
                "UPDATE {$this->table} SET status = 'completed', lease_expires_at = NULL, error_code = %s, updated_at = %s WHERE job_id = %s",
                $errorCode,
                $this->utc($job->availableAt()),
                $current
            ));

            if ($job->cursor() === null) {
                $insertSql = $this->db->prepare(
                    "INSERT INTO {$this->table}
                        (job_id, generation_id, connector_key, cursor_value, mode, attempt, available_at, status, error_code, created_at, updated_at)
                     VALUES (%s, %s, %s, NULL, %s, %d, %s, 'queued', %s, %s, %s)
                     ON DUPLICATE KEY UPDATE
                        available_at = VALUES(available_at), status = 'queued', error_code = VALUES(error_code),
                        lease_expires_at = NULL, updated_at = VALUES(updated_at)",
                    $job->jobId(),
                    $job->generationId(),
                    $job->connectorKey(),
                    $job->mode(),
                    $job->attempt(),
                    $this->utc($job->availableAt()),
                    $errorCode,
                    $this->utc($job->availableAt()),
                    $this->utc($job->availableAt())
                );
            } else {
                $insertSql = $this->db->prepare(
                    "INSERT INTO {$this->table}
                        (job_id, generation_id, connector_key, cursor_value, mode, attempt, available_at, status, error_code, created_at, updated_at)
                     VALUES (%s, %s, %s, %s, %s, %d, %s, 'queued', %s, %s, %s)
                     ON DUPLICATE KEY UPDATE
                        available_at = VALUES(available_at), status = 'queued', error_code = VALUES(error_code),
                        lease_expires_at = NULL, updated_at = VALUES(updated_at)",
                    $job->jobId(),
                    $job->generationId(),
                    $job->connectorKey(),
                    $job->cursor(),
                    $job->mode(),
                    $job->attempt(),
                    $this->utc($job->availableAt()),
                    $errorCode,
                    $this->utc($job->availableAt()),
                    $this->utc($job->availableAt())
                );
            }

            $inserted = $this->db->query($insertSql);
            if ($inserted === false) {
                throw new InvariantViolation('Persistent retry job insertion failed.');
            }

            $this->db->query('COMMIT');
        } catch (Throwable $exception) {
            $this->db->query('ROLLBACK');
            throw $exception;
        }
    }

    public function deadLetter(RebuildJob $job, string $errorCode, DateTimeImmutable $failedAt): void
    {
        $this->assertErrorCode($errorCode);
        $updated = $this->db->query($this->db->prepare(
            "UPDATE {$this->table}
             SET status = 'dead_letter', lease_expires_at = NULL, error_code = %s, updated_at = %s
             WHERE job_id = %s AND status = 'running'",
            $errorCode,
            $this->utc($failedAt),
            $job->jobId()
        ));
        if ($updated !== 1) {
            throw new InvariantViolation('Persistent running job dead-letter transition failed.');
        }
    }

    public function stats(): array
    {
        $stats = ['queued' => 0, 'running' => 0, 'completed' => 0, 'dead_letter' => 0];
        $rows = $this->db->get_results("SELECT status, COUNT(*) AS total FROM {$this->table} GROUP BY status", ARRAY_A);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $status = (string) $row['status'];
            if (isset($stats[$status])) {
                $stats[$status] = (int) $row['total'];
            }
        }

        return $stats;
    }

    private function assertErrorCode(string $errorCode): void
    {
        if (! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $errorCode)) {
            throw new InvariantViolation('Job error codes must be stable bounded lowercase keys.');
        }
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
