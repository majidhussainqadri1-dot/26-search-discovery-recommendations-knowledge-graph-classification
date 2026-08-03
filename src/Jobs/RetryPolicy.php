<?php

declare(strict_types=1);

namespace Sabri\File26\Jobs;

use DateInterval;
use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class RetryPolicy
{
    /** @param list<int> $delaysSeconds */
    public function __construct(private readonly array $delaysSeconds = [60, 300, 1800, 7200, 43200])
    {
        if ($delaysSeconds === [] || count($delaysSeconds) > 10 || ! array_is_list($delaysSeconds)) {
            throw new InvariantViolation('Retry policy delays must be a non-empty bounded list.');
        }

        $previous = 0;
        foreach ($delaysSeconds as $delay) {
            if (! is_int($delay) || $delay < 1 || $delay > 604800 || $delay < $previous) {
                throw new InvariantViolation('Retry delays must be ascending positive seconds within one week.');
            }
            $previous = $delay;
        }
    }

    public function canRetry(int $currentAttempt): bool
    {
        return $currentAttempt < count($this->delaysSeconds);
    }

    public function nextAvailableAt(int $currentAttempt, DateTimeImmutable $now): DateTimeImmutable
    {
        if (! $this->canRetry($currentAttempt)) {
            throw new InvariantViolation('Retry policy is exhausted for this job.');
        }

        return $now->add(new DateInterval('PT' . $this->delaysSeconds[$currentAttempt] . 'S'));
    }

    public function maximumRetries(): int
    {
        return count($this->delaysSeconds);
    }
}
