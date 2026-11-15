# File26 V12.3 FR004/FR006: every-write canonical owner freshness (2026-11-15)

## Exact source truth and governing requirements

The user-selected `Sabri-Website-Master-Plan-V12.3.docx` File26-FR-002 requires canonical source object/version and out-of-order convergence; FR-003 requires index, request and click owner visibility; FR-004 requires replay-safe owner event consumption and retry/dead letters; FR-005 mandates shadow/rollback; FR-006 requires bounded tombstones and downstream revocation propagation. Source-only branch is NOT a claim of installed Hostinger runtime.

Base: File26 PR #48 exact `67a4468a4d64b121f4bb0253871c4710f7bbb2ca`, which had 40/40 repeated complete exact-source QA and 3 disposable WordPress/MariaDB versions green, but **only fetched current source on cold inserts**.

## Proven source-side correction

An already-indexed v1 record may receive delayed published owner v2 even though the true owner has already moved to v3 (or deleted/restricted the object). Before this PR, File26 local ordering correctly recognizes v2 > v1 and writes v2 to live or shadow WITHOUT checking the present owner. This creates a privacy/freshness gap even before finite tombstone expiry.

The PR keeps serving index order and ownership but adds mandatory `Owner_Freshness::attest_cold_document()` for **every** public/indexable serving write and Shadow Index staging, including existing keys. It validates:
- exact current native `fetch_object(entity_type, object_id)`;
- current typed version/sequence (when owner supplies sequence), connector/domain/object/type, state/visibility, locale, title, safe URL;
- index-safe abstract and *normalized search body*, author key, country, location, availability and safety class;
- no acceptable owner response on missing/deleted/restricted object, malformed metadata or outage. All such cases reject the proposed write with typed errors. No automatic ownership transfer or override.

### Reproducible tests
- Synthetic per-field owner freshness regression includes same-version stale body, location and safety labels.
- Actual disposable WordPress/MariaDB R3: publish v1; owner now v3; deliver old v2; verify typed rejection and intact v1; then verify bounded tombstone v2 expiry and v1 denial, real v3 reapproval, and native File21 physical deletion after tombstone TTL.
- Existing guest search, File07 doctor ACL, File24 original ranking assurance, File03 source fail closed, full Shadow Index cutover and recovery must stay green on each exact final SHA.
- File03 native owner missing-schema false-done defect surfaced by the cross-repo SQL matrix and has a separate unmerged Draft PR #47, which this PR pins in disposable SQL.

### Non-claims and remaining release gates

Canonical owner read and File26 derivative SQL mutation occur in **different** transaction contexts. A source change can occur between the owner's attestation and File26 COMMIT; mandatory durable native owner outbox/change feed + consumer ACK, gap detection, version-floor protection, cache/CDN and recommendation/graph purging, staged recoveries and all 11 certified owners remain open. Also File03 File00 display-name changes need a new owner version/sequence and exact reindex workflow; File12 PDF/book_pack governance must be decided and implemented, rather than marked done.

All PRs here remain Draft and unmerged; no verified installed Source SHA, Hostinger DB schema/options/migration, no staged backup/restore, no authorized deployment, no live re-test. 40 automated QA repeats are **not** 2 independent human reviews.

Required five-field incident truth: `Repository main` = `1b064c6ea9f81195611ed001d5457e8ced65460a`; `Deployed Version` = UNKNOWN; `DB Version` = UNKNOWN; `Migration State` = UNKNOWN; `Live Verification Status` = NOT VERIFIED. Zero fixes proven deployed.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
