# File 26 Requirements Traceability

## Phase 26A — Foundation

| Requirement | Implementation | Evidence |
|---|---|---|
| F26-A-001 Repository and runtime identity | README, plugin bootstrap, constants | CI lint and repository review |
| F26-A-002 Fail-closed activation baseline | `Plugin::activate()` | PHP lint; Hostinger-equivalent staging pending |
| F26-A-003 Versioned connector contract | `ConnectorInterface`, `ConnectorManifest`, JSON Schema | Foundation test suite |
| F26-A-004 Duplicate connector rejection | `ConnectorRegistry::register()` | Foundation test suite |
| F26-A-005 Public-safe connector health | `ConnectorRegistry::sanitizeHealth()` | Foundation test suite |
| F26-A-006 Canonical document identity/version | `SearchDocument` | Foundation test suite |
| F26-A-007 Visibility-envelope invariants | `VisibilityEnvelope` | Foundation test suite |
| F26-A-008 Cursor batch and tombstone invariants | `ConnectorBatch`, `IndexTombstone` | Foundation and review suites |
| F26-A-009 Synthetic evaluation baseline | `tests/fixtures/golden-queries.json` | Foundation test suite |
| F26-A-010 Threat and data-flow baseline | Architecture and threat-model documents | Manual review |
| F26-A-011 Non-destructive lifecycle | deactivation and `uninstall.php` | Source review; Hostinger-equivalent staging pending |
| F26-A-012 Automated checks | GitHub Actions CI | Per-head workflow evidence |

## Phase 26B — Public connector and shadow proof

| Requirement | Implementation | Evidence |
|---|---|---|
| F26-B-001 Typed owner source provider | `SourceBatchProviderInterface` | PHP lint and connector tests |
| F26-B-002 File 21 public connector | `File21PublicationsConnector` | Public record, field and deletion tests |
| F26-B-003 File 10 public connector | `File10VideosConnector` | Public video and host-policy tests |
| F26-B-004 Public field minimization | connector-specific allowlists | Unexpected sensitive-field regression |
| F26-B-005 Canonical destination protection | `AbstractPublicOwnerConnector::assertCanonicalHost()` | External/lookalike host regressions |
| F26-B-006 Bounded cursor and page law | connector base + `ConnectorBatch` | Over-limit, continuing and terminal cursor tests |
| F26-B-007 Strict scalar/no-coercion law | connector record/tombstone mapping | Array identity and numeric reason regressions |
| F26-B-008 Tombstone propagation | tombstone-bearing `ConnectorBatch`, `ShadowIndex` | Deletion and stale-resurrection regressions |
| F26-B-009 Query-time eligibility | `AudienceContext`, `EligibilityEvaluator` | guest/capability/entitlement/age/guardian tests |
| F26-B-010 Deterministic shadow query | `ShadowIndex::query()` | Urdu and English synthetic queries |
| F26-B-011 Owner/shadow parity proof | `ShadowIndex::reconcileExpectedKeys()` | missing/orphaned and duplicate-key tests |
| F26-B-012 Truthful runtime status | plugin `0.2.0`, README and health stage | repository review and CI evidence |

## Phase 26C — Persistent generations and rebuild jobs

| Requirement | Implementation | Evidence |
|---|---|---|
| F26-C-001 Backend-neutral persistence contract | `ShadowStoreInterface` | interface/source review and lint |
| F26-C-002 Persistent derivative schema | `SchemaManager` and `wp_s26_*` tables | successful isolated WordPress/MariaDB smoke |
| F26-C-003 Candidate-generation isolation | `InMemoryShadowStore`, `WordPressShadowStore` | active-alias failure and rollback tests |
| F26-C-004 Bounded rebuild/delta orchestration | `RebuildCoordinator`, `RebuildWorker` | two-page bounded rebuild tests |
| F26-C-005 Durable cursor checkpoints | store checkpoint contracts and tables | continuation, terminal, stale and database smoke tests |
| F26-C-006 Idempotent job identity | `RebuildJob`, queue implementations | duplicate enqueue, stale-job and persistent queue tests |
| F26-C-007 Bounded retries and dead letter | `RetryPolicy`, job queues, worker | delayed retry, exhaustion, active-alias safety and database smoke |
| F26-C-008 Lease and concurrency boundary | in-memory/WordPress lease locks | contention, expiry, takeover and stale-token tests; DB stress pending |
| F26-C-009 Tombstone/document chronology | generation stores | stale write and cross-owner tests |
| F26-C-010 Deterministic validation checksum | sorted canonical key + payload hashes | ingestion-order regression and database smoke |
| F26-C-011 Count/divergence promotion gate | `GenerationValidationPolicy` + coordinator | empty, invalid policy and explicit-zero tests |
| F26-C-012 Atomic alias promotion and rollback | store lifecycle + predecessor record | replacement/rollback tests and database promotion smoke |
| F26-C-013 Fail-closed schema/runtime health | activation schema verification and health stage | isolated WordPress activation and table/column verification |
| F26-C-014 Truthful runtime identity | plugin/schema `0.3.0` and Phase 26C documentation | repository review and per-head CI |

