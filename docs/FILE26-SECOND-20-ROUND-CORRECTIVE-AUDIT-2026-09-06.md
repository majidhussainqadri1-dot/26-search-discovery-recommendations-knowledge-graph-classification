# File 26 — Second Independent 20-Round Corrective Audit — 2026-09-06

## Governing method

Baseline SHA: `90f5e7e2502e6516d2d2009b89cac33ded2b4916`.
Review branch: `codex/file26-second-20-round-review-20260906`.

Each round follows **Review → Ledger Freeze → Fix → Regression → Exact-head CI → Next Round**. No active-round defect is corrected before that round's review is complete and its ledger is frozen.

This second cycle does not reuse the prior 20 rounds as completion evidence. It starts from the fully corrected prior-cycle exact head and independently re-reviews the repository.

Repository/source/package evidence is separate from staging/live deployment, deployed database/schema, real owner connectors, browser evidence and operational behavior.

## Round ledger

| Round | Result | Frozen findings | Corrective closure |
|---:|---|---|---|
| 1 | CLEAN | Deterministic package build, source/package manifest parity, path safety, immutable workflow action pins, PHP 7.4/8.3 matrix, artifact generation and release/source separation are internally consistent. No new proven defect. | No production change. Exact-head CI GREEN `ef41d8337fc84ee9fab35e27bb4df22153299337`. |
| 2 | DEFECT | `Schema_Integrity` verified only column names and index names; safety-critical type/nullability and index uniqueness/order drift could pass. | Added safety-critical column signatures and exact index signatures. Regression: `tests/review-round-02-schema-signatures-second-cycle.php`. Exact-head CI GREEN `a176c9cbbcbde5fce23e63f471500f0b6ca087a3`. |
| 3 | DEFECT | Canonical identity normalization differed between upsert and revocation; normalized identity and monotonic versions could be invalid/coerced. | Unified normalized identity/key semantics and strict version/sequence validation. Regression: `tests/review-round-03-identity-version-second-cycle.php`. Exact-head CI GREEN `4247d116f879b668eee34acae7b4d0f63994e49e`. |
| 4 | DEFECT | Connector registration could persist an index/production lifecycle status before proving current runtime callbacks; governance could promote persisted rows without a compatible runtime adapter and leave DB/in-memory state divergent; health callback state could be reported despite failed persistence. | Registration now resolves effective status, proves required callbacks before persistence, then registers. Governance receives the live registry, gates `shadow/approved/active` promotions on runtime capability and synchronizes successful status transitions. Health persistence failure is audited and returned as degraded. Regression: `tests/review-round-04-connector-runtime-truth-second-cycle.php`. Exact-head CI pending on this closure head. |

## Round status

- Completed reviews/corrections: **4/20**
- Defect rounds: **2, 3, 4**
- Clean rounds: **1**

Round 5 must not begin until this exact head is green.
