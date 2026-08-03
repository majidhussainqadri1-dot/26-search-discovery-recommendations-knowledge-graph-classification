<?php

declare(strict_types=1);

namespace Sabri\File26\Operations;

use DateTimeImmutable;

interface DeadLetterOperationsInterface
{
    /**
     * @return list<array{
     *   job_id:string,generation_id:string,connector_key:string,attempt:int,
     *   error_code:string,replay_count:int,updated_at:string
     * }>
     */
    public function deadLetters(int $limit = 20): array;

    /**
     * Replay only when the caller supplies the exact current error code.
     *
     * @return array{job_id:string,status:string,replay_count:int,replayed_at:string}
     */
    public function replay(
        string $jobId,
        string $expectedErrorCode,
        DateTimeImmutable $replayedAt
    ): array;
}
