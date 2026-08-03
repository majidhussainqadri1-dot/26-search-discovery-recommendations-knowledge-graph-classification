# File 26 Requirements Traceability

## Phase 26A — Foundation

| Requirement | Implementation | Evidence |
|---|---|---|
| F26-A-001 Repository and runtime identity | README, plugin bootstrap, constants | CI lint and repository review |
| F26-A-002 Fail-closed activation baseline | `Plugin::activate()` | PHP lint; WordPress staging test pending |
| F26-A-003 Versioned connector contract | `ConnectorInterface`, `ConnectorManifest`, JSON Schema | Foundation test suite |
| F26-A-004 Duplicate connector rejection | `ConnectorRegistry::register()` | Foundation test suite |
| F26-A-005 Public-safe connector health | `ConnectorRegistry::sanitizeHealth()` | Foundation test suite |
| F26-A-006 Canonical document identity/version | `SearchDocument` | Foundation test suite |
| F26-A-007 Visibility-envelope invariants | `VisibilityEnvelope` | Foundation test suite |
| F26-A-008 Cursor batch and tombstone invariants | `ConnectorBatch`, `IndexTombstone` | Foundation and review suites |
| F26-A-009 Synthetic evaluation baseline | `tests/fixtures/golden-queries.json` | Foundation test suite |
| F26-A-010 Threat and data-flow baseline | Architecture and threat-model documents | Manual review |
| F26-A-011 Non-destructive lifecycle | deactivation and `uninstall.php` | Source review; staging test pending |
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
| F26-B-012 Truthful runtime status | plugin `0.2.0`, README and health stage | repository review; WordPress staging pending |

## Phase 26C — Persistent generations and rebuild jobs

| Requirement | Implementation | Evidence |
|---|---|---|
| F26-C-001 Backend-neutral persistence contract | `ShadowStoreInterface` | interface/source review and lint |
| F26-C-002 Persistent derivative schema | `SchemaManager` and `wp_s26_*` tables | schema source review; real `dbDelta` staging pending |
| F26-C-003 Candidate-generation isolation | `InMemoryShadowStore`, `WordPressShadowStore` | active-alias failure and rollback tests |
| F26-C-004 Bounded rebuild/delta orchestration | `RebuildCoordinator`, `RebuildWorker` | two-page bounded rebuild tests |
| F26-C-005 Durable cursor checkpoints | store checkpoint contracts and tables | continuation, terminal, stale and regression tests |
| F26-C-006 Idempotent job identity | `RebuildJob`, queue implementations | duplicate enqueue and stale-job tests |
| F26-C-007 Bounded retries and dead letter | `RetryPolicy`, job queues, worker | delayed retry, exhaustion and active-alias safety tests |
| F26-C-008 Lease and concurrency boundary | in-memory/WordPress lease locks | contention, expiry, takeover and stale-token tests |
| F26-C-009 Tombstone/document chronology | generation stores | stale write and cross-owner tests |
| F26-C-010 Deterministic validation checksum | sorted canonical key + payload hashes | ingestion-order checksum regression |
| F26-C-011 Count/divergence promotion gate | `GenerationValidationPolicy` + coordinator | empty, invalid policy and explicit-zero tests |
| F26-C-012 Atomic alias promotion and rollback | store lifecycle + predecessor record | replacement, supersession and rollback tests |
| F26-C-013 Fail-closed schema/runtime health | activation schema verification and health stage | source/lint; WordPress staging pending |
| F26-C-014 Truthful runtime identity | plugin/schema `0.3.0` and Phase 26C documentation | repository review and per-head CI |

## Phase 26C review round 1 corrections

- Required complete connector checkpoints before validation.
- Prevented generation reuse, stale checkpoint overwrite and completed-checkpoint regression.
- Isolated candidate generations from the active alias during retries and dead-letter failure.
- Added deterministic checksums, persistent counts and rollback predecessor state.
- Added activation-time schema verification rather than trusting `dbDelta` invocation alone.
- Added explicit minimum count, expected count/divergence and tombstone-ceiling policy before promotion.

## Phase 26C fresh adversarial review coverage

- unexpectedly empty generation promotion;
- malformed validation thresholds and retry policies;
- connector preflight failure and partial-generation prevention;
- missing or stale checkpoints before provider execution;
- duplicate job enqueue and competing worker leases;
- lease expiry/takeover and stale-token release;
- cross-owner canonical writes;
- retry exhaustion/dead-letter with active-alias preservation;
- checksum determinism under reversed ingestion order.

## Pending acceptance evidence

- WordPress 6.x/7.x activation, upgrade, reactivation and deactivation on isolated Hostinger-equivalent staging.
- Real MySQL/MariaDB `dbDelta`, transaction, lock-expiry, concurrent-worker and rollback tests.
- Approved real File 21 and File 10 provider adapters and jointly frozen owner contracts.
- WP-Cron and real-cron scheduling, missed-cron recovery and operator dead-letter replay.
- Persistent query reader, staging-data parity, leakage and deletion-propagation SLO evidence.
- Public-query API, File 20 search surface and File 25 result-card work remain outside this batch.
- Source/package parity, installable ZIP and Founder staging acceptance remain pending.
