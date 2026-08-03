<?php

declare(strict_types=1);

namespace Sabri\File26\Jobs;

use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class RebuildJob
{
    public function __construct(
        private readonly string $jobId,
        private readonly string $generationId,
        private readonly string $connectorKey,
        private readonly ?string $cursor,
        private readonly string $mode,
        private readonly int $attempt,
        private readonly DateTimeImmutable $availableAt
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/', $jobId)) {
            throw new InvariantViolation('Job IDs must be SHA-256 hexadecimal identifiers.');
        }
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $generationId)) {
            throw new InvariantViolation('Job generation IDs must be stable bounded lowercase keys.');
        }
        if (! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $connectorKey)) {
            throw new InvariantViolation('Job connector keys must be stable bounded lowercase keys.');
        }
        if ($cursor !== null && (trim($cursor) === '' || strlen($cursor) > 512)) {
            throw new InvariantViolation('Job cursors must be null or bounded non-empty opaque values.');
        }
        if (! in_array($mode, ['full', 'partial', 'delta'], true)) {
            throw new InvariantViolation('Job mode must be full, partial or delta.');
        }
        if ($attempt < 0 || $attempt > 50) {
            throw new InvariantViolation('Job attempt is outside the supported range.');
        }
    }

    public static function create(
        string $generationId,
        string $connectorKey,
        ?string $cursor,
        string $mode,
        int $attempt,
        DateTimeImmutable $availableAt
    ): self {
        $identity = implode('|', [$generationId, $connectorKey, $cursor ?? '<start>', $mode, (string) $attempt]);

        return new self(hash('sha256', $identity), $generationId, $connectorKey, $cursor, $mode, $attempt, $availableAt);
    }

    public function retry(DateTimeImmutable $availableAt): self
    {
        return self::create(
            $this->generationId,
            $this->connectorKey,
            $this->cursor,
            $this->mode,
            $this->attempt + 1,
            $availableAt
        );
    }

    public function reschedule(DateTimeImmutable $availableAt): self
    {
        return new self(
            $this->jobId,
            $this->generationId,
            $this->connectorKey,
            $this->cursor,
            $this->mode,
            $this->attempt,
            $availableAt
        );
    }

    public function jobId(): string { return $this->jobId; }
    public function generationId(): string { return $this->generationId; }
    public function connectorKey(): string { return $this->connectorKey; }
    public function cursor(): ?string { return $this->cursor; }
    public function mode(): string { return $this->mode; }
    public function attempt(): int { return $this->attempt; }
    public function availableAt(): DateTimeImmutable { return $this->availableAt; }
}
