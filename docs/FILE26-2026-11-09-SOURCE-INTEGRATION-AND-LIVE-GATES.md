# File 26 — 9 November 2026 Source Integration, Defect Closure and Release Gates

## Governing decision

File 26 is **NOT** approved for production release merely because GitHub checks pass. This record distinguishes:
1. GitHub source candidate on draft PR #22.
2. Ephemeral CI WordPress + MariaDB behavior.
3. Hostinger staging (not inspected).
4. Actual sabrihomeopathy.com deployment and DB/migration state (unverified).

The branch `audit/file26-v12-shadow-index-20261108` remains a release **candidate**, not the deployed or accepted production source. Exact HEAD must be refreshed at the release gate; never infer deployment parity from a branch, CI run, or package.

## Changes applied in this completion session

- **Defect A — stale-worker/fence bypass:** `Shadow_Index::stage` now acquires the generation-wide advisory lock BEFORE reading the job lease, checks exact running status and lock_token, and rechecks the recorded prepared generation under the same lock. Old workers cannot resume staging after a lease is reclaimed or a generation is swapped.
- **Defect B — cutover/staging race:** the same lock serializes all staging commits with validation, second approval, atomic swap and rollback. Lock release is in `finally`; a denied lock does not write.
- **Defect C — corrected graph-edge lifecycle write:** `Governance::transition_edge` now serializes the previously unlocked `active -> corrected` UPDATE with the generation-wide lock, in addition to existing graph mutation guards.
- **Regression expansion:** static invariants in `tests/file26-20261108-shadow-index-regression.php`; executable PHP lease-fencing cases in `tests/file26-20261109-shadow-worker-runtime.php`. Both source and extracted installable ZIP run these tests through `qa/run-tests.sh`.
- **Real isolated SQL fixture:** `tests/file26-20261109-wordpress-mysql-integration.php` executed by `.github/workflows/file26-mysql-integration.yml`. The file categorically refuses any database outside the exact disposable `file26-ci.local` host plus `FILE26_CI_EPHEMERAL_DB=yes`.

## Ephemeral integration acceptance

Verified in GitHub Actions on 2026-11-09, using a disposable **WordPress 6.8.3 / PHP 8.3 / MariaDB 10.11** installation, synthetic connector, synthetic Founder/admin identity and separate synthetic auditor:
- Successful plugin activation and complete real schema.
- Real WordPress connector registration and canonical serving document write.
- Owner full reindex using `CREATE TABLE ... LIKE`, physical shadow staging and queue lease.
- Original serving generation remains visible during rebuild.
- Independent SHA-256 candidate validation; auditor step-up simulation; guest denied swap.
- Multi-table `RENAME TABLE` atomic cutover; document and node change together.
- Guest search finds original content before cutover, new content after cutover, and original content again after rollback.
- Completed/stale worker prevented from staging after cutover.
- Distinct auditor rollback approval and Founder-authorized rollback to the exact original data.
- Additional negative fixture: members-only search is hidden from guests but available to a valid signed-in audience.
- Additional negative fixture: post-snapshot live-index drift causes candidate validation to fail closed.

**Grounded evidence:** workflow `File 26 Disposable WordPress MariaDB Integration`, e.g. successful run `42541049338`; recheck the run on the exact final branch HEAD before acceptance. The GitHub run and test logs are source/CI facts, **not** a production deployment receipt.

## Explicitly NOT proven

- Live or Hostinger staging deployed version/package/hash and source parity.
- Actual WordPress DB/schema version, migration completion, wp_options data and live runtime logs.
- Real Founder and independent reviewer step-up accounts, role binding, external authorization service and organizational separation of duties.
- Real owner adapters, callback inventories, eligibility semantics and publication/deletion/revocation from **Files 03, 05, 06, 07, 08, 10, 11, 12, 15, 18, 21**.
- File 00 membership, File 19 alert deliveries, File 20 shell, File 24 assurance, File 25 visual, and File 04 legacy migration/cutover with their real deployed code.
- Search relevance gold sets (Urdu, English, Arabic, Roman Urdu), provider-grounded semantic/multimodal future24 behavior, doctor ranking cohorts, trust-boundary penetration testing, screen-reader/RTL/mobile UX, operational recovery under load.
- Real source inventory completeness from owner `list_batch`, multi-object filtered partial rebuild (intentionally unsupported/fail-closed), and performance budgets on production-scale records.
- Production backup/restore drill, staged installation/upgrade/revert, live incident retest and exact deployed parity.

**Owner-source reality:** default branches of Files 03, 07, 08, 10, 15 contain executable code; Files 05, 06, 11, 12 and 18 had no executable owner module on their inspected `main`; no canonical File 21 repository was established in the accessible repository list. This audit does not prove other branches or deployments are empty.

## Open release gates, in order

1. Freeze LIVE active plugins/files/package SHA-256, current DB columns/rows/options, migration state, runtime errors, config/dependencies, security boundary; freeze STAGING separately.
2. If GitHub/Live differ, run deployment-parity audit **before** calling any source fix a live fix.
3. Obtain approved canonical owner adapters and attest owner source inventory completeness, visibility and deletion semantics for all launched modules.
4. Run File 26 integration with those real owners on staging, actual DB snapshots, anonymized realistic volumes, golden queries and negative privacy/authorization matrices.
5. Test backups, migration, failed/partial queue, index drift, rollback failure handling, zero downtime, TTL/cache correctness, recovery/load/performance.
6. Finish final source/ZIP QA on exact HEAD and obtain two distinct real step-up approvals and explicit Founder decision for exact SHA-256 artifact.
7. Deploy approved artifact; repeat live journeys/DB parity and record a post-deployment verification before marking incident resolved.

## Precise reporting

- **Confirmed new source-level concurrency defects addressed in this session:** 3.
- **New runtime PHP tests:** 1 executable harness plus enlarged source regression tests.
- **New real-DB CI:** 1 workflow + 1 WordPress/MariaDB integration script.
- **Prior P0 FR-005:** initial source implementation in PR #22; real synthetic WordPress/MariaDB happy-path promotion/rollback now proved. It is **not** fully accepted against real owner providers and live.
- **Live verified fixes:** 0.
- **Repository main:** unchanged, original HEAD at this review `1b064c6ea9f81195611ed001d5457e8ced65460a`.
- **Deployed Version:** unknown.
- **Actual DB Version:** unknown.
- **Migration State:** unknown.
- **Live Verification Status:** not verified.

The previous site connection via WPVibe returned HTTP 530; this does not establish the failing hop or root cause. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
