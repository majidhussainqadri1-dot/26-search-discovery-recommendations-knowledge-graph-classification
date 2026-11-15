# File 26 ↔ File 24 — Versioned doctor-ranking assurance compatibility (2026-11-15)

## Scope and exact source evidence

- Governing V12.3: File 26 must compute transparent, versioned, donation-independent doctor merit rankings, while File 24 retains independent assurance authority.
- Source baseline: File26 main `1b064c6ea9f81195611ed001d5457e8ced65460a`.
- File24 reference: `a5b8d49968a7a5a7d6f3f4655bea541bf38a9acb`, `plugin/sabri-security-center/src/Policy/RankingFairnessPolicy.php`.
- File26 `DB::install_defaults()` and `Doctor_Ranking::policy()` defaulted to `doctor-global-1.0`.
- File24 `RankingFairnessPolicy::evaluate()` requires `^[0-9]+(?:\.[0-9]+){0,3}(?:-[a-z0-9.-]+)?$`. Therefore, the default identifier is not a valid policy_version. Issue #40 remains independently tracked.
- A standalone PHP regular-expression reproduction shows File24 **rejects** `doctor-global-1.0` and **accepts** `1.0.0`.
- No claim that a real File26-to-File24 assurance failure has occurred on Hostinger. Real effective options, schema, plugin packages and current File24 evidence remain unavailable.

## Minimal isolated source correction

1. Fresh File26 installs use `doctor_ranking_policy_version=1.0.0`; this is a **format** correction, not an assertion that a policy has been independently approved.
2. `Doctor_Ranking::is_assurance_policy_version` shares the File24-supported numeric/semantic syntax and 40-byte bound. Arbitrary identifiers, malformed values and non-scalar inputs are rejected.
3. An already-saved legacy setting is not overwritten by activation defaults. If no valid active policy exists, public doctor ranking returns `policy_read_failed` and fail-closed state; do not masquerade an old identifier as a new approved policy.
4. An invalid active policy row and zero-weight active policy fail closed rather than producing an invented valid version.
5. Governance rejects incompatible doctor-global versions at staging, activation and rollback, including historical policy rows that predate the new validation.
6. `tests/file26-20261115-file24-policy-version-regression.php` runs in the source tree and clean-extracted deterministic package from `qa/run-tests.sh`; it tests the fallback, legacy config, malformed/active rows, valid versions, zero weights and DB-read errors.

## Explicitly unresolved

- File24 independence is preserved. Its evidence_ref, recomputation age, control list, forbidden influence and policy validity must still be verified by **actual** File24.
- Existing owner policy identity and historical option values require separately approved migration/activation. This branch performs no silent DB update.
- File07's official Top10/100/1000 remains gated by File24's independent `verified` assurance; never bypass it to display a badge.
- Must obtain actual WordPress/Hostinger staging installed package, File26 File24 File07 effective versions, current policy DB and audit data; execute authorizations, monthly recomputation and both failure and success journeys.
- Release still depends on 11 owner connectors, FR-005 shadow rebuild, FR-006 durable revocation and issue #37, File12 #23, two independent reviews, exact SHA CI, backup/restore and Founder approval.

## Test and release record

Repository branch is a source-only Draft; no production edits, Live source verification, DB writes or deployment were made. Exact PR-head Actions must be examined independently after creation.

Repository HEAD: main `1b064c6ea9f81195611ed001d5457e8ced65460a`; candidate branch is separately versioned.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT VERIFIED.
Verified Live fixes: 0.

**Decision:** source fix candidate only, not release GO or issue resolution.