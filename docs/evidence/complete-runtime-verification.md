# File 26 Complete Runtime Verification Record

## Candidate scope

Runtime `1.0.0`, schema `1.0.0`, stage `complete-runtime`.

This document defines the evidence required on the exact candidate head. The immutable head SHA, workflow run IDs, artifact ID/digest and final conclusions are recorded in the Draft PR conversation after all documentation is committed. Evidence from an earlier head cannot approve a later head.

## Required repository gates

### PHP 8.1 and PHP 8.3

Each runtime must pass:

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
| **Total per PHP version** | **320** |

Matrix total: 640 assertion executions.

Additional mandatory checks:

- Composer metadata validates with `--strict`;
- every PHP file passes syntax lint;
- connector example, JSON schema and golden-query fixtures parse successfully;
- transfer payload files are absent;
- unresolved source markers are absent;
- duplicate prototype runtime files are absent.

## Required WordPress/MariaDB gate

Environment:

```text
WordPress 7.0.2
MariaDB 11.4
PHP 8.3
WP-CLI 2.12.0
```

The complete smoke executes 83 assertions covering:

- plugin/runtime/schema identity;
- all nineteen derivative tables and required columns;
- persistent generation creation, checkpoint, validation, checksum and promotion;
- Urdu active-generation query;
- persistent job claim, dead letter and guarded replay;
- taxonomy persistent round-trip;
- graph persistent round-trip and provenance;
- recommendation feedback record/query/reversal;
- ordered change-event append, idempotency, claim and acknowledgement;
- purge request, completion and verified absence;
- public/admin/operations route registration;
- public Urdu query execution and click-time revalidation marker;
- topic execution and no generated medical claims;
- truthful health state with staging/live/operational false.

## Required deterministic-package gate

The exact head must produce twice-identical:

- `sabri-search-discovery-1.0.0.zip`;
- `sabri-search-discovery-1.0.0-source.zip`;
- `SBOM.spdx.json`;
- `CHECKSUMS.sha256`.

The installable ZIP must pass integrity testing and contain the canonical top-level plugin folder and main plugin file.

The PR evidence must record:

- exact source head SHA;
- CI workflow run ID;
- WordPress/MariaDB workflow run ID;
- artifact ID and name;
- artifact SHA-256 digest;
- assertion totals and conclusions;
- explicit non-claims.

## Review round 1 defect record

The first review/fix/retest cycle corrected:

- outdated schema test assumptions;
- destination accessor mismatch;
- missing explicit public visibility factory;
- temporary-contract test drift;
- source-format-dependent traceability checks;
- incomplete permanent CI test inclusion;
- temporary transfer payload/workflow residue;
- duplicate prototype implementations.

## Fresh adversarial review defect record

The fresh review/fix/retest cycle corrected:

- numeric-string key coercion in queries, synonyms, transliteration, APIs and recommendations;
- inconsistent evaluation metric type;
- forged graph source-owner provenance;
- insufficient persistent provenance revalidation;
- unsafe/ambiguous public list, cursor and boolean parsing;
- missing explicit topic click-time owner revalidation metadata;
- CI unresolved-marker self-matching.

## Truthful conclusion law

When every gate above passes on the same exact head, the approved repository label is:

```text
File 26 v1.0.0 — Coded, Packaged and Automated-QA Green Candidate
```

The following labels remain prohibited until separate evidence exists:

```text
Staging-Accepted
Live-Deployed
Operational
Production Complete
```
