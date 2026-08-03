# File 26 — Search, Discovery, Recommendations, Knowledge Graph and Content Classification

Canonical runtime repository for File 26 of the **Sabri Social Homeopathy Platform**.

## Governing scope

File 26 owns the platform-wide derivative search and discovery plane: versioned domain connectors, normalized searchable documents, query understanding, ranking policy, privacy-safe recommendations, controlled taxonomy and classification workflows, owner-sourced knowledge-graph projections, evaluation, reconciliation, and privacy-minimized telemetry.

It does **not** become the canonical owner of posts, lessons, doctors, clinics, videos, Reels, PDFs, Radar records, marketplace listings, private messages, clinical charts, identity evidence, payments, global shell, or public visual design. Canonical domain owners remain authoritative, and every click-through must be revalidated by the source owner.

## Current implementation status

| Stage | Status |
|---|---|
| Specified | Complete planning baseline |
| Coded | Phases 26A–26C coded on the draft branch |
| Packaged | Not yet claimed |
| Automated QA Green | Repository CI evidence required for each exact head |
| Staging Accepted | Not yet claimed |
| Live Deployed | Not claimed |
| Operational | Not claimed |

The current branch contains connector contracts, two public-only connector proofs, a deterministic query proof and a persistent **shadow-generation foundation**. It does not expose a public search endpoint and does not replace canonical owner records.

## Runtime identifiers

- Plugin name: `Sabri Search, Discovery and Knowledge Graph`
- Plugin version: `0.3.0`
- Schema version: `0.3.0`
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

## Explicit non-claims

This repository does not yet claim:

- approved live File 21 or File 10 owner adapters;
- completed MySQL/MariaDB concurrency and migration acceptance on WordPress staging;
- public autocomplete, search or persistent query-reader routes;
- production ranking, recommendations, taxonomy or knowledge-graph traversal;
- File 20 global-search UI integration;
- source/package parity, release ZIP, live deployment or operations.

## Local verification

```bash
composer validate --strict
composer test
composer lint
```

The contract and in-memory generation tests have no WordPress dependency. WordPress database behavior remains subject to isolated staging and real MySQL/MariaDB acceptance.

## Release law

Every coding batch must pass:

1. implementation and tests;
2. first review, defect correction, and retest;
3. fresh adversarial review, further correction, and retest;
4. source/package parity and evidence recording before any release claim.

Direct production edits are prohibited.
