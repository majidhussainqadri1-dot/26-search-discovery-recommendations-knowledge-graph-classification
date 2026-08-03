# File 26 Threat Model — Phase 26A Baseline

## Protected assets

- Canonical owner identifiers and source versions.
- Visibility, entitlement, age, guardian, suspension, and consent assertions.
- Searchable derivative documents, tombstones, taxonomy assignments, graph edges, policies, and evaluation evidence.
- Query and recommendation telemetry, which must remain minimized and purpose-bound.

## Trust boundaries

1. Companion domain owner to connector.
2. Connector to File 26 validation and indexing pipeline.
3. File 26 query service to File 20/File 25 presentation.
4. Recommendation inputs to audience eligibility and consent controls.
5. File 26 result to canonical owner click-time authorization.
6. Operators, curators, auditors, and policy administrators to restricted control surfaces.

## Initial threats and mandatory controls

| Threat | Control baseline |
|---|---|
| Private or deleted data appears in search | Index-time projection, query-time eligibility, click-time owner recheck, tombstone and purge reconciliation |
| Connector impersonates another owner | Stable connector key, owner-file declaration, signed/approved contract registry, duplicate rejection |
| Stale event resurrects restricted data | Source version ordering, restriction priority, idempotency, reconciliation |
| BOLA/IDOR through search result | Result is a reference only; canonical owner authorizes every destination request |
| Query logs expose PII or clinical data | Sensitive sampling off by default, redaction, short retention, restricted access |
| Ranking policy manipulation | Versioned policy, approvals, audit evidence, bounded experiments, rollback |
| Unsafe medical synonym or recommendation | Domain-approved taxonomy, safety classifications, refusal/escalation policy, human review |
| Operational endpoint leaks secrets | Administrator-only access and allowlisted public-safe health fields |
| Deactivation deletes data unexpectedly | Non-destructive deactivation/uninstall; guarded purge workflow only |

## Explicit exclusions

The foundation does not claim encryption completeness, penetration-test completion, production privacy compliance, staging acceptance, or live operational resilience. Those remain later evidence gates.
