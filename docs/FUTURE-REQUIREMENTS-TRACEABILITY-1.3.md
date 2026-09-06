# File 26 Future Search & Knowledge Intelligence — Traceability v1.3

| Requirement | Runtime route / contract | Primary implementation | Acceptance evidence |
|---|---|---|---|
| F26-FUT-01 | `/future/conversational-grounded-search` | `Future_Search_Core_Trait::conversational_grounded_search()` | grounded citation keys; sensitive provider bypass; provider-use/rejection disclosure; no autonomous diagnosis/prescription |
| F26-FUT-02 | `/future/query-planner` | `Future_Search_Core_Trait::query_planner()` | bounded steps; hardened advanced-search execution |
| F26-FUT-03 | `/future/cross-language-search` | `Future_Search_Core_Trait::cross_language_search()` | normalized/deduplicated variants; sensitive generated variants re-screened; deterministic equal-score ordering |
| F26-FUT-04 | `/future/semantic-rerank` | `Future_Search_Core_Trait::semantic_rerank()` | eligible candidate set only; finite usable scores only; deterministic fallback/ties; prohibited financial/favoritism signals |
| F26-FUT-05 | `/future/multimodal-search` | `Future_Multimodal_Trait::multimodal_search()` | owner authorization attestation; strict clinical-image/diagnosis intent handling; patient-image diagnosis blocked; empty-derived-query rejection |
| F26-FUT-06 | `/future/voice-search` | `Future_Multimodal_Trait::voice_search()` | client transcript or owner-authorized adapter; sanitized non-empty audio reference; File 26 retains no audio |
| F26-FUT-07 | `/future/segment-search` | `Future_Multimodal_Trait::segment_search()` | owner revalidation; provenance; safe canonical resource URL; bounded page/paragraph/timestamp/chapter/lesson positions |
| F26-FUT-08 | `/future/find-similar` | `Future_Multimodal_Trait::find_similar()` | valid canonical key; owner-revalidated seed; seed excluded |
| F26-FUT-09 | `/future/research-search` | `Future_Knowledge_Trait::research_search()` | central advanced search; special constraints select existing eligible keys only; snapshot attestation |
| F26-FUT-10 | `/future/result-clusters` | `Future_Knowledge_Trait::result_clusters()` | bounded clustering over eligible results only |
| F26-FUT-11 | `/future/graph-path` | `Future_Knowledge_Trait::graph_path()` | owner revalidation; provenance; edge owner/type integrity; returned edges reference returned nodes; bounded graph is proven to connect requested endpoints |
| F26-FUT-12 | `/future/evidence-map` | `Future_Knowledge_Trait::evidence_map()` | allowed relation types; provenance; stable 64-character source key; non-empty owner; safe canonical resource URL |
| F26-FUT-13 | `/future/disambiguate` | `Future_Knowledge_Trait::disambiguate()` | ambiguity surfaced; automatic merge false |
| F26-FUT-14 | `/future/historical-search` | `Future_Knowledge_Trait::historical_search()` | calendar-valid `as_of`; actual owner snapshot + revalidation; snapshot ID + provenance; current substitution false |
| F26-FUT-15 | `/future/research-trails` | `Future_User_Data_Trait::research_trails()` | reference-only storage; bounded collections; CAS conflict handling; privacy export/erase |
| F26-FUT-16 | `/future/saved-search-alerts` | `Future_User_Data_Trait::saved_search_alerts()` | sensitive query/filter rejection; explicit cadence restricted to hourly/daily/weekly; File 19 delivery owner; CAS writes |
| F26-FUT-17 | `/future/search-history` + local JS | `Future_User_Data_Trait::search_history()` / `file26-future.js` | local-first; explicit sync; sensitive block; bounded/deduplicated browser history; bounded/deduplicated CAS server history; mutation lock |
| F26-FUT-18 | `/future/recommendation-transparency` | `Future_User_Discovery_Trait::recommendation_transparency()` | effective less-personalization bypass; non-personal why-this remains available; consent/opt-out/interests controls remain visible; CAS preferences; no paid/donor signal |
| F26-FUT-19 | `/future/discovery-breadth` | `Future_User_Discovery_Trait::discovery_breadth()` | standard/diverse/broad modes; bounded source/author concentration; true less-personalization state reported; CAS preference |
| F26-FUT-20 | `/future/geo-availability` | `Future_User_Discovery_Trait::geo_availability()` | only doctor/clinic entity types; whole-number radius 1–500 km; owner-revalidated constraints; File 07/08 truth; availability suppression without owner provider; click-time owner revalidation |
| F26-FUT-21 | `/future/search-modes` | `Future_User_Discovery_Trait::search_modes()` | bounded modes/commands; unknown mode only falls back to All; hardened advanced-search execution |
| F26-FUT-22 | `/future/private-search-vault` | `Future_Advanced_Trait::private_search_vault()` | valid membership + strict recent step-up + native owner authorization; public index false; no-store |
| F26-FUT-23 | `/future/external-evidence` | `Future_Advanced_Trait::external_evidence()` | non-sensitive + strict explicit consent + strict approved connector + public attestation + HTTPS/provenance/rights; organic merge false |
| F26-FUT-24 | `/future/relevance-lab` | `Future_Advanced_Trait::relevance_lab()` | search-auditor only; production mutation false; eligible baseline keys only; bounded comparison metrics |

## Cross-cutting evidence

- Future REST permission/dispatch contract: `tests/future-intelligence-contract-tests.php`.
- Earlier Future24 parity/current-cycle gates: `tests/review-second-forty-round-contract-tests.php` and the parity review documents.
- R62–R81 evidence: `docs/FILE26-R62-R81-SEQUENTIAL-REVIEW-2026-08-29.md` plus the corresponding permanent regressions.
- R82–R101 evidence: `docs/FILE26-R82-R101-SEQUENTIAL-REVIEW-2026-09-06.md` plus each defect-bearing `tests/review-round-82-regressions.php` through `tests/review-round-101-regressions.php` (R86 was clean and therefore has no defect regression file).
- `qa/run-tests.sh` explicitly gates current-cycle regression-file presence, fails warning-bearing PHP tests, performs deterministic double-build comparison, safe-ZIP checks, clean-extract regression execution and generated `MANIFEST.sha256` source/package parity.
- `.github/workflows/qa.yml` runs PHP 7.4 and 8.3 and uploads the deterministic WordPress package together with `CHECKSUMS.sha256` from the PHP 8.3 exact-head job.

Repository QA evidence does not imply staging or live deployment. Native owners remain authoritative at click/action time, and provider-dependent behavior fails closed when required authorization, provenance or owner truth is unavailable.
