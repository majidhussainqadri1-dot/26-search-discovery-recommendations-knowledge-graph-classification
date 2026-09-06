# File 26 — Second Independent 20-Round Corrective Audit — 2026-09-06

Baseline SHA: `90f5e7e2502e6516d2d2009b89cac33ded2b4916`  
Review branch: `codex/file26-second-20-round-review-20260906`

Method: **Review → Ledger Freeze → Fix → Regression → Exact-head CI → Next Round**. No active-round defect is fixed before the full review is complete and frozen. Repository/CI evidence is separate from staging/live evidence.

## Round ledger

| Round | Result | Frozen finding / closure |
|---:|---|---|
| 1 | CLEAN | Packaging/release/CI integrity re-reviewed. Exact-head GREEN `ef41d8337fc84ee9fab35e27bb4df22153299337`. |
| 2 | DEFECT | Schema structural signatures hardened. GREEN `a176c9cbbcbde5fce23e63f471500f0b6ca087a3`. |
| 3 | DEFECT | Canonical identity/version normalization unified and made strict. GREEN `4247d116f879b668eee34acae7b4d0f63994e49e`. |
| 4 | DEFECT | Connector DB/runtime lifecycle and health truth hardened. GREEN `1a33b6c96e3c0c34ad7d8ebc4e263a5f68326ccb`. |
| 5 | DEFECT | Search DB failure can no longer masquerade as empty success. GREEN `7f2dc890def1cee881d33709cd785a6dec19ad5a`. |
| 6 | DEFECT | Feedback/undo idempotency made operation-bound and replay-safe. GREEN `3fa9e285877be2ea057b9738b91e6937da89f5ad`. |
| 7 | DEFECT | Blocked/restricted ranking exclusion and hard first-page concentration boundary enforced. GREEN `8186bdcba8e93ffcc49223c2ce65520381ec8e0c`. |
| 8 | DEFECT | Taxonomy create/alias transfer/merge read/high-impact-review integrity hardened. GREEN `ded988a25e24ee93a8b2657874fa271bd5ad5482`. |
| 9 | DEFECT | Graph read-truth hardened. GREEN `8f9024bfbf6dbeb48c93bb28b7455bd2de719f3e`. |
| 10 | DEFECT | REST/health operation truth hardened. GREEN `ff2b99e0f87e1c53b181d6d0ca53d9451613c62e`; ledger-head GREEN `5c76b6789e982545916626c381cfcee17cd899e2`. |
| 11 | DEFECT | Privacy/retention truth hardened. Exact-head GREEN `43a3b4fcc01b95bfc2a3224d3aaf86288f4eda73`; ledger-head GREEN `974cefc3d8534243aab8b76ffdbd921a387882d8`. |
| 12 | DEFECT | Queue/worker operation truth hardened: queue-head read failures, claim/completion/failure-transition write failures and CAS loss now fail closed; stale recovery/backoff retained. Exact-head GREEN `fb0be05360bc6e0c6fb6ef28903280fd4ee6ef2b`; ledger-head GREEN `2feeafb35473e780069cf1061019a8cd9f149c1b`. |
| 13 | DEFECT | Deletion/replay integrity hardened: precedence reads now fail closed on DB errors; reconciliation protects higher-version live resurrection from stale tombstone derivative purges. Dedicated Round 13 regression passed. Reformatting exposed three stale formatting-bound historical test assertions; those harness assertions were made whitespace-insensitive without weakening their semantics. Exact-head corrective CI GREEN `4d5e617bd45b50f6921fc2ab980d9bf32e9d0b6e`; ledger-head GREEN `cf3e2936f28b44b3d802305b3bf76060c25e14a7`. |
| 14 | DEFECT | Autocomplete/accessibility integrity hardened: IME composition now blocks premature keyboard/input suggestion actions; active-descendant listbox options no longer contain nested focusable anchors and render labels through `textContent` with same-origin navigation; suggestion DB read failures are audited and propagated through REST as explicit errors. Dedicated Round 14 regression passed. Exact-head corrective CI GREEN `709db739acb6db84c806775713c3562688ed013e`; ledger-head GREEN `0b4d7873efb7cfd6a9d94759a284c15c5b8dd352`. |
| 15 | DEFECT — FROZEN | Route/filter truth: (1) native HTML search/discover/topic handling can set HTTP 200 before backend error or missing-topic state is known, so transport status can contradict rendered failure/unavailable state; (2) `country` and `location` filters are passed through `sanitize_key` in `Search::sanitize_filters()`, stripping spaces/Unicode even though retrieval compares exact stored text. Correction/regression/exact-head CI pending. |

## First-ten checkpoint

- Defect rounds: **2, 3, 4, 5, 6, 7, 8, 9, 10**
- Clean rounds: **1**

## Status

- Completed rounds: **14/20**
- Round 15 review: **FROZEN — correction pending**
- Round 16 must not begin until Round 15 correction, regression and exact-head CI are GREEN.
