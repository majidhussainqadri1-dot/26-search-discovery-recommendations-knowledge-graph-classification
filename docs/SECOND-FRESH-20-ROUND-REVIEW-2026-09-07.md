# File 26 — Second Fresh 20-Round Review / Fix Ledger — 2026-09-07

Branch: `review/file26-second-fresh-20-round-2026-09-07`
Baseline HEAD: `9067d19a82eeae59713a705cd16f3e56a3ec435e`

Governing sequence for every round: **Review (no edits) → Ledger Freeze → Fix all round findings → Regression → Exact-head CI → Next Round**.

Repository evidence is repository truth only. Staging/live/deployed state remains separate and unverified unless independently frozen and proven.

## Round 1 — Activation / schema / role fail-safe review

### Review scope completed before any Round-1 fix
- activation hook and compensation path
- DB activation/deactivation scheduling and settings gate
- separation-of-duties role installation and verification
- appeal-schema activation dependency
- failure-state persistence and public-action fail-closed behavior

### Frozen review defects
- **R1-D1 — Critical — activation compensation can fatal:** `file-26-search-discovery.php` invokes `Roles::uninstall()` after an activation error, but `Roles` has no `uninstall()` method. A failure after role/capability mutation can therefore replace the original activation error with an undefined-method fatal and leave partial authorization state behind. This violates atomic activation/failure compensation and release/migration safety requirements.

Round 1 review was frozen before any Round-1 code correction.

### Regression-gate addendum frozen before correction
After the R1-D1 correction and dedicated regression were committed, exact-head CI exposed inherited pre-existing baseline failures that also occur at baseline commit `9067d19a82eeae59713a705cd16f3e56a3ec435e`.

- **R1-D2 — High — stale release-evidence regression contradicts runtime-only package law:** `tests/review-round-20-release-evidence.php` required the QA runner to execute `tests/review-round-*.php` from the clean installable package, while the governed package builder/QA intentionally excludes `/tests/` and other development-only paths and validates a runtime-only ZIP. The stale assertion guaranteed CI failure even when the runtime package was correctly stripped.
- **R1-D3 — High — legacy source regressions are whitespace-coupled and reject semantically equivalent hardened code:** the durable-audit correction at baseline compacted portions of `class-file26-governance.php`, and existing static regressions asserted exact formatting rather than semantic source tokens. Confirmed affected gates include `tests/corrective-contract-tests.php`, `tests/review-round-12-governance-transitions.php`, `tests/review-round-21-rest-reconcile-truth.php`, and `tests/review-round-27-graph-owner-and-traversal-truth.php`. For example, `user_can($second,'approve_sabri_ranking')` is semantically the same call rejected by a test requiring `user_can( $second, 'approve_sabri_ranking' )`; REST reconciliation returns the actual indexer result but a legacy test requires one exact statement layout. These false negatives make exact-head CI red while hiding real regressions behind formatting noise.

These regression-gate findings were frozen before correction. Round 2 must not begin until Round 1 exact-head CI is green.
