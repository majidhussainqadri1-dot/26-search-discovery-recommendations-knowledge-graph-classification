# Requirements Traceability Matrix

| Requirement | Capability | Implementation | Evidence |
|---|---|---|---|
| File26-FR-001 | Connector registry | `Connectors, DB` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-002 | Canonical identity and version | `Indexer` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-003 | Visibility envelope | `Search, Security, Connectors` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-004 | Reliable change ingestion | `Indexer` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-005 | Rebuild and shadow index | `Indexer jobs, MIGRATION` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-006 | Deletion and tombstones | `Indexer tombstones/reconcile` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-007 | Unicode and language normalization | `Normalizer` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-008 | Transliteration strategy | `Normalizer settings/hooks` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-009 | Spelling and synonym service | `Normalizer settings/hooks` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-010 | Autocomplete safety | `Search::suggest, Security` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-011 | Filters, facets and sorting | `Search` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-012 | Hybrid retrieval and deduplication | `Search, Ranking` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-013 | Versioned ranking policy | `Governance, Ranking` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-014 | Authority and source quality | `Ranking` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-015 | Freshness and popularity controls | `Ranking` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-016 | Safety and status gate | `Search, Ranking` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-017 | Diversity and concentration limits | `Ranking` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-018 | Paid/promoted separation | `Governance, Ranking` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-019 | Cold-start recommendations | `Recommendations` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-020 | Consented personalization | `Recommendations` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-021 | Explainability and controls | `Recommendations templates/API` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-022 | Minor and vulnerable-user policy | `Security, Recommendations` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-023 | Feedback integrity | `Recommendations` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-024 | Evaluation and experiments | `Governance/docs/evaluation hooks` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-025 | Controlled taxonomy registry | `Taxonomy` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-026 | Classification workflow | `Taxonomy, Governance` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-027 | Knowledge graph edges | `Graph` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-028 | Merge, split and correction | `Taxonomy/Governance/MIGRATION` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-029 | Graph query safety | `Graph` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-030 | Human-readable topic pages | `Routes/templates` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-031 | Privacy-minimized query telemetry | `Search::record_metric` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-032 | Relevance evaluation registry | `Governance reports, QA fixtures` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-033 | Safe configuration | `Admin, Governance` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-034 | Health and drift dashboard | `Health, Governance reports` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-035 | Export and audit | `Privacy, Governance reports` | `tests/contract-tests.php`, local QA, staging checklist |
| File26-FR-036 | Degraded modes | `Search/Recommendations/Indexer failure rules` | `tests/contract-tests.php`, local QA, staging checklist |

All requirements are **Must** unless a later Founder-approved change record explicitly marks a feature conditional and disables its UI/API/data path. Runtime completion still requires the external staging evidence in `STAGING-ACCEPTANCE.md`.
