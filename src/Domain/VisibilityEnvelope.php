<?php

declare(strict_types=1);

namespace Sabri\File26\Domain;

use Sabri\File26\Support\InvariantViolation;

final class VisibilityEnvelope
{
    /**
     * @param list<string> $requiredCapabilities
     */
    public function __construct(
        private readonly bool $public,
        private readonly array $requiredCapabilities = [],
        private readonly ?string $requiredEntitlement = null,
        private readonly ?int $minimumAge = null,
        private readonly bool $guardianConsentRequired = false
    ) {
        if (count($requiredCapabilities) !== count(array_unique($requiredCapabilities))) {
            throw new InvariantViolation('Capabilities must be unique.');
        }

        foreach ($requiredCapabilities as $capability) {
            if (! is_string($capability) || ! preg_match('/^[a-z][a-z0-9_]{2,99}$/', $capability)) {
                throw new InvariantViolation('Capabilities must use stable lowercase machine keys.');
            }
        }

        if ($requiredEntitlement !== null && ! preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $requiredEntitlement)) {
            throw new InvariantViolation('Entitlements must use stable lowercase machine keys.');
        }

        if ($public && ($requiredCapabilities !== [] || $requiredEntitlement !== null)) {
            throw new InvariantViolation('Public documents cannot silently require a capability or entitlement.');
        }

        if ($minimumAge !== null && ($minimumAge < 0 || $minimumAge > 130)) {
            throw new InvariantViolation('Minimum age is outside the supported range.');
        }

        if ($public && ($minimumAge !== null || $guardianConsentRequired)) {
            throw new InvariantViolation('Age- or guardian-gated records cannot be marked public.');
        }
    }

    public static function public(): self
    {
        return new self(true);
    }

    /** @param list<string> $requiredCapabilities */
    public static function restricted(
        array $requiredCapabilities = [],
        ?string $requiredEntitlement = null,
        ?int $minimumAge = null,
        bool $guardianConsentRequired = false
    ): self {
        return new self(false, $requiredCapabilities, $requiredEntitlement, $minimumAge, $guardianConsentRequired);
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    /** @return list<string> */
    public function requiredCapabilities(): array
    {
        return $this->requiredCapabilities;
    }

    public function requiredEntitlement(): ?string
    {
        return $this->requiredEntitlement;
    }

    public function minimumAge(): ?int
    {
        return $this->minimumAge;
    }

    public function guardianConsentRequired(): bool
    {
        return $this->guardianConsentRequired;
    }

    /** @return array<string, bool|int|string|list<string>|null> */
    public function toArray(): array
    {
        return [
            'public' => $this->public,
            'required_capabilities' => $this->requiredCapabilities,
            'required_entitlement' => $this->requiredEntitlement,
            'minimum_age' => $this->minimumAge,
            'guardian_consent_required' => $this->guardianConsentRequired,
        ];
    }
}
