# File26 V12.3 — FR003 canonical owner ACL Source Audit
**Date:** 2026-11-14. **Status:** isolated SOURCE correction; no Live/staging assertion.

## Governing law
Master Plan V12.3 File26-FR-003 requires three independent visibility gates: index-time projection, query-time eligibility and click/action-time CURRENT canonical owner. F26-CEN-01 requires rights, ACL, freshness and deletion truth to come from the actual owner. A public projection alone does not establish request-time permission.

## Two separate source flaws in inherited Connectors::can_view
- **D26-ACL-MISSING-011 / P0:** An ACTIVE connector with no callable can_view fell back to generic Security::can_view_visibility(). If the derivative projection was marked public, File26 could return it without a native owner decision. This is a source-path defect, NOT proof that a deployed connector leaked private information.
- **D26-ACL-UNTYPED-012 / P1:** Native owner callback return string 'true' was normalized through generic boolean conversion and treated as authorization. The owner contract needs a genuine current PHP boolean true, not an untyped string/integer or other ambiguous value.

## Correction
- Keep ACTIVE-only outer lane.
- If the native current owner can_view is missing or noncallable, DENY. No generic visibility fallback.
- Invoke real callback and authorize only on identity-comparable boolean true. A false, 'true', 1, WP_Error or exception denies.
- Do not invent source owner rights or activate any absent owner. Public and authenticated result safety remains further gated by File26 visibility and source condition checks.
- Existing callback-exception audit remains; missing callback is denied without spamming a per-result audit event.

## Source and real disposable tests
1. tests/file26-v12-3-owner-acl.php: isolated PHP reflection-injected active connector, missing callback, explicit denial, untyped 'true', typed true, exception and proposed owner. Must prove no fallback.
2. tests/file26-20261109-wordpress-mysql-integration.php: in genuine disposable WordPress/MariaDB, register a synthetic ACTIVE missing-callback connector with an actual PUBLIC indexed row and prove anonymous File26 search suppresses the row.
3. tests/review-round-104-regressions.php: updated from old strict-coercion string check to stricter exact typed authorization and no-fallback invariant.
4. qa/run-tests.sh: new standalone ACL regression runs in checkout and clean-extracted deterministic ZIP.

## Remaining acceptance
This does NOT prove File03/05/06/07/08/10/11/12/15/18/21 live can_view contracts or action-time links, nor File00 session consent, File12 Book Pack rights, File10 safety, File21 owner outbox, File24 independent reviewer, staging restore, production latency or deployed DB migration parity.
Source CI on the exact final PR35 HEAD is mandatory. No source-only fix can be marked Live resolved.

## Five fields
Repository main 1b064c6ea9f81195611ed001d5457e8ced65460a; PR35 SHA recheck.
Deployed Version UNVERIFIED. DB Version UNVERIFIED. Migration State UNVERIFIED. Live Verification Status NOT VERIFIED. Verified Live fixes 0.
