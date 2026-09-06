# File 26 — Independent 20-Round Corrective Audit — 2026-09-06

## Governing method

Baseline `main` SHA: `89fd57bb9408dddf5f6390982ba4c55d87752286`.
Review branch: `codex/file26-20-round-review-20260906`.

Each round follows **Review → Ledger Freeze → Fix → Regression → Exact-head CI → Next Round**. No active-round defect is corrected before that round's review is complete and its ledger is frozen.

This ledger is repository evidence only. Staging, live deployment, deployed database/schema, migration completion, real connectors and operational behavior require separate evidence.

## Round ledger

| Round | Result | Frozen findings | Corrective closure |
|---:|---|---|---|
| 1 | DEFECT | Stale committed package manifest/release evidence and QA masking manifest drift. | Package/source parity hardened. CI GREEN `9713c14f5ce46ef2b92bc8317cd82be19641490b`. |
| 2 | DEFECT | Schema gate trusted version options without physical-table verification. | Physical table verification/repair. CI GREEN `4c466f290a4641b107eb9be7ddbaf280bd829b38`. |
| 3 | DEFECT | Upsert resurrection/tombstone purge partial-commit risk. | Checked transactions. CI GREEN `b8635b128188ab7dd8bb81967a49f6b079051ae4`. |
| 4 | DEFECT | Connector persistence/deletion/active-callback fail-open paths. | Connector lifecycle hardened. CI GREEN `ced324077bdaa9818b5dba059a4c58a2a3237c92`. |
| 5 | DEFECT | Shared search cache could survive governed revocation. | Mutation invalidation. CI GREEN `ad242677ad6c2c68472517063bb20ec36955147b`. |
| 6 | DEFECT | Recommendation feedback/consent/reset partial-success paths. | Atomic checked lifecycle. CI GREEN `0361f4be58b8c7788a33133dd32425aa454dfd0c`. |
| 7 | CLEAN | Ranking/bounds/safety/diversity/prohibited signals consistent. | No production change. CI GREEN `592ae636f7dd8ed518851893621d05f394fb95c6`. |
| 8 | DEFECT | Doctor-ranking recompute transaction/metadata persistence unchecked. | Atomic recompute. CI GREEN `b6cbc5048e9f6427e04dc4fb2518873da3233180`; ledger-close `cee21fce26b516687331ee07a01529801d401d57` GREEN. |
| 9 | DEFECT | REST reconcile could falsely report backend success. | REST surfaces `WP_Error`. CI GREEN `6b2416ea7ec19b33b8bc5290d218bdd65cae0ecd`; ledger-close `d27042a8d35bb26408f816f6eef87ce2a7e45d50` GREEN. |
| 10 | DEFECT | Role-version fast-path masked physical capability drift. | Physical role integrity verification. CI GREEN `3af3a27adec0279aba7f18eed6c1db420ac0e871`; checkpoint `a5ce1bc56fa9fbc0286ddef6cce57eb601eba159` GREEN. |
| 11 | DEFECT | Privacy erasure/appeal retention false-success DB boundaries. | Checked transactions. CI GREEN `6e844687315afbd98f1cc42db2877e0e99b71568`; ledger-close `dfbb05553ed3dd541d4ab7fe6ca9e6d6b39613f5` GREEN. |
| 12 | DEFECT | Ranking activation/rollback and taxonomy merge/split unchecked transaction boundaries. | High-risk transitions hardened. CI GREEN `dc0db18b5485b89c84df84796bb657f413f3666b`. |
| 13 | CLEAN | Doctor-ranking appeal ownership, serialization, bounds, CAS/final-state/membership controls consistent. | No production change. CI GREEN `73af6d5c54c4477c8a2dba51a8e9c2e99008e4ee`. |
| 14 | DEFECT | Current QA report presented stale 2026-08-13 provenance as current. | QA evidence aligned to current cycle. CI GREEN `dc4bb37b0a3d307b6e26beef163674d61c033a16`. |
| 15 | DEFECT | Migration checked table existence but not physical columns/indexes. | Structural schema verification/repair/fail-closed Health. Final CI GREEN `e1265a13e738f46e9623e642ad1017f53fdf2eed`. |
| 16 | DEFECT | Authenticated topic HTML could include non-public visibility classes while shared-public cacheable; unknown injected route values were intercepted after a 200 claim. | Canonical route validation and anonymous-only public topic caching. CI GREEN `7ac7c8330e78cd8038c13117bc7d211eb78fd2b6`. |
| 17 | DEFECT | `Indexer::reconcile()` did not verify `START TRANSACTION`; admin settings could redirect `updated=1` without read-back proof that requested state persisted. | Reconciliation now refuses to execute destructive queries when transaction start fails and still verifies commit/rollback. Admin settings now build an explicit requested state, persist it, read it back, compare every key/value, and fail with HTTP 500 instead of success when persistence cannot be verified. `tests/review-round-17-operation-atomicity.php` protects both boundaries. Exact-head CI pending on this closure head. |

## First-ten-round checkpoint

- Defect rounds 1–10: **1, 2, 3, 4, 5, 6, 8, 9, 10**
- Clean rounds 1–10: **7**

## Round status

- Completed reviews/corrections: **17/20**
- Round 17 exact-head CI: **PENDING on this closure head**

Round 18 must not begin until this exact head is green.
