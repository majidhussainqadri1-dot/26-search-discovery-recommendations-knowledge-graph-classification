# File 26 — Independent 20-Round Corrective Audit — 2026-09-06

## Governing method

Baseline `main` SHA: `89fd57bb9408dddf5f6390982ba4c55d87752286`.
Review branch: `codex/file26-20-round-review-20260906`.

Each round follows the strict sequence **Review → Ledger Freeze → Fix → Regression → Exact-head CI → Next Round**. No defect discovered during an active review is corrected until that round's review has completed and its ledger has been frozen.

This ledger is repository evidence only. Staging, live deployment, deployed database/schema, migration completion, real connectors and operational behavior require separate evidence.

## Round ledger

| Round | Result | Frozen findings | Corrective closure |
|---:|---|---|---|
| 1 | DEFECT | Committed package manifest/release evidence was stale and QA masked manifest drift. | Package manifest is generated non-mutating from exact source; stale committed manifest/release removed; source/package parity enforced. CI GREEN `9713c14f5ce46ef2b92bc8317cd82be19641490b`. |
| 2 | DEFECT | Runtime schema gate trusted version options without required physical-table verification. | Required File 26 and appeals tables are physically verified before runtime; missing schema forces serialized repair/fail-closed. CI GREEN `4c466f290a4641b107eb9be7ddbaf280bd829b38`. |
| 3 | DEFECT | Upsert resurrection and tombstone derivative purge could partially commit on DB failures. | Document/tombstone/node resurrection and revocation derivatives are transactional with checked operations/commit. CI GREEN `b8635b128188ab7dd8bb81967a49f6b079051ae4`. |
| 4 | DEFECT | Connector DB persistence failure could still register in memory; unsupported deletion semantics and incomplete active callbacks could survive. | Persistence fails closed; only versioned tombstones accepted; index/active lanes require live callbacks; missing active visibility fails closed. CI GREEN `ced324077bdaa9818b5dba059a4c58a2a3237c92`. |
| 5 | DEFECT | Anonymous 300-second shared search cache could survive visibility or connector-state revocation. | Governed File 26 mutation events invalidate shared search cache. Initial CI attempt caught a test-string interpolation bug; corrected exact-head CI GREEN `ad242677ad6c2c68472517063bb20ec36955147b`. |
| 6 | DEFECT | Feedback insertion/rebuild could report success after DB failure; consent revocation was not atomic with feedback purge; reset did not verify transaction start/commit. | Feedback/undo + negative projection are atomic, DB results checked; consent revoke atomically clears profile signals and feedback; reset verifies start/deletes/commit. Corrective harness was strengthened to verify semantics rather than a deleted comment. Exact-head CI GREEN `0361f4be58b8c7788a33133dd32425aa454dfd0c`. |
| 7 | CLEAN | Organic ranking, policy loading, bounded weights/limits, deterministic tie-break, safety exclusion, diversity and prohibited donation/payment/Founder-favoritism signals were reviewed. The optional `audience` policy parameter has no governing requirement for distinct audience-specific organic ranking in the current contracts, so its default-public use is not classified as a defect. | No production change required. Existing regressions remain the closure evidence. Exact-head CI GREEN `592ae636f7dd8ed518851893621d05f394fb95c6`. |
| 8 | DEFECT | Doctor-ranking recompute did not verify transaction start/commit and could not reliably distinguish ranking-metadata option persistence failure, permitting a false-success claim for an allegedly atomic recompute. | Recompute now verifies START TRANSACTION, ranking projection writes, ranking metadata persistence and COMMIT; failures roll back/fail closed. A dedicated regression protects the atomicity boundary. Exact-head CI GREEN `b6cbc5048e9f6427e04dc4fb2518873da3233180`; ledger-close exact head `cee21fce26b516687331ee07a01529801d401d57` also GREEN. |
| 9 | DEFECT | REST `/admin/reconcile` discarded the `Indexer::reconcile()` result and always returned `reconciled=true`, while the admin control path correctly surfaced `WP_Error`; a backend reconciliation failure could therefore be reported as API success. | Pending correction after this frozen ledger. |

## Round status

- Completed rounds: **8/20**
- Round 9 review: **FROZEN — correction pending**
- Defect rounds so far: **1, 2, 3, 4, 5, 6, 8, 9**
- Clean rounds so far: **7**

Round 10 must not begin until Round 9 correction, regression and exact-head CI are green.
