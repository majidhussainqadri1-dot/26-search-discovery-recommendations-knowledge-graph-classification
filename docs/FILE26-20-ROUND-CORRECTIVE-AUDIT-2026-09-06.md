# File 26 — Independent 20-Round Corrective Audit — 2026-09-06

## Governing method

Baseline `main` SHA: `89fd57bb9408dddf5f6390982ba4c55d87752286`.
Review branch: `codex/file26-20-round-review-20260906`.

Each round follows the strict sequence **Review → Ledger Freeze → Fix → Regression → Exact-head CI → Next Round**. No defect discovered during an active review is corrected until that round's review has completed and its ledger has been frozen.

This ledger is repository evidence only. Staging, live deployment, deployed database/schema, migration completion, real connectors and operational behavior require separate evidence.

## Round ledger

| Round | Result | Frozen findings | Corrective closure |
|---:|---|---|---|
| 1 | DEFECT | The committed root `MANIFEST.sha256` was stale and QA regenerated/overwrote it before verification, masking source-manifest drift. The committed `release/` contained only a stale 1.0.0 ZIP and absolute-path checksum while runtime/readme were 1.2.0 and README directed installation from `release/`. The apparent absence of regression files 04/05/14 was cross-checked against the prior audit and correctly explained by those rounds being clean; it was not treated as a defect. | Package manifest generation is now in-memory and non-mutating; QA verifies package-manifest hashes against both clean extraction and exact source; stale committed root manifest and 1.0.0 release artifacts were removed; generated checksums are relative-path; README now states the exact-head artifact/generated-release boundary. |

## Round status

- Completed rounds: **1/20**
- Defect rounds: **1**
- Clean rounds: none yet
- Current corrective head before exact-head CI: `0f9c44bcb42fb78542baad75c588c83f0052e9de`

Round 2 must not begin until Round 1 exact-head CI is green.
