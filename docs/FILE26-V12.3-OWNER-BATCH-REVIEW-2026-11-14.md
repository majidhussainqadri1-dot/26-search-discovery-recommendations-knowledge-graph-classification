# File26 V12.3 — FR-004/FR-005 Owner Snapshot Completion Source Audit
**2026-11-14. Scope:** SOURCE + isolated disposable WordPress/MariaDB tests; no deployed Live owner claim.

## Governing exact V12.3 requirements
- FR-004: reliable ingestion from owner outbox/feeds with idempotency, retry/backoff/dead-letter, sequence/checkpoint, gap detection and safe replay. Acceptance must prove delayed, duplicate, missing and reordered event reconciliation.
- FR-005: bounded full/partial reindex, separate blue/green generation, count/checksum validation, atomic promotion and safe rollback. An untrusted 'done' flag must never become proof of complete owner inventory.

## Three concrete source defects at inherited PR33
- D26-BATCH-DONE-008: Indexer::process_queue used !empty(batch['done']); nonempty string 'false' or integer 1 wrongly certified end-of-source. Thus job could reach completed before owner had attested the full enumeration. Severity P0 source correctness/release.
- D26-BATCH-LIMIT-009: list_batch requested 100 records but would iterate any array length without verifying bounded compliance. Severity P1 source pressure/cursor integrity.
- D26-BATCH-CURSOR-010: next_cursor silently truncated to 191 bytes and was sanitized without checking identity, permitting collision/replay/loss of opaque owner checkpoints. Severity P1 source integrity.

These are SOURCE-rooted defects and NOT evidence that an actual Hostinger index has been corrupted or that an unapproved owner supplied these invalid values.

## Bounded fix in standalone Draft PR #34
A pure validate_owner_batch() boundary runs before staging any document. It requires items array, exactly boolean done, a maximum requested page size, an array for each candidate owner document, and an opaque cursor that is scalar, at most 191 bytes and not silently modified by sanitization. A nonfinal batch requires an advancing cursor, including empty-but-advanced pages. Invalid batches create a typed job retry error, never promote or mutate serving tables. Existing Shadow_Index::stage lease fencing, source version/tombstone and atomic commit remain unchanged.

## Independent tests
- tests/file26-v12-3-owner-batch-contract.php: positive 1-page and final typed batches, nonboolean done ('false', '0', 1, null), missing done, >100 items, mixed item types, unchanged/missing/oversize/HTML/array cursor, bounded empty advance.
- tests/file26-20261020-queue-partial-failure-regression.php: updated to require typed validation before stage and failed records before completion, rather than accepting the obsolete !empty(done) expression.
- qa/run-tests.sh runs both checkout and clean-extracted deterministic ZIP.
- Parent PR #22 disposable MariaDB worker and shadow reindex remains a separate acceptance; exact current PR #34 HEAD workflow must pass.

## Why this does not close P03/P04/P05
A source owner can falsely claim that a typed done=true is complete or return empty after DB access failure. Full source completeness, canonical source-issued cursor/snapshot proof, source health, owner-outbox ACK, consent and tombstones need REAL native source + DB tests. Typed validation is necessary but not sufficient. No File12 book_pack or real File10 version/consent problem is claimed fixed.

## Gate status and deployment truth
- Source defect correction: CANDIDATE; code branch #34, stacked on unmerged #33 → #30 → #22.
- Final native Owner lifecycle, File00/File09 credentials, rights, full/partial shadow, backup/restore and Hostinger staging: OPEN.
- GitHub source QA and MariaDB workflow: recheck at FINAL HEAD.
- Main branch, installed plugin and Live site: not changed.
- Repository HEAD: main 1b064c6ea9f81195611ed001d5457e8ced65460a; draft PR34 head to recheck.
- Deployed Version: UNVERIFIED. DB Version: UNVERIFIED. Migration State: UNVERIFIED. Live Verification Status: NOT VERIFIED. Live fixes: 0.
