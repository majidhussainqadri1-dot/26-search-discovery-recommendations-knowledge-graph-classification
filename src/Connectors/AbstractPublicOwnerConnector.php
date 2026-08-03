<?php

declare(strict_types=1);

namespace Sabri\File26\Connectors;

use DateTimeImmutable;
use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Contracts\ConnectorInterface;
use Sabri\File26\Contracts\SourceBatchProviderInterface;
use Sabri\File26\Domain\IndexTombstone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Support\InvariantViolation;
use Throwable;

abstract class AbstractPublicOwnerConnector implements ConnectorInterface
{
    private const MAX_BATCH_SIZE = 200;
    private const MAX_CURSOR_LENGTH = 512;

    public function __construct(private readonly SourceBatchProviderInterface $provider)
    {
    }

    final public function fetchBatch(?string $cursor, int $limit): ConnectorBatch
    {
        if ($limit < 1 || $limit > self::MAX_BATCH_SIZE) {
            throw new InvariantViolation('Connector batch limit must be between 1 and 200.');
        }

        if ($cursor !== null && (trim($cursor) === '' || strlen($cursor) > self::MAX_CURSOR_LENGTH)) {
            throw new InvariantViolation('Connector cursor must be null or a bounded non-empty opaque value.');
        }

        $payload = $this->provider->fetch($cursor, $limit);
        $allowedPayloadKeys = ['records', 'tombstones', 'next_cursor', 'has_more'];
        foreach (array_keys($payload) as $payloadKey) {
            if (! in_array($payloadKey, $allowedPayloadKeys, true)) {
                throw new InvariantViolation('Owner provider returned an undocumented batch field.');
            }
        }

        $records = $payload['records'] ?? null;
        $tombstones = $payload['tombstones'] ?? [];
        $nextCursor = $payload['next_cursor'] ?? null;
        $hasMore = $payload['has_more'] ?? null;

        if (! is_array($records) || ! array_is_list($records)) {
            throw new InvariantViolation('Owner provider records must be a list.');
        }

        if (! is_array($tombstones) || ! array_is_list($tombstones)) {
            throw new InvariantViolation('Owner provider tombstones must be a list.');
        }

        if (count($records) + count($tombstones) > $limit) {
            throw new InvariantViolation('Owner provider exceeded the requested bounded batch size.');
        }

        if (! is_bool($hasMore)) {
            throw new InvariantViolation('Owner provider has_more must be boolean.');
        }

        if ($nextCursor !== null && ! is_string($nextCursor)) {
            throw new InvariantViolation('Owner provider next_cursor must be null or a string.');
        }

        $documents = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                throw new InvariantViolation('Owner provider records must contain arrays only.');
            }
            $documents[] = $this->mapRecord($record);
        }

        $mappedTombstones = [];
        foreach ($tombstones as $tombstone) {
            if (! is_array($tombstone)) {
                throw new InvariantViolation('Owner provider tombstones must contain arrays only.');
            }
            $mappedTombstones[] = $this->mapTombstone($tombstone);
        }

        return new ConnectorBatch($documents, $nextCursor, $hasMore, $mappedTombstones);
    }

    final public function health(): array
    {
        return $this->provider->health();
    }

    /** @return list<string> */
    abstract protected function allowedFieldKeys(): array;

    /** @return list<string> */
    protected function requiredFieldKeys(): array
    {
        return ['title'];
    }

    abstract protected function canonicalDomain(): string;

    /** @param array<string,mixed> $record */
    private function mapRecord(array $record): SearchDocument
    {
        $allowedRecordKeys = [
            'object_id',
            'object_version',
            'locale',
            'state',
            'canonical_url',
            'fields',
            'source_event_at',
        ];

        foreach (array_keys($record) as $recordKey) {
            if (! in_array($recordKey, $allowedRecordKeys, true)) {
                throw new InvariantViolation('Owner record contains an undocumented field.');
            }
        }

        foreach ($allowedRecordKeys as $requiredKey) {
            if (! array_key_exists($requiredKey, $record)) {
                throw new InvariantViolation('Owner record is missing a required field.');
            }
        }

        if (! is_string($record['state']) || ! in_array($record['state'], ['published', 'corrected'], true)) {
            throw new InvariantViolation('Public connectors accept published or corrected records only; other states require tombstones.');
        }

        if (! is_array($record['fields']) || ! $record['fields']) {
            throw new InvariantViolation('Owner record fields must be a non-empty map.');
        }

        $allowedFields = $this->allowedFieldKeys();
        foreach (array_keys($record['fields']) as $fieldKey) {
            if (! is_string($fieldKey) || ! in_array($fieldKey, $allowedFields, true)) {
                throw new InvariantViolation('Owner record contains a field outside the approved connector allowlist.');
            }
        }

        foreach ($this->requiredFieldKeys() as $requiredField) {
            if (! array_key_exists($requiredField, $record['fields'])) {
                throw new InvariantViolation('Owner record is missing a connector-required search field.');
            }
        }

        if (! is_string($record['canonical_url'])) {
            throw new InvariantViolation('Owner record canonical_url must be a string.');
        }
        $this->assertCanonicalHost($record['canonical_url']);

        try {
            $sourceEventAt = new DateTimeImmutable((string) $record['source_event_at']);
        } catch (Throwable $exception) {
            unset($exception);
            throw new InvariantViolation('Owner record source_event_at must be a valid immutable date-time.');
        }

        return new SearchDocument(
            $this->canonicalDomain(),
            (string) $record['object_id'],
            (string) $record['object_version'],
            (string) $record['locale'],
            $record['state'],
            $record['canonical_url'],
            $record['fields'],
            new VisibilityEnvelope(true),
            $sourceEventAt
        );
    }

    /** @param array<string,mixed> $tombstone */
    private function mapTombstone(array $tombstone): IndexTombstone
    {
        $allowedKeys = [
            'object_id',
            'last_object_version',
            'reason',
            'received_at',
            'purge_after',
        ];

        foreach (array_keys($tombstone) as $key) {
            if (! in_array($key, $allowedKeys, true)) {
                throw new InvariantViolation('Owner tombstone contains an undocumented field.');
            }
        }

        foreach (['object_id', 'last_object_version', 'reason', 'received_at'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $tombstone)) {
                throw new InvariantViolation('Owner tombstone is missing a required field.');
            }
        }

        try {
            $receivedAt = new DateTimeImmutable((string) $tombstone['received_at']);
            $purgeAfter = isset($tombstone['purge_after']) && $tombstone['purge_after'] !== null
                ? new DateTimeImmutable((string) $tombstone['purge_after'])
                : null;
        } catch (Throwable $exception) {
            unset($exception);
            throw new InvariantViolation('Owner tombstone dates must be valid immutable date-times.');
        }

        return new IndexTombstone(
            $this->canonicalDomain(),
            (string) $tombstone['object_id'],
            (string) $tombstone['last_object_version'],
            (string) $tombstone['reason'],
            $receivedAt,
            $purgeAfter
        );
    }

    private function assertCanonicalHost(string $url): void
    {
        $parts = parse_url($url);
        $host = is_array($parts) && isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $port = is_array($parts) && isset($parts['port']) ? (int) $parts['port'] : null;

        if ($host !== 'sabrihomeopathy.com' && ! str_ends_with($host, '.sabrihomeopathy.com')) {
            throw new InvariantViolation('Public connector canonical URLs must remain on an approved Sabri platform host.');
        }

        if ($port !== null && $port !== 443) {
            throw new InvariantViolation('Public connector canonical URLs may use the default HTTPS port only.');
        }
    }
}
