# File 26 Future Search & Knowledge Intelligence — Traceability v1.3

| Requirement | Runtime route / contract | Primary implementation | Acceptance evidence |
|---|---|---|---|
| F26-FUT-01 | `/future/conversational-grounded-search` | `Future_Search_Core_Trait::conversational_grounded_search()` | grounded citation keys; sensitive provider bypass; no autonomous diagnosis/prescription |
| F26-FUT-02 | `/future/query-planner` | `Future_Search_Core_Trait::query_planner()` | bounded steps; hardened advanced-search execution |
| F26-FUT-03 | `/future/cross-language-search` | `Future_Search_Core_Trait::cross_language_search()` | normalized variants; bounded provider variants; sensitive provider bypass |
| F26-FUT-04 | `/future/semantic-rerank` | `Future_Search_Core_Trait::semantic_rerank()` | eligible candidate set only; provider fallback; prohibited financial/favoritism signals |
| F26-FUT-05 | `/future/multimodal-search` | `Future_Multimodal_Trait::multimodal_search()` | owner authorization attestation; patient-image diagnosis blocked; empty-derived-query rejection |
| F26-FUT-06 | `/future/voice-search` | `Future_Multimodal_Trait::voice_search()` | client transcript or owner-authorized adapter; File 26 retains no audio |
| F26-FUT-07 | `/future/segment-search` | `Future_Multimodal_Trait::segment_search()` | owner revalidation; provenance; bounded page/paragraph/timestamp/chapter/lesson positions |
| F26-FUT-08 | `/future/find-similar` | `Future_Multimodal_Trait::find_similar()` | valid canonical key; owner-revalidated seed; seed excluded |
| F26-FUT-09 | `/future/research-search` | `Future_Knowledge_Trait::research_search()` | central advanced search; special constraints select existing eligible keys only; snapshot attestation |
| F26-FUT-10 | `/future/result-clusters` | `Future_Knowledge_Trait::result_clusters()` | bounded clustering over eligible results only |
| F26-FUT-11 | `/future/graph-path` | `Future_Knowledge_Trait::graph_path()` | owner revalidation; provenance; edge endpoints in returned node set |
| F26-FUT-12 | `/future/evidence-map` | `Future_Knowledge_Trait::evidence_map()` | allowed relation types; provenance; stable 64-character source key required; canonical URL may be supplementary |
| F26-FUT-13 | `/future/disambiguate` | `Future_Knowledge_Trait::disambiguate()` | ambiguity surfaced; automatic merge false |
| F26-FUT-14 | `/future/historical-search` | `Future_Knowledge_Trait::historical_search()` | actual owner snapshot + revalidation; current substitution false |
| F26-FUT-15 | `/future/research-trails` | `Future_User_Data_Trait::research_trails()` | reference-only storage; bounded collections; CAS conflict handling; privacy export/erase |
| F26-FUT-16 | `/future/saved-search-alerts` | `Future_User_Data_Trait::saved_search_alerts()` | sensitive query rejection; File 19 delivery owner; CAS writes |
| F26-FUT-17 | `/future/search-history` + local JS | `Future_User_Data_Trait::search_history()` / `file26-future.js` | local-first; explicit sync; sensitive block; bounded CAS server history |
| F26-FUT-18 | `/future/recommendation-transparency` | `Future_User_Discovery_Trait::recommendation_transparency()` | effective less-personalization; CAS preferences; no paid/donor signal |
| F26-FUT-19 | `/future/discovery-breadth` | `Future_User_Discovery_Trait::discovery_breadth()` | standard/diverse/broad modes; bounded source/author concentration; CAS preference |
| F26-FUT-20 | `/future/geo-availability` | `Future_User_Discovery_Trait::geo_availability()` | owner-revalidated constraints; File 07/08 truth; entity-type reassertion |
| F26-FUT-21 | `/future/search-modes` | `Future_User_Discovery_Trait::search_modes()` | bounded modes/commands; hardened advanced-search execution |
| F26-FUT-22 | `/future/private-search-vault` | `Future_Advanced_Trait::private_search_vault()` | valid membership + recent step-up + native owner authorization; public index false |
| F26-FUT-23 | `/future/external-evidence` | `Future_Advanced_Trait::external_evidence()` | non-sensitive + explicit consent + approved connector + public attestation + HTTPS/provenance/rights; organic merge false |
| F26-FUT-24 | `/future/relevance-lab` | `Future_Advanced_Trait::relevance_lab()` | search-auditor only; production mutation false; bounded comparison metrics |

Cross-cutting regression evidence: `tests/future-intelligence-contract-tests.php`, `tests/review-second-forty-round-contract-tests.php`, both parity review documents, all inherited central/corrective tests and all twenty independent `review-round-*.php` regressions.
