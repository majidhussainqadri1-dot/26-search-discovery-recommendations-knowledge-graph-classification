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
| 2 | DEFECT | `Schema_Integrity` verified only column names and index names. It did not verify safety-critical column data types/nullability or index uniqueness/column order, so a physically incompatible schema could still be reported complete when names survived drift. | Added safety-critical `DATA_TYPE`/`COLUMN_TYPE`/nullability/length signatures and exact index uniqueness/ordered-column signatures across File 26 tables including ranking appeals. Incompatible columns/indexes now make the structural snapshot incomplete and force the existing migration fail-closed path. `tests/review-round-02-schema-signatures-second-cycle.php` protects the new invariant. Exact-head CI pending on this closure head. |

## Round status

- Completed reviews/corrections: **2/20**
- Defect rounds: **2**
- Clean rounds: **1**

Round 3 must not begin until this exact head is green.
