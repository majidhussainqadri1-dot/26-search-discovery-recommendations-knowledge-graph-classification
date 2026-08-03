<?php

declare(strict_types=1);

namespace Sabri\File26\Domain;

use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class IndexTombstone
{
    private const REASONS = [
        'deleted',
        'restricted',
        'suspended',
        'merged',
        'owner-retired',
    ];

    public function __construct(
        private readonly string $canonicalDomain,
        private readonly string $objectId,
        private readonly string $lastObjectVersion,
        private readonly string $reason,
        private readonly DateTimeImmutable $receivedAt,
        private readonly ?DateTimeImmutable $purgeAfter = null
    ) {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $canonicalDomain)) {
            throw new InvariantViolation('Canonical domain must be a stable lowercase machine key.');
        }

        if (trim($objectId) === '' || strlen($objectId) > 191) {
            throw new InvariantViolation('Object ID must be non-empty and bounded.');
        }

        if (trim($lastObjectVersion) === '' || strlen($lastObjectVersion) > 100) {
            throw new InvariantViolation('Last object version must be non-empty and bounded.');
        }

        if (! in_array($reason, self::REASONS, true)) {
            throw new InvariantViolation('Unsupported tombstone reason.');
        }

        if ($purgeAfter !== null && $purgeAfter <= $receivedAt) {
            throw new InvariantViolation('Tombstone purge time must be after receipt time.');
        }
    }

    public function canonicalKey(): string
    {
        return $this->canonicalDomain . ':' . $this->objectId;
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'canonical_domain' => $this->canonicalDomain,
            'object_id' => $this->objectId,
            'last_object_version' => $this->lastObjectVersion,
            'reason' => $this->reason,
            'received_at' => $this->receivedAt->format(DATE_ATOM),
            'purge_after' => $this->purgeAfter?->format(DATE_ATOM),
        ];
    }
}
