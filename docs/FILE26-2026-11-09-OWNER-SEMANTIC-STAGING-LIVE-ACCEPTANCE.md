# File26 — Exact Source Owners, Semantic Model and Hostinger Deployment Acceptance Gates
**Date:** 2026-11-09. **Source candidate only.** This is not installed Hostinger or Live truth.

## I. Canonical File26 owner contracts, EXACT owner source candidate and release state

The word **handshake** below means original owner code was loaded into an isolated WordPress and supplied callable proposed contract, with negative privacy/missing-dependency evidence. It DOES NOT mean full owner lifecycle, reviewed production activation, semantic corpus, or Live acceptance.

| File | Owner source branch / DRAFT PR | Native source safety/remaining gate |
|---|---|---|
| 03 | `03-sabri-profiles-and-doctors` PR #42 | Reuse existing SPD owner adapters; failed DB read returns WP_Error, never done=true empty; remove volatile request timestamps from indexed checksum. Live File00/09 and profile lifecycle/tombstone gate pending |
| 05 | `05-learn-sabri-classical-homeopathy` PR #7 | Proposed lessons/courses/books; File05 public eligibility; no patient case lessons/private assessments. Full publication/revision and consent gate pending |
| 06 | `06-homeopathy-encyclopedia-foundation` PR #15 | Proposed approved safety/review concept DTO only; migration/owner integrity and revoke/retract gate pending |
| 07 | `07-doctors-directory-and-discovery` PR #12 | Proposed doctor_directory_projection; owner File00 identity, File03 profile and File09 verification MUST all be real and current. No invented doctor identity; source revocation gate pending |
| 08 | `08-worldwide-clinic-and-appointments-foundation` PR #44 | Proposed clinic projection only via native WCA_Central_Governance::file26_clinic_projection. No patient/appointments/native IDs. Appointment availability reserved, no public slots until owner event/version proof |
| 10 | `10-video-wall-and-educational-broadcasting-foundation` PR #19 | Proposed video/live_video/channel; native File10 public authorization, consent/rights/kill switch; owner outbox/tombstone rollout pending |
| 11 | Existing unmerged native File11 candidate `1df22845452419b3e67686d41b4ea178bfd9f484` | Proposed reels; actual File10 plugin dependency required. Full reel publication/playback/transcript lifecycle pending |
| 12 | `12-pdf-library-and-digital-reading-foundation` PR #47 | Proposed public PDF catalogue; native license/read policy and published edition; no corpus, download grants or private reading history; content-rights revocation pending |
| 15 | `15-radar-symptom-and-remedy-analysis-foundation` PR #13 | Proposed editorially published aggregate trend reports only; NEVER private doctor Radar Studies. Editorial revoke/correct owner event gate pending |
| 18 | `18-sabri-marketplace` PR #33 | Proposed active listings from approved sellers; expiry, active recall, content safety on current File18 owner; no private offers/purchases/payment |
| 21 | `sabri-complete-home-news-feed` PR #59 | Monotonic post+metadata source versions and current public/moderation checks. Exact native public→private→public, review/locale/deletion MariaDB passed; full installed lifecycle remains to be approved |

All underlying DRAFT PR bases are different source repository truths, with some **not based on main**. Any cherry-pick/merge must respect the owner team's open PR review state and dependency branch; none are valid evidence of deployed code.

## II. Real semantic embedding model evidence

- Tested, *actually pulled and executed* on an ephemeral Ubuntu GitHub runner: `qwen3-embedding:0.6b` via Ollama `/api/embed`; 639 MB model, 1,024 dimensions.
- Actual synthetic cross-language reading test: 7 English public educational catalog records and 14 queries (2 each for Urdu, Arabic, en-US, Persian, Hindi, Bengali, Roman Urdu) returned top-1 14/14 and top-3 14/14 in the small fixture, one 21-input embedding batch ~4.214 seconds.
- GitHub real-model run `42587089460` SUCCESS; no simulated vectors. This is an initial gold screen only; no per-disease, medical efficacy, real user traffic or long-tail clinical relevance claim.
- Actual WordPress/File26 connected semantic API test: `tests/file26-20261109-real-ollama-wordpress-e2e.php` is wired into the real-model GitHub workflow. It must report PASS on the current exact SHA before declaring model + WP end-to-end even in disposable CI.
- Source File26 adapter is opt-in, default OFF, same-server loopback only; supported exact model `qwen3-embedding:0.6b`. No Hostinger installation/model storage/CPU quota or live endpoint verification has been performed.
- **Architectural limitation:** File26's current Future_Intelligence::semantic_rerank performs lexical retrieval FIRST and uses embeddings to rerank the existing authorized candidates; this is not full corpus-wide cross-language vector retrieval. A separate governed vector-indexing/retrieval provider is needed for true semantic recall across zero lexical overlap. Do NOT label it a fully deployed semantic search service.

