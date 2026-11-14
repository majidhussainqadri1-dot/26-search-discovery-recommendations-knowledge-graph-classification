# File26 V12.3 — 2026-11-14 Privacy Source Review and Targeted Corrective Tests

**Status: SOURCE FIX CANDIDATES; current branch CI MUST be checked again. NOT a Live incident or verified deployment.**

## Root-cause evidence (exact pre-patch source from PR #22 at c0dc5ac09fdf068f5e77dab0e7f0862a21f33cb1)

| Defect | Code path | Reproducible root cause | User/data risk | Scoped correction |
|---|---|---|---|---|
| D26-20261114-01 | Normalizer::prefix_is_safe | Normalization strips punctuation, then original code checks for @ on the transformed string, so the at-sign check cannot trigger. Formatted phone digits could be separated to avoid contiguous 7-digit check. | Autocomplete can accept an email-shaped or formatted phone-shaped prefix; separate Security checks reduce the risk for ordinary ASCII email, but do not cover all Unicode variants. | Reject raw ASCII/fullwidth at-sign before normalization, and count normalized ASCII digits across spacing/hyphens. |
| D26-20261114-02 | Security::contains_sensitive_query | Sensitive classifier knew conventional email and contiguous Pakistani phone, but not Unicode/fullwidth at-sign, spaced mobile or spaced CNIC; Urdu/Arabic digits were unhandled. | Optional provider/saved-history/telemetry-sensitive routing may not recognize a private input. This is a source-level risk, not a demonstrated external data disclosure. | Conservative raw at-sign check, Urdu/Arabic digit translation for screening only, formatted mobile/CNIC detection. Preserve original search text. |
| D26-20261114-03 | assets/js/file26-future.js :: sensitive | Local-first history sync checked a small word list, not emails, fullwidth at-signs, Pakistani mobile formats, Urdu digits, or CNIC numeric patterns. Server rejection happens only after transmission. | Sensitive local-only query could be sent over the network during explicit opt-in even though server might later reject storage. | Perform synchronized sensitive-pattern checks in browser before any optional sync request; default no-sync remains. |

## Follow-up independent review — HTTP 200 is not a saved receipt

| Defect | Code path | Exact source root cause | Scoped correction |
|---|---|---|---|
| D26-20261114-04 | assets/js/file26-future.js :: history.syncOptIn | The server's Future_User_Data_Trait::search_history() returns HTTP 200 with saved=false and sensitive_query_not_synced=true when server policy rejects a query, but the browser previously awaited the request and unconditionally added the query to its synced count. | Count sync only for explicit response.saved===true; count sensitive rejections separately; refuse unacknowledged success bodies with a typed local error. |

**Negative regression:** The isolated Node VM fetch mock now returns HTTP 200 with saved=false for a client-safe/server-denied fixture, and HTTP 200 with a malformed empty success body for a second fixture. The test requires zero false "synced" counts, one server_rejected and an exception on missing acknowledgement.

## Third-pass output-disclosure review — suggestion result PII

| Defect | Code path | Original exact code root cause | Scoped correction |
|---|---|---|---|
| D26-20261114-05 | Search::suggest | Search::suggest validates only the input prefix and owner eligibility, then unconditionally emits each eligible row's title as an autocomplete label; a publicly indexed title containing a phone/email value could be echoed for an otherwise harmless prefix. | Before appending to output, require that each candidate label passes the same PII-sensitive prefix and query screens, independently of the owner visibility gate. |

**SQL-backed negative test:** disposable-only WordPress/MariaDB test adds two synthetic PII-shaped public titles and two harmless matching public titles. Harmless prefixes must return at least one safe suggestion, but never include the PII-shaped titles. A private or unauthorized result must also remain excluded independently.

## Concrete independent tests added

1. Pure PHP Normalizer tests: ordinary ASCII email, fullwidth-at email, spaces/hyphen phone and safe public query control.
2. Pure PHP Security tests: fullwidth-at email, spaced Pakistani phone, Urdu-digit mobile, spaced CNIC and ordinary educational query.
3. Genuine disposable WordPress + MariaDB suggest regression: index two **public, eligible decoy titles**, verify harmless prefixes actually yield suggestions, then prove fullwidth email and formatted phone queries yield zero suggestions. No real user/clinic content is touched; hard guard still requires FILE26_CI_EPHEMERAL_DB=yes and file26-ci.local.
4. Node.js isolated browser-history regression: record 6 synthetically sensitive and 1 safe query locally, prove zero HTTP requests on load and record, then explicitly opt-in and assert only the 1 safe query travels to the mocked fetch endpoint. Verify local clear.
5. Real WordPress/MariaDB public-title output-filter regression separately checks sensitive suggestion labels.
6. JS sync-truth regression refuses server-sensitive HTTP-200 rejection and missing saved acknowledgement.
7. Both JavaScript and V12.3 registry checks run against the original source and the deterministic clean-extracted WordPress package, in addition to existing PHP/SQL tests.

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
- D26-20261114-01/02/03/04/05: SOURCE FIX CANDIDATE (not merged to main, not deployed).
- New Live defects fixed: 0.
- Verified Live fixes: 0.
- Repository HEAD: to be re-frozen on CI check.
- Deployed Version: UNVERIFIED.
- DB Version: UNVERIFIED.
- Migration State: UNVERIFIED.
- Live Verification Status: NOT VERIFIED.

This review neither overrides master-plan V12.3 nor authorizes activation of any search owner.
