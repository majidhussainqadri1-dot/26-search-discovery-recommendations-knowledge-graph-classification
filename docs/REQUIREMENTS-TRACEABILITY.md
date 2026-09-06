# Requirements Traceability Matrix

This matrix records **repository implementation mapping only**. It does not assert staging acceptance, deployment, live database parity or operational completion. Automated source evidence is supplemented by the sequential `tests/review-round-*.php` regression gates; external acceptance remains in `STAGING-ACCEPTANCE.md`.

| Requirement | Capability | Repository implementation | Source evidence |
|---|---|---|---|
| File26-FR-001 | Connector registry | `Connectors, DB, Owner_Contracts` | contract + connector/governance review regressions |
| File26-FR-002 | Canonical identity and version | `Indexer` | index lifecycle + jobs/concurrency regressions |
| File26-FR-003 | Visibility envelope | `Search, Security, Connectors` | search/security/public-surface regressions |
| File26-FR-004 | Reliable change ingestion | `Indexer` | index lifecycle + jobs/concurrency regressions |
| File26-FR-005 | Rebuild and shadow index | `Indexer jobs, MIGRATION` | connector/jobs/concurrency regressions |
| File26-FR-006 | Deletion and tombstones | `Indexer tombstones/reconcile` | reconciliation + index lifecycle regressions |
| File26-FR-007 | Unicode and language normalization | `Normalizer` | normalization/ranking tests + contract tests |
| File26-FR-008 | Transliteration strategy | `Normalizer settings/hooks` | normalization/ranking + central-plan tests |
| File26-FR-009 | Spelling and synonym service | `Normalizer settings/hooks` | normalization/ranking + central-plan tests |
| File26-FR-010 | Autocomplete safety | `Search::suggest, Security, JS` | security + public-surface regressions |
| File26-FR-011 | Filters, facets and sorting | `Search, Routes, REST` | contract + public-surface regressions |
| File26-FR-012 | Hybrid retrieval and deduplication | `Search, Ranking` | contract + search integrity tests |
| File26-FR-013 | Versioned ranking policy | `Governance, Ranking` | governance + doctor-ranking regressions |
| File26-FR-014 | Authority and source quality | `Ranking` | ranking/central-plan tests |
| File26-FR-015 | Freshness and popularity controls | `Ranking, Central_Plan` | central-plan + search tests |
| File26-FR-016 | Safety and status gate | `Search, Ranking, Central_Plan` | search/security/public-surface regressions |
| File26-FR-017 | Diversity and concentration limits | `Ranking` | ranking/central-plan tests |
| File26-FR-018 | Paid/promoted separation | `Governance, Ranking, Central_Plan` | forbidden-signal scan + governance tests |
| File26-FR-019 | Cold-start recommendations | `Recommendations` | recommendation privacy tests |
| File26-FR-020 | Consented personalization | `Recommendations` | recommendation + preference-privacy regressions |
| File26-FR-021 | Explainability and controls | `Recommendations, templates/API` | contract + public-surface tests |
| File26-FR-022 | Minor and vulnerable-user policy | `Security, Recommendations` | security + recommendation tests |
| File26-FR-023 | Feedback integrity | `Recommendations` | recommendation + preference-privacy regressions |
| File26-FR-024 | Evaluation and experiments | `Governance reports, evaluation hooks` | governance + central-plan tests |
| File26-FR-025 | Controlled taxonomy registry | `Taxonomy` | taxonomy lifecycle regressions |
| File26-FR-026 | Classification workflow | `Taxonomy, Governance` | taxonomy + governance regressions |
| File26-FR-027 | Knowledge graph edges | `Graph` | graph lifecycle regressions |
| File26-FR-028 | Merge, split and correction | `Taxonomy, Governance, MIGRATION` | taxonomy lifecycle regressions |
| File26-FR-029 | Graph query safety | `Graph, Search graph signal` | graph + jobs/cache regressions |
| File26-FR-030 | Human-readable topic pages | `Routes/templates` | public-surface/cache/a11y regression |
| File26-FR-031 | Privacy-minimized query telemetry | `Search metrics, Central_Plan radar` | privacy/retention + central-plan tests |
| File26-FR-032 | Relevance evaluation registry | `Governance reports, QA fixtures` | governance/contract tests |
| File26-FR-033 | Safe configuration | `Admin, Governance, Security` | governance/security/admin regressions |
| File26-FR-034 | Health and drift dashboard | `Health, Governance reports` | health/admin/observability regression |
| File26-FR-035 | Export and audit | `Privacy, Central_Plan privacy hooks, Governance` | privacy/retention + security regressions |
| File26-FR-036 | Degraded modes | `Search, Recommendations, Indexer, Health` | reconciliation/search/jobs/health regressions |

## Central-plan / continuous-value addendum mapping

The v1.2 repository also contains the central-plan requirements represented in `Central_Plan`. Their canonical labels remain the labels used by that governing addendum; this table does not renumber them into new permanent File numbers.

| Requirement | Repository mapping | Evidence |
|---|---|---|
| CV-164 / F26-CEN-01 | Advanced search contract and bounded filters | `Central_Plan`, central-plan contract tests |
| CV-165 | Saved-query ownership and retention | `Central_Plan` | central-plan tests + privacy hooks |
| CV-166 | Sensitive saved-query encryption/step-up contract | `Central_Plan`, `Security` | central-plan/security tests |
| CV-167 | Zero-result recovery | `Central_Plan` | central-plan tests |
| CV-168 | Search safety diversion | `Central_Plan` | central-plan tests |
| CV-169 / F26-CEN-02 | Public ranking constitution | `Central_Plan`, `Ranking`, `Doctor_Ranking` | central-plan + doctor-ranking tests |
| CV-170 | Canonical knowledge/research consumption only | File 06/15 remain owners; File 26 consumes projections | owner-contract/architecture evidence |
| CV-171 | Trend truth remains File 15 | `Central_Plan::editorial_radar` labels derivative telemetry only | central-plan docs/tests |
| CV-172 | Privacy-minimized editorial/search telemetry | `Central_Plan`, `metrics` | privacy/retention + central-plan tests |
| CV-173 | Explicit identity-free content-gap submission | `Central_Plan` | central-plan tests |
| CV-174 | Index freshness/integrity evidence | `Central_Plan` | central-plan tests |
| CV-175 | Free-tier/rank parity and prohibited paid signals | `Central_Plan`, `Ranking` | forbidden-signal scan + central-plan tests |

## Status law

All File 26 functional requirements remain **Must** unless a later Founder-approved change record explicitly changes them. `Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed` and `Operational` are separate states. This matrix supports the first two states only; package/CI evidence comes from an exact-head QA run, and staging/live states require independent external evidence and deployment parity.
