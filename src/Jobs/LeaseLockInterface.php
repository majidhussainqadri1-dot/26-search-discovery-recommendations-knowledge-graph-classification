<?php

declare(strict_types=1);

namespace Sabri\File26\Jobs;

use DateTimeImmutable;

interface LeaseLockInterface
{
    public function acquire(string $key, string $token, DateTimeImmutable $now, int $ttlSeconds): bool;

    public function renew(string $key, string $token, DateTimeImmutable $now, int $ttlSeconds): bool;

    public function release(string $key, string $token): void;
}
