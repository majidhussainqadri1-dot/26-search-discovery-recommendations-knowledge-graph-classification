<?php

declare(strict_types=1);

namespace Sabri\File26\Domain;

use DateTimeImmutable;
use Sabri\File26\Support\InvariantViolation;

final class SearchDocument
{
    private const STATES = [
        'published',
        'restricted',
        'corrected',
        'retracted',
        'suspended',
    ];

    /**
     * @param array<string, bool|int|float|string|list<string>|null> $fields
     */
    public function __construct(
        private readonly string $canonicalDomain,
        private readonly string $objectId,
        private readonly string $objectVersion,
        private readonly string $locale,
        private readonly string $state,
        private readonly string $canonicalUrl,
        private readonly array $fields,
        private readonly VisibilityEnvelope $visibility,
        private readonly DateTimeImmutable $lastSourceEventAt
    ) {
        if (! preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $canonicalDomain)) {
            throw new InvariantViolation('Canonical domain must be a stable lowercase machine key.');
        }

        if (trim($objectId) === '' || strlen($objectId) > 191) {
            throw new InvariantViolation('Object ID must be non-empty and bounded.');
        }

        if (trim($objectVersion) === '' || strlen($objectVersion) > 100) {
            throw new InvariantViolation('Object version must be non-empty and bounded.');
        }

        if (! preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/', $locale)) {
            throw new InvariantViolation('Locale must be a valid bounded language tag.');
        }

        if (! in_array($state, self::STATES, true)) {
            throw new InvariantViolation('Unsupported search-document state.');
        }

        if (! filter_var($canonicalUrl, FILTER_VALIDATE_URL)) {
            throw new InvariantViolation('Canonical URL must be an absolute valid URL.');
        }

        $urlParts = parse_url($canonicalUrl);
        if (! is_array($urlParts) || ($urlParts['scheme'] ?? null) !== 'https' || isset($urlParts['user']) || isset($urlParts['pass'])) {
            throw new InvariantViolation('Canonical URL must use HTTPS and must not contain credentials.');
        }

        if ($fields === [] || count($fields) > 64) {
            throw new InvariantViolation('A search document requires 1 to 64 approved fields.');
        }

        foreach ($fields as $field => $value) {
            if (! is_string($field) || ! preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $field)) {
                throw new InvariantViolation('Search field keys must be stable lowercase machine keys.');
            }

            if (is_array($value)) {
                if (count($value) > 100) {
                    throw new InvariantViolation('Search field lists must be bounded.');
                }

                foreach ($value as $item) {
                    if (! is_string($item) || strlen($item) > 2000) {
                        throw new InvariantViolation('Search field lists may contain bounded strings only.');
                    }
                }

                continue;
            }

            if (! is_bool($value) && ! is_int($value) && ! is_float($value) && ! is_string($value) && $value !== null) {
                throw new InvariantViolation('Search fields may contain bounded scalar values or string lists only.');
            }

            if (is_string($value) && strlen($value) > 10000) {
                throw new InvariantViolation('Search field strings must be bounded.');
            }
        }
    }

    public function canonicalKey(): string
    {
        return $this->canonicalDomain . ':' . $this->objectId;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function visibility(): VisibilityEnvelope
    {
        return $this->visibility;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'canonical_domain' => $this->canonicalDomain,
            'object_id' => $this->objectId,
            'object_version' => $this->objectVersion,
            'locale' => str_replace('_', '-', $this->locale),
            'state' => $this->state,
            'canonical_url' => $this->canonicalUrl,
            'fields' => $this->fields,
            'visibility' => $this->visibility->toArray(),
            'last_source_event_at' => $this->lastSourceEventAt->format(DATE_ATOM),
        ];
    }
}
