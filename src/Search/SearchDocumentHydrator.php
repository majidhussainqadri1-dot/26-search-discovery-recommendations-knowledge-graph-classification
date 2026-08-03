<?php

declare(strict_types=1);

namespace Sabri\File26\Search;

use DateTimeImmutable;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Support\InvariantViolation;
use Throwable;

final class SearchDocumentHydrator
{
    public function hydrate(string $payload): SearchDocument
    {
        if ($payload === '' || strlen($payload) > 100000) {
            throw new InvariantViolation('Stored search-document payloads must be non-empty and bounded.');
        }

        try {
            $data = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            unset($exception);
            throw new InvariantViolation('Stored search-document payload is invalid JSON.');
        }

        $expectedKeys = [
            'canonical_domain',
            'object_id',
            'object_version',
            'locale',
            'state',
            'canonical_url',
            'fields',
            'visibility',
            'last_source_event_at',
        ];

        if (! is_array($data) || array_keys($data) !== $expectedKeys) {
            throw new InvariantViolation('Stored search-document payload shape is invalid.');
        }

        foreach (['canonical_domain', 'object_id', 'object_version', 'locale', 'state', 'canonical_url', 'last_source_event_at'] as $key) {
            if (! is_string($data[$key])) {
                throw new InvariantViolation('Stored search-document scalar types are invalid.');
            }
        }

        if (! is_array($data['fields']) || ! is_array($data['visibility'])) {
            throw new InvariantViolation('Stored search-document maps are invalid.');
        }

        $visibility = $data['visibility'];
        $visibilityKeys = [
            'public',
            'required_capabilities',
            'required_entitlement',
            'minimum_age',
            'guardian_consent_required',
        ];
        if (array_keys($visibility) !== $visibilityKeys) {
            throw new InvariantViolation('Stored visibility payload shape is invalid.');
        }
        if (
            ! is_bool($visibility['public'])
            || ! is_array($visibility['required_capabilities'])
            || ($visibility['required_entitlement'] !== null && ! is_string($visibility['required_entitlement']))
            || ($visibility['minimum_age'] !== null && ! is_int($visibility['minimum_age']))
            || ! is_bool($visibility['guardian_consent_required'])
        ) {
            throw new InvariantViolation('Stored visibility payload types are invalid.');
        }

        foreach ($visibility['required_capabilities'] as $capability) {
            if (! is_string($capability)) {
                throw new InvariantViolation('Stored visibility capabilities must be strings.');
            }
        }

        try {
            $eventAt = new DateTimeImmutable($data['last_source_event_at']);
        } catch (Throwable $exception) {
            unset($exception);
            throw new InvariantViolation('Stored source-event date is invalid.');
        }

        return new SearchDocument(
            $data['canonical_domain'],
            $data['object_id'],
            $data['object_version'],
            $data['locale'],
            $data['state'],
            $data['canonical_url'],
            $data['fields'],
            new VisibilityEnvelope(
                $visibility['public'],
                $visibility['required_capabilities'],
                $visibility['required_entitlement'],
                $visibility['minimum_age'],
                $visibility['guardian_consent_required']
            ),
            $eventAt
        );
    }
}
