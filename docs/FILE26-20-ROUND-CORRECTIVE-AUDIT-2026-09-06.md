# File 26 — Independent 20-Round Corrective Audit — 2026-09-06

## Governing method

Baseline `main` SHA: `89fd57bb9408dddf5f6390982ba4c55d87752286`.
Review branch: `codex/file26-20-round-review-20260906`.

Each round follows the strict sequence **Review → Ledger Freeze → Fix → Regression → Exact-head CI → Next Round**. No defect discovered during an active review is corrected until that round's review has completed and its ledger has been frozen.

This ledger is repository evidence only. Staging, live deployment, deployed database/schema, migration completion, real connectors and operational behavior require separate evidence.

## Round ledger

| Round | Result | Frozen findings | Corrective closure |
|---:|---|---|---|
| 1 | DEFECT | The committed root `MANIFEST.sha256` was stale and QA regenerated/overwrote it before verification, masking source-manifest drift. The committed `release/` contained only a stale 1.0.0 ZIP and absolute-path checksum while runtime/readme were 1.2.0 and README directed installation from `release/`. The apparent absence of regression files 04/05/14 was cross-checked against the prior audit and correctly explained by those rounds being clean; it was not treated as a defect. | Package manifest generation is now in-memory and non-mutating; QA verifies package-manifest hashes against both clean extraction and exact source; stale committed root manifest and 1.0.0 release artifacts were removed; generated checksums are relative-path; README now states the exact-head artifact/generated-release boundary. Exact-head CI `9713c14f5ce46ef2b92bc8317cd82be19641490b` passed PHP 7.4/8.3. |
| 2 | DEFECT | Runtime schema gating trusted version options as sufficient proof. If the stored main/appeals schema version was current while a required physical table had been deleted or failed to exist, `ensure_schema_current()` could return success and expose runtime contracts. The appeals installer likewise skipped repair solely on version equality. | Runtime now requires physical presence of every required File 26 table and the appeals table before the fast-path can pass; migration is forced when physical integrity is missing; appeals schema installation checks table existence and records the schema option only after table verification. The migration regression now explicitly protects against version-only bypass. |

## Round status

- Completed rounds pending Round 2 exact-head CI: **2/20**
- Defect rounds: **1, 2**
- Clean rounds: none yet
- Round 1 exact-head CI: **GREEN** at `9713c14f5ce46ef2b92bc8317cd82be19641490b`
- Round 2 corrective code head before ledger close: `9200e85a800d095a5492f8b393fd3c21a5f8a282`

Round 3 must not begin until the exact head containing this Round 2 ledger is green.
