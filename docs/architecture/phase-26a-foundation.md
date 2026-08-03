# Phase 26A — Foundation Architecture

## Decision

Phase 26A creates the repository, contract registry, canonical derivative document model, safety boundaries, traceability baseline, and automated contract tests. It intentionally does not create a search index, ranking engine, public search UI, taxonomy editor, recommendation runtime, or knowledge-graph traversal service.

## Canonical ownership boundary

```text
Canonical domain owner
  -> versioned read/index connector
  -> File 26 validation and visibility projection
  -> derivative index / taxonomy / graph / recommendation projection
  -> query-time eligibility
  -> result reference
  -> click-time canonical owner revalidation
```

File 26 may derive, cache, rank, classify, connect, and evaluate eligible references. It may not directly mutate the source domain record or treat a provider/index identifier as canonical identity.

## Connector activation gate

A connector remains inactive until all of the following are approved:

1. canonical owner and entity types;
2. semantic contract version;
3. explicit privacy classes and field allowlist;
4. bounded cursor or change-feed strategy;
5. rebuild and backfill method;
6. restriction, tombstone, purge, and deletion-reconciliation semantics;
7. public-safe health contract;
8. shadow parity, leakage, rollback, and owner-consumer contract tests.

Unknown contract versions fail closed.

## Data-flow baseline

1. The owner emits an approved past-tense event or exposes a versioned read contract.
2. The connector validates source state, version, visibility, and allowlisted fields.
3. File 26 creates a derivative `SearchDocument` containing canonical owner identity and source version.
4. Restricted, suspended, retracted, corrected, and deleted states receive explicit policy treatment.
5. Query-time eligibility is evaluated against current audience assertions.
6. The canonical owner rechecks visibility when the user opens the destination.
7. Reconciliation continuously detects stale, missing, duplicated, or leaked projections.

## Fail-closed rules

- Duplicate connector keys are rejected.
- Invalid manifests are rejected during registration.
- Private or entitlement-gated records cannot be marked public.
- Operational health output is allowlisted and strips unknown fields.
- Deactivation and uninstall are non-destructive.
- Companion-domain tables and metadata are never directly modified.

## Phase exit evidence

Phase 26A exits only after owner contracts and safety/privacy policy are approved, CI is green, both review-and-fix rounds are recorded, and known critical/high defects are zero.
