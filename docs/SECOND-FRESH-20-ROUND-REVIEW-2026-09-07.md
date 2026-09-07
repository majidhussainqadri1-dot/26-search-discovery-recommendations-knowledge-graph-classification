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
- **R1-D3 — High — legacy source regressions are whitespace-coupled and reject semantically equivalent hardened code:** the durable-audit correction at baseline compacted portions of `class-file26-governance.php`, and existing static regressions asserted exact formatting rather than semantic source tokens. Confirmed affected gates include `tests/corrective-contract-tests.php`, `tests/review-round-12-governance-transitions.php`, `tests/review-round-21-rest-reconcile-truth.php`, and `tests/review-round-27-graph-owner-and-traversal-truth.php`.
- **R1-D4 — High — residual whitespace-coupled partial-state assertion:** exact-head run `34085851001` on `6397288f6c24a0e37adb8c3b3536722cc906979e` passed PHP syntax, normalization/ranking, and the entire architecture/traceability suite, then failed only the corrective assertion “Bounded corpus scans disclose truthful partial state.” The implementation contains the required `health => scan_limit` partial-domain signal, but the test compared exact whitespace.
- **R1-D5 — High — Round-03 search-integrity regression is formatting-coupled:** exact-head run `34086105329` reached sequential regressions and failed four assertions although current Search implements sensitivity-before-cache, sensitive shared-cache exclusion, deterministic freshness tie-break, and canonical-key tie-break in compact formatting.
- **R1-D6 — High — Round-07 taxonomy lifecycle regression is formatting-coupled:** after R1-D5 correction, exact-head run `34086227706` passed Round 01, Round 02, Round 03 and Round 06 regressions, then failed only “merge target must remain active.” Current taxonomy implementation contains the same guard in compact form (`'active'!==$target['status']`); `tests/review-round-07-taxonomy-lifecycle.php` still requires spaces around the operator. The lifecycle safeguard exists; the regression test is the defect.
- **R1-D7 — High — Round-08 graph-governance regression is formatting-coupled:** exact-head run `34086317597` on `70a97194123900e0d81c9481241c9c680633cdc5` passed Round 01, 02, 03, 06 and 07, then failed two Round-08 assertions: “approved transition writes active state” and “edges to revoked nodes are removed from response.” Current `class-file26-graph.php` implements both safeguards in compact form (`array('state'=>'active',...)` and the final `isset($visible_keys[$edge['source_key']],$visible_keys[$edge['target_key']])` filter). The runtime graph safeguards are present; `tests/review-round-08-graph-governance.php` is rejecting semantically equivalent formatting.

All regression-gate findings above were frozen before their corrections. Round 2 must not begin until Round 1 exact-head CI is green.
