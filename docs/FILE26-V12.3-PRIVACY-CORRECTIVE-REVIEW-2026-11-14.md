# File26 V12.3 — 2026-11-14 Privacy Source Review and Targeted Corrective Tests

**Status: SOURCE FIX CANDIDATES; current branch CI MUST be checked again. NOT a Live incident or verified deployment.**

## Root-cause evidence (exact pre-patch source from PR #22 at c0dc5ac09fdf068f5e77dab0e7f0862a21f33cb1)

| Defect | Code path | Reproducible root cause | User/data risk | Scoped correction |
|---|---|---|---|---|
| D26-20261114-01 | Normalizer::prefix_is_safe | Normalization strips punctuation, then original code checks for @ on the transformed string, so the at-sign check cannot trigger. Formatted phone digits could be separated to avoid contiguous 7-digit check. | Autocomplete can accept an email-shaped or formatted phone-shaped prefix; separate Security checks reduce the risk for ordinary ASCII email, but do not cover all Unicode variants. | Reject raw ASCII/fullwidth at-sign before normalization, and count normalized ASCII digits across spacing/hyphens. |
| D26-20261114-02 | Security::contains_sensitive_query | Sensitive classifier knew conventional email and contiguous Pakistani phone, but not Unicode/fullwidth at-sign, spaced mobile or spaced CNIC; Urdu/Arabic digits were unhandled. | Optional provider/saved-history/telemetry-sensitive routing may not recognize a private input. This is a source-level risk, not a demonstrated external data disclosure. | Conservative raw at-sign check, Urdu/Arabic digit translation for screening only, formatted mobile/CNIC detection. Preserve original search text. |
| D26-20261114-03 | assets/js/file26-future.js :: sensitive | Local-first history sync checked a small word list, not emails, fullwidth at-signs, Pakistani mobile formats, Urdu digits, or CNIC numeric patterns. Server rejection happens only after transmission. | Sensitive local-only query could be sent over the network during explicit opt-in even though server might later reject storage. | Perform synchronized sensitive-pattern checks in browser before any optional sync request; default no-sync remains. |

## Concrete independent tests added

1. Pure PHP Normalizer tests: ordinary ASCII email, fullwidth-at email, spaces/hyphen phone and safe public query control.
2. Pure PHP Security tests: fullwidth-at email, spaced Pakistani phone, Urdu-digit mobile, spaced CNIC and ordinary educational query.
3. Genuine disposable WordPress + MariaDB suggest regression: index two **public, eligible decoy titles**, verify harmless prefixes actually yield suggestions, then prove fullwidth email and formatted phone queries yield zero suggestions. No real user/clinic content is touched; hard guard still requires FILE26_CI_EPHEMERAL_DB=yes and file26-ci.local.
4. Node.js isolated browser-history regression: record 6 synthetically sensitive and 1 safe query locally, prove zero HTTP requests on load and record, then explicitly opt-in and assert only the 1 safe query travels to the mocked fetch endpoint. Verify local clear.
5. Both JavaScript and V12.3 registry checks run against the original source and the deterministic clean-extracted WordPress package, in addition to existing PHP/SQL tests.

## Boundaries and acceptance

- Tests use synthetic data, not real patient details; **no evidence of actual past leakage** is asserted.
- Existing server refusal still applies; client-side prefilter is defense before the network boundary, not a replacement for server validation.
- The corrected sensitive classifier may conservatively mark more queries as private; this is acceptable for preventing optional provider disclosure but needs later real user/relevance examination.
- A green PHP/Node/WordPress MariaDB CI would prove source and disposable behavior only. It cannot prove browser Safari/Android compatibility, actual CDN/Hostinger cache clearing, Live consent/history behavior or deployed code parity.
- Two independent source reviews, live/staging code freeze, real owner integration, privacy and manual browser acceptance, and Founder release decision remain required.

## Evidence slots and status

- Baseline main: 1b064c6ea9f81195611ed001d5457e8ced65460a.
- Root cause observed in PR #22 candidate SHA: c0dc5ac09fdf068f5e77dab0e7f0862a21f33cb1.
- Corrected code: File26 V12.3 stacked Draft PR #30; use exact new HEAD and associated Action runs after last commit.
- D26-20261114-01/02/03: SOURCE FIX CANDIDATE (not merged to main, not deployed).
- New Live defects fixed: 0.
- Verified Live fixes: 0.
- Repository HEAD: to be re-frozen on CI check.
- Deployed Version: UNVERIFIED.
- DB Version: UNVERIFIED.
- Migration State: UNVERIFIED.
- Live Verification Status: NOT VERIFIED.

This review neither overrides master-plan V12.3 nor authorizes activation of any search owner.
