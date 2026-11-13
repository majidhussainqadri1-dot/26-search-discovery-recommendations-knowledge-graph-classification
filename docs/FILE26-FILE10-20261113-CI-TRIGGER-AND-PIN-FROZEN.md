# Follow-up source review: File26 native File10 CI compatibility and trigger — 2026-11-13

**Exact source baseline:** File26 Draft PR #24 head `beb6cd98666c2282981226596c91b177bd3fc5ad` after F26-FIXTURE-001 public-ID fix, not production; real disposable MariaDB job has not yet been claimed green.

## F26-QA-002 — native real DB fixture change does not trigger dedicated integration workflow (medium, source QA)
`.github/workflows/file26-mysql-integration.yml` has `pull_request.paths` for `includes/**`, `file-26-search-discovery.php`, `tests/file26-20261109-wordpress-mysql-integration.php`, and itself, but **not** `tests/file26-20261109-native-file10-public-owner.php`. Consequently PR #24 changing that native owner test runs generic `File 26 QA` yet need not start the real WordPress/MariaDB native owner job. Minimal fix: add the native File10 test path to the same workflow's PR path filter. Preserve other triggers unchanged.

## Self-review: modern canonical validator method is not available on pinned old owner
This workflow's `Checkout exact unmerged compatible File10 owner source` step is pinned to `08fb8ff525b346be88038589aff5d1f979017c5b` (old File10 v1.2.5 candidate), where `VWLB_Helpers::is_public_id` is **absent**. Newly added fixture assertions must not call this method unconditionally. Correct the fixture to use the modern grammar regular expression, and additionally call owner `is_public_id` when method exists in a *future approved* newer File10 pin. This is a new test self-review correction, not a live bug.

## Hard boundaries
Do not re-pin the real-DB workflow to modern File10 PR #21 while its File26 connector is deliberately dormant. Staged/production approval and versioned outbox tombstone gates remain blocked; green disposable CI on the legacy pin would not prove readiness with new source or live code.

No production patch; all changes are in a Draft test-only child of File26 PR #22.