# File26 V12.3 — FR002 Owner-Version Overflow and Anti-Resurrection Source Review
**Date:** 2026-11-14. **Evidence:** exact GitHub source and isolated disposable WordPress/MariaDB test only. **Not** a Hostinger Live incident.

## Source root D26-VERSION-013 — P1 integrity
Indexer::strict_integer accepted any digit-only string and cast it directly to signed PHP integer. Distinct decimal input values above PHP_INT_MAX can saturate to the same integer, losing owner version/sequence identity. File26 documents/tombstones use MySQL unsigned BIGINT, but File26 version-ordering and security checks use signed PHP integers. A saturated version is not a trustworthy monotonic owner event proof.

## Narrow source fix, Draft PR #36
- Normalize leading zeros in a decimal source version without altering its numerical value.
- Compare canonical decimal string length and lexical order to PHP_INT_MAX **before** casting.
- Reject unsupported values above the PHP signed integer range with the existing typed invalid-object-version or invalid-source-event-sequence error.
- Preserve valid positive versions, supported zero sequences, and existing tombstone rules. Do not pretend to support arbitrary unsigned 64-bit PHP arithmetic.
- No deployment, schema migration, data deletion, source-owner bypass or production update.

## Actual verification
- Pure PHP test tests/file26-v12-3-version-overflow.php includes PHP_INT_MAX, overflow-by-one, 18446744073709551615, huge numbers, leading zeros, negative/scientific/float input.
- Disposable WordPress/MariaDB test tests/file26-20261109-wordpress-mysql-integration.php verifies that overlong object versions and source event sequences return exact typed errors and do not create serving records.
- qa/run-tests.sh runs regression against original checkout and clean-extracted deterministic package.
- GitHub exact source SHA 89106fe4ad1b8b6983a195b7eb4c10ed2049b23c: File 26 QA run **43599978154 SUCCESS** and Disposable WordPress MariaDB Integration run **43599978155 SUCCESS**.
- Tests prove source/ephemeral behavior, **not** real File00/other owner WordPress version protocol, complete source lifecycle, Hostinger parity or Live operation.

## Outstanding governing gates
FR002 native owner-issued version monotonicity needs original owner package approval. FR004 outbox durable acknowledgement/gap detection, FR005 real full/partial owner inventory acceptance, File12 book_pack rights, 11 owner contracts, File00/09 identity, independent reviews, Hostinger Staging backup/restore, Founder approval, deployment parity and Live retest remain blocked/open. The no-patch-stacking rule is preserved by a distinct isolated PR with an explicit root and regression rather than changing prior PR34/35 history.

**Five release fields:** repository main 1b064c6ea9f81195611ed001d5457e8ced65460a, candidate PR36 source SHA above; Deployed Version **UNVERIFIED**; DB Version **UNVERIFIED**; Migration State **UNVERIFIED**; Live Verification **NOT VERIFIED**. Verified Live fixes **0**.
