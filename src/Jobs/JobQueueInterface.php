<?php

declare(strict_types=1);

namespace Sabri\File26\Jobs;

use DateTimeImmutable;

interface JobQueueInterface
{
    public function enqueue(RebuildJob $job): void;

    public function claim(DateTimeImmutable $now): ?RebuildJob;

    public function acknowledge(string $jobId, DateTimeImmutable $completedAt): void;

    public function reschedule(RebuildJob $job, string $errorCode): void;

    public function deadLetter(RebuildJob $job, string $errorCode, DateTimeImmutable $failedAt): void;

    /** @return array{queued:int,running:int,completed:int,dead_letter:int} */
    public function stats(): array;
}
