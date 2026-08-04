# Phase 26E — Complete Runtime Architecture

## Governing decision

Phase 26E completes the File 26 repository coding scope without changing canonical ownership in any source module. File 26 remains a derivative search, discovery, recommendation, controlled-taxonomy, classification and owner-sourced graph plane.

## Runtime composition

```text
WordPress plugin bootstrap
  ├─ locked schema coordinator
  ├─ canonical connector registry
  │    └─ nine public-only owner adapter contracts
  ├─ persistent generation runtime
  │    ├─ shadow store
  │    ├─ jobs / retries / dead letters
  │    ├─ lease locks
  │    ├─ checkpoints
  │    └─ active-generation query repository
  ├─ complete application runtime
  │    ├─ query understanding
  │    ├─ ranking
  │    ├─ suggestions and facets
  │    ├─ recommendations and feedback
  │    ├─ taxonomy
  │    ├─ classification
  │    ├─ knowledge graph
  │    ├─ telemetry and evaluation
  │    ├─ policy and audit
  │    └─ export
  ├─ public/admin REST controllers
  └─ WP-Cron / real-cron / WP-CLI operations
```

All components receive the same WordPress database, registry, active-generation repository and derived signing secret through the canonical composition root. Hidden parallel stores or registries are prohibited.

## Data flow

### Full rebuild

```text
owner provider filter
  -> strict page validation
  -> public allowlist mapping
  -> document/tombstone batch
  -> candidate generation
  -> durable checkpoint
  -> expected connector completion
  -> count/divergence/checksum validation
  -> atomic active alias promotion
  -> retained predecessor
```

### Delta event

```text
owner fact event
  -> idempotency and owner sequence ledger
  -> version/chronology validation
  -> document or tombstone update
  -> suggestion/recommendation/graph purge as required
  -> reconciliation evidence
```

### Public query

```text
raw query
  -> Unicode normalization
  -> sensitive-query classification
  -> approved synonym/transliteration expansion
  -> signed active-generation snapshot
  -> visibility-aware candidates
  -> versioned ranking/diversity
  -> response with click-time owner recheck
  -> canonical destination owner validation
```

### Recommendation

```text
guest -> cold-start source-quality curation
member + consent -> bounded declared/follow/learning/save signals
minor -> verified guardian consent required
all candidates -> state/visibility + hidden item/creator/topic filters
result -> reasons + controls + click-time owner recheck
```

## Schema ownership

File 26 owns nineteen rebuildable/operational derivative tables. It does not own source bodies, private records or companion-module truth.

| Domain | Tables |
|---|---|
| Generations | generations, aliases, documents, tombstones, checkpoints |
| Work execution | jobs, locks |
| User controls | feedback |
| Knowledge governance | taxonomy_terms, classifications, graph_edges |
| Evaluation/governance | evaluation_sets, policies, telemetry_daily, audit_log, export_tokens |
| Delta/deletion | change_events, owner_sequences, purge_ledger |

## Authorization boundaries

- Guest access is limited to anonymous-public derivative records.
- Authentication is not authorization.
- Capability, entitlement, age, guardian consent, state and suspension are rechecked after hydration.
- Personalized recommendations require authenticated explicit consent.
- High-risk taxonomy/classification/policy/export operations require administrator/capability gates and independent evidence where defined.
- File 26 health, feature detection or connector existence never grants access.

## Failure modes

| Failure | Safe behavior |
|---|---|
| Optional owner adapter missing | Domain unavailable/hidden; no fabricated data |
| Candidate rebuild fails | Existing active generation remains readable |
| Checkpoint/cursor corrupt | Job fails closed and may dead-letter |
| Schema upgrade unsupported | Runtime remains degraded; public API not enabled |
| Signing secret unavailable | Runtime initialization fails closed |
| Stored payload hash mismatch | Result rejected; no partial unsafe hydration |
| Private/restricted owner state changes | Tombstone/purge plus click-time canonical denial |
| File 24 unavailable | Native File 26 enforcement continues; assurance status is unknown/degraded |
| File 20/File 25 unavailable | API remains bounded; no duplicate shell/cards created |

## Scale path

The WordPress/MariaDB implementation is the canonical initial backend. Interfaces preserve later adapter replacement for external search/index providers without changing canonical owner contracts. Any provider extraction requires:

- versioned adapter and exit plan;
- data-flow/privacy/threat review;
- deterministic rebuild and reconciliation;
- dual-read parity;
- deletion propagation proof;
- rollback to a validated generation/provider;
- updated SBOM and operational evidence.

## Completion boundary

Phase 26E closes repository coding, package and automated-test scope. Real owner provider implementations in companion repositories, Hostinger staging, File 20/File 25 presentation, accessibility/browser/load evidence, backup/restore, deployment and operations remain separate acceptance gates rather than hidden code claims.
