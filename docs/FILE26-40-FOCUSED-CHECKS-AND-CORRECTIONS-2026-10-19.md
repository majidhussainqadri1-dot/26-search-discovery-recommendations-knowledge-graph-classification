# File 26 — Forty Focused Plan-to-Code Checks and Corrective Ledger (2026-10-19)

**Repository:** `majidhussainqadri1-dot/26-search-discovery-recommendations-knowledge-graph-classification`  
**Frozen original main HEAD:** `bbea3aad466792a4a6a62b53532bbd45c7c592de`  
**Audit branch:** `audit/file26-forty-check-revocation-20261019`  
**Source baseline:** Three-Central-Plans Consolidated Governing Master Plan; File 26 v1.1 Future Search Knowledge Intelligence 24; and cross-owner plans Files 10, 11, 14, 17, 25 and Traffic/Disease Intelligence.

## Evidence boundaries

This record contains **40 distinct scoped source-level checks**, each mapped to the file's requirements and inter-file ownership. Presence of a symbol/class is **not** a behavioral pass, exhaustive feature-coverage finding, deployed artifact, or forty separate full-system end-to-end reruns. The attached comprehensive functional/staging/security/load/browser and genuine cross-repository acceptance gates are still required. Previous August 20-round historical evidence is not counted as these new checks.

## 40 checks

| # | Plan / owner | Reviewed obligation | Implementation locus | Observed status |
|---:|---|---|---|---|
| 1 | F26-FR-001 | Connector registration | Connectors | Corresponding source path/symbol found; behavior not fully verified |
| 2 | F26-FR-002 | Canonical identity/version | Indexer | Corresponding source path/symbol found; behavior not fully verified |
| 3 | F26-FR-003 | Visibility at retrieval/click | Search / Connectors | Corresponding source path/symbol found; behavior not fully verified |
| 4 | F26-FR-004 | Change sequence and replay | Indexer | Corresponding source path/symbol found; behavior not fully verified |
| 5 | F26-FR-005 | Shadow/backfill jobs | Indexer | Corresponding source path/symbol found; behavior not fully verified |
| 6 | F26-FR-006 | Tombstones/revocation | Indexer | Corresponding source path/symbol found; behavior not fully verified |
| 7 | F26-FR-007 | Unicode normalization | Normalizer | Corresponding source path/symbol found; behavior not fully verified |
| 8 | F26-FR-008 | Roman-Urdu aliases | Normalizer | Corresponding source path/symbol found; behavior not fully verified |
| 9 | F26-FR-009 | Synonyms | Normalizer | Corresponding source path/symbol found; behavior not fully verified |
| 10 | F26-FR-010 | Autocomplete | Search | Corresponding source path/symbol found; behavior not fully verified |
| 11 | F26-FR-011 | Filters/facets | Search | Corresponding source path/symbol found; behavior not fully verified |
| 12 | F26-FR-012 | Hybrid graph blend | Search | Corresponding source path/symbol found; behavior not fully verified |
| 13 | F26-FR-013 | Ranking policy versions | Ranking | Corresponding source path/symbol found; behavior not fully verified |
| 14 | F26-FR-014 | Authority/source quality | Ranking | Corresponding source path/symbol found; behavior not fully verified |
| 15 | F26-FR-015 | Freshness/popularity | Ranking | Corresponding source path/symbol found; behavior not fully verified |
| 16 | F26-FR-016 | Medical safety | Central_Plan | Corresponding source path/symbol found; behavior not fully verified |
| 17 | F26-FR-017 | Diversity | Ranking | Corresponding source path/symbol found; behavior not fully verified |
| 18 | F26-FR-018 | Payment/donor non-signal | Central_Plan | Corresponding source path/symbol found; behavior not fully verified |
| 19 | F26-FR-019 | Guest cold start | Recommendations | Corresponding source path/symbol found; behavior not fully verified |
| 20 | F26-FR-020 | Explicit personalization consent | Recommendations | Corresponding source path/symbol found; behavior not fully verified |
| 21 | F26-FR-021 | Explanation and controls | Recommendations | Corresponding source path/symbol found; behavior not fully verified |
| 22 | F26-FR-022 | Age/guardian checks | Recommendations | Corresponding source path/symbol found; behavior not fully verified |
| 23 | F26-FR-023 | Feedback controls | Recommendations | Corresponding source path/symbol found; behavior not fully verified |
| 24 | F26-FR-024 | Governed rollback | Governance | Corresponding source path/symbol found; behavior not fully verified |
| 25 | F26-FR-025 | Stable taxonomy concept IDs | Taxonomy | Corresponding source path/symbol found; behavior not fully verified |
| 26 | F26-FR-026 | Classification approval | Governance | Corresponding source path/symbol found; behavior not fully verified |
| 27 | F26-FR-027 | Provenance graph | Graph | Corresponding source path/symbol found; behavior not fully verified |
| 28 | F26-FR-028 | Merge/split | Taxonomy | Corresponding source path/symbol found; behavior not fully verified |
| 29 | F26-FR-029 | Graph depth/degree | Graph | Corresponding source path/symbol found; behavior not fully verified |
| 30 | F26-FR-030 | Topic routes | Routes | Corresponding source path/symbol found; behavior not fully verified |
| 31 | F26-FR-031 | Privacy-minimized metrics | Search | Corresponding source path/symbol found; behavior not fully verified |
| 32 | F26-FR-032 | Relevance QA harness | qa/run-tests.sh | Corresponding source path/symbol found; behavior not fully verified |
| 33 | F26-FR-033 | Admin authorization | Admin | Corresponding source path/symbol found; behavior not fully verified |
| 34 | F26-FR-034 | Health/drift | Health | Corresponding source path/symbol found; behavior not fully verified |
| 35 | F26-FR-035 | Privacy export/erasure | Privacy | Corresponding source path/symbol found; behavior not fully verified |
| 36 | F26-FR-036 | Partial/degraded reporting | Search | Corresponding source path/symbol found; behavior not fully verified |
| 37 | CROSS-00 | Membership assertions | Security | Corresponding source path/symbol found; behavior not fully verified |
| 38 | CROSS-20 | Shell/route ownership | Routes | Corresponding source path/symbol found; behavior not fully verified |
| 39 | CROSS-25/24 | Visual and assurance gates | Owner_Contracts | Corresponding source path/symbol found; behavior not fully verified |
| 40 | CROSS-04/21 | Migration cutover reindex receipt | Plugin | Corresponding source path/symbol found; behavior not fully verified |

