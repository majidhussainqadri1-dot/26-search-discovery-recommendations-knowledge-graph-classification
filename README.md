# File 26 — Search, Discovery, Recommendations, Knowledge Graph and Content Classification

Canonical runtime repository for File 26 of the **Sabri Social Homeopathy Platform**.

## Governing scope

File 26 owns the platform-wide derivative search and discovery plane: versioned domain connectors, normalized searchable documents, query understanding, ranking policy, privacy-safe recommendations, controlled taxonomy and classification workflows, owner-sourced knowledge-graph projections, evaluation, reconciliation, and privacy-minimized telemetry.

It does **not** become the canonical owner of posts, lessons, doctors, clinics, videos, Reels, PDFs, Radar records, marketplace listings, private messages, clinical charts, identity evidence, payments, global shell, or public visual design. Canonical domain owners remain authoritative, and every click-through must be revalidated by the source owner.

## Current implementation status

| Stage | Status |
|---|---|
| Specified | Complete planning baseline |
| Coded | Phase 26A foundation in progress |
| Packaged | Not yet claimed |
| Automated QA Green | Not yet claimed |
| Staging Accepted | Not yet claimed |
| Live Deployed | Not yet claimed |
| Operational | Not yet claimed |

The current branch implements the Phase 26A repository and contract skeleton only. It makes no production, staging, indexing, recommendation, or security-completion claim.

## Runtime identifiers

- Plugin name: `Sabri Search, Discovery and Knowledge Graph`
- Plugin version: `0.1.0`
- Plugin slug / text domain: `sabri-search-discovery`
- PHP namespace: `Sabri\\File26`
- REST namespace: `sabri-search/v1`
- Canonical development branch: `codex/file-26-phase-26a-foundation`

These are the current foundation identifiers. After a packaged release, any rename requires an approved migration and change-control record.

## Phase 26A deliverables

- Fail-closed plugin bootstrap with no companion-module mutation.
- Versioned connector contract and manifest schema.
- Connector registry with duplicate-owner rejection.
- Canonical search-document and visibility-envelope value objects.
- Synthetic, non-PII golden-query fixture.
- Threat-model and data-flow baseline.
- Requirements traceability and automated foundation tests.

## Local verification

```bash
composer validate --strict
composer test
composer lint
```

The test runner has no WordPress dependency and validates the contract layer independently.

## Release law

Every coding batch must pass:

1. implementation and tests;
2. first review, defect correction, and retest;
3. fresh adversarial review, further correction, and retest;
4. source/package parity and evidence recording before any release claim.

Direct production edits are prohibited.
