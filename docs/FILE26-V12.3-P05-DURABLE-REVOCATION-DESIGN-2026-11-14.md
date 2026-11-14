# File26 V12.3 — P05 Durable Revocation and Owner Cursor Acceptance Design
Date: 2026-11-14. Governing source: Sabri-Website-Master-Plan-V12.3, FR-002, FR-004, FR-005, FR-006, CEN-01, CEN-REL-001.

**Source-design/review only. NOT a DB migration, a resolved vulnerability, or an authorized Live deployment.**
**Canonical owners remain File 03/05/06/07/08/10/11/12/15/18/21.**

## Evidence-frozen root causes

### A. P0 — File26 Issue #37, tombstone expiry and delayed replay
- Exact source in candidate PR #36: \`Indexer::tombstone\` records canonical key, object version, received/purged/expiration, then deletes live document+node+graph edges. \`Indexer::retention\` later deletes expired tombstones with no downstream durable high-watermark check.
- \`Indexer::upsert\` compares event version only to an existing live document or tombstone. When **both no longer exist**, stale public owner event v1 can be mistaken for a brand-new v1 publication despite an earlier owner deletion/restriction v2.
- A bounded tombstone's disappearance therefore destroys information required for source version precedence unless a separate canonical freshness proof exists. This is a **source-reasoned potential resurrection**, not proof of any observed actual incident.

### B. P0/High — File21 Issue #60, owner offset cursor shifts after publication withdrawal
- Exact File21 PR #59 native \`SearchProviderRegistry::file26_list_batch\` used \`offset\` into currently published posts. Removing an earlier post before the next batch shifts remaining SQL rows, potentially skipping a post.
- File21 Draft PR #78 proposes a versioned \`f21-id-v1:<last_post_id>\` keyset cursor, explicit rejection of old numeric offset cursors and typed owner-query failure. Its isolated source QA is not Hostinger staging or full File26 integration.
- File26 Draft PR #39 includes a disposable actual WordPress/MariaDB test of that exact File21 proposed owner. A passing test is cross-owner source CI evidence only.

## Design invariants for Issue #37 closure (require owner approval BEFORE code/migration)

**I1 — Owner source of truth and highest observed versions.** A deletion/restriction event must establish a durable monotonic owner version and a separate, privacy-minimized File26 control plane high-watermark. A stale event below that watermark must be rejected even after the user-facing derivative tombstone is purged.

**I2 — Bounded data retention without indefinite identifiers.** Never solve resurrection by making raw object_id/title/URL, deleted content, email or personal identity fields permanent. The minimum replay fence must be pseudonymized, encrypted/secret-bound where appropriate, scoped to documented TTL/consent law, with automatic elimination and an owner-verified rehydration path. A hash alone does NOT equal guaranteed anonymization.

**I3 — No weak 'create after expiration' bypass.** When neither live document nor tombstone nor trusted floor exists, a replay or cold restore MUST NOT silently be treated as a fresh canonical publication. Either require a contemporaneous canonical owner \`fetch_object\` or equivalent typed version/eligibility attestation, or fail closed until complete owner backfill and governed reconciliation.

**I4 — Owner outbox + ACK.** Owner durable event outbox must advance before derivative indexing; File26 ingestion/replay/dead letter, owner ACK, version monotonicity, current rights and cache/CDN purge proof must all be connected. HTTP 200 on queueing is not a final consumer ACK.

**I5 — Atomicity and concurrency.** Serialize live index/tombstone/revocation floor changes with shadow cutover, running owner workers and object-level locks. Never perform destructive TTL pruning while required proof or a stale owner snapshot is under review.

**I6 — Versioned schema and rollback.** If a new control table is required, bump declared schema, implement idempotent WP dbDelta migration, exact column/type/index/row checks, privacy export/erasure, restoration under old snapshots and a reversible roll-forward strategy. No forced production install, no blind DB DDL.

**I7 — Special cases.** Public→private→public restoration with real higher owner version; physical deletion and content-rights revocation; owner DB outage; consent kill switch; legacy cursor migration; out-of-order owner events; post deletion followed by ID recreation (identity reuse forbidden); partial and full shadow scans; cold restore and 90/180-day expiry; content erased under privacy rights.

## Target SQL-backed negative matrix (mandatory)

| Scenario | Initial state | Transition | Expected |
|---|---|---|---|
| R1 | public v1 indexed | owner delete v2, purge original tombstone, stale event v1 replay | REJECT, no document/node/facet/suggestion/graph/REST visibility |
| R2 | delete v2 confirmed | owner reapproves v3 and reissues current attestation | ACCEPT only v3 and current public rights |
| R3 | public v4 and owner unavailable | after expired retention, ambiguous event v1 | DENY with typed owner-source-unknown, not success/empty |
| R4 | shadow prepared pre-deletion | owner revoke during rebuild and candidate includes v1 | FAIL cutover; no restored deleted content |
| R5 | source rows 10/20/30/40 | page 1 10/20, row 10 leaves public, page 2 | Native source returns 30/40 (keyset), no omitted ID |
| R6 | old File21 job cursor "2" | new keyset owner code loads | REJECT incompatible cursor, restart full generation |
| R7 | compliant owner backfill | fail/retry/partial cursor and transaction rollback | no false done=true; no partial promotion |
| R8 | real File12 PDF rights withdrawal | grants expire or licence revoked | index and derivative download entitlements removed |
| R9 | File10 consent/video kill switch | owner outbox read outage | typed fail closed with durable retry/ACK |
| R10 | metadata-only File21 review/visibility change | post_modified_gmt unchanged | owner-issued v+1, not stale/identical checksum |
| R11 | secure delete/account erasure | user requests erasure | no raw identifier retained in derivative ledger beyond approved policy |
| R12 | restore older backup | later owner revocations had occurred | no older content can silently return on any query/URL/cache |

## Current decision and release status

**Do NOT consider Issue #37 fixed.** Proposed mitigation requires explicit privacy/legal retention decision, real File21/other-owner contract acceptance, source-level checksum/monotonic proof and staging migration test. The new File21 keyset PR addresses R5/R6 only; it does not independently satisfy Issue #37 or full File26 FR-005.

- File26 main HEAD: 1b064c6ea9f81195611ed001d5457e8ced65460a.
- File26 current PR #39 source candidate: recheck latest exact SHA, stacked on PR #36.
- File21 candidate: Draft PR #78 stacked on File21 Draft #59.
- Deployed Version: UNVERIFIED.
- DB Version: UNVERIFIED.
- Migration State: UNVERIFIED.
- Live Verification Status: NOT VERIFIED.
- Live verified fixes: 0.

## Enforced process gate

Live symptom → exact deployed files/source/plugin/package SHA → physical DB/version/options/migration/logs → parity → root cause → isolated fix → source/real native owner QA → staging backup+restore/canary/rollback → Founder exact-artifact approval → Live retest → parity confirmed.

No current connected WordPress API can read exact Hostinger runtime; WPVibe not connected and Jetpack site_disconnected (previous owner-side evidence). This is an access blocker, **not** proof of DNS, host or WordPress failure.
