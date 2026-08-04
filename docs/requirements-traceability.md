# File 26 Requirements Traceability — Runtime 1.0.0

## Status law

This matrix proves the File 26 **coded, packaged and automated-QA candidate**. It does not convert repository evidence into Hostinger staging acceptance, production deployment or operational readiness.

| Status | Current evidence |
|---|---|
| Specified | File 26 reviewed master plan and governing platform plan |
| Coded | Runtime/plugin/schema `1.0.0`; public/admin/API/operations source present |
| Packaged | Deterministic install/source ZIP builder, manifest, checksums and SPDX SBOM |
| Automated-QA Green | PHP 8.1/8.3 complete matrix plus isolated WordPress/MariaDB workflow |
| Staging-Accepted | Pending real Hostinger-equivalent owner/UI/rollback acceptance |
| Live-Deployed | Not claimed |
| Operational | Not claimed |

## Foundation, ownership and safety

| Requirement family | Implementation | Automated/review evidence |
|---|---|---|
| One canonical derivative search owner | `Plugin`, `ConnectorRegistry`, `SearchDocument`, owner-prefix validation | foundation, connector and adversarial suites |
| No canonical owner replacement | owner adapter contract, read-only probes, derivative schema, non-destructive uninstall | source review, default connector tests, WP smoke |
| Fail-closed activation and health | `SchemaManager`, `SchemaUpgradeCoordinator`, truthful health response | PHP suites; all 19 tables verified in MariaDB |
| Versioned contracts | `ConnectorInterface`, `ConnectorManifest`, source provider and JSON schema | foundation and Phase 26E complete suites |
| Public-only indexing boundary | C1 manifests, field allowlists, canonical HTTPS host, visibility envelope | connector field/host/cross-owner negative tests |
| Private-domain exclusion | no general adapters for messages, clinical charts, identity/payment secrets, drafts or restricted attachments | review/adversarial source checks |
| Click/action revalidation | ranked/recommendation/topic response markers and audience rechecks | review chain and WP public-route assertions |
| Non-destructive lifecycle | deactivation scheduler cleanup; `uninstall.php` no destructive table removal | source/lint review; staging uninstall acceptance pending |

## Connector and index lifecycle

| Requirement family | Implementation | Evidence |
|---|---|---|
| Approved domain registry | nine `GenericPublicOwnerConnector` registrations for Files 05/06/09/10/11/12/15/18/21 | Phase 26E complete and adversarial suites |
| Bounded owner pages | `WordPressFilterBatchProvider`, `ConnectorBatch` | cursor/page/type/terminal regressions |
| Strict record mapping | canonical identity, owner, type, timestamp, URL and field validation | cross-owner, external URL, unsafe value tests |
| Tombstones and stale resurrection resistance | `IndexTombstone`, shadow/persistent generation stores | Phase 26B/C suites |
| Blue/green generations | `ShadowStoreInterface`, `WordPressShadowStore` | persistent generation and isolated DB smoke |
| Durable checkpoints | generation/connector cursor records | pagination, stale checkpoint and DB tests |
| Validation before promotion | deterministic checksum, minimum count, divergence and tombstone ceilings | Phase 26C persistence/adversarial tests |
| Atomic promotion and rollback predecessor | active alias and previous generation | unit tests and MariaDB promotion smoke |
| Ordered delta ingestion | `ChangeEvent`, `WordPressChangeEventLedger`, owner sequence ledger | Phase 26E and MariaDB append/idempotency/ack tests |
| Deletion reconciliation | `WordPressPurgeLedger`, overdue checks and CLI reconciliation | Phase 26E and MariaDB request/complete/verify tests |

## Query understanding and retrieval

| Requirement family | Implementation | Evidence |
|---|---|---|
| Unicode normalization | `UnicodeNormalizer` with Arabic/Urdu presentation and digit normalization | Urdu/English and numeric-token regressions |
| Urdu/English transliteration | versioned `TransliterationService` | Phase 26E complete suite |
| Approved synonyms | `SynonymRegistry`, prohibited-pair support and bounded expansion | duplicate/numeric-string adversarial tests |
| Sensitive query classification | `SensitiveQueryClassifier` | email, phone/CNIC-like and clinical tests |
| No raw sensitive telemetry | `QueryPlan::allowsRawTelemetry`, `TelemetryRedactor` | complete/review tests |
| Persistent active-generation query | `WordPressActiveGenerationRepository`, `PersistentQueryService` | Phase 26D and MariaDB Urdu query |
| Signed snapshot cursors | `QueryCursorCodec`, query fingerprint and generation binding | tamper, mismatch, offset and generation-swap tests |
| Stored payload integrity | SHA-256 and strict `SearchDocumentHydrator` | malformed/reordered/extra/timestamp tests |
| Eligibility after hydration | `EligibilityEvaluator` with auth/capability/entitlement/age/guardian context | Phase 26B/D tests |
| Bounded filters and pagination | `PersistentQuery`, normalized domain/locale lists | duplicate/over-limit/type tests |

