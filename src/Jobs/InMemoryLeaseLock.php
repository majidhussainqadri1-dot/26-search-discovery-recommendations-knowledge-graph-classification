<?php

declare(strict_types=1);

namespace Sabri\File26\Jobs;

use DateInterval;
use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class InMemoryLeaseLock implements LeaseLockInterface
{
    /** @var array<string,array{token:string,expires_at:DateTimeImmutable}> */
    private array $locks = [];

    public function acquire(string $key, string $token, DateTimeImmutable $now, int $ttlSeconds): bool
    {
        $this->validate($key, $token, $ttlSeconds);
        $existing = $this->locks[$key] ?? null;
        if ($existing !== null && $existing['expires_at'] > $now && $existing['token'] !== $token) {
            return false;
        }

        $this->locks[$key] = [
            'token' => $token,
            'expires_at' => $now->add(new DateInterval('PT' . $ttlSeconds . 'S')),
        ];

        return true;
    }

    public function renew(string $key, string $token, DateTimeImmutable $now, int $ttlSeconds): bool
    {
        $this->validate($key, $token, $ttlSeconds);
        $existing = $this->locks[$key] ?? null;
        if ($existing === null || $existing['token'] !== $token || $existing['expires_at'] <= $now) {
            return false;
        }

        $this->locks[$key]['expires_at'] = $now->add(new DateInterval('PT' . $ttlSeconds . 'S'));

        return true;
    }

    public function release(string $key, string $token): void
    {
        $this->validate($key, $token, 1);
        if (($this->locks[$key]['token'] ?? null) === $token) {
            unset($this->locks[$key]);
        }
    }

    private function validate(string $key, string $token, int $ttlSeconds): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9:._-]{2,190}$/', $key)) {
            throw new InvariantViolation('Lease lock keys must be stable bounded lowercase keys.');
        }
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new InvariantViolation('Lease tokens must be SHA-256 hexadecimal values.');
        }
        if ($ttlSeconds < 1 || $ttlSeconds > 3600) {
            throw new InvariantViolation('Lease TTL must be between 1 and 3600 seconds.');
        }
    }
}
