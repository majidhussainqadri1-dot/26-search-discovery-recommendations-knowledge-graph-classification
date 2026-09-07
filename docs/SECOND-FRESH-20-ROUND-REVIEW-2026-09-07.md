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
After the R1-D1 correction and dedicated regression were committed, exact-head CI exposed an inherited pre-existing baseline failure that also occurs at baseline commit `9067d19a82eeae59713a705cd16f3e56a3ec435e`.

- **R1-D2 — High — stale release-evidence regression contradicts runtime-only package law:** `tests/review-round-20-release-evidence.php` requires the QA runner to execute `tests/review-round-*.php` from the clean installable package, while the current governed package builder/QA intentionally excludes `/tests/` and other development-only paths and validates a runtime-only ZIP. The test therefore guarantees CI failure even when the runtime package is correctly stripped. Root cause is a stale test assertion, not the R1-D1 fix.

This regression-gate finding is frozen before its correction. Round 2 must not begin until Round 1 exact-head CI is green.
