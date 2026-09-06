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
| 3 | DEFECT | Canonical object identity was normalized inconsistently between upsert and tombstone/restrict; normalized `domain/object_id` could become invalid; malformed/non-positive monotonic versions were silently coerced. | Canonical key now normalizes object IDs consistently; upsert rejects normalized-empty/oversized identities, invalid positive versions and invalid non-negative event sequences; tombstone/restrict normalize and validate the same identity/version before hashing. Regression: `tests/review-round-03-identity-version-second-cycle.php`. Exact-head CI pending on this closure head. |

## Round status

- Completed reviews/corrections: **3/20**
- Defect rounds: **2, 3**
- Clean rounds: **1**

Round 4 must not begin until this exact head is green.
