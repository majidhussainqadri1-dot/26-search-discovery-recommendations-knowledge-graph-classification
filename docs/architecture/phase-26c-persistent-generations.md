# Phase 26C — Persistent Generations and Rebuild Jobs

## Decision

Phase 26C converts the Phase 26B in-memory proof into a backend-agnostic persistent-generation contract and a WordPress database adapter. It introduces bounded rebuild/delta jobs, checkpoints, retry/dead-letter behavior, lease locks, deterministic validation checksums, atomic generation promotion and rollback.

This phase does **not** expose public search routes and does not claim Hostinger staging acceptance.

## Blue/green generation law

```text
active generation remains readable
    -> candidate generation created as building
    -> each owner connector runs in bounded cursor pages
    -> every page writes documents/tombstones + checkpoint atomically
    -> all expected connectors complete
    -> counts and deterministic checksum validated
    -> candidate becomes validated
    -> active alias atomically points to candidate
    -> former active generation remains rollback predecessor
```

A building, failed, incomplete or unchecksummed generation cannot become active.

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
- A failed candidate never changes the active alias.
- Operator reconciliation is required before replaying dead-letter work.

## Validation and promotion

Validation requires a completed checkpoint for every expected connector. The checksum is calculated from sorted canonical keys and payload hashes so ingestion order cannot alter the result. Promotion records the previous generation and preserves it for rollback.

## Explicit pending evidence

- WordPress `dbDelta` fresh-install and upgrade tests.
- Real MySQL/MariaDB transaction, lock-expiry and concurrent-worker tests.
- Real File 21/File 10 provider adapters.
- WP-Cron plus real-cron scheduling and missed-cron recovery.
- Persistent query reader and File 20 public search surface.
- Hostinger staging backup/restore and rollback rehearsal.
