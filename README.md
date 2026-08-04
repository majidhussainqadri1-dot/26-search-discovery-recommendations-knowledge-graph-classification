# File 26 — Search, Discovery, Recommendations, Knowledge Graph and Classification

Canonical WordPress runtime for federated public search and discovery across the Sabri Social Homeopathy Platform.

## Release identity

| Item | Value |
|---|---|
| Plugin | Sabri Search, Discovery and Knowledge Graph |
| Plugin version | `1.0.0` |
| Schema version | `1.0.0` |
| Runtime stage | `complete-runtime` |
| Slug / text domain | `sabri-search-discovery` |
| Namespace | `Sabri\File26` |
| REST namespace | `sabri-search/v1` |
| PHP | 8.1+ |
| WordPress | 6.0+ |
| Status | Coded, packaged and automated-QA candidate; staging/live/operational acceptance remains separate |

A post-release rename of the slug, namespace, REST namespace or table prefix requires an approved migration and compatibility plan.

## Canonical responsibility

File 26 owns:

- versioned domain connector and derivative-index registry;
- owner-sourced public search documents and deletion tombstones;
- Unicode normalization, Urdu/English transliteration and approved synonyms;
- exact/term matching, filters, facets, suggestions and signed cursor pagination;
- versioned relevance, authority, quality, freshness and diversity ranking;
- consented recommendations with cold start, explanations, hide, not-interested, reset and opt-out controls;
- controlled taxonomy and reviewed classification workflow;
- typed, provenance-rich, visibility-aware knowledge-graph projections;
- privacy-minimized telemetry, evaluation sets, policy configuration and export controls;
- generation rebuild, reconciliation, retention, health and repair operations.

It does **not** own canonical posts, doctors, lessons, videos, PDFs, marketplace listings, profiles, clinical data, messages, payments, the global shell or public visual cards. Those remain with their numbered owner modules. Search documents, graph nodes, telemetry and caches are rebuildable derivatives—not systems of record.

## Approved owner adapters

The runtime registers bounded public-only connector contracts for:

- File 21 publications and editorial news;
- File 09 verified doctors;
- File 05 lessons, courses and books;
- File 06 encyclopedia entries;
- File 10 recorded videos and live replays;
- File 11 reels;
- File 12 public PDF metadata;
- File 15 Radar and research items;
- File 18 marketplace listings.

Owner modules provide data through versioned WordPress filters documented in [`docs/integration/owner-adapter-contract.md`](docs/integration/owner-adapter-contract.md). Missing owner adapters report unavailable health; they never broaden access or create shadow truth.

## Public REST API

- `GET /wp-json/sabri-search/v1/query`
- `GET /wp-json/sabri-search/v1/suggest`
- `GET /wp-json/sabri-search/v1/facets`
- `GET /wp-json/sabri-search/v1/recommendations`
- `POST /wp-json/sabri-search/v1/recommendation-feedback`
- `GET /wp-json/sabri-search/v1/topics/{concept}`

All returned destinations carry a click-time owner-visibility recheck requirement. Personalized recommendations require an authenticated actor and explicit consent. Minor personalization requires verified guardian consent from the canonical identity context.

## Administrator and operator interfaces

Administrator routes cover health, taxonomy, graph edges, classification, policies, evaluation sets, telemetry, signed exports, queue diagnostics, connector probes and guarded dead-letter replay. WP-CLI provides bounded job execution, missed-run recovery, reconciliation and telemetry retention operations.

See [`docs/api.md`](docs/api.md) and [`docs/operations/reconciliation-runbook.md`](docs/operations/reconciliation-runbook.md).

## Persistent architecture

Schema `1.0.0` contains nineteen derivative tables:

`generations`, `aliases`, `documents`, `tombstones`, `checkpoints`, `jobs`, `locks`, `feedback`, `taxonomy_terms`, `graph_edges`, `classifications`, `evaluation_sets`, `export_tokens`, `policies`, `telemetry_daily`, `audit_log`, `change_events`, `owner_sequences`, and `purge_ledger`.

Candidate generations remain isolated until connector checkpoints, counts, divergence limits and deterministic checksums pass. Promotion changes the active alias atomically and retains the predecessor for rollback. Tombstones and click-time owner checks prevent stale resurrection.

## Security and privacy invariants

- HTTPS canonical destinations only; credentials and nonstandard ports are rejected.
- Connector fields are domain-specific allowlists.
- Private messages, clinical charts, identity evidence, payment secrets, unpublished drafts and restricted attachments are not general-index domains.
- Stored payload hashes and canonical JSON shapes are revalidated on read.
- Query cursors and export tokens are signed, bounded and context-bound.
- Sensitive and PII-like queries are classified; raw query telemetry is not retained.
- Capability, entitlement, age, guardian consent and state are rechecked after hydration and at destination click.
- High-risk policy/classification actions require independent review and audit evidence.
- Uninstall is intentionally non-destructive.

## Automated evidence

The required CI matrix runs on PHP 8.1 and 8.3. Each PHP version executes 320 assertions:

| Suite | Assertions |
|---|---:|
| Foundation and shadow index | 41 |
| Phase 26B review | 13 |
| Phase 26C persistence | 32 |
| Phase 26C adversarial | 22 |
| Phase 26D query | 28 |
| Phase 26D operations | 24 |
| Phase 26D adversarial | 20 |
| Phase 26E complete runtime | 55 |
| Review round 1 | 55 |
| Fresh adversarial review round 2 | 30 |
| **Total per PHP version** | **320** |

The isolated WordPress 7.0.2 / MariaDB 11.4 / PHP 8.3 workflow executes 83 assertions across activation, all nineteen tables, persistent generations, Urdu search, queues, taxonomy, graph, recommendations feedback, ordered change events, purge evidence, route registration and truthful health status.

CI also requires Composer validation, complete PHP lint, JSON contract validation, unresolved-marker rejection, deterministic install/source package reproduction, byte-for-byte parity, SBOM generation and artifact upload.

## Build

```bash
composer validate --strict
composer lint
composer test
python3 tools/build-package.py --output-dir dist
```

The builder produces:

- `sabri-search-discovery-1.0.0.zip`
- `sabri-search-discovery-1.0.0-source.zip`
- `SBOM.spdx.json`
- `CHECKSUMS.sha256`

## Truthful completion boundary

This repository can establish **Coded**, **Packaged** and **Automated-QA Green** status. It does not by itself establish Hostinger staging acceptance, real owner-data readiness, File 20/File 25 visual acceptance, production deployment, live monitoring or operational staffing. Those require the separate evidence gates in the governing master plan and File 26 plan.
