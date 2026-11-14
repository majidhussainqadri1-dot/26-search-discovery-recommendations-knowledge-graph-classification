# File26 V12.3 — Native File00 Identity and Free Entitlement Source Contract
**Date:** 2026-11-14. **Evidence:** repository source and isolated test fixtures only, not Hostinger staging or Live.

## Exact owner truth
File00 repository majidhussainqadri1-dot/00-sabri-membership-core; main source tree 1fad051c86d530d558be3eb1890d3c6e67bce6e8; plugin 1.2.44, canonical membership contract **1.2.3**. Canonical smc_membership_assertions(user_id) delegates to SMC_Contracts::assertions(). The owner emits subject user_id, approved, eligible, suspended, guardian_verified, age_context (known, minor, guardian_required), and entitlement policy base_services with boolean per-service eligibility. Approved membership alone is not sufficient: File00 includes professional, guardian, contact, identity and advanced-trust checks when calculating eligible.

## Documented source defects
- **D26-AUTH-006 / P0:** Previous File26 Security::audience accepted only File00 versions 1.0, 1.1 and 1.1.2, excluding current File00 1.2.3. It ignored the canonical owner approved and eligible denials and did not validate returned subject ID; it expected top-level is_minor, while current owner returns age_context. A compatible-looking forged filter could yield false member authorization. This is a source flaw, not a verified production disclosure.
- **D26-FREE-007 / P1:** File00's entitlement assertion is not a flat string list; it has base_services (education/clinic/marketplace and others), with true/false values. File26's older flat-claim decoder failed to recognize real free services and could mix policy metadata into authorization. This is a source-contract incompatibility, not a claimed Live failure.

## Narrow correction in Draft PR #33
- Prefer actual native smc_membership_assertions() whenever available; no third-party filter may override a canonical owner denial. Runtime exceptions deny.
- Require matching logged-in WordPress user ID, supported exact version (including **1.2.3**), approved=true, eligible=true, suspended=false and minor/guardian conditions.
- File00 age_context is used; unknown age does not imply verified adulthood. Guest remains PUBLIC-only.
- Only explicitly true keys inside File00 base_services qualify as free entitlements. No false premium/donation/payment privilege is inferred.
- Source and clean-extracted ZIP run the same PHP negative fixture. The disposable MariaDB founder/auditor test identities now explicitly carry owner-approved subject claims; these remain SYNTHETIC.

## Regressions
- tests/file26-v12-3-file00-audience.php: native File00 stub precedence over intentionally forged filter; approved and pending subjects; eligible=false; wrong subject; future unsupported contract; suspended; minor without verified guardian; unknown age; owner exception; guest public-only; enabled education and disabled premium entitlement.
- tests/test-normalizer-ranking.php: legacy allowed-owner fixture updated to include explicit approved/eligible and known age.
- tests/file26-20261109-wordpress-mysql-integration.php: disposable user assertions now carry explicit matching synthetic user IDs and positive eligibility, with no real File00 bypass.
- qa/run-tests.sh exercises new fixture on checkout and deterministic extracted ZIP.

## Verification and blocking
New PR source CI must be verified at the exact final HEAD; an older green run does not certify new edits.
No real Hostinger File00 installed adapter/version, entitlement policy, minor/guardian lifecycle, File00↔File09 onboarding Issue #57, Staging migration, rollback, real user acceptance, deployment checksum or Live retest is available.
Explicit unresolved owner issues File26 #20, #23, #31, #32 and File00 #57 remain outside any source-only closure.

**Mandatory five-field truth:** Repository main 1b064c6ea9f81195611ed001d5457e8ced65460a; PR33 SHA requires refresh. Deployed Version UNVERIFIED. DB Version UNVERIFIED. Migration State UNVERIFIED. Live Verification Status NOT VERIFIED. Live-resolved defects **0**.
