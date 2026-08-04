# File 26 Threat Model — Runtime 1.0.0

## Security objective

File 26 must improve discovery without becoming a source of truth, an authorization authority, a private-data index or a single security point of failure. A stale, unavailable or compromised derivative must not grant access that the canonical owner would deny.

## Protected assets

- Canonical owner identifiers, object versions and event chronology.
- Public/private state, entitlement, age, guardian, suspension and consent assertions.
- Searchable derivative documents, tombstones and active-generation aliases.
- Controlled taxonomy terms, classification decisions and provenance-rich graph edges.
- Ranking/recommendation policies, evaluation sets and audit evidence.
- Query/feedback telemetry, which must remain minimized and purpose-bound.
- Cursor, export and operational signing material.
- Queue, checkpoint, purge and reconciliation evidence.

## Trust boundaries

1. Canonical owner module → owner provider filter.
2. Provider page → strict connector validation.
3. Connector batch → candidate generation and change-event pipeline.
4. Candidate generation → validation and active alias promotion.
5. Persistent row → hash verification and strict hydration.
6. Query/recommendation input → normalization, sensitivity and audience policy.
7. File 26 result → File 20/File 25 presentation.
8. Presented destination → canonical owner click/action authorization.
9. Operator/curator/reviewer → admin REST and WP-CLI control surfaces.
10. WordPress database/cron → jobs, locks, upgrades, retention and export.
11. Future external search/provider adapter → File 26 contract and provider-exit boundary.

## Threats and mandatory controls

| Threat | Mandatory controls | Evidence |
|---|---|---|
| Private/deleted/restricted data appears | public-only allowlist; visibility envelope; query eligibility; tombstones; purge ledger; click-time owner recheck | connector, eligibility, deletion and WP route tests |
| Connector impersonates another owner | fixed connector key; owner prefix; manifest owner file; cross-owner rejection | adversarial connector tests |
| Lookalike/external destination phishing | credential-free HTTPS; exact canonical host/subdomain; no nonstandard port | host-policy tests |
| Stale event resurrects data | owner sequence, object version, timestamp, tombstone priority, stale cursor suppression | Phase 26B/C/E tests |
| Replayed/duplicate event | idempotency key, unique owner sequence, row locks, acknowledgement | MariaDB event tests |
| Partial rebuild replaces active index | isolated candidate generation; complete checkpoints; count/divergence/checksum gates | persistence and DB promotion tests |
| Empty/divergent malicious generation | explicit minimum, expected count, divergence and tombstone ceilings | generation validation tests |
| Cursor tampering or cross-query reuse | HMAC signature, query fingerprint, generation snapshot and offset bounds | Phase 26D tests |
| Stored-row corruption | SHA-256 payload verification, exact JSON shape, exact ISO timestamp hydration | hydration tests |
| BOLA/IDOR through result | result is reference only; canonical owner authorizes destination/action | response marker and integration law |
| Unauthorized personalized recommendations | authentication, explicit consent, canonical minor/guardian context, hidden controls | review/adversarial tests |
| Sensitive signals influence ranking | no private messages, clinical records or payment inputs; field allowlists | API declaration and source review |
| Query logs expose PII/clinical data | sensitivity classifier, no raw sensitive telemetry, hashed bounded dimensions, retention purge | complete/review tests |
| Numeric-string identity mutation | string-preserving prefixed maps in query/API/recommendation normalization | regression tests |
| Taxonomy poisoning | stable terms, version increments, collision/cycle checks, merge preview and curator authority | taxonomy tests |
| Self-approved high-impact classification | proposer/reviewer separation and appeal | classification tests |
| Forged graph provenance | source owner must own source endpoint or be approved curator; validated on create/put/hydrate | adversarial + DB graph tests |
| Ranking manipulation | versioned policy, bounded weights, diversity caps, evaluation, approvals and rollback | ranking/policy/evaluation tests |
| Export replay/cross-user exposure | signed scoped token, expiry, persistent single-use boundary, package exclusions | export review/adversarial tests |
| Operational endpoint leaks secrets | capability gate, bounded allowlisted health, generic unexpected errors | source review and route smoke |
| Queue retry storm | bounded jobs/pages, lease locks, backoff, retry ceiling and dead letter | Phase 26C/D tests |
| Dead-letter bypass | exact job/error confirmation, building generation, incomplete checkpoint and replay ceiling | unit + DB replay test |
| Schema option forged | actual 19-table/column verification, locked supported upgrades, fail-closed boot | DB smoke and schema tests |
| Cron cleanup loop | finite unschedule attempts and stop-on-failure | adversarial test |
| Deactivation/uninstall destroys evidence | non-destructive lifecycle; guarded separate purge | source review |
| CI/package supply-chain drift | exact-head tests, deterministic double build, manifest, checksums and SPDX SBOM | package workflow |
| Duplicate implementation ambiguity | temporary payload/prototype removal and one canonical autoloader map | repository review/CI |

