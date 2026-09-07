# Fresh Review Round 20 — Adversarial Ledger Freeze

Date: 2026-09-07 (Asia/Karachi)
Branch reviewed: `review/file26-fresh-20-round-2026-09-06`
Review-start exact HEAD: `0cb6a37cce302a703e7bad8cad858dd7e966c1b6`
Governing basis: File 26 Complete Master Plan 2026 plus the platform central governing plans and the repository/live truth separation law.

## Process law

This round was reviewed to completion before any Round-20 corrective coding began. Findings below were frozen first. Corrections may begin only after this ledger freeze. Repository evidence is not staging/live evidence.

## Frozen defects

| ID | Severity | Requirement / invariant | Frozen finding |
|---|---|---|---|
| R20-D01 | High | Exact-head QA truth | Exact-head GitHub Actions is red because the Round-06 recommendation/privacy regression asserts formatting-specific source strings even though the current guards exist semantically. A refactor can therefore create false CI failure. |
| R20-D02 | Critical | Activation atomicity / role separation | Activation ignores the return value of `Roles::install(true)`. A role-model installation failure can be masked by the following appeals-schema result. Later activation failure also does not compensate scheduled hooks/capability side effects. |
| R20-D03 | High | Privacy lifecycle / destructive uninstall | Explicit destructive uninstall drops File-26 tables but leaves central-plan saved-query user meta, content-gap/migration options and operational failure markers. A destructive purge is therefore incomplete. |
| R20-D04 | Critical | FR-027/029 / production-lane isolation | Public knowledge-graph node/traversal eligibility is derived from node state/visibility without proving that the backing connector is `active`; owner lookup also accepts shadow/approved lanes. Non-production projections can therefore become graph-visible or graph-authoritative. |
| R20-D05 | High | Doctor-ranking production-lane isolation | Doctor-ranking appeal submission verifies a public doctor document but does not join the connector registry and require `active`; a shadow/approved doctor projection can become an appeal target. |
| R20-D06 | High | FR-028 / transaction truth | Taxonomy create/merge/split start and/or commit transactions without consistently checking START/COMMIT results. A database transaction failure can be reported as successful semantic mutation. |
| R20-D07 | Critical | Canonical ownership / domain-owner approval | Taxonomy split targets may supply `owner_file` independently after approval scope was computed only from the source. This allows a curator to create a target attributed to an external canonical owner without that target owner being in the approval scope. |
| R20-D08 | High | FR-028 no silent semantic loss | During taxonomy merge, a duplicate target classification can have its existing provenance overwritten with source provenance. Evidence can be silently lost instead of being preserved/combined. |
| R20-D09 | Medium | FR-031/034 observability | Ordinary search metric persistence ignores database write failure. Search can return success while zero-result/latency telemetry required for health/evaluation is silently missing. |
| R20-D10 | Critical | FR-013/036 / unknown-policy fail closed | Organic ranking policy reads do not distinguish “no active policy” from database read failure. A DB failure silently falls back to defaults, so an unknown/unverified policy state can still rank results. |
| R20-D11 | High | FR-012 hybrid retrieval/dedup | Runtime retrieval is lexical/fuzzy only. There is no governed optional semantic-provider candidate contract, provider-degraded disclosure, or canonical-destination duplicate collapse for cross-domain duplicates. |
| R20-D12 | Critical | FR-005 shadow/blue-green rebuild | Jobs are named `shadow_reindex` but batch items are written directly through the live `documents` projection. There is no isolated candidate generation, parity validation and atomic cutover while the old active index remains available. |
| R20-D13 | High | FR-024 / FR-032 | The repository has QA fixtures and ranking policy governance but no functional relevance-evaluation registry and no bounded experiment lifecycle containing hypothesis, guardrails, sample, duration, stop/rollback, privacy and Founder approval evidence. |
| R20-D14 | High | FR-031 / global PII minimization | Sensitive-query detection strongly covers Pakistan mobile/CNIC patterns but does not robustly catch generic international phone-number patterns. Explicit content-gap storage can therefore retain some PII-like queries as ordinary text. |
| R20-D15 | High | High-risk audit evidence | Multiple governance mutations persist connector/ranking/classification/graph state and then ignore `Security::audit()` failure. They can report ordinary success even when required audit evidence was not persisted. |
| R20-D16 | Medium | FR-034 health/drift dashboard | Health exposes schema/tables/jobs/connectors and failure markers but not the required aggregate zero-result/latency/cache, recommendation-control and graph-integrity signals in one health snapshot. |

## Cross-file review coverage

The round inspected bootstrap/activation/deactivation, schema and uninstall behavior, roles and File-00 membership assertions, connector/owner contracts, indexing and reindex jobs, search/ranking/normalization, recommendations/privacy, taxonomy/classification, graph, doctor ranking/appeals, central-plan routes, health/observability, QA workflow/package truth and requirements traceability against File26 FR-001 through FR-036.

## Ledger freeze result

Round 20 contains defects. No Round-20 corrective coding was performed before this ledger was frozen. The correction phase must address every item above, add durable regressions, run the complete deterministic package QA, and then require exact-head GitHub Actions green before Automated-QA Green may be claimed.

Staging-Accepted, Live-Deployed and Operational remain unclaimed until their separate evidence gates are completed.