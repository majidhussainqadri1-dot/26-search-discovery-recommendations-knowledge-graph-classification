# File 26 — Search, Discovery, Recommendations, Knowledge Graph and Content Classification

Canonical runtime repository for File 26 of the **Sabri Social Homeopathy Platform**.

## Governing scope

File 26 owns the platform-wide derivative search and discovery plane: versioned domain connectors, normalized searchable documents, query understanding, ranking policy, privacy-safe recommendations, controlled taxonomy and classification workflows, owner-sourced knowledge-graph projections, evaluation, reconciliation, and privacy-minimized telemetry.

It does **not** become the canonical owner of posts, lessons, doctors, clinics, videos, Reels, PDFs, Radar records, marketplace listings, private messages, clinical charts, identity evidence, payments, global shell, or public visual design. Canonical domain owners remain authoritative, and every click-through must be revalidated by the source owner.

## Current implementation status

| Stage | Status |
|---|---|
| Specified | Complete planning baseline |
| Coded | Phases 26A–26D coded on the draft branch |
| Packaged | Not yet claimed |
| Automated QA Green | PHP 8.1/8.3 exact-head matrix required |
| Isolated WordPress/MariaDB Smoke | Green on WordPress 7.0.2, MariaDB 11.4 and PHP 8.3 |
| Hostinger-equivalent Staging Accepted | Not yet claimed |
| Live Deployed | Not claimed |
| Operational | Not claimed |

The current branch contains connector contracts, two public-only connector proofs, persistent blue/green shadow generations, an **internal active-generation query reader**, and administrator/CLI operations controls. It does not register a public search endpoint and does not replace canonical owner records.

## Runtime identifiers

- Plugin name: `Sabri Search, Discovery and Knowledge Graph`
- Plugin version: `0.4.0`
- Schema version: `0.4.0`
- Runtime stage: `phase-26d-query-operations`
- Plugin slug / text domain: `sabri-search-discovery`
- PHP namespace: `Sabri\File26`
- REST namespace: `sabri-search/v1`
- Development branch: `codex/file-26-phase-26a-foundation`

After a packaged release, any identifier rename requires approved migration and change control.

## Phase 26A deliverables

- Fail-closed plugin bootstrap with no companion-module mutation.
- Versioned connector contract and manifest schema.
- Connector registry with duplicate-key rejection.
- Canonical search-document, visibility-envelope and tombstone value objects.
- Synthetic, non-PII golden-query fixture.
- Threat-model, data-flow and requirements-traceability baselines.

## Phase 26B deliverables

- `File21PublicationsConnector` for approved public publications/editorial news.
- `File10VideosConnector` for approved public recorded-video/live-replay metadata.
- Strict owner-provider batch contract with bounded cursors and pages.
- Connector-specific public field allowlists and Sabri canonical-host enforcement.
- Tombstone-bearing connector batches and stale resurrection prevention.
- Query-time capability, entitlement, age and guardian evaluation.
- Deterministic shadow index with Urdu/English matching and parity reporting.
- Separate review and adversarial regression suites.

## Phase 26C deliverables

- Backend-neutral persistent shadow-store, job-queue and lease-lock contracts.
- WordPress schema for generations, aliases, documents, tombstones, checkpoints, jobs and locks.
- Bounded full/partial/delta rebuild worker with durable opaque-cursor checkpoints.
- Deterministic job identities, bounded retries, dead-letter evidence and stale-job suppression.
- Candidate generation isolation so a failed rebuild cannot alter the active alias.
- Deterministic count/checksum validation and explicit minimum/divergence/tombstone policies.
- Atomic active-generation promotion with rollback-predecessor restoration.
- Separate persistence and fresh adversarial regression suites.

## Phase 26D deliverables

- Internal-only persistent query service over active or cursor-bound superseded generations.
- HMAC-signed opaque cursors tied to the normalized query, filters and generation snapshot.
- Strict payload SHA-256 verification, canonical JSON hydration and exact ISO-8601 source times.
- Query-time capability, entitlement, age and guardian enforcement after persistent hydration.
- Deterministic Urdu/English ranking, domain/locale filters and bounded candidate sets.
- Five-minute WP-Cron scheduling, bounded worker loops and real-cron WP-CLI execution.
- Throttled administrator missed-run recovery checks and finite cron cleanup.
- Administrator-only queue, scheduler and dead-letter diagnostics.
- Guarded dead-letter replay with exact error confirmation and replay audit counters.
- Read-only bounded owner-connector probes with cursor, identity and checksum validation.
- Locked in-place schema upgrade from `0.3.0` to `0.4.0`.
- Isolated WordPress/MariaDB integration workflow required on every pull-request head.

## Blue/green safety law

```text
active generation remains readable
  -> candidate generation builds in bounded pages
  -> documents/tombstones and checkpoints persist
  -> every expected connector completes
  -> counts, divergence and checksum validate
  -> active alias swaps atomically
  -> former active generation remains available for rollback
```

Building, incomplete, failed, unvalidated, unexpectedly empty or excessively divergent generations cannot be promoted through the rebuild coordinator.

## Internal query snapshot law

```text
fresh query -> current active generation
continuation cursor -> signed original generation snapshot
candidate rows -> payload hash and strict hydration
result eligibility -> rechecked for the current audience
ranking -> deterministic score plus canonical-key tie break
```

A generation swap changes new queries only. A valid continuation cursor remains on its original superseded generation while that snapshot is retained and readable.

## Operations surfaces

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

No public query route is registered in Phase 26D.

## Automated verification

The repository matrix runs the complete contract, shadow, persistence, query, operations and adversarial suites on PHP 8.1 and PHP 8.3:

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

The isolated database workflow additionally provisions WordPress 7.0.2 with MariaDB 11.4 and PHP 8.3. Its smoke test passed 21 assertions covering activation, schema/table/column verification, persistent generation write/checkpoint/validation/promotion, Urdu active-generation query, persistent queue claim, dead-letter transition and guarded replay.

## Explicit non-claims

This repository does not yet claim:

- approved live File 21 or File 10 owner adapters;
- Hostinger-equivalent migration, concurrency, backup and rollback acceptance;
- public autocomplete, search REST API or File 20 global-search UI integration;
- production transliteration, advanced ranking, recommendations, taxonomy or knowledge-graph traversal;
- measured staging leakage, deletion-lag and latency SLOs;
- source/package parity, release ZIP, live deployment or operations.

## Local verification

```bash
composer validate --strict
composer test
composer lint
```

The default contract, query and in-memory generation suites have no WordPress dependency. Every pull-request head also runs the isolated `File 26 WordPress MariaDB Integration` workflow.

## Release law

Every coding batch must pass:

1. implementation and tests;
2. first review, defect correction, and retest;
3. fresh adversarial review, further correction, and retest;
4. source/package parity and evidence recording before any release claim.

Direct production edits are prohibited.
