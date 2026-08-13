# File 26 v1.2.0 — Post-Coding Review Round 1

Date: 2026-08-13 (Asia/Karachi)
Baseline reviewed: new governing-plan v1.2.0 candidate after initial implementation
Method: complete review first, then one corrective batch, then retest.

## Review scope

- New central governing plan requirements CV-164 through CV-175 and F26-CEN-01/F26-CEN-02.
- File 26 FR-001 through FR-036 preservation.
- Query, advanced search, ranking, recommendations, doctor ranking, privacy, safety, deletion/freshness, cross-owner boundaries, package/CI and PHP 7.4 compatibility.
- Failure/degraded paths, privacy defaults and evidence-truthfulness.

## Defects found in the completed review

1. Public doctor ranking carried an internal author reference in its public scored object.
2. Initial advanced-search pagination could terminate inside a base page before a reliable continuation cursor was captured.
3. The `source` parameter had been conflated with connector-lane filtering instead of preserving source-vs-connector semantics.
4. Advanced access filtering could assume `public` when owner access metadata was absent.
5. Sensitive saved-query text could be stored as plaintext after confirmation.
6. Emergency-resource acceptance checked a boolean verification flag but not verification freshness/expiry.
7. Index freshness did not mark missing database evidence as unknown for every result and timestamp parsing could mishandle already-zoned timestamps.
8. F26-CEN-01 integrity output did not explicitly expose language/deletion-state evidence.
9. Content-gap and saved-query retention values were partially hardcoded instead of consistently using governed settings.
10. Zero-result “ask expert” had an owner label but no validated owner-supplied destination contract.

## Corrective batch applied after review completion

- Removed internal author reference from public doctor-ranking output and its retrieval SQL.
- Rebuilt advanced pagination as a signed, query-bound advanced offset over a bounded deterministic scan, with truthful partial state.
- Separated `connector` from owner/source filtering and added source metadata matching.
- Hydrated owner-index metadata for advanced filters; unknown access now fails closed.
- Added encryption-provider contract for sensitive saved queries; plaintext persistence is prohibited and absence of an approved encryption provider fails closed.
- Added current-verification and expiry checks for owner-supplied emergency resources; external resource URLs remain allowlisted by Security.
- Added truthful unknown freshness, timezone-safe timestamp parsing, language/deletion evidence, indexed visibility/state, stale badges and owner revalidation requirements.
- Bound all new retention periods to governed settings with conservative limits.
- Added owner-supplied, safety-validated zero-result help destination.
- Expanded central-plan regression tests to lock all above corrections.

## Retest state

A fresh exact-head GitHub Actions run is required after this document commit. This review does not claim staging/live acceptance.

## Truth boundary

Repository corrective evidence only. Deployed package, deployed DB/schema, migration state, Hostinger staging and live verification remain separate and unverified here.
