# File 26 — Search, Discovery, Recommendations, Knowledge Graph and Classification

Production-oriented WordPress plugin source for the **Sabri Social Homeopathy Platform**.

## Canonical responsibility

File 26 owns federated connector/index orchestration, query understanding, safe autocomplete, organic ranking, privacy-safe recommendations, controlled taxonomy/classification, provenance-rich knowledge-graph projections, relevance evaluation, deletion reconciliation and the **Future Search & Knowledge Intelligence Superset 24** derivative orchestration layer.

It does **not** own canonical posts, doctors, clinics, lessons, videos, PDFs, marketplace records, clinical truth, private-data authorization, notification delivery, external evidence truth, the global shell (File 20), result-card visual authority (File 25), identity truth (File 00) or cross-platform assurance governance (File 24).

## v1.3 Future Search & Knowledge Intelligence

Version 1.3.0 adds 24 versioned capabilities: grounded conversational search, intelligent query planning, cross-language semantic orchestration, semantic reranking, multimodal and voice search, page/paragraph/timestamp search, find-similar, research mode, result clustering, graph paths, evidence/contradiction maps, entity disambiguation, historical as-of search, research trails, saved-search alerts, local-first history, recommendation transparency, broadened discovery, geo/availability discovery, search modes/commands, isolated private-vault search, approved external-evidence connectors and a read-only relevance laboratory.

Provider-dependent features fail closed or return an explicit unavailable state. File 26 never fabricates canonical, historical, clinical, page/timestamp, availability or external-evidence data.

## Safety invariants

- Index-time, query-time and click/action-time eligibility.
- Unknown connector/identity/policy versions fail closed.
- Donation, payment, advertising, followers and Founder favoritism are prohibited organic-ranking signals.
- Private messages, clinical charts, identity evidence, payment data and unpublished drafts are excluded from general indexing.
- Personalization is off by default and requires explicit consent, visible explanation and reset/opt-out controls.
- Patient-image diagnosis and autonomous diagnosis/prescription/dose/potency generation are outside File 26 scope.
- Private Search Vault never uses the public File 26 index and requires step-up plus native-owner authorization.
- Search history is local-first; server sync is explicit opt-in and blocks sensitive queries.
- External evidence is separately labelled and never silently merged into organic platform ranking.
- Public activation remains off until owner connectors, migration, security/privacy, staging and Founder approval gates are accepted.

## Installation

Install the deterministic ZIP from `release/`, activate the plugin, register versioned owner connectors, execute shadow reindex and reconciliation, complete `docs/STAGING-ACCEPTANCE.md`, then enable **Approved runtime activation**.

## Local QA

```bash
bash qa/run-tests.sh
```

This proves source/package checks in the local environment. Hostinger staging, real owner/provider connectors, browser/accessibility evidence, load tests, restore/rollback rehearsal, live deployment and live monitoring are separate acceptance gates.
