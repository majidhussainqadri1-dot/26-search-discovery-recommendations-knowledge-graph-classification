# Phase 26D — Persistent Query and Operations Control Plane

## Decision

Phase 26D adds an **internal-only** active-generation query reader and an administrator/CLI operations control plane. It deliberately does not publish a public search endpoint. Public query exposure remains gated on approved real owner adapters, Hostinger-equivalent staging, leakage evaluation, measured SLO evidence and File 20/File 25 integration.

## Query law

```text
internal caller builds bounded PersistentQuery
    -> query fingerprint calculated from normalized query and filters
    -> fresh request binds to current active generation
    -> continuation cursor is HMAC-signed and generation-bound
    -> storage returns a bounded candidate set
    -> payload SHA-256 and canonical JSON shape are verified
    -> SearchDocument and VisibilityEnvelope are strictly rehydrated
    -> capability, entitlement, age and guardian assertions run at query time
    -> deterministic score and canonical-key tie-break are applied
    -> next cursor remains bound to the same readable generation
```

A generation swap therefore affects new queries only. Existing cursors continue against their superseded snapshot while that generation remains readable. Tampered cursors, changed filters, unreadable generations, corrupt payloads, invalid ISO-8601 timestamps and excessive offsets fail closed.

## Operations law

- WP-Cron uses a five-minute schedule and a bounded worker loop.
- The same loop is available to real server cron through WP-CLI.
- Each invocation processes at most 50 jobs and each connector page at most 200 items.
- Administrator visits perform a throttled missed-run check; overdue pending work schedules one recovery event.
- Cron cleanup is finite and stops when WordPress cannot remove an event.
- Diagnostics are administrator-only and expose bounded operational metadata, not source records or secrets.
- Dead-letter replay requires the exact job ID and current error code, a building generation, an incomplete connector checkpoint and a replay count below ten.
- Owner connector probes are read-only, bounded, checksum-producing and reject repeated cursors, duplicate identities and cross-owner records.

## Schema upgrade

Schema `0.4.0` adds auditable dead-letter replay fields:

- `replay_count`
- `last_replayed_at`

An already-active `0.3.0` installation is upgraded at boot under a MySQL advisory lock. Unknown or skipped schema versions fail closed and require an explicit migration path. Table and column presence is verified after `dbDelta` before the runtime becomes available.

## Interfaces and adapters

### Internal query

- `PersistentQuery`
- `QueryCursorCodec`
- `SearchDocumentHydrator`
- `ActiveGenerationRepositoryInterface`
- `WordPressActiveGenerationRepository`
- `PersistentQueryService`
- `QueryPage`

### Operations

- `WorkerLoop`
- `WordPressWorkerScheduler`
- `MissedRunDetector`
- `DeadLetterOperationsInterface`
- `WordPressDeadLetterOperations`
- `OwnerConnectorProbe`
- `WordPressRuntime`
- `WordPressCliAdapter`

## Administrator and CLI surfaces

Administrator-only REST operations:

```text
GET  /sabri-search/v1/operations
POST /sabri-search/v1/operations/dead-letter/replay
POST /sabri-search/v1/operations/connectors/{connector}/probe
```

WP-CLI operations:

```text
wp sabri-file26 jobs run
wp sabri-file26 jobs recover
wp sabri-file26 operations status
wp sabri-file26 dead-letter replay --job=<sha256> --error=<current-code>
wp sabri-file26 connector probe --connector=<key>
```

No public query route is registered in this phase.

## Review and correction record

### Review round 1

- Unicode candidate matching was corrected to preserve Urdu text.
- Authorization tests were narrowed to restricted-only terms.
- WP-CLI real-cron callback binding was corrected.
- Active-plugin schema upgrades were added instead of relying only on activation hooks.

### Fresh adversarial review

- Stored relative or non-ISO timestamps are rejected.
- Locale aliases that normalize to the same value are rejected.
- Missed-run checks are administrator-only and throttled.
- Cron unscheduling is finite and stops on removal failure.
- Cursor tampering, malformed fingerprints and excessive offsets are covered.
- Connector probes cover repeated cursors, cross-owner identity and non-terminal pagination.

### Real WordPress/MariaDB correction round

The isolated database workflow exposed two further defects that repository-only tests could not prove:

1. WP-CLI `eval-file` does not permit the smoke script's `strict_types` declaration in its evaluated context; the harness was corrected without weakening plugin source strictness.
2. Nullable job cursors could return from WordPress/MariaDB as an empty string. `WordPressJobQueue` now writes SQL `NULL` explicitly, normalizes legacy `NULL/''` rows to canonical `null`, and accepts both forms only for legacy retry lookup.

The database smoke was rerun after both corrections and passed.

## Verification layers

### Repository matrix

The exact-head matrix runs on PHP 8.1 and PHP 8.3:

```text
Foundation and shadow-index suite: 41 assertions
Phase 26B review suite:           13 assertions
Phase 26C persistence suite:      32 assertions
Phase 26C adversarial suite:      22 assertions
Phase 26D query suite:            28 assertions
Phase 26D operations suite:       24 assertions
Phase 26D adversarial suite:      16 assertions
Total per PHP version:           176 assertions
```

Composer strict validation, complete PHP lint and JSON schema/fixture validation are also required.

### Isolated WordPress/MariaDB integration

Every pull-request head provisions:

- WordPress `7.0.2`;
- MariaDB `11.4`;
- PHP `8.3`;
- WP-CLI `2.12.0`.

The smoke passed **21 assertions** covering:

- plugin activation;
- schema version and seven derivative tables;
- replay audit columns;
- persistent generation creation and checkpointing;
- document persistence, validation checksum and atomic promotion;
- Urdu active-generation query;
- persistent queue enqueue and claim;
- dead-letter transition;
- guarded replay and replay-count audit.

This is genuine isolated database integration evidence, but it is not Hostinger staging acceptance.

## Explicit pending evidence

- Hostinger-equivalent activation, in-place upgrade, backup, restore and rollback rehearsal.
- Concurrent worker/advisory-lock stress testing under representative load.
- Approved real File 21 and File 10 source adapters.
- Query leakage, deletion-lag and latency measurements on representative staging data.
- Public query API, autocomplete, File 20 search surface and File 25 result cards.
- Production ranking, transliteration, recommendations, taxonomy and graph traversal.
