# File26 disposable File10 canonical read-outage verification — FROZEN 2026-11-13

## Exact source
File26 baseline PR #25 head `c97d4ae6bb96f52ecc756bf56e1ece5543e11c5c`. File10 modern owner source fix in Draft PR #24 head `2e04a6ae8a6dafb95fb90cfa3962f7c4dd8625d9`.

## Scope
Within `FILE26_CI_EPHEMERAL_DB=yes`, host `file26-ci.local` **only**, extend the native WordPress/MariaDB integration test to prove:
1. A verified public video `fetch_object()` returns owner-versioned public DTO.
2. Truly missing public object returns `null`.
3. Current private object returns `null` and cannot be clicked through the File26 index.
4. File10 Safe Mode returns `WP_Error`, not false deletion.
5. Real native File10 table-read error (temporarily rename disposable `videos` table with guaranteed restore BEFORE assertion) returns typed `WP_Error`, not false deletion.
6. If safe, separately test a consent-table outage with deterministic restore and typed `WP_Error`.

**Never run these destructive fault injections against Staging/Live.** The GitHub workflow already isolates MariaDB/WordPress; test must refuse unless ephemeral environment token and host match. Keep source-only proposed File26 connector, do not activate indexing/transport, do not treat isolated tests as Production validation.

## Explicit limitation
Full File26 event delivery/ack, hard-delete version tombstones, rights invalidation fanout, CF04 purge/restore, Founder staging approval, exact deployed checksum/DB/migration and Live re-test remain unverified. Issue #13/22 remain blockers.
