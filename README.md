# File 26 — Search, Discovery, Recommendations, Knowledge Graph and Content Classification

Canonical runtime repository for File 26 of the **Sabri Social Homeopathy Platform**.

## Governing scope

File 26 owns the platform-wide derivative search and discovery plane: versioned domain connectors, normalized searchable documents, query understanding, ranking policy, privacy-safe recommendations, controlled taxonomy and classification workflows, owner-sourced knowledge-graph projections, evaluation, reconciliation, and privacy-minimized telemetry.

It does **not** become the canonical owner of posts, lessons, doctors, clinics, videos, Reels, PDFs, Radar records, marketplace listings, private messages, clinical charts, identity evidence, payments, global shell, or public visual design. Canonical domain owners remain authoritative, and every click-through must be revalidated by the source owner.

## Current implementation status

| Stage | Status |
|---|---|
| Specified | Complete planning baseline |
| Coded | Phase 26A foundation + Phase 26B shadow proof coded |
| Packaged | Not yet claimed |
| Automated QA Green | Repository CI evidence required for each head |
| Staging Accepted | Not yet claimed |
| Live Deployed | Not claimed |
| Operational | Not claimed |

The current branch contains a deterministic in-memory **shadow index proof**, not a production index and not a public search endpoint. It exists to prove connector validation, public-field allowlists, visibility evaluation, deletion/tombstone ordering, stale-event resistance, Urdu/English matching, and owner/shadow parity reporting before any storage or public-query cutover.

## Runtime identifiers

- Plugin name: `Sabri Search, Discovery and Knowledge Graph`
- Plugin version: `0.2.0`
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

## Explicit non-claims

This repository does not yet claim:

- live File 21 or File 10 owner adapters;
- a persistent database/search-engine index;
- public autocomplete or search routes;
- production ranking, recommendations, taxonomy or knowledge-graph traversal;
- WordPress/Hostinger staging acceptance;
- source/package parity, release ZIP, live deployment or operations.

## Local verification

```bash
composer validate --strict
composer test
composer lint
```

The current test runners have no WordPress dependency and validate the contract and shadow-projection layers independently.

## Release law

Every coding batch must pass:

1. implementation and tests;
2. first review, defect correction, and retest;
3. fresh adversarial review, further correction, and retest;
4. source/package parity and evidence recording before any release claim.

Direct production edits are prohibited.
