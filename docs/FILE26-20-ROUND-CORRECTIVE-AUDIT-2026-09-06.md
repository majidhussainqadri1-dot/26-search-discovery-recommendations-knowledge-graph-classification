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
| 5 | DEFECT | Anonymous 300-second shared search cache could survive visibility or connector-state revocation. | Governed File 26 mutation events invalidate shared search cache. Corrected exact-head CI GREEN `ad242677ad6c2c68472517063bb20ec36955147b`. |
| 6 | DEFECT | Feedback insertion/rebuild could report success after DB failure; consent revocation was not atomic with feedback purge; reset did not verify transaction start/commit. | Feedback/undo + negative projection are atomic; consent revoke/reset are checked and transactional. Exact-head CI GREEN `0361f4be58b8c7788a33133dd32425aa454dfd0c`. |
| 7 | CLEAN | Organic ranking/policy, bounds, deterministic ties, safety/diversity and prohibited financial/favoritism signals were consistent with governing contracts. | No production change. Exact-head CI GREEN `592ae636f7dd8ed518851893621d05f394fb95c6`. |
| 8 | DEFECT | Doctor-ranking recompute did not verify transaction start/commit or ranking-metadata persistence. | Recompute verifies START, writes, metadata and COMMIT. CI GREEN `b6cbc5048e9f6427e04dc4fb2518873da3233180`; ledger-close `cee21fce26b516687331ee07a01529801d401d57` GREEN. |
| 9 | DEFECT | REST `/admin/reconcile` discarded backend error and always reported success. | REST now surfaces `WP_Error`; CI GREEN `6b2416ea7ec19b33b8bc5290d218bdd65cae0ecd`; ledger-close `d27042a8d35bb26408f816f6eef87ce2a7e45d50` GREEN. |
| 10 | DEFECT | Role-model version fast-path masked physical role/capability drift. | Physical role/cap integrity is verified before fast-path. CI GREEN `3af3a27adec0279aba7f18eed6c1db420ac0e871`; checkpoint `a5ce1bc56fa9fbc0286ddef6cce57eb601eba159` GREEN. |
| 11 | DEFECT | Privacy erasure and appeal retention could falsely succeed across failed transaction/DB boundaries. | Both lifecycles now verify START/DB operations/COMMIT and fail closed. CI GREEN `6e844687315afbd98f1cc42db2877e0e99b71568`; ledger-close `dfbb05553ed3dd541d4ab7fe6ca9e6d6b39613f5` GREEN. |
| 12 | DEFECT | High-risk ranking activation/rollback and taxonomy merge/split had unchecked transaction boundaries; activation also ignored previous-policy demotion DB failure. | Ranking activation/rollback and taxonomy merge/split now verify START/COMMIT; activation checks demotion failure; regression covers all four high-risk paths. Exact-head CI pending on this final closure head. |

## First-ten-round checkpoint

- Defect rounds in Rounds 1–10: **1, 2, 3, 4, 5, 6, 8, 9, 10**
- Clean rounds in Rounds 1–10: **7**

## Round status

- Completed reviews/corrections: **12/20**
- Round 12 exact-head CI: **PENDING on this closure head**

Round 13 must not begin until this exact head is green.