## Ranking, suggestions and facets

| Requirement family | Implementation | Evidence |
|---|---|---|
| Versioned ranking | `RankingPolicy`, `RankingEngine`, `RankedResult` | complete and adversarial suites |
| Relevance/authority/quality/freshness | bounded component scoring and explanations | deterministic ranking tests |
| Diversity/anti-concentration | creator/domain caps | dominant-creator adversarial test |
| Deterministic ties | canonical-key tie breaking | ranking suite |
| Safe suggestions | prefix normalization and post-eligibility documents only | complete/review tests and public route registration |
| Eligibility-aware facets | actual search snapshot then `FacetService::counts()` | review contract trace and route smoke |
| Public API parsing safety | bounded integer/cursor/list/boolean parsers; numeric-string preservation | review/adversarial tests |

## Recommendations and user controls

| Requirement family | Implementation | Evidence |
|---|---|---|
| Guest/cold start | `RecommendationContext::coldStart`, source-quality curation | complete suite |
| Consented personalization | authenticated public API and explicit consent | review/adversarial source checks |
| Minor protection | canonical audience age plus verified guardian requirement | adversarial tests |
| Educational/source-quality priority | recommendation scoring and reason codes | complete suite |
| Explainability | `why_this` reason list and policy version | complete suite |
| Hide/not-interested/reset/opt-out | result controls and idempotent feedback store | in-memory and MariaDB feedback tests |
| Reversible feedback | active/reversed states and actor purge | complete and DB tests |
| Prohibited signal exclusion | API declaration and no clinical/message/payment inputs | review/adversarial tests |

## Taxonomy, classification and knowledge graph

| Requirement family | Implementation | Evidence |
|---|---|---|
| Stable versioned taxonomy | `TaxonomyTerm`, `TaxonomyRegistry`, `WordPressTaxonomyStore` | complete and MariaDB round-trip |
| Label/alias collision prevention | normalized active-label registry | adversarial collision test |
| Parent cycle prevention | DFS cycle check | adversarial cycle test |
| Merge preview and redirect | preview/version check, affected relation rewrite | complete/adversarial tests |
| Reviewed classification | `ClassificationWorkflow`, independent reviewer law | self-review rejection and approval tests |
| Appeals | explicit appealed state | complete suite |
| Typed graph edges | controlled edge types and endpoint existence | complete suite |
| Provenance enforcement | source owner must own source endpoint or be `file26-curated` | fresh adversarial defect regression and DB hydration |
| Visibility-aware traversal | audience eligibility on start/target/edge | guest vs authorized tests |
| Bounded graph reads | depth/degree/node ceilings and hash-verified persistent edges | adversarial and MariaDB tests |

## Governance, evaluation, telemetry and export

| Requirement family | Implementation | Evidence |
|---|---|---|
| Versioned rollbackable policies | `VersionedConfiguration`, `ConfigurationRegistry`, persistent policy store | complete/adversarial tests |
| Dual approval for high risk | independent approvers and predecessor identity | adversarial tests |
| Reviewed evaluation sets | `EvaluationCase`, `EvaluationRegistry`, persistent store | complete tests |
| Safety-critical release block | forbidden hit/missing critical expectation makes `release_pass=false` | complete/adversarial tests |
| Stable metrics | recall is always a float | defect regression |
| Privacy-minimized telemetry | `TelemetryRedactor`, daily aggregate store and retention purge | complete/review tests and CLI |
| Health guardrails | connector lag, failed events, leakage, zero result, latency and graph integrity | complete/adversarial tests |
| Signed scoped export | `ExportTokenService`, persistent single-use store and export package | review/adversarial tests |
| Cross-user/private exclusion | export metadata and source review | review suite |
| Audit evidence | `WordPressAuditLog`, trace/reason/policy fields | schema/store traceability and MariaDB table verification |

