# File 26 — Independent 20-Round Corrective Audit — 2026-09-06

## Governing method

Baseline `main` SHA: `89fd57bb9408dddf5f6390982ba4c55d87752286`.
Review branch: `codex/file26-20-round-review-20260906`.

Each round follows **Review → Ledger Freeze → Fix → Regression → Exact-head CI → Next Round**. No active-round defect is corrected before that round's review is complete and its ledger is frozen.

This ledger is repository evidence only. Staging/live deployment, deployed database/schema, migration completion, real connectors and operational behavior require separate evidence.

## Round ledger

| Round | Result | Frozen findings | Corrective closure |
|---:|---|---|---|
| 1 | DEFECT | Stale committed package/release evidence; QA masked manifest drift. | Source/package parity hardened. CI GREEN `9713c14f5ce46ef2b92bc8317cd82be19641490b`. |
| 2 | DEFECT | Version options could mask missing physical tables. | Physical verification/repair. CI GREEN `4c466f290a4641b107eb9be7ddbaf280bd829b38`. |
| 3 | DEFECT | Index resurrection/tombstone purge partial-commit paths. | Checked transactions. CI GREEN `b8635b128188ab7dd8bb81967a49f6b079051ae4`. |
| 4 | DEFECT | Connector persistence/deletion/active-callback fail-open paths. | Lifecycle hardened. CI GREEN `ced324077bdaa9818b5dba059a4c58a2a3237c92`. |
| 5 | DEFECT | Shared search cache could survive governed revocation. | Mutation invalidation. CI GREEN `ad242677ad6c2c68472517063bb20ec36955147b`. |
| 6 | DEFECT | Recommendation feedback/consent/reset partial-success paths. | Atomic checked lifecycle. CI GREEN `0361f4be58b8c7788a33133dd32425aa454dfd0c`. |
| 7 | CLEAN | Ranking/bounds/safety/diversity/prohibited signals consistent. | No change. CI GREEN `592ae636f7dd8ed518851893621d05f394fb95c6`. |
| 8 | DEFECT | Doctor-ranking recompute transaction/metadata persistence unchecked. | Atomic recompute. CI GREEN `b6cbc5048e9f6427e04dc4fb2518873da3233180`; ledger-close `cee21fce26b516687331ee07a01529801d401d57` GREEN. |
| 9 | DEFECT | REST reconcile could falsely report success. | `WP_Error` surfaced. CI GREEN `6b2416ea7ec19b33b8bc5290d218bdd65cae0ecd`; ledger-close `d27042a8d35bb26408f816f6eef87ce2a7e45d50` GREEN. |
| 10 | DEFECT | Role-version fast-path masked capability drift. | Physical role integrity verified. CI GREEN `3af3a27adec0279aba7f18eed6c1db420ac0e871`; checkpoint `a5ce1bc56fa9fbc0286ddef6cce57eb601eba159` GREEN. |
| 11 | DEFECT | Privacy erasure/appeal retention false-success DB boundaries. | Checked transactions. CI GREEN `6e844687315afbd98f1cc42db2877e0e99b71568`; ledger-close `dfbb05553ed3dd541d4ab7fe6ca9e6d6b39613f5` GREEN. |
| 12 | DEFECT | Ranking/taxonomy high-risk transaction boundaries unchecked. | Hardened transitions. CI GREEN `dc0db18b5485b89c84df84796bb657f413f3666b`. |
| 13 | CLEAN | Doctor-ranking appeal ownership/serialization/CAS/final-state/membership controls consistent. | No change. CI GREEN `73af6d5c54c4477c8a2dba51a8e9c2e99008e4ee`. |
| 14 | DEFECT | Current QA report used stale 2026-08-13 provenance. | Current evidence truth restored. CI GREEN `dc4bb37b0a3d307b6e26beef163674d61c033a16`. |
| 15 | DEFECT | Migration checked tables but not physical columns/indexes. | Structural verification/repair/Health added. Final CI GREEN `e1265a13e738f46e9623e642ad1017f53fdf2eed`. |
| 16 | DEFECT | Authenticated topic HTML could enter shared public cache; unknown route identities were intercepted. | Canonical route validation + anonymous-only public topic caching. CI GREEN `7ac7c8330e78cd8038c13117bc7d211eb78fd2b6`. |
| 17 | DEFECT | Reconcile transaction start unchecked; admin settings success lacked persistence read-back. | Reconcile verifies START/COMMIT; admin settings verify exact persisted state. First CI caught only an interpolation-prone regression literal; literal was corrected. Final exact-head CI GREEN `bd97dc260619dcc5af59c5fb2d90e095f69d6da9`. |
| 18 | DEFECT | Stale-worker recovery UPDATE failure was ignored and queue processing could continue with recovery state unknown. `Indexer::retention()` ignored DB failures for expired tombstone, feedback, rate-limit and audit deletion, allowing bounded-retention failure to remain silent. | Queue processing now stops with explicit audited `WP_Error` if stale-worker recovery cannot persist. Retention now checks every governed deletion class, audits/returns explicit failure, and reports counts only after all classes succeed. `tests/review-round-18-operation-durability.php` protects both durability boundaries. Exact-head CI GREEN `ed07b1983ac0f9d4cb611c7ec001991f7e0e5b11`. |
| 19 | DEFECT | Search/Discover/Topic shortcode templates used fixed DOM IDs, so multiple instances on one document could duplicate IDs and break `aria-labelledby`/label ownership. Front-end status announcements targeted the first document-wide live region instead of the initiating component. Autocomplete blur did not invalidate/abort an in-flight request, allowing a late response to reopen the list after focus left the combobox. | Rendered shortcodes now receive unique instance IDs; Search/Discover/Topic labels/headings derive IDs from the instance; search input declares combobox/listbox semantics; live-region updates are component-scoped; blur invalidates and aborts pending suggestions and late responses require active focus. `tests/review-round-19-autocomplete-accessibility.php` protects these boundaries. Exact-head CI pending on this closure head. |

## First-ten-round checkpoint

- Defect rounds 1–10: **1, 2, 3, 4, 5, 6, 8, 9, 10**
- Clean rounds 1–10: **7**

## Round status

- Completed reviews/corrections: **19/20**
- Round 19 exact-head CI: **PENDING on this closure head**

Round 20 must not begin until this exact head is green.
