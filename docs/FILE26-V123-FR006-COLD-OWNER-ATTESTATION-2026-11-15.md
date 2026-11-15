# File26 V12.3 — FR006 Cold Owner Freshness Gate (2026-11-15)

## Governing original Master Plan evidence

`Sabri-Website-Master-Plan-V12.3.docx`, File26-FR-002/003/004/005/006 (plan paragraphs 2128–2132): each document has a canonical source/version; replay-safe change feed; bounded tombstones; full/shadow index must converge to the current owner, without resurrecting deleted/restricted source. `F26-CEN-01` requires owner rights and deletion propagation to remain authoritative.

## Exact observed SOURCE risk

Baseline is the separate, unmerged candidate PR #45 exact source HEAD `680dcc0e402f6f9d8bbb526e610cdc7927f1e747`, **not** proven installed on Hostinger.

- `Indexer::upsert` checks the *current* live document and the current tombstone. After `retention()` deletes a v2 tombstone at its finite TTL, neither row exists.
- A stale owner v1 event can then pass the previous absence checks and create a new serving document + node.
- The independent FR005 `Shadow_Index::stage` similarly checked live serving/tombstone but did not require an owner read for a cold public source, allowing a cold stale snapshot candidate to be staged.
- This is a reproducible source-level potential resurrection. No actual production record exposure has been established.

## Proposed minimal, conservative correction — no indefinite deleted identifiers

- Add `Owner_Freshness::attest_cold_document()`. New cold public records (whether a *first* publication or a *post-expiry replay*) cannot be distinguished from absent File26 tables. Always require a current read-only **canonical owner** `fetch_object(entity_type, object_id)`.
- Owner response must be a typed document, exact canonical owner connector/domain/object/URL/type/state/visibility/locale/title, exact positive `object_version`, exact non-negative owner `source_event_sequence` when supplied, and a matching public-safe excerpt when supplied.
- A missing callback, unknown/deleted owner, owner outage, superseding owner version, unchanged version but changed title/URL/excerpt or malformed sequence yields a typed error, and no File26 serving write.
- Invoke the same proof on shadow-index staging when no current serving document exists and the staged record is public/indexable. Preserve the normal tombstone-required path for actual deletion/restriction.
- Retain the original *bounded* tombstone TTL; do **not** change it to indefinite raw IDs, hashes or pseudonyms. No new schema/migration in this PR.
- Add a source + clean ZIP no-DB test; add an actual disposable WordPress/MariaDB R1 (publish v1 → tombstone v2 → expire & purge tombstone → replay stale v1 denied) and R2 (owner truly approves v3 → accept only v3) test.

## Important constraints and remaining P0

This is **only a stronger ingestion freshness gate**. It does not implement a durable version-floor checkpoint or an atomic owner outbox+ACK. Owner changes *after* attestation and *before* final write can still race. A malicious, incomplete or non-monotonic native owner could return an old projection; this code does not manufacture canonical trust. The source owner must supply `fetch_object`; older fixture providers and real owners without it are intentionally **blocked**, not presumed safe. The 11 canonical owners must explicitly accept this contract or fail closed.

The complete P0 Issue #37 still requires owner event durability, kill-switch/cache/graph/semantic paths, service restart after backup restore, 90/180-day boundaries, dual-origin cold scans, real cross-owner SQL, privacy retention approval and Staging acceptance. Do **not** close #37 based on this PR.

No production deployment, DB migration, schema mutation, or Live parity claim. A PR/CI green may only prove the exact candidate source. Required before any merge: exact-SHA independent source and package QA, real MariaDB matrix, review existing owners, 2 independent human reviews, Hostinger installed-artifact+DB+schema+wp_options/migration freeze, Founder backup/restore and authorization.

Repository main HEAD: `1b064c6ea9f81195611ed001d5457e8ced65460a`.
Candidate base PR45 HEAD: `680dcc0e402f6f9d8bbb526e610cdc7927f1e747`.
Deployed Version: UNVERIFIED.
DB Version: UNVERIFIED.
Migration State: UNVERIFIED.
Live Verification Status: NOT VERIFIED.
Live-verified fixes: 0.
