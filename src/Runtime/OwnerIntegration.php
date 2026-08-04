<?php

declare(strict_types=1);

namespace Sabri\File26\Adapters;

use Sabri\File26\Contracts\SourceBatchProviderInterface;
use Sabri\File26\Support\InvariantViolation;

final class WordPressFilterBatchProvider implements SourceBatchProviderInterface
{
    public function __construct(
        private readonly string $filterName,
        private readonly string $ownerKey
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]{2,127}$/', $filterName)
            || ! preg_match('/^file\d{2}$/', $ownerKey)) {
            throw new InvariantViolation('Owner adapter filter or owner key is invalid.');
        }
    }

    public function fetch(?string $cursor, int $limit): array
    {
        if ($limit < 1 || $limit > 200) {
            throw new InvariantViolation('Owner adapter page limit is invalid.');
        }
        if ($cursor !== null && (trim($cursor) === '' || strlen($cursor) > 500)) {
            throw new InvariantViolation('Owner adapter cursor is invalid.');
        }
        if (! function_exists('apply_filters')) {
            throw new InvariantViolation('WordPress owner provider is unavailable.');
        }

        $page = apply_filters($this->filterName, null, $cursor, $limit, $this->ownerKey);
        if (! is_array($page)) {
            throw new InvariantViolation('Owner module did not return a source batch page.');
        }

        $records = $page['records'] ?? null;
        $tombstones = $page['tombstones'] ?? [];
        $nextCursor = $page['next_cursor'] ?? null;
        $hasMore = $page['has_more'] ?? null;
        if ($hasMore === null && array_key_exists('complete', $page) && is_bool($page['complete'])) {
            $hasMore = ! $page['complete'];
        }

        if (! is_array($records) || ! array_is_list($records)
            || ! is_array($tombstones) || ! array_is_list($tombstones)
            || ! is_bool($hasMore)
            || ($nextCursor !== null && ! is_string($nextCursor))) {
            throw new InvariantViolation('Owner source batch page has an invalid canonical shape.');
        }
        if (count($records) + count($tombstones) > $limit) {
            throw new InvariantViolation('Owner source batch exceeded its requested page limit.');
        }
        if (! $hasMore && $nextCursor !== null) {
            throw new InvariantViolation('Terminal owner page cannot contain a continuation cursor.');
        }
        if ($hasMore && ($nextCursor === null || trim($nextCursor) === '' || $nextCursor === $cursor)) {
            throw new InvariantViolation('Continuing owner page requires a distinct bounded cursor.');
        }

        return [
            'records' => $records,
            'tombstones' => $tombstones,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    public function health(): array
    {
        $registered = function_exists('has_filter') ? has_filter($this->filterName) !== false : false;
        $health = [
            'status' => $registered ? 'available' : 'unavailable',
            'healthy' => $registered,
            'contract_version' => '1.0.0',
            'message_code' => $registered ? 'owner-adapter-registered' : 'owner-adapter-missing',
        ];

        if (function_exists('apply_filters')) {
            $candidate = apply_filters($this->filterName . '_health', $health, $this->ownerKey);
            if (is_array($candidate)) {
                $health = $candidate;
            }
        }

        return $health;
    }
}

namespace Sabri\File26\Connectors;

use DateTimeImmutable;
use Sabri\File26\Contracts\ConnectorBatch;
use Sabri\File26\Contracts\ConnectorInterface;
use Sabri\File26\Contracts\ConnectorManifest;
use Sabri\File26\Contracts\SourceBatchProviderInterface;
use Sabri\File26\Domain\IndexTombstone;
use Sabri\File26\Domain\SearchDocument;
use Sabri\File26\Domain\VisibilityEnvelope;
use Sabri\File26\Support\InvariantViolation;

final class GenericPublicOwnerConnector implements ConnectorInterface
{
    public function __construct(
        private readonly string $connectorKey,
        private readonly string $canonicalDomain,
        private readonly ConnectorManifest $manifest,
        private readonly SourceBatchProviderInterface $provider,
        private readonly array $allowedFields,
        private readonly string $canonicalHost = 'sabrihomeopathy.com'
    ) {
        if (! preg_match('/^[a-z][a-z0-9.-]{2,99}$/', $connectorKey)
            || ! preg_match('/^file\d{2}$/', $canonicalDomain)
            || ! array_is_list($allowedFields)
            || $allowedFields === []
            || count($allowedFields) > 100
            || count($allowedFields) !== count(array_unique($allowedFields))
            || ! preg_match('/^[a-z0-9.-]+$/', $canonicalHost)) {
            throw new InvariantViolation('Public owner connector configuration is invalid.');
        }
        foreach ($allowedFields as $field) {
            if (! is_string($field) || ! preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $field)) {
                throw new InvariantViolation('Public owner connector field name is invalid.');
            }
        }
    }

    public function key(): string
    {
        return $this->connectorKey;
    }

    public function manifest(): ConnectorManifest
    {
        return $this->manifest;
    }

    public function fetchBatch(?string $cursor, int $limit): ConnectorBatch
    {
        $page = $this->provider->fetch($cursor, $limit);
        $documents = [];
        $tombstones = [];
        $seen = [];

        foreach ($page['records'] as $record) {
            if (! is_array($record)) {
                throw new InvariantViolation('Owner connector record must be an associative array.');
            }
            $document = $this->mapRecord($record);
            if (isset($seen[$document->canonicalKey()])) {
                throw new InvariantViolation('Owner connector returned a duplicate canonical identity.');
            }
            $seen[$document->canonicalKey()] = true;
            $documents[] = $document;
        }

        foreach ($page['tombstones'] ?? [] as $record) {
            if (! is_array($record)) {
                throw new InvariantViolation('Owner connector tombstone must be an associative array.');
            }
            $tombstone = $this->mapTombstone($record);
            if (isset($seen[$tombstone->canonicalKey()])) {
                throw new InvariantViolation('Owner connector returned a duplicate document/tombstone identity.');
            }
            $seen[$tombstone->canonicalKey()] = true;
            $tombstones[] = $tombstone;
        }

        return new ConnectorBatch(
            $documents,
            $page['next_cursor'],
            $page['has_more'],
            $tombstones
        );
    }

    public function health(): array
    {
        $health = $this->provider->health();
        $health['contract_version'] = $this->manifest->contractVersion();
        return $health;
    }

    private function mapRecord(array $record): SearchDocument
    {
        $required = [
            'canonical_key', 'owner_key', 'object_version', 'locale', 'state',
            'destination_url', 'last_source_event_at', 'fields',
        ];
        foreach ($required as $key) {
            if (! array_key_exists($key, $record)) {
                throw new InvariantViolation('Owner connector record is missing required field: ' . $key);
            }
        }

        if (! is_string($record['canonical_key'])
            || ! str_starts_with($record['canonical_key'], $this->canonicalDomain . ':')
            || ! is_string($record['owner_key'])
            || $record['owner_key'] !== $this->canonicalDomain
            || ! is_string($record['object_version'])
            || ! is_string($record['locale'])
            || ! is_string($record['state'])
            || ! is_string($record['destination_url'])
            || ! is_string($record['last_source_event_at'])
            || ! is_array($record['fields'])) {
            throw new InvariantViolation('Owner connector record types or ownership are invalid.');
        }

        $this->assertCanonicalHost($record['destination_url']);
        $fields = [];
        foreach ($record['fields'] as $field => $value) {
            if (! is_string($field) || ! in_array($field, $this->allowedFields, true)) {
                throw new InvariantViolation('Owner connector exposed a field outside its public allowlist.');
            }
            $this->assertSafePublicValue($value);
            $fields[$field] = $value;
        }
        if (! isset($fields['title']) || ! is_string($fields['title']) || trim($fields['title']) === '') {
            throw new InvariantViolation('Public owner record requires a title.');
        }

        $eventAt = DateTimeImmutable::createFromFormat(DATE_ATOM, $record['last_source_event_at']);
        if (! $eventAt instanceof DateTimeImmutable || $eventAt->format(DATE_ATOM) !== $record['last_source_event_at']) {
            throw new InvariantViolation('Owner source event timestamp must be exact ISO-8601.');
        }

        $objectId = substr($record['canonical_key'], strlen($this->canonicalDomain) + 1);
        return new SearchDocument(
            $this->canonicalDomain,
            $objectId,
            $record['object_version'],
            $record['locale'],
            $record['state'],
            $record['destination_url'],
            $fields,
            VisibilityEnvelope::public(),
            $eventAt
        );
    }

    private function mapTombstone(array $record): IndexTombstone
    {
        foreach (['canonical_key', 'owner_key', 'object_version', 'reason', 'occurred_at'] as $key) {
            if (! array_key_exists($key, $record)) {
                throw new InvariantViolation('Owner tombstone is missing required field: ' . $key);
            }
        }
        if (! is_string($record['canonical_key'])
            || ! str_starts_with($record['canonical_key'], $this->canonicalDomain . ':')
            || ! is_string($record['owner_key'])
            || $record['owner_key'] !== $this->canonicalDomain
            || ! is_string($record['object_version'])
            || ! is_string($record['reason'])
            || ! is_string($record['occurred_at'])) {
            throw new InvariantViolation('Owner tombstone types or ownership are invalid.');
        }

        $occurredAt = DateTimeImmutable::createFromFormat(DATE_ATOM, $record['occurred_at']);
        if (! $occurredAt instanceof DateTimeImmutable || $occurredAt->format(DATE_ATOM) !== $record['occurred_at']) {
            throw new InvariantViolation('Owner tombstone timestamp must be exact ISO-8601.');
        }

        return new IndexTombstone(
            $this->canonicalDomain,
            substr($record['canonical_key'], strlen($this->canonicalDomain) + 1),
            $record['object_version'],
            $record['reason'],
            $occurredAt
        );
    }

    private function assertCanonicalHost(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || ! isset($parts['host'])
            || isset($parts['port'])) {
            throw new InvariantViolation('Destination URL must be credential-free HTTPS on the canonical port.');
        }
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host !== $this->canonicalHost && ! str_ends_with($host, '.' . $this->canonicalHost)) {
            throw new InvariantViolation('Destination URL must remain on the canonical Sabri host.');
        }
    }

    private function assertSafePublicValue(mixed $value): void
    {
        if (is_string($value)) {
            if (strlen($value) > 10000) {
                throw new InvariantViolation('Public owner field value is too large.');
            }
            return;
        }
        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return;
        }
        if (is_array($value)) {
            if (! array_is_list($value) || count($value) > 100) {
                throw new InvariantViolation('Public owner list field is invalid.');
            }
            foreach ($value as $item) {
                if (! is_string($item) || strlen($item) > 2000) {
                    throw new InvariantViolation('Public owner list item is not a bounded string.');
                }
            }
            return;
        }
        throw new InvariantViolation('Public owner field value is not a safe public scalar.');
    }
}

