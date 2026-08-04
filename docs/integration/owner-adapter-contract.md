# File 26 Owner-Adapter Contract 1.0.0

## Purpose

File 26 consumes bounded, public-only derivative projections from canonical numbered owner modules. An adapter never transfers canonical ownership, never grants authorization, and never allows File 26 to write an owner record.

Every connector uses:

- contract version `1.0.0`;
- privacy class `C1` only;
- an opaque owner cursor;
- a page ceiling of 200 total records plus tombstones;
- exact canonical identity and owner-prefix validation;
- explicit field allowlists;
- credential-free HTTPS destinations on `sabrihomeopathy.com` or a true subdomain;
- exact ISO-8601 event timestamps;
- public visibility only.

## Registered provider filters

| Connector key | Canonical owner | Provider filter | Entity types |
|---|---|---|---|
| `file-21-publications` | File 21 | `sabri_file21_public_search_batch` | publication, editorial-news |
| `file-09-doctors` | File 09 | `sabri_file09_public_doctor_search_batch` | doctor |
| `file-05-learning` | File 05 | `sabri_file05_public_learning_search_batch` | lesson, course, book |
| `file-06-encyclopedia` | File 06 | `sabri_file06_public_encyclopedia_search_batch` | encyclopedia-entry |
| `file-10-videos` | File 10 | `sabri_file10_public_video_search_batch` | video, live-replay |
| `file-11-reels` | File 11 | `sabri_file11_public_reel_search_batch` | reel |
| `file-12-pdfs` | File 12 | `sabri_file12_public_pdf_search_batch` | pdf |
| `file-15-radar` | File 15 | `sabri_file15_public_radar_search_batch` | radar-item, research-item |
| `file-18-marketplace` | File 18 | `sabri_file18_public_market_search_batch` | listing |

Each provider may expose a health projection using the same filter name with `_health` appended, for example `sabri_file21_public_search_batch_health`.

## Provider invocation

File 26 calls the WordPress filter with four arguments:

```php
$page = apply_filters(
    'sabri_file21_public_search_batch',
    null,
    $cursor,
    $limit,
    'file21'
);
```

- `$cursor` is `null` for the first page, otherwise the exact opaque cursor previously returned by the owner.
- `$limit` is an integer from 1 through 200.
- `$ownerKey` is the expected canonical owner key, such as `file21`.
- The provider must not infer authorization from filter existence. It must emit only records already approved as anonymous-public by the canonical owner.

## Canonical page shape

```php
[
    'records' => [/* list of public records */],
    'tombstones' => [/* list of deletion/restriction tombstones */],
    'next_cursor' => null, // or non-empty opaque string
    'has_more' => false,
]
```

Compatibility input may use boolean `complete` instead of `has_more`; File 26 normalizes it at the boundary. New providers must use `has_more`.

Rules:

1. `records` and `tombstones` are list arrays.
2. `count(records) + count(tombstones) <= requested limit`.
3. A terminal page has `has_more=false` and `next_cursor=null`.
4. A continuing page has `has_more=true` and a non-empty cursor different from the incoming cursor.
5. Repeated cursors, oversized pages, associative lists, malformed values and ambiguous terminal state fail closed.

## Public record shape

```php
[
    'canonical_key' => 'file21:publication-123',
    'owner_key' => 'file21',
    'object_version' => '17',
    'locale' => 'ur-PK',
    'state' => 'published',
    'destination_url' => 'https://sabrihomeopathy.com/news/example/',
    'last_source_event_at' => '2026-08-04T00:00:00+00:00',
    'fields' => [
        'title' => 'Example',
        'excerpt' => 'Public-safe excerpt',
        'content_type' => 'editorial-news',
        'creator_id' => 'doctor-42',
        'topics' => ['homeopathy', 'education'],
        'authority_score' => 90,
        'quality_score' => 95,
    ],
]
```

Mandatory invariants:

- `canonical_key` starts with the exact owner prefix and contains a non-empty object ID.
- `owner_key` equals the expected owner.
- `object_version`, `locale`, `state`, URL and timestamp are strings.
- `state` must be a `SearchDocument` state accepted by the canonical contract.
- `fields` contains only the connector allowlist.
- `title` is mandatory and non-empty.
- scalar fields are bounded; list fields are bounded lists of strings.
- nested objects, PHP objects, resources and unsafe arbitrary structures are rejected.
- public records cannot contain capability, entitlement, age or guardian gates.

## Tombstone shape

```php
[
    'canonical_key' => 'file21:publication-123',
    'owner_key' => 'file21',
    'object_version' => '18',
    'reason' => 'deleted',
    'occurred_at' => '2026-08-04T00:05:00+00:00',
]
```

Allowed reasons are controlled by `IndexTombstone`: `deleted`, `restricted`, `suspended`, `merged`, and `owner-retired`.

A batch cannot contain both a document and a tombstone for the same canonical identity. Newer tombstones dominate older documents; a truly restored object must carry a later owner event/version.

## Field allowlists

### File 21

`title`, `excerpt`, `content_type`, `creator_id`, `topics`, `authority_score`, `quality_score`, `popularity_score`, `trending_score`

### File 09

`title`, `clinic_name`, `country`, `languages`, `specialization`, `creator_id`, `topics`, `authority_score`, `quality_score`

### File 05 and File 06

`title`, `excerpt`, `content_type`, `creator_id`, `topics`, `authority_score`, `quality_score`

### File 10 and File 11

`title`, `summary`, `content_type`, `creator_id`, `topics`, `authority_score`, `quality_score`, `popularity_score`, `trending_score`

### File 12

`title`, `summary`, `content_type`, `creator_id`, `topics`, `authority_score`, `quality_score`

### File 15

`title`, `summary`, `content_type`, `creator_id`, `topics`, `authority_score`, `quality_score`, `trending_score`

### File 18

`title`, `summary`, `content_type`, `creator_id`, `topics`, `authority_score`, `quality_score`, `popularity_score`

Adding or renaming a field requires a versioned change-control decision, privacy review, migration/rebuild plan, tests and rollback evidence.

## Health projection

A health filter may return only bounded operational metadata accepted by the connector registry, such as:

```php
[
    'status' => 'available',
    'healthy' => true,
    'contract_version' => '1.0.0',
    'message_code' => 'owner-adapter-registered',
    'latency_ms' => 12,
    'last_success_at' => '2026-08-04T00:00:00+00:00',
]
```

Raw records, secrets, SQL errors, stack traces and private identifiers must not appear in health output.

## Probe and acceptance

Before activation of a connector in staging:

1. freeze the exact owner contract and allowlist;
2. run `wp sabri-file26 connector probe --connector=<key>`;
3. prove terminal pagination, no repeated cursor and deterministic checksum;
4. compare canonical public eligibility with the derivative output;
5. test deletion, restriction, suspension and restoration chronology;
6. measure parity, leakage, deletion lag and latency;
7. verify File 20 and File 25 consume only the File 26 result contract;
8. retain the active predecessor generation for rollback.

A missing or unhealthy optional owner adapter is hidden/unavailable. It must never cause a permissive fallback, fabricated result or broad access.