## III. Hostinger and external staging blockers

- WPVibe `connect_site` returned Cloudflare HTTP 530; no WPVibe site is currently connected.
- WordPress.com/Jetpack user's site listing showed `mcp_access.status=unavailable`, `reason_code=site_disconnected`. A connector name or domain in an account listing is not operational site access.
- Neither cause proves a specific DNS error, Web Application Firewall failure, origin timeout or server issue. User/host needs to restore **authorized owner-side WordPress/Hostinger connection**. Never ask for password/secret in chat.
- Before writing staging or Live: run `tools/live-reality-freeze.php` via OWNER-AUTHORIZED WP-CLI in EACH environment; record installed plugin path, version, full plugin file-tree checksum, DB name/server/version (never credentials), actual table shapes/counts, wp_options schema/migration, active owner plugin versions and health, actual logs and deployed artifacts.
- Then run `tools/file26-readonly-deployment-parity.py --staging <PRIVATE_STAGE_FREEZE> --live <PRIVATE_LIVE_FREEZE> --expected-tree-sha256 <APPROVED_PACKAGED_PLUGIN_TREE_SHA>`.
- This read-only checker is intentionally fail-closed and NEVER authorizes a deploy on its own. Its synthetic fixture test is `tests/file26-20261109-parity-guard.py`. Real stage/live reports unavailable, so deployment parity itself is **UNVERIFIED**.

## IV. Staging migration and rollback (not yet executed)

**Do not perform any of the following until exact staging deployment and database evidence exist.**

1. **Freeze** exact staging artifact/source SHA, current settings, WordPress/PHP/MySQL versions, all File26 tables/columns/counts, migrations, owner status and diagnostic logs.
2. **Back up** staging DB + File26 plugin + relevant owner DB and plugin files; verify actual restoration into disposable copy and record checksum and RTO/RPO.
3. **Branch parity** check that planned File26 SHA and each owner PR head is the reviewed build, never assume GitHub HEAD is deployed version.
4. **Install to staging** only after explicit Founder/release-owner authorization and isolated rollback plan; execute original migrations and verify tables/options/zero dead-letter. If version mismatch appears, halt normal debugging and run a Deployment-Parity Audit.
5. **Shadow rebuild** all valid canonical public owners using their real list_batch/current event/version/tombstone ledger, compare digest/full source counts, prove no stale/omitted revocations. Independent auditor approves exact candidate SHA.
6. **Founder cutover** with step-up, checksum-protected atomic swap. Verify guest search, members/private boundaries, owner click-time rechecks, 7 scripts, clinical risk bypass, clinical privacy, real PDF rights, doctor verified status, marketplace recalls and video kill switch. Explicitly record failures.
7. **Rollback drill** with different auditor and Founder step-up; recheck exact MySQL physical digest and live API, not merely option flags. Preserve audit/authorization evidence.
8. **Performance/security** load/query corpus and critical negative tests; set accepted p95 latency, impact and SLO explicitly with owner approval, avoid invented metrics.
9. **Deploy to Live only when staging complete** and exact deployed package parity signed by Founder. Never overwrite live while Jetpack/WPVibe disconnected.
10. **Live Freeze + Retest** with fresh logs, database version and actual artifact checksum, production guest/member/verified doctors/public PDF/video/reel/marketplace/clinic/public Radar journeys, migration proof and direct source parity; only then mark defects Resolved.

## V. Mandatory final release truth table

| Metric | Current known fact |
|---|---|
| File26 GitHub main | `1b064c6ea9f81195611ed001d5457e8ced65460a` (source repository only) |
| File26 candidate | Draft PR #22; recheck exact head after all source edits |
| File26 source plugin | 1.3.0, source schema 1.0.0 |
| Actual deployed version | UNVERIFIED |
| Actual DB version / schema | UNVERIFIED |
| Actual migration state | UNVERIFIED |
| Real Hostinger staging | NOT TESTED |
| Actual Live verification | NOT TESTED |
| Confirmed Live fixes | **0** |
| Owner contracts operationally approved | **0 out of 11 on evidence here**; proposed source handshakes are not activation |
| Full semantic corpus retrieval | Not implemented by current candidate; candidate reranking only |
| Remaining principal blockers | Genuine owner lifecycle + event/tombstone, source-to-installed parity, Hostinger access, staging migration/rollback, model capacity and real-world semantic gold samples, separate security/load acceptance, Live retest |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