## Abuse cases

### Search leakage probing

An attacker varies terms, locales, facets, pagination and object IDs to infer private records. Controls: public-only candidate set, normalized eligibility before counts/suggestions, generic not-found behavior, rate limits and canonical owner denial.

### Recommendation manipulation

An attacker submits fabricated consent, minor status, creator/topic IDs or hidden controls. Controls: consent is explicit; authentication and age/guardian come from canonical audience context; request-supplied minor state is ignored; lists are bounded strings.

### Connector cursor loop

A compromised owner repeatedly returns the same cursor or oversized pages to exhaust workers. Controls: distinct continuation cursor, page ceiling, maximum probe pages, bounded worker invocation and dead-letter handling.

### Graph relation laundering

A source attempts to assert a relation on behalf of another owner. Controls: source endpoint prefix must match `source_owner`, except explicit `file26-curated` governance; persistent hydration revalidates it.

### Policy capture

A single operator deploys a high-risk ranking or safety change. Controls: versioned predecessor, two independent approvals, audit, evaluation and rollback.

### Deletion evasion

A document disappears from the owner but remains in suggestions, recommendations, graph or cache. Controls: owner tombstone/change event, purge ledger, active-index reconciliation, click-time denial and overdue-purge alert.

## Secrets

File 26 derives purpose-separated HMAC material from a sufficiently strong WordPress secret source or an explicit `SABRI_FILE26_SECRET`. Secrets must not be stored in the repository, ordinary options, logs, REST responses or package manifests.

Secret rotation requires:

- change-control approval;
- staged cursor/export-token invalidation plan;
- rollback window;
- audit and post-rotation smoke tests.

## Privacy classes

General public connectors accept `C1` projections only. Private or restricted data classes require a separate approved contract and must not enter the general search/recommendation path.

Prohibited general-index content includes:

- private messages or call content;
- patient charts, prescriptions and clinical attachments;
- national identity/passport evidence;
- authentication, OTP, provider or payment secrets;
- unpublished drafts and review-only material;
- restricted attachments;
- raw support/security incident evidence.

## Failure and incident response

- private leakage: disable affected public surface, preserve evidence, purge/reconcile, notify File 24 assurance and retest;
- signing secret compromise: rotate, invalidate tokens/cursors, inspect audit and rebuild where necessary;
- active-generation corruption: roll back to validated predecessor or disable File 26 public query;
- connector compromise: remove/disable connector, retain other domains, rebuild candidate and compare parity;
- graph/taxonomy poisoning: suspend affected projection/policy, restore reviewed predecessor and bounded reindex.

## Residual and external risks

Repository evidence cannot prove:

- Hostinger/network/WAF/DNS resilience;
- third-party owner-provider security;
- real-user privacy consent quality;
- accessibility/visual behavior in File 20/File 25;
- operational staffing and incident response;
- production-scale latency or deletion SLOs;
- legal/compliance applicability.

Those remain staging, organizational and File 24 assurance gates.
