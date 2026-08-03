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

## Review round 1 corrections

- Rejected scalar coercion of owner IDs, versions, locale, timestamps and tombstone fields.
- Required an unambiguous cursor state: continuing pages have a cursor; terminal pages do not.
- Added owner page-size enforcement and connector-specific public field allowlists.
- Added deletion chronology and stale-document resurrection protection.

## Fresh adversarial review coverage

- external and lookalike canonical-host destinations;
- unexpected patient/sensitive fields;
- anonymous restricted-result leakage;
- missing entitlement, age and guardian assertions;
- duplicate reconciliation identities;
- provider payload expansion and over-limit pages.

## Pending acceptance evidence

- WordPress 6.x/7.x activation and deactivation on isolated Hostinger-equivalent staging.
- Approved real File 21 and File 10 provider adapters and jointly frozen owner contracts.
- Persistent shadow storage design, background jobs, rebuild/checkpoint and rollback implementation.
- Staging-data parity, leakage, deletion-propagation and measured SLO evidence.
- Public-query API, File 20 surface and File 25 result-card work remain outside this batch.
- Founder approval of Phase 26B exit and the next implementation gate.
