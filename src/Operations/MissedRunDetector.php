<?php

declare(strict_types=1);

namespace Sabri\File26\Operations;

use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class MissedRunDetector
{
    /** @return array{status:string,missed:bool,lag_seconds:int,pending_jobs:int} */
    public function inspect(
        ?DateTimeImmutable $lastRunAt,
        DateTimeImmutable $now,
        int $pendingJobs,
        int $thresholdSeconds = 900
    ): array {
        if ($pendingJobs < 0 || $pendingJobs > 100000000) {
            throw new InvariantViolation('Pending job counts are outside the supported range.');
        }
        if ($thresholdSeconds < 60 || $thresholdSeconds > 86400) {
            throw new InvariantViolation('Missed-run thresholds must be between 60 seconds and 24 hours.');
        }

        if ($pendingJobs === 0) {
            return ['status' => 'idle', 'missed' => false, 'lag_seconds' => 0, 'pending_jobs' => 0];
        }

        if ($lastRunAt === null) {
            return ['status' => 'never-ran', 'missed' => true, 'lag_seconds' => $thresholdSeconds, 'pending_jobs' => $pendingJobs];
        }

        $lag = $now->getTimestamp() - $lastRunAt->getTimestamp();
        if ($lag < 0) {
            throw new InvariantViolation('Last worker run cannot be in the future.');
        }

        return [
            'status' => $lag > $thresholdSeconds ? 'overdue' : 'on-time',
            'missed' => $lag > $thresholdSeconds,
            'lag_seconds' => $lag,
            'pending_jobs' => $pendingJobs,
        ];
    }
}
