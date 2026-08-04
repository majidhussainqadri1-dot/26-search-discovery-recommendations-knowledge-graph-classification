<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use Sabri\File26\Support\InvariantViolation;

final class AudienceContext
{
    /**
     * @param list<string> $capabilities
     * @param list<string> $entitlements
     */
    public function __construct(
        private readonly bool $authenticated,
        private readonly array $capabilities = [],
        private readonly array $entitlements = [],
        private readonly ?int $age = null,
        private readonly bool $guardianConsentVerified = false
    ) {
        $this->assertUniqueMachineKeys($capabilities, '/^[a-z][a-z0-9_]{2,99}$/', 'capability');
        $this->assertUniqueMachineKeys($entitlements, '/^[a-z][a-z0-9_.-]{2,99}$/', 'entitlement');

        if ($age !== null && ($age < 0 || $age > 130)) {
            throw new InvariantViolation('Audience age is outside the supported range.');
        }

        if (! $authenticated && ($capabilities !== [] || $entitlements !== [] || $age !== null || $guardianConsentVerified)) {
            throw new InvariantViolation('Anonymous audience context cannot carry authenticated assertions.');
        }
    }

    public static function guest(): self
    {
        return new self(false);
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function hasEntitlement(string $entitlement): bool
    {
        return in_array($entitlement, $this->entitlements, true);
    }

    public function age(): ?int
    {
        return $this->age;
    }

    public function hasVerifiedGuardianConsent(): bool
    {
        return $this->guardianConsentVerified;
    }

    /** @param list<string> $keys */
    private function assertUniqueMachineKeys(array $keys, string $pattern, string $label): void
    {
        if (count($keys) !== count(array_unique($keys))) {
            throw new InvariantViolation('Audience ' . $label . ' assertions must be unique.');
        }

        foreach ($keys as $key) {
            if (! is_string($key) || ! preg_match($pattern, $key)) {
                throw new InvariantViolation('Audience ' . $label . ' assertions must use stable machine keys.');
            }
        }
    }
}
