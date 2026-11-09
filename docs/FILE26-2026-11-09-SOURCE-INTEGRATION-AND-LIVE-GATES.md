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

Verified in GitHub Actions on 2026-11-09, using disposable **WordPress 6.8.3, 7.0.1 and 7.1.4 / PHP 8.3 / MariaDB 10.11** installations, synthetic connector, synthetic Founder/admin identity and separate synthetic auditor:
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

**Owner-source reality:** default branches of Files 03, 07, 08, 10, 15 contain executable code; Files 05, 06, 11, 12 and 18 had no executable owner module on their inspected `main`; canonical File21 is now identified as public repository `sabri-complete-home-news-feed`, with a genuinely installed native `file21-publication` connector handshake tested on three isolated WordPress versions. This audit does not prove other branches or deployments are empty.

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

## Extended 2026-11-09 corrections and native-owner acceptance

**New exact-branch source safety closures beyond the initial three concurrency defects:**

1. Per-object serving-version monotonicity: a full owner rebuild cannot replay older events, or change the checksum at the same version/sequence.
2. Per-object canonical removal proof: candidates that omit even one live document without a sufficiently new tombstone fail validation, including cases with an **equal total document count**. Shadow deletion/restriction without a live revocation record fails closed.
3. Canonical File04 cutover contract: its three hashes belong in an idempotent, exact-request `wp_options` acceptance receipt, not Indexer scope. File04 explicitly accepts an asynchronously queued search job; the receipt now verifies a real existing queue job, its full-snapshot connector and non-dead-letter state, checks File04's canonical SHA-256 request digest, and never claims candidate promotion or a completed rebuild.
4. Interrupted atomic cutover/rollback: journal source/candidate checksums before MySQL RENAME; step-up-authorized `sabri_file26_shadow_recover_interrupted` determines whether the entire rename happened, validates **both** physical generations against the journal, and corrects only the provenance option. Unexpected/partial table inventories or inconsistent checksums fail closed, without a second RENAME or any table deletion.
5. Read-only `tools/live-reality-freeze.php`: WP-CLI-only privacy-minimized installed-source tree fingerprint, installed plugin/declared schema/DB version and actual table shape/counts, File03..File21 source-owner readiness, and review/migration metadata. Its executable CI smoke passed; this tool has **not** run on actual Hostinger Live.

**Executable acceptance on actual SQL:** `tests/file26-20261109-wordpress-mysql-integration.php` checks initial guest search, isolated owner rebuild, checksum, distinct approver, atomic swap, exact rollback, private exclusions, source drift, stale owner events, equal-count unrevoked omissions, tombstone failure, and interrupted post-swap/rollback state restoration. `tests/file26-20261109-file04-cutover-integration.php` checks File04 acceptance semantics on MariaDB. `tests/file26-20261109-owner-gate-runtime.php` tests eleven synthetic owner shapes and all seven separate activation approvals.

**First genuine File21 installed-owner handshake:** the exact source HEAD `626b380328d4400014504e679032291cd023bbdb` from the public repository `majidhussainqadri1-dot/sabri-complete-home-news-feed` is fetched by the CI and installed alongside File26 on a disposable WordPress 6.8.3, 7.0.1 and 7.1.4 matrix. Tests prove actual `Sabri\\HomeNewsFeed\\SearchProviderRegistry` registration, callable native `list_batch`, `can_view`, `health`, bounded batch and fail-closed `proposed` lane. No synthetic owner substitutes for this evidence. The real native content *lifecycle* is not yet accepted, due in part to File21's source-version incompatibility documented in its Issue #58.

**Unresolved non-File26 owner blocker:** the canonical File21 metadata-only visibility/review changes can reuse the same post `post_modified_gmt`-derived source version, conflicting with File26 tombstone and checksum precedence. This remains an open owner-source defect (Issue #58), NOT a verified Live incident and NOT a valid reason to weaken File26's anti-resurrection rules.

**All final acceptance evidence must be exact-HEAD:** recompute SHA and validate current PHP 7.4/8.3 CI, full forty sequential QA job, and three real WordPress/MariaDB integration matrix runs on the last PR #22 commit. No older green run can certify a later documentation or code commit.

**Physical Hostinger staging and production acceptance are still missing.** All 11 launched canonical owner journeys, realistic medical/linguistic gold queries, performance/load, restore, security penetration, and Founder deployment approvals remain mandatory. Owner File21 source handshake is now evidenced, but no real deployed owner/version claims are made.
