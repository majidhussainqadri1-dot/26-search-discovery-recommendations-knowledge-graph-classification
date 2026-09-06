# File 26 — Independent 20-Round Corrective Audit — 2026-09-06

## Governing method

Baseline `main` SHA: `89fd57bb9408dddf5f6390982ba4c55d87752286`.
Review branch: `codex/file26-20-round-review-20260906`.

Each round follows the strict sequence **Review → Ledger Freeze → Fix → Regression → Exact-head CI → Next Round**. No defect discovered during an active review is corrected until that round's review has completed and its ledger has been frozen.

This ledger is repository evidence only. Staging, live deployment, deployed database/schema, migration completion, real connectors and operational behavior require separate evidence.

## Round ledger

| Round | Result | Frozen findings | Corrective closure |
|---:|---|---|---|
| 1 | DEFECT | Committed package manifest/release evidence was stale and QA masked manifest drift. | Package manifest/source parity hardened. CI GREEN `9713c14f5ce46ef2b92bc8317cd82be19641490b`. |
| 2 | DEFECT | Runtime schema gate trusted version options without physical-table verification. | Physical schema verification/repair enforced. CI GREEN `4c466f290a4641b107eb9be7ddbaf280bd829b38`. |
| 3 | DEFECT | Upsert resurrection/tombstone purge could partially commit. | Transactional checked lifecycle. CI GREEN `b8635b128188ab7dd8bb81967a49f6b079051ae4`. |
| 4 | DEFECT | Connector persistence/deletion semantics/active callbacks could fail open. | Connector lifecycle hardened. CI GREEN `ced324077bdaa9818b5dba059a4c58a2a3237c92`. |
| 5 | DEFECT | Shared anonymous search cache could survive governed revocation. | Mutation-driven invalidation. CI GREEN `ad242677ad6c2c68472517063bb20ec36955147b`. |
| 6 | DEFECT | Recommendation feedback/consent/reset persistence had partial-success paths. | Atomic checked preference lifecycle. CI GREEN `0361f4be58b8c7788a33133dd32425aa454dfd0c`. |
| 7 | CLEAN | Organic ranking/policy/bounds/safety/diversity/prohibited signals consistent. | No production change. CI GREEN `592ae636f7dd8ed518851893621d05f394fb95c6`. |
| 8 | DEFECT | Doctor-ranking recompute transaction/metadata persistence unchecked. | Checked atomic recompute. CI GREEN `b6cbc5048e9f6427e04dc4fb2518873da3233180`; ledger-close `cee21fce26b516687331ee07a01529801d401d57` GREEN. |
| 9 | DEFECT | REST reconcile could report success after backend failure. | REST surfaces `WP_Error`. CI GREEN `6b2416ea7ec19b33b8bc5290d218bdd65cae0ecd`; ledger-close `d27042a8d35bb26408f816f6eef87ce2a7e45d50` GREEN. |
| 10 | DEFECT | Role-model version fast-path masked physical capability drift. | Physical role integrity verification. CI GREEN `3af3a27adec0279aba7f18eed6c1db420ac0e871`; checkpoint `a5ce1bc56fa9fbc0286ddef6cce57eb601eba159` GREEN. |
| 11 | DEFECT | Privacy erasure/appeal retention could falsely succeed across failed DB boundaries. | Checked transactional lifecycles. CI GREEN `6e844687315afbd98f1cc42db2877e0e99b71568`; ledger-close `dfbb05553ed3dd541d4ab7fe6ca9e6d6b39613f5` GREEN. |
| 12 | DEFECT | Ranking activation/rollback and taxonomy merge/split had unchecked transaction boundaries. | High-risk transitions verify START/COMMIT and demotion failure. CI GREEN `dc0db18b5485b89c84df84796bb657f413f3666b`. |
| 13 | CLEAN | Doctor-ranking appeals ownership, serialization, bounds, conflict/CAS, final-state and membership checks were consistent; recompute result is separately audited and no Must-contract equates appeal correction with synchronous recompute completion. | No production change. Exact-head CI GREEN `73af6d5c54c4477c8a2dba51a8e9c2e99008e4ee`. |
| 14 | DEFECT | Unversioned `docs/QA-REPORT.md` presented stale 2026-08-13 review branch, baseline SHA and prior 20-round ledger as current corrective evidence, creating documentation/evidence provenance drift despite current runtime contracts being present. | Pending correction after this frozen ledger. |

## First-ten-round checkpoint

- Defect rounds in Rounds 1–10: **1, 2, 3, 4, 5, 6, 8, 9, 10**
- Clean rounds in Rounds 1–10: **7**

## Round status

- Completed rounds: **13/20**
- Round 14 review: **FROZEN — correction pending**

Round 15 must not begin until Round 14 correction, regression and exact-head CI are green.