namespace Sabri\File26\Registry;

use Sabri\File26\Adapters\WordPressFilterBatchProvider;
use Sabri\File26\Connectors\GenericPublicOwnerConnector;
use Sabri\File26\Contracts\ConnectorManifest;

final class DefaultConnectorRegistrar
{
    public function registerInto(ConnectorRegistry $registry): void
    {
        foreach ($this->definitions() as $definition) {
            [$key, $fileNumber, $domain, $filter, $entities, $fields] = $definition;
            $registry->register(new GenericPublicOwnerConnector(
                $key,
                $domain,
                new ConnectorManifest(
                    $fileNumber,
                    '1.0.0',
                    $entities,
                    ['C1'],
                    'opaque-owner-cursor',
                    'versioned-owner-filter-contract',
                    'full-rebuild-partial-and-delta',
                    'restrict-tombstone-purge-and-reconcile',
                    'bounded-public-safe-health'
                ),
                new WordPressFilterBatchProvider($filter, $domain),
                $fields,
                defined('SABRI_FILE26_CANONICAL_HOST') ? SABRI_FILE26_CANONICAL_HOST : 'sabrihomeopathy.com'
            ));
        }
    }

    private function definitions(): array
    {
        return [
            ['file-21-publications', '21', 'file21', 'sabri_file21_public_search_batch', ['publication', 'editorial-news'], ['title','excerpt','content_type','creator_id','topics','authority_score','quality_score','popularity_score','trending_score']],
            ['file-09-doctors', '09', 'file09', 'sabri_file09_public_doctor_search_batch', ['doctor'], ['title','clinic_name','country','languages','specialization','creator_id','topics','authority_score','quality_score']],
            ['file-05-learning', '05', 'file05', 'sabri_file05_public_learning_search_batch', ['lesson','course','book'], ['title','excerpt','content_type','creator_id','topics','authority_score','quality_score']],
            ['file-06-encyclopedia', '06', 'file06', 'sabri_file06_public_encyclopedia_search_batch', ['encyclopedia-entry'], ['title','excerpt','content_type','creator_id','topics','authority_score','quality_score']],
            ['file-10-videos', '10', 'file10', 'sabri_file10_public_video_search_batch', ['video','live-replay'], ['title','summary','content_type','creator_id','topics','authority_score','quality_score','popularity_score','trending_score']],
            ['file-11-reels', '11', 'file11', 'sabri_file11_public_reel_search_batch', ['reel'], ['title','summary','content_type','creator_id','topics','authority_score','quality_score','popularity_score','trending_score']],
            ['file-12-pdfs', '12', 'file12', 'sabri_file12_public_pdf_search_batch', ['pdf'], ['title','summary','content_type','creator_id','topics','authority_score','quality_score']],
            ['file-15-radar', '15', 'file15', 'sabri_file15_public_radar_search_batch', ['radar-item','research-item'], ['title','summary','content_type','creator_id','topics','authority_score','quality_score','trending_score']],
            ['file-18-marketplace', '18', 'file18', 'sabri_file18_public_market_search_batch', ['listing'], ['title','summary','content_type','creator_id','topics','authority_score','quality_score','popularity_score']],
        ];
    }
}
