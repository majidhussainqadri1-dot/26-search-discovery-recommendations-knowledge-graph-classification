# Phase 26C — Persistent Generations and Rebuild Jobs

## Decision

Phase 26C converts the Phase 26B in-memory proof into a backend-neutral persistent-generation contract and a WordPress database adapter. It introduces bounded rebuild/delta jobs, durable checkpoints, retry/dead-letter behavior, lease locks, deterministic validation checksums, count/divergence gates, atomic generation promotion and rollback.

This phase does **not** expose public search routes and does not claim Hostinger staging acceptance.

## Blue/green generation law

```text
active generation remains readable
    -> candidate generation created as building
    -> each owner connector runs in bounded cursor pages
    -> every page writes documents/tombstones + checkpoint atomically
    -> all expected connectors complete
    -> counts, divergence and deterministic checksum validate
    -> candidate becomes validated
    -> active alias atomically points to candidate
    -> former active generation remains rollback predecessor
```

A building, failed, incomplete, unchecksummed, unexpectedly empty or excessively divergent generation cannot be promoted through the rebuild coordinator.

## Persistent tables

The WordPress adapter owns only derivative File 26 data:

- `wp_s26_generations`
- `wp_s26_aliases`
- `wp_s26_documents`
- `wp_s26_tombstones`
- `wp_s26_checkpoints`
- `wp_s26_jobs`
- `wp_s26_locks`

Canonical owner records remain in their source modules. Raw private content is not copied into these tables.

## Job and failure law

- Jobs are idempotently identified by generation, connector, cursor, mode and attempt.
- Connector pages are bounded to 1–200 records/tombstones.
- One connector/generation lease prevents concurrent duplicate execution.
- Cursor progress is persisted after each accepted page.
- Retry delays are bounded and increasing; exhausted work enters dead letter.
- Stale jobs behind the durable checkpoint are acknowledged without owner replay.
- A failed candidate never changes the active alias.
- Operator reconciliation is required before replaying dead-letter work.

## Validation and promotion

Validation requires a completed checkpoint for every expected connector. Promotion is additionally gated by an explicit policy containing minimum documents, optional expected documents, maximum count divergence and maximum tombstones. The checksum is calculated from sorted canonical keys and payload hashes so ingestion order cannot alter the result. Promotion records the previous generation and preserves it for rollback.

## Review record

### First review and correction

- blocked incomplete checkpoints and connector-set mismatch;
- rejected cross-owner writes, stale checkpoints and checkpoint regression;
- protected the active alias from failed candidate jobs;
- added schema-install verification after `dbDelta`;
- added count/divergence validation before promotion.

### Fresh adversarial review and correction

- tested unexpectedly empty generations and explicit zero-record approval;
- tested missing/stale checkpoints before connector execution;
- tested duplicate enqueue, lease contention, expiry and stale-token release;
- tested retry exhaustion, dead-letter evidence and active-alias preservation;
- upgraded GitHub checkout from the deprecated Node.js 20 action generation.

## Explicit pending evidence

- WordPress `dbDelta` fresh-install and upgrade tests.
- Real MySQL/MariaDB transaction, lock-expiry and concurrent-worker tests.
- Real File 21/File 10 provider adapters.
- WP-Cron plus real-cron scheduling and missed-cron recovery.
- Persistent query reader and File 20 public search surface.
- Hostinger staging backup/restore and rollback rehearsal.
