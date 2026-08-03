<?php

declare(strict_types=1);

namespace Sabri\File26\Jobs;

use DateInterval;
use DateTimeImmutable;
use Sabri\File26\Registry\ConnectorRegistry;
use Sabri\File26\Storage\ShadowStoreInterface;
use Sabri\File26\Support\InvariantViolation;
use Throwable;

final class RebuildWorker
{
    private const LOCK_TTL_SECONDS = 600;

    public function __construct(
        private readonly ConnectorRegistry $registry,
        private readonly ShadowStoreInterface $store,
        private readonly JobQueueInterface $queue,
        private readonly LeaseLockInterface $locks,
        private readonly RetryPolicy $retryPolicy = new RetryPolicy()
    ) {
    }

    /** @return array{status:string,job_id:?string,error_code:?string} */
    public function runOne(DateTimeImmutable $now, int $batchLimit = 100): array
    {
        if ($batchLimit < 1 || $batchLimit > 200) {
            throw new InvariantViolation('Rebuild worker batch limit must be between 1 and 200.');
        }

        $job = $this->queue->claim($now);
        if ($job === null) {
            return ['status' => 'idle', 'job_id' => null, 'error_code' => null];
        }

        $checkpoint = $this->store->checkpoint($job->generationId(), $job->connectorKey());
        if ($checkpoint === null) {
            $this->queue->deadLetter($job, 'checkpoint-missing', $now);

            return ['status' => 'dead-letter', 'job_id' => $job->jobId(), 'error_code' => 'checkpoint-missing'];
        }

        $expectedCursor = $checkpoint['cursor'] === '<start>' ? null : $checkpoint['cursor'];
        if ($checkpoint['complete'] || $expectedCursor !== $job->cursor()) {
            $this->queue->acknowledge($job->jobId(), $now);

            return ['status' => 'stale-skipped', 'job_id' => $job->jobId(), 'error_code' => null];
        }

        $lockKey = 'file26:rebuild:' . $job->generationId() . ':' . $job->connectorKey();
        $token = hash('sha256', $job->jobId() . '|' . $now->format('U.u'));
        if (! $this->locks->acquire($lockKey, $token, $now, self::LOCK_TTL_SECONDS)) {
            $this->queue->reschedule($job->reschedule($now->add(new DateInterval('PT15S'))), 'lease-busy');

            return ['status' => 'rescheduled', 'job_id' => $job->jobId(), 'error_code' => 'lease-busy'];
        }

        try {
            $connector = $this->registry->get($job->connectorKey());
            $batch = $connector->fetchBatch($job->cursor(), $batchLimit);
            $this->store->applyBatch($job->generationId(), $job->connectorKey(), $batch);

            if ($batch->hasMore()) {
                $nextCursor = $batch->nextCursor();
                if ($nextCursor === null) {
                    throw new InvariantViolation('Continuing connector batches require a cursor.');
                }
                $this->store->saveCheckpoint($job->generationId(), $job->connectorKey(), $nextCursor, false, $now);
                $this->queue->enqueue(RebuildJob::create(
                    $job->generationId(),
                    $job->connectorKey(),
                    $nextCursor,
                    $job->mode(),
                    0,
                    $now
                ));
            } else {
                $this->store->saveCheckpoint($job->generationId(), $job->connectorKey(), null, true, $now);
            }

            $this->queue->acknowledge($job->jobId(), $now);

            return ['status' => $batch->hasMore() ? 'checkpointed' : 'completed', 'job_id' => $job->jobId(), 'error_code' => null];
        } catch (Throwable $exception) {
            $errorCode = $this->safeErrorCode($exception);
            if ($this->retryPolicy->canRetry($job->attempt())) {
                $retryAt = $this->retryPolicy->nextAvailableAt($job->attempt(), $now);
                $this->queue->reschedule($job->retry($retryAt), $errorCode);

                return ['status' => 'retry', 'job_id' => $job->jobId(), 'error_code' => $errorCode];
            }

            $this->queue->deadLetter($job, $errorCode, $now);

            return ['status' => 'dead-letter', 'job_id' => $job->jobId(), 'error_code' => $errorCode];
        } finally {
            $this->locks->release($lockKey, $token);
        }
    }

    private function safeErrorCode(Throwable $exception): string
    {
        if ($exception instanceof InvariantViolation) {
            return 'contract-invariant-failed';
        }

        return 'connector-execution-failed';
    }
}