## Public, admin and operations interfaces

| Requirement family | Implementation | Evidence |
|---|---|---|
| Public search/suggest/facets | `PublicApiController` | PHP suites and WordPress route/query smoke |
| Recommendations/feedback/topic APIs | `PublicApiController` | PHP suites and route/topic smoke |
| Admin health/taxonomy/graph/classification | `AdminApiController` | route registration and 19-table DB smoke |
| Policy/evaluation/telemetry/export | `AdminApiController`, governance stores | route and source tests |
| Queue/scheduler diagnostics | `WordPressRuntime`, operations route | Phase 26D and DB tests |
| Guarded dead-letter replay | exact job/error/generation/checkpoint/replay conditions | Phase 26D and DB replay test |
| Connector probe | `OwnerConnectorProbe` and REST/CLI adapters | repeated cursor/cross-owner/checksum tests |
| WP-Cron and real cron | `WordPressWorkerScheduler`, `WorkerLoop`, WP-CLI | Phase 26D tests |
| Missed-run recovery | `MissedRunDetector` and throttled admin check | operations/adversarial tests |
| Reconciliation and retention CLI | `WordPressCliApplication` | source review and operations runbook |

## Schema, migration and package

| Requirement family | Implementation | Evidence |
|---|---|---|
| Complete schema identity | `SchemaManager::SCHEMA_VERSION = 1.0.0` | 19-table registry and MariaDB assertions |
| Supported locked upgrade | advisory lock; 0.1/0.2/0.3/0.4 to 1.0 | Phase 26D adversarial and DB fresh install; Hostinger upgrade rehearsal pending |
| Deterministic package | `tools/build-package.py` | two independent builds compared byte-for-byte |
| Install/source parity | canonical sorted file manifests in both artifacts | deterministic package CI |
| Checksums | `CHECKSUMS.sha256` | package CI artifact |
| SBOM | `SBOM.spdx.json` | package CI artifact |
| No transfer/prototype residue | temporary payloads/workflow and duplicate `src/Complete` implementations removed | unresolved-marker and repository review |
| Mandatory two review suites | Composer and CI include Phase 26E complete, review and adversarial suites | exact-head CI |

## Automated evidence totals

Per PHP version:

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
| **Total** | **320** |

The PHP 8.1/8.3 matrix therefore executes 640 assertions. The isolated WordPress 7.0.2 / MariaDB 11.4 / PHP 8.3 smoke executes 83 additional assertions.

## Defects corrected during final coding cycle

- reconciled legacy schema tests with the approved locked upgrade chain;
- corrected a nonexistent destination accessor;
- added explicit public visibility construction;
- prevented numeric-string coercion in query tokens, synonyms, transliteration, API lists and recommendation maps;
- stabilized evaluation recall as a float metric;
- reconciled Phase 26E tests to canonical contracts rather than temporary DTO prototypes;
- removed duplicate prototype implementations;
- enforced graph source-owner provenance at creation, insertion and hydration;
- made topic/result click-time revalidation explicit;
- hardened public cursor, boolean and associative-list parsing;
- expanded the real database smoke from 21 to 83 assertions and all 19 tables;
- removed temporary payload transfer machinery;
- corrected CI unresolved-marker self-matching.

## Remaining non-coding acceptance gates

The following are not hidden coding omissions; they require external modules, real environments, people or operational evidence:

1. jointly freeze and implement the owner-side provider filters in the relevant File 05/06/09/10/11/12/15/18/21 repositories;
2. run representative real-data parity, leakage, deletion-lag and latency measurements;
3. integrate File 20 global search surface and File 25 result/topic cards without duplicate ownership;
4. rehearse fresh install and each deployed-version upgrade on Hostinger-equivalent staging;
5. run concurrent-worker/advisory-lock stress and large-data/load tests;
6. prove backup restore, generation rollback and plugin rollback;
7. complete responsive, browser, RTL and WCAG acceptance through File 20/File 25;
8. record security/privacy/medical/Sharīʿah and Founder acceptance as applicable;
9. deploy only the approved immutable artifact, run production smoke and retain a monitored rollback window;
10. establish operational owners, alerts, support, backups and incident routines.

Until those gates pass, the correct label is:

```text
File 26 v1.0.0 — Coded, Packaged and Automated-QA Candidate
```

It is not yet `Staging-Accepted`, `Live-Deployed` or `Operational`.
