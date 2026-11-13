# Modern File10 disposable owner candidate — isolated real DB trial freeze, 2026-11-13

## Before test change: verified baselines
- File26 Draft PR #24 currently `a83f904891c6fac9bcdaabda0f2a367e67e475bf`.
- Exact-head real disposable WordPress/MariaDB run `43359840993` **SUCCESS** for WordPress 6.8.3, 7.0.1 and 7.1.4; File10-specific test only runs on 7.0.1 and 7.1.4.
- Existing CI owner pin `08fb8ff525b346be88038589aff5d1f979017c5b` is File10's **old** `1.2.5-rc1` proposed connector lineage, not the current advanced Draft File10 #21.
- Modern exact candidate `e668f39a6dc2549e49f98274f6416fe76b75220e` is File10 Draft PR #21 source `1.2.25-rc1`. Its reviewed File26 class is deliberately not loaded/registered by production bootstrap until owner tombstone/cutover/rights/staging approval.

## Limited QA-only experiment, not a production feature
1. In the isolated CI checkout step, pin File10 source to exact modern immutable SHA `e668f39a6dc2549e49f98274f6416fe76b75220e`, **without changing any real site or File10 runtime autoload**.
2. Inside the disposable File26 WordPress/MariaDB test, and **only after** the existing `FILE26_CI_EPHEMERAL_DB=yes` AND exact `file26-ci.local` guard, require the dormant File10 class from the installed local plugin and explicitly register `VWLB_File26_Connector::manifest()` as `proposed` in File26's CI-local connector registry. The activation guard remains intact, so no source or live indexing is authorized.
3. Preserve all source permissions and rights/consent checks, and refuse public indexing or status promotion. The test asserts `status=proposed`.
4. Run the real WordPress 7.0.1 and 7.1.4 + MariaDB native owner test and full relevant File26 QA on exact head; if a provider/DB failure arises, treat as proof to investigate, not to stack speculative patches.
5. Keep File26 PR24 Draft. External live DB/schema/migrations, production provider configuration, source-to-deployed parity, Founder acceptance and File10 tombstone event contract remain **unverified**.

## Scope and risk
This is stronger **disposable staging-like integration evidence only**; it is not staging acceptance or production proof. Re-pin must never silently update to moving main; immutable source SHA and written acceptance must be visible.
