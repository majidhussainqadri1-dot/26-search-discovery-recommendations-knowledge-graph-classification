# File26 ↔ File10: disposable batch snapshot consent-outage regression — FROZEN BEFORE EDIT

Date: 2026-11-13. This is a source/isolated WordPress/MariaDB test only, not the live website.

## Exact source points
- File26 existing PR #27 source: `ac0c8b597d5c0096ac7fcff006a39d7d3abd4d3e`.
- File10 latest reviewed PR #29 exact SHA: `3120b7a53f86ef44caa34332c854323c4f68517d`, descendant of File10 PR #28.
- File10 PR #29 independently detected source defect `SNAP-001`: `VWLB_File26_Connector::list_batch()` could finish a page despite R109 consent database lookup failure.

## Test scope
At the isolated disposable WordPress (host `file26-ci.local`, env `FILE26_CI_EPHEMERAL_DB=yes`), use existing native File10 plugin tables and WP classes. Temporarily rename File10 `consent_links` table, call public File26 source `list_batch('',50)`, then restore the original table in `finally` before asserting a typed WP_Error. A genuine private video still must be excluded without error. Test no consumer indexing and no source mutation outside this isolated disposable CI.

## Expected governance
The new checkout pin must use exact File10 PR #29 SHA; do not use GitHub main. Run WordPress 7.0.1 and 7.1.4 with PHP 8.3 in File26 disposable matrix, plus File26 QA. Keep File10 File26 connector `status=proposed` and `owner_outbox_tombstone_gate_pending`.

## Remaining
Issue #13 (WordPress/Jetpack disconnected; Live deployed version+DB unknown), Issue #22 (required CI checks/ruleset branch coverage) and Issue #26 (durable tombstone ACK/storage/index purge) remain unresolved. No deployment or Staging/Live completion inferred.
