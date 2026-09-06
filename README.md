# File 26 — Search, Discovery, Recommendations, Knowledge Graph and Classification

Production-oriented WordPress plugin source for the **Sabri Social Homeopathy Platform**.

## Canonical responsibility

File 26 owns federated connector/index orchestration, query understanding, safe autocomplete, organic ranking, privacy-safe recommendations, controlled taxonomy/classification, provenance-rich knowledge-graph projections, relevance evaluation and deletion reconciliation.

It does **not** own canonical posts, doctors, clinics, lessons, videos, PDFs, marketplace records, the global shell (File 20), result-card visual authority (File 25), identity truth (File 00) or cross-platform assurance governance (File 24).

## Safety invariants

- Index-time, query-time and click-time eligibility.
- Unknown connector/identity/policy versions fail closed.
- Donation, payment and Founder favoritism are prohibited organic-ranking signals.
- Private messages, clinical charts, identity evidence, payment data and unpublished drafts are excluded from general indexing.
- Personalization is off by default and requires explicit consent, visible explanation and reset/opt-out controls.
- Public activation remains off until owner connectors, migration, security/privacy, staging and Founder approval gates are accepted.

## Installation

Build the deterministic ZIP from the exact reviewed source with `bash qa/run-tests.sh`, or use the package artifact produced by the exact-head `File 26 QA` GitHub Actions run. The `release/` directory is generated output and is deliberately not a committed source of truth. Verify the generated `release/CHECKSUMS.sha256`, install the matching `26-sabri-file26-search-discovery-1.2.0.zip`, activate the plugin, register versioned owner connectors, execute shadow reindex and reconciliation, complete `docs/STAGING-ACCEPTANCE.md`, then enable **Approved runtime activation**.

The package contains a `MANIFEST.sha256` generated from the exact source snapshot without modifying repository source files.

## Local QA

```bash
bash qa/run-tests.sh
```

This proves source/package checks in the local environment. Hostinger staging, real owner connectors, browser/accessibility evidence, load tests, restore/rollback rehearsal and live monitoring are separate acceptance gates.
