# File 26 Future24 — Independent Review Round 7

Baseline: `a6af03c82416306c310f276b0d2143bca326e9c0`, with Round 6 exact-head QA run `31706358285` green on PHP 7.4 and PHP 8.3.

The complete provider-attestation, snapshot-reproducibility and provider-freshness review was finished before correction.

## Proven defects

1. Research snapshot output could be labelled `snapshot_available` with a missing, malformed or future `created_at`, weakening the reproducibility evidence contract.
2. Research snapshot output did not require its returned `policy_version` to match the policy version of the search being snapshotted, so a provider could associate the snapshot with the wrong ranking/search policy.
3. External-evidence `retrieved_at` accepted any parseable timestamp, including an impossible future retrieval time, allowing misleading freshness/provenance evidence.

Synchronous owner/provider attestations were otherwise treated as request-local contracts and are not persisted or reused by File 26, so no replay-cache defect was recorded in this round.

Correction begins only after this completed review record.