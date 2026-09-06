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
| 4 | DEFECT | Connector registration/lifecycle/health persistence could diverge from current runtime truth. | Runtime-gated persistence/promotion, DB↔memory status sync and degraded health-on-persistence-failure added. Regression: `tests/review-round-04-connector-runtime-truth-second-cycle.php`. Corrective CI GREEN `c9b508a99bb74d4ced48015c7cfa4e6bb4918a16`; ledger-closing CI GREEN `1a33b6c96e3c0c34ad7d8ebc4e263a5f68326ccb`. |
| 5 | DEFECT | Primary federated search treated a failed candidate DB read like an empty batch, allowing database failure to become apparently successful empty/complete output. | Candidate reads now require an array result; non-array DB failure returns audited `file26_search_read_failed` with HTTP 503 and trace ID, while a valid empty array remains normal termination. Regression: `tests/review-round-05-search-read-truth-second-cycle.php`. Exact-head CI GREEN `7f2dc890def1cee881d33709cd785a6dec19ad5a`. |
| 6 | DEFECT | Feedback idempotency could report `recorded=true` when an existing idempotency key represented a different feedback operation; undo accepted an idempotency key without persisting an undo receipt, so identical undo replay became a conflict; identical ordinary replay could also rebuild profile projections and mutate profile version. | Feedback/undo now bind the idempotency key to a persisted operation fingerprint, reject mismatched reuse with `file26_idempotency_conflict`, persist inactive undo receipts, and return identical replays before mutable projection work. Regression: `tests/review-round-06-feedback-idempotency-second-cycle.php`. Initial CI exposed only a regression-literal harness issue; corrected in `72ba8fa0fd8271aaf02be702eea0d6943c654980`. Final exact-head CI GREEN `3fa9e285877be2ea057b9738b91e6937da89f5ad`. |
| 7 | DEFECT | `blocked/restricted` safety classes received only a large negative score but were not excluded from the returned ranked set, so a blocked item could still surface when candidate volume was low. First-page author/connector concentration limits were also reintroduced by appending deferred rows before a protected first-page boundary, allowing the declared caps to be violated. | FROZEN — correction pending. |

## Round status

- Completed reviews/corrections: **6/20**
- Active frozen round: **7/20 — correction pending**
- Defect rounds so far: **2, 3, 4, 5, 6, 7**
- Clean rounds so far: **1**

Round 8 must not begin until Round 7 is corrected, regressed and exact-head CI is green.