## Proven defects at original HEAD, corrected on audit branch pending CI

1. **D26-20261019-01: Dynamic topic HTML cache disclosure risk.** `Routes::template_redirect()` sent a public caching header for status-200 topic pages despite `topic_shortcode()` calling audience-dependent `Search::run()`. A signed-in visitor's eligible nonpublic snippets could be cached by shared intermediaries. **Correction:** all File 26 dynamic HTML pages use `nocache_headers()` while non-indexable states retain their robots rules.
2. **D26-20261019-02: Revoked result retention in caches.** Search results used `wp_cache_get/wp_cache_set` for 300 seconds, with no complete owner-revocation eviction; REST projections advertised public max-age for up to 60 seconds. Tombstone/restriction could not guarantee instantaneous cache removal. **Correction:** disable shared result object caching and force `Cache-Control: private, no-store` for dynamic File 26 REST projections until proven distributed revocation is installed. Tradeoff: relevance performance/load must be rebenchmarked before staging acceptance.
3. **D26-20261019-03: False reconciliation success.** `REST::reconcile()` discarded the return value of `Indexer::reconcile()` and always sent `reconciled=true`, even when it returned `WP_Error`. **Correction:** propagate the actual result through `REST::respond()`.

## Tests and restrictions

- Existing Round 03 source check updated to assert that the revoked-result shared cache cannot run.
- New `tests/review-round-24-revocation-cache-and-reconcile.php` adds six focused regression assertions; it is executed automatically by the `qa/run-tests.sh` round-test loop.
- These are source regression checks, **not** host/CDN, access-control, migration, failover or real-record integration tests. Exact branch/PR CI must be checked and failures fixed before describing repository QA as green.
- Release ZIP in historical `release/` does not establish current exact-HEAD packaging. Rebuild and verify manifest/checksum/source-package parity.
- Live/staging/runtime version, deployed schema, WordPress `wp_options`, migration state and artifact hash were not accessible. No production patch/deployment or operational completion claim.
- Block production acceptance until owner connections Files 03,05,06,07,08,10,11,12,15,18,21; File 00/20/24/25 contracts; Files 10/11/14/17 boundary and public/privacy flows; full staging acceptance and Founder deployment approval.

## Release evidence slots (must not be fabricated)

Repository HEAD: exact GitHub commit required.  
Deployed Version: **unverified**.  
DB Version: **unverified**.  
Migration State: **unverified**.  
Live Verification Status: **not performed**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
