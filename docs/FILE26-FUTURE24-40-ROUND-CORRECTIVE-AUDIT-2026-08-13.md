# File 26 — Future Search & Knowledge Intelligence 24
## Independent Sequential 40-Round Corrective Audit — 2026-08-13

Baseline candidate reviewed: `4ce34052c1d91dc446d40877cec144d46e6e0489`

Review branch: `review/file26-future24-40-round-2026-08-13`

Governing discipline: every numbered round is completed in full before any code correction begins. All defects proven in that completed round are then corrected as one batch, regression-tested/retested, and only then does the next numbered round begin. Repository evidence does not establish staging/live/operational status.

## Round 1 — full Future24 parity, authorization, privacy, provider-integrity and concurrency review

Review completed before correction.

Proven defects:

1. Sensitive queries were still sent to the optional grounded-answer provider despite the PR/plan privacy boundary.
2. Sensitive queries were still sent to the optional cross-language provider.
3. Sensitive queries were still sent to the optional semantic reranker.
4. External-evidence search did not require explicit per-request consent and did not reject sensitive queries before invoking external connector/provider hooks.
5. Research special-constraint providers could inject keys that were not in the native eligible File 26 result set instead of only selecting/reordering eligible candidates.
6. Relevance-lab candidate providers could inject non-baseline result keys, weakening experiment integrity.
7. Graph-path responses required provenance but did not verify node uniqueness/non-empty IDs or that every edge endpoint existed in the returned node set.
8. Evidence-map relations could survive without a valid canonical source key/owner/HTTPS source URL after sanitization.
9. Geo/availability provider output required no current owner attestation and could override the File 26-bound entity type.
10. Recommendation-transparency preference writes used unconditional user-meta updates, allowing lost-update races despite the Future CAS contract.
11. Discovery-breadth preference writes used unconditional user-meta updates, allowing lost-update races.
12. Search-history sync opt-in state used an unconditional user-meta update instead of conflict-aware persistence.
13. Saved-search alerts rejected sensitive query text but could still persist sensitive values through alert filter metadata.
14. Privacy erasure deleted saved-alert metadata without emitting the File 19 reconciliation/change hook for each removed alert.
15. A multimodal adapter query that was non-empty before sanitization but empty afterward could fall through to an unintended broad search.
16. A find-similar provider seed query could likewise sanitize to empty and fall through to broad search.
17. Research snapshot provider invocation could receive a sensitive research query even though optional provider disclosure is prohibited for sensitive searches.
18. Historical `as_of` validation checked only string shape and accepted impossible calendar/time values.

Correction batch: pending immediately after this completed review.
