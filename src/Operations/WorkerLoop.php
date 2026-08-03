<?php

declare(strict_types=1);

namespace Sabri\File26\Operations;

use DateTimeImmutable;
use Sabri\File26\Jobs\RebuildWorker;
use Sabri\File26\Support\InvariantViolation;

final class WorkerLoop
{
    public function __construct(private readonly RebuildWorker $worker)
    {
    }

    /** @return array{processed:int,idle:bool,status_counts:array<string,int>,last_error_code:?string} */
    public function run(DateTimeImmutable $now, int $maximumJobs = 10, int $batchLimit = 100): array
    {
        if ($maximumJobs < 1 || $maximumJobs > 50) {
            throw new InvariantViolation('Worker loops may process between 1 and 50 jobs per run.');
        }
        if ($batchLimit < 1 || $batchLimit > 200) {
            throw new InvariantViolation('Worker loop batch limits must be between 1 and 200.');
        }

        $counts = [];
        $processed = 0;
        $idle = false;
        $lastErrorCode = null;

        for ($index = 0; $index < $maximumJobs; ++$index) {
            $result = $this->worker->runOne($now, $batchLimit);
            $status = $result['status'];
            if (! is_string($status) || preg_match('/^[a-z][a-z-]{1,49}$/', $status) !== 1) {
                throw new InvariantViolation('Worker returned an invalid status code.');
            }

            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $lastErrorCode = $result['error_code'];
            if ($status === 'idle') {
                $idle = true;
                break;
            }
            ++$processed;
        }

        ksort($counts);

        return [
            'processed' => $processed,
            'idle' => $idle,
            'status_counts' => $counts,
            'last_error_code' => $lastErrorCode,
        ];
    }
}
