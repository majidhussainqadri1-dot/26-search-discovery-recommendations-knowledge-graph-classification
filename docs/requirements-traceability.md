# Phase 26A Requirements Traceability

| Requirement | Implementation | Evidence |
|---|---|---|
| F26-A-001 Repository and runtime identity | README, plugin bootstrap, constants | CI lint and repository review |
| F26-A-002 Fail-closed activation baseline | `Plugin::activate()` | PHP lint; WordPress staging test pending |
| F26-A-003 Versioned connector contract | `ConnectorInterface`, `ConnectorManifest`, JSON Schema | Foundation unit test |
| F26-A-004 Duplicate connector rejection | `ConnectorRegistry::register()` | Foundation unit test |
| F26-A-005 Public-safe connector health | `ConnectorRegistry::sanitizeHealth()` | Foundation unit test |
| F26-A-006 Canonical document identity/version | `SearchDocument` | Foundation unit test |
| F26-A-007 Visibility-envelope invariants | `VisibilityEnvelope` | Foundation unit test |
| F26-A-008 Cursor batch and tombstone invariants | `ConnectorBatch`, `IndexTombstone` | Foundation unit test |
| F26-A-009 Synthetic evaluation baseline | `tests/fixtures/golden-queries.json` | Foundation unit test |
| F26-A-010 Threat and data-flow baseline | Architecture and threat-model documents | Manual review |
| F26-A-011 Non-destructive lifecycle | deactivation and `uninstall.php` | Source review; staging test pending |
| F26-A-012 Automated checks | GitHub Actions CI | Workflow run pending |

## Pending Phase 26A acceptance evidence

- WordPress 6.x/7.x activation and deactivation on isolated staging.
- Companion connector contract approval from at least two low-risk canonical owners.
- Shadow-index parity and leakage proof of concept.
- First review/fix record and fresh adversarial review/fix record.
- Founder approval of the runtime identifiers and Phase 26A exit gate.
