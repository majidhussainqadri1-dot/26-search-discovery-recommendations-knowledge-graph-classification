# File 26 — Independent 20-Round Corrective Audit — 2026-08-13

## Governing method

Baseline: repository `main` exact SHA `e5f0dc21db57889b9df9f724edfd5652fc1675fb`.
Review branch: `review/file26-next-20-round-2026-08-13`.

Each round was completed as a review before that round's proven defects were corrected. Regression evidence was then added and the next round started from the corrected state. Historical earlier reviews are not counted in these 20 rounds.

This ledger is repository evidence only. Staging, live deployment, deployed database/schema, migration completion and operational behavior require separate evidence.

## Round ledger

| Round | Result | Proven defect / review focus | Corrective closure |
|---:|---|---|---|
| 1 | DEFECT | Concurrent upsert/tombstone requests could race deletion precedence and stale metadata. | Per-canonical-object advisory locking, protected stale/tombstone precedence, version-safe tombstone metadata, regression test. |
| 2 | DEFECT | Connector reload could reset durable event/health checkpoints; normalized manifests could become empty after initial validation. | Post-normalization validation and same-contract checkpoint/health preservation. |
| 3 | DEFECT | Sensitive guest queries could enter shared object cache; explicit date sorting lacked deterministic tie-break. | Sensitive-query cache exclusion and canonical-key deterministic tie-break. |
| 4 | CLEAN | Unicode normalization, transliteration, synonym and server-side autocomplete safety. | No new proven repository defect. |
| 5 | CLEAN | Organic relevance, policy-versioned ranking, prohibited financial/favoritism signals and diversity. | No new proven repository defect. |
| 6 | DEFECT | Persistent recommendation controls did not revalidate current membership/guardian state; consent revocation did not fully purge stored signals. | Fresh assertion gate, minor/guardian gate, consent-revoke purge, atomic reset and explicit opt-out failure. |
| 7 | DEFECT | Taxonomy merge/split lifecycle lacked complete preview, affected-owner approval, atomic migration/reindex/rollback evidence. | Merge/split previews, owner approval contract, CAS/locks, atomic alias/classification migration, reindex events and rollback mapping. |
| 8 | DEFECT | Graph edges could be created without a distinct governed activation transition and traversal could expose an endpoint revoked during response assembly. | Draft→approved lifecycle with step-up/owner/provenance checks, governed removal, final visibility filtering. |
| 9 | DEFECT | Sensitive/search-session REST responses could be publicly cacheable and merge/split governance surfaces were incomplete. | Sensitive/session-aware HTTP cache rules, merge/split preview/execution REST routes, active redirect validation. |
| 10 | DEFECT | Membership adapter could influence request-subject assertions; privileged WP capabilities were not uniformly bound to fresh File 00 validity; same-host scheme/port downgrade was insufficiently constrained; rate-limit DB failure was permissive. | Subject binding, fresh membership capability gates, stricter same-origin validation and fail-closed rate-limit failures. |
| 11 | DEFECT | Privacy exporter could truncate large feedback/appeal datasets; erasure could partially succeed while reporting completion. | Paged export across both datasets, transactional erasure, row-count verification and retryable failure. |
| 12 | DEFECT | Ranking dual approval could be simulated by supplying another user ID; classification decisions lacked explicit CAS/domain-review gate; graph transitions could bypass Graph-native approval checks. | Real second-approver action/receipt, one-time rollback approval, classification expected-version/domain-review checks, Graph-native transition delegation. |
| 13 | DEFECT | Doctor ranking recompute and open-appeal creation had concurrency/partial-write risk; own-appeal reads did not revalidate membership. | Cron-context/auth gate, ranking advisory lock+transaction, per-doctor appeal lock, fresh membership on own reads. |
| 14 | CLEAN | Data schema/table definitions, indexes, defaults and DB-install structure. | No new proven repository defect in this round. |
| 15 | DEFECT | Front-end merged-topic route could redirect/render through a stale non-active target. | Active-target recheck before canonical redirect; stale target fails closed. |
| 16 | DEFECT | Schema upgrades ran from admin-init only, allowing front-end runtime exposure before migration after deployment. | Serialized migration-before-runtime gate, required-table verification and fail-closed activation/search on migration failure. |
| 17 | DEFECT | Admin reindex/reconcile UI could redirect to success even after a proven operation error. | Capture and surface operation WP_Error before success redirect. |
| 18 | DEFECT | Crashed workers could remain `running`; enqueue failure was not explicit; reconciliation did not guarantee removal of all tombstone derivative remnants; health omitted important drift signals. | Stale-worker recovery, enqueue failure, atomic document/node/classification/edge reconciliation, schema/cron/stale-worker health evidence. |
| 19 | DEFECT | Autocomplete could render stale out-of-order responses and lacked full listbox keyboard/ARIA interaction. | Request sequencing+abort/current-value check and Arrow/Escape/Enter/ARIA listbox behavior. |
| 20 | DEFECT | Corrective QA itself contained interpolation-prone regression literals; CI used older official GitHub Action runtimes; release evidence/docs required fresh 20-round closure. | Regression tests made interpolation-safe; official checkout/setup-node/upload-artifact pinned to current immutable Node 24 releases; final ledger/QA evidence added. Exact-head CI and deterministic package are the closing evidence for this round. |

## Round result summary

Defect rounds: **1, 2, 3, 6, 7, 8, 9, 10, 11, 12, 13, 15, 16, 17, 18, 19, 20**.

Clean rounds: **4, 5, 14**.

Total: **20/20 rounds**; **17 defect rounds**, **3 clean rounds**.

## Release-truth boundary

- Specified: governed by the current central plan and File 26 plan.
- Coded: corrective branch candidate after the 20-round cycle.
- Packaged: must be proven by the exact final-head deterministic build.
- Automated-QA Green: must be proven by the exact final-head GitHub Actions run.
- Staging-Accepted: not established by this repository review.
- Live-Deployed: not established by this repository review.
- Operational: not established by this repository review.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
