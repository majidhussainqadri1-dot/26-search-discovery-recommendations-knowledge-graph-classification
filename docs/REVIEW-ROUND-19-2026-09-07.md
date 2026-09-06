# File 26 — Fresh Review Round 19 — Review Freeze and Correction Record

Date: 2026-09-07
Branch: `review/file26-fresh-20-round-2026-09-06`

## Governing method

The review was completed before correction began. Findings were frozen as one Round 19 ledger, then corrected as a batch. Repository evidence is not a claim about staging or live deployment.

## Frozen Round 19 defect ledger

1. The preceding exact-head QA run was not green: PHP syntax failed in `tests/review-round-27-graph-owner-and-traversal-truth.php` because a double-quoted static-search needle attempted invalid `$input['owner_file']` interpolation.
2. Authenticated REST permission callbacks could accept a WordPress login without requiring current valid, non-suspended File 00 membership assertions.
3. Advanced-search continuation context did not bind its signed cursor to the current audience/eligibility fingerprint.
4. Advanced-search metadata DB failure could collapse into an empty metadata map instead of an explicit failure.
5. Saved-query mutations used unlocked read-modify-write user-meta operations, could lose concurrent updates, did not verify persistence, and existing-record updates did not require expected-version evidence.
6. Saved-query deletion and expiry cleanup could report/assume success without verifying persistent state.
7. Explicit content-gap aggregation used an unlocked read-modify-write option and did not verify persistence.
8. Editorial-radar aggregate DB failure was not explicitly surfaced.
9. Zero-result related-topic DB failure was indistinguishable from a genuine empty related-topic set.
10. Index-freshness auxiliary DB read failure was indistinguishable from ordinary unknown source freshness.
11. Central aggregate metric writes did not expose persistence failure.
12. Central settings migration could advance its migration pointer without verified settings persistence.
13. Content-gap retention did not serialize or verify its write.
14. Saved-query privacy erasure could claim completion without verifying deletion.
15. Central-plan operational failures were not represented in the health snapshot.

## Corrections applied

- Added a shared `Security::valid_authenticated_member()` gate and bound REST/Central authenticated routes to it.
- Bound advanced cursors to a deterministic audience fingerprint and made metadata DB failure explicit.
- Added serialized named locks, persistence readback verification and expected-version enforcement for saved-query updates.
- Added serialized/verified content-gap mutation and retention.
- Added explicit editorial-radar, zero-result, freshness and telemetry failure truth.
- Made migration-pointer advancement contingent on verified settings persistence.
- Made privacy erasure verify actual user-meta removal.
- Added central-plan failure ledger visibility to health.
- Repaired the pre-existing graph regression syntax defect.
- Added `tests/review-round-19-central-plan-state-truth.php` as a permanent regression gate.

## Status law

This record proves only that Round 19 review was frozen and its repository corrections were written. Automated-QA Green requires a successful GitHub Actions run at the exact post-correction HEAD. Staging-Accepted, Live-Deployed and Operational remain separate external states.
