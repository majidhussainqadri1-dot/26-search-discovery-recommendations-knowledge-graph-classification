# Disposable File26 x File10 event-proposal acceptance — frozen before test edits (2026-11-13)

Exact File26 fixture baseline: `a6c9934ebc661bf1782f850ed62fbeca4362858b`, Draft PR #24. Current native File10 source candidate: Draft PR #23 exact SHA `696aa862a67701a68cf076cf734dfb09ed40452b`, descended from modern PR #21.

## Explicit narrow scope
Extend only File26's **disposable** MariaDB/WordPress integration fixture and checkout pin to verify the proposed owner event-to-document/tombstone mapping using actual File10 tables. No File26 source runtime code or production activation; the owner adapter is still dormant in File10 and registered locally by the test after `FILE26_CI_EPHEMERAL_DB=yes` guard.

## Requirements
- An owner-created public video with current version 3 yields a source-only `upsert` proposal with the real File10 public ID and version.
- The same video made private/version 4 yields a content-free, owner-versioned `tombstone` proposal. Existing previous File26 document click permission must already fail.
- A live event emergency kill/version 4 yields a tombstone proposal.
- A missing authoritative source row (no verified deletion version) must not be invented as a tombstone.
- Numeric internal owner SQL primary keys must never appear in public projection output.
- File26 connector remains `proposed`, not index-eligible. No event bus, shadow cutover, consumer ack, deletion/restore completion or production claim.

## Acceptance boundary
Test matrix WordPress 7.0.1 and 7.1.4 on ephemeral DB; PHP 8.3 source QA. This is NOT live deployed-state freeze or final File26 event delivery/reconciliation acceptance. Issue #13 (Live WP access) and File10 ruleset Issue #22 remain open.