## Phase 26D — Persistent query and operations control plane

| Requirement | Implementation | Evidence |
|---|---|---|
| F26-D-001 Internal active-generation query contract | `ActiveGenerationRepositoryInterface`, `PersistentQueryService` | Phase 26D query suite and database Urdu query smoke |
| F26-D-002 Signed snapshot pagination | `QueryCursorCodec`, query fingerprint | tampering, query mismatch and generation-swap tests |
| F26-D-003 Strict stored-row integrity | payload SHA-256, `SearchDocumentHydrator` | hydration, extra-field, reordered-key and timestamp regressions |
| F26-D-004 Query-time audience authorization | `EligibilityEvaluator` after persistent hydration | guest, entitlement, age and guardian tests |
| F26-D-005 Urdu/English deterministic matching | persistent query service and repositories | Unicode/locale tests and real WordPress/MariaDB Urdu query |
| F26-D-006 Bounded domain/locale/candidate filters | `PersistentQuery`, repository candidate limits | malformed, duplicate-normalized and over-limit tests |
| F26-D-007 Bounded reusable worker execution | `WorkerLoop` | two-page completion, idle and ceiling tests |
| F26-D-008 WP-Cron and real-cron adapters | `WordPressWorkerScheduler`, `WordPressCliAdapter` | source/lint and Phase 26D operations suite |
| F26-D-009 Missed-run detection and recovery | `MissedRunDetector`, throttled `admin_init` check | idle, never-run, on-time, overdue and future-time tests |
| F26-D-010 Administrator diagnostics | operations REST route and `WordPressRuntime::diagnostics()` | capability/source review; Hostinger-equivalent acceptance pending |
| F26-D-011 Guarded dead-letter replay | dead-letter adapters + replay audit fields | unit/adversarial regressions and successful database replay smoke |
| F26-D-012 Read-only owner connector probe | `OwnerConnectorProbe` | terminal, repeated-cursor, checksum and cross-owner tests |
| F26-D-013 Supported active-plugin schema upgrade | `SchemaUpgradeCoordinator` | version-path tests; fresh schema/database verification green; live upgrade rehearsal pending |
| F26-D-014 Finite cron cleanup | bounded `WordPressWorkerScheduler::unschedule()` | fresh adversarial source regression |
| F26-D-015 Isolated database harness | PR-gated WordPress/MariaDB workflow and smoke script | WordPress 7.0.2, MariaDB 11.4, PHP 8.3; 21 assertions passed |
| F26-D-016 Truthful runtime identity | plugin/schema `0.4.0`, internal-only health status | repository review and exact-head CI |
| F26-D-017 Nullable persistent cursor parity | explicit SQL `NULL`, legacy `NULL/''` normalization | defect discovered by real DB run; corrected smoke rerun green |

## Review round 1 corrections

- Preserved Unicode text during candidate matching.
- Narrowed authorization assertions to restricted-only terms.
- Corrected the WP-CLI real-cron callback binding.
- Added a supported locked schema upgrade path for already-active `0.3.0` installations.

## Fresh adversarial review corrections

- Rejected relative and non-ISO stored timestamps.
- Rejected locale aliases that become duplicates after normalization.
- Added administrator-only throttled missed-run checks.
- Bounded cron unscheduling and stopped on WordPress removal failure.
- Added cursor offset/fingerprint, payload-shape, connector-pagination and schema-skip regressions.

## Real database correction record

- Corrected the WP-CLI `eval-file` smoke harness context without weakening plugin strict typing.
- Corrected nullable job-cursor persistence: SQL `NULL` is now explicit, hydrated `NULL/''` becomes canonical `null`, and legacy retry lookup accepts both historical forms.
- Re-ran the full isolated WordPress/MariaDB smoke after correction; all 21 assertions passed.

## Pending acceptance evidence

- WordPress activation, in-place `0.3.0` to `0.4.0` upgrade, reactivation and deactivation on isolated Hostinger-equivalent staging.
- MariaDB advisory-lock contention, concurrent-worker stress, backup/restore and rollback rehearsal.
- Approved real File 21 and File 10 provider adapters and jointly frozen owner contracts.
- Representative staging-data parity, leakage, deletion-propagation and latency SLO evidence.
- Public query API, autocomplete, File 20 search surface and File 25 result-card work remain outside this batch.
- Source/package parity, installable ZIP and Founder staging acceptance remain pending.
