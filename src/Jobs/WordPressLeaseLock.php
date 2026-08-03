<?php

declare(strict_types=1);

namespace Sabri\File26\Jobs;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Sabri\File26\Support\InvariantViolation;
use wpdb;

final class WordPressLeaseLock implements LeaseLockInterface
{
    private readonly string $table;

    public function __construct(private readonly wpdb $db)
    {
        $this->table = $db->prefix . 's26_locks';
    }

    public function acquire(string $key, string $token, DateTimeImmutable $now, int $ttlSeconds): bool
    {
        $this->validate($key, $token, $ttlSeconds);
        $expires = $this->utc($now->add(new DateInterval('PT' . $ttlSeconds . 'S')));
        $nowValue = $this->utc($now);

        $inserted = $this->db->query($this->db->prepare(
            "INSERT IGNORE INTO {$this->table} (lock_key, token, expires_at) VALUES (%s, %s, %s)",
            $key,
            $token,
            $expires
        ));
        if ($inserted === 1) {
            return true;
        }

        $updated = $this->db->query($this->db->prepare(
            "UPDATE {$this->table} SET token = %s, expires_at = %s WHERE lock_key = %s AND expires_at <= %s",
            $token,
            $expires,
            $key,
            $nowValue
        ));

        return $updated === 1;
    }

    public function renew(string $key, string $token, DateTimeImmutable $now, int $ttlSeconds): bool
    {
        $this->validate($key, $token, $ttlSeconds);
        $updated = $this->db->query($this->db->prepare(
            "UPDATE {$this->table} SET expires_at = %s WHERE lock_key = %s AND token = %s AND expires_at > %s",
            $this->utc($now->add(new DateInterval('PT' . $ttlSeconds . 'S'))),
            $key,
            $token,
            $this->utc($now)
        ));

        return $updated === 1;
    }

    public function release(string $key, string $token): void
    {
        $this->validate($key, $token, 1);
        $this->db->query($this->db->prepare(
            "DELETE FROM {$this->table} WHERE lock_key = %s AND token = %s",
            $key,
            $token
        ));
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

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
