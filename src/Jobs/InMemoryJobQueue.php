<?php

declare(strict_types=1);

namespace Sabri\File26\Jobs;

use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class InMemoryJobQueue implements JobQueueInterface
{
    /** @var array<string,array{job:RebuildJob,status:string,error_code:?string,updated_at:DateTimeImmutable}> */
    private array $jobs = [];

    public function enqueue(RebuildJob $job): void
    {
        $existing = $this->jobs[$job->jobId()] ?? null;
        if ($existing !== null) {
            $candidate = $existing['job'];
            if (
                $candidate->generationId() !== $job->generationId()
                || $candidate->connectorKey() !== $job->connectorKey()
                || $candidate->cursor() !== $job->cursor()
                || $candidate->mode() !== $job->mode()
                || $candidate->attempt() !== $job->attempt()
            ) {
                throw new InvariantViolation('A job identifier collision contains conflicting immutable job data.');
            }

            return;
        }

        $this->jobs[$job->jobId()] = [
            'job' => $job,
            'status' => 'queued',
            'error_code' => null,
            'updated_at' => $job->availableAt(),
        ];
    }

    public function claim(DateTimeImmutable $now): ?RebuildJob
    {
        $eligible = [];
        foreach ($this->jobs as $jobId => $row) {
            if ($row['status'] === 'queued' && $row['job']->availableAt() <= $now) {
                $eligible[$jobId] = $row['job'];
            }
        }

        if ($eligible === []) {
            return null;
        }

        uasort($eligible, static function (RebuildJob $left, RebuildJob $right): int {
            return ($left->availableAt() <=> $right->availableAt()) ?: ($left->jobId() <=> $right->jobId());
        });

        $job = reset($eligible);
        if (! $job instanceof RebuildJob) {
            return null;
        }

        $this->jobs[$job->jobId()]['status'] = 'running';
        $this->jobs[$job->jobId()]['updated_at'] = $now;

        return $job;
    }

    public function acknowledge(string $jobId, DateTimeImmutable $completedAt): void
    {
        $row = &$this->rowForWrite($jobId);
        if ($row['status'] !== 'running') {
            throw new InvariantViolation('Only a running job may be acknowledged.');
        }
        $row['status'] = 'completed';
        $row['updated_at'] = $completedAt;
        $row['error_code'] = null;
    }

    public function reschedule(RebuildJob $job, string $errorCode): void
    {
        $this->assertErrorCode($errorCode);
        $current = &$this->rowForWrite($job->attempt() === 0 ? $job->jobId() : $this->findRunningJobId($job));
        if ($current['status'] !== 'running') {
            throw new InvariantViolation('Only a running job may be rescheduled.');
        }

        $current['status'] = 'completed';
        $current['updated_at'] = $job->availableAt();
        $current['error_code'] = $errorCode;

        if (! isset($this->jobs[$job->jobId()])) {
            $this->jobs[$job->jobId()] = [
                'job' => $job,
                'status' => 'queued',
                'error_code' => $errorCode,
                'updated_at' => $job->availableAt(),
            ];
        } else {
            $this->jobs[$job->jobId()]['job'] = $job;
            $this->jobs[$job->jobId()]['status'] = 'queued';
            $this->jobs[$job->jobId()]['error_code'] = $errorCode;
            $this->jobs[$job->jobId()]['updated_at'] = $job->availableAt();
        }
    }

    public function deadLetter(RebuildJob $job, string $errorCode, DateTimeImmutable $failedAt): void
    {
        $this->assertErrorCode($errorCode);
        $jobId = $this->findRunningJobId($job);
        $row = &$this->rowForWrite($jobId);
        if ($row['status'] !== 'running') {
            throw new InvariantViolation('Only a running job may enter dead letter.');
        }
        $row['status'] = 'dead_letter';
        $row['error_code'] = $errorCode;
        $row['updated_at'] = $failedAt;
    }

    public function stats(): array
    {
        $stats = ['queued' => 0, 'running' => 0, 'completed' => 0, 'dead_letter' => 0];
        foreach ($this->jobs as $row) {
            if (isset($stats[$row['status']])) {
                ++$stats[$row['status']];
            }
        }

        return $stats;
    }

    /** @return array{job:RebuildJob,status:string,error_code:?string,updated_at:DateTimeImmutable} */
    private function &rowForWrite(string $jobId): array
    {
        if (! isset($this->jobs[$jobId])) {
            throw new InvariantViolation('Unknown job identifier.');
        }

        return $this->jobs[$jobId];
    }

    private function findRunningJobId(RebuildJob $replacement): string
    {
        foreach ($this->jobs as $jobId => $row) {
            $candidate = $row['job'];
            if (
                $row['status'] === 'running'
                && $candidate->generationId() === $replacement->generationId()
                && $candidate->connectorKey() === $replacement->connectorKey()
                && $candidate->cursor() === $replacement->cursor()
                && $candidate->mode() === $replacement->mode()
            ) {
                return $jobId;
            }
        }

        throw new InvariantViolation('No matching running job exists for retry or dead letter.');
    }

    private function assertErrorCode(string $errorCode): void
    {
        if (! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $errorCode)) {
            throw new InvariantViolation('Job error codes must be stable bounded lowercase keys.');
        }
    }
}
