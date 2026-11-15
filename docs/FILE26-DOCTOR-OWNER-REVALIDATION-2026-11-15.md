# File26 V12.3 — Native File07 owner revalidation for direct doctor ranking (2026-11-15)

## Exact SOURCE root cause — not a diagnosed production incident

File26 main `1b064c6ea9f81195611ed001d5457e8ced65460a`, `includes/class-file26-doctor-ranking.php::eligible_rows()` executes a raw indexed-document query restricted to active connector DB rows, `owner_file='File 07'`, `visibility='public'` and published states. Unlike ordinary File26 `Search::run()`, it did **not** invoke the connector's current `can_view` authorization for each row.

A doctor could become retired, legacy, suspended or no longer verified **at the original owner** while a stale indexed result remained. Even if File07 later withholds official badges through independent File24 fairness assurance, the File26 ranking provider could return the old public doctor record. This is a reproducible source authorization gap, **not** evidence that a real patient or doctor has been exposed in Live.

## Proposed isolated correction

1. Pass the existing `Connectors` dependency to `Doctor_Ranking` from the canonical `Plugin` constructor; no new owner table or schema.
2. Select all canonical connector and object/version fields needed by native owner `can_view`.
3. Check current membership audience, active File07 manifest, non-empty real owner `can_view` callback, and the current native owner decision for **each** candidate. Never use File26's generic public-visibility fallback for the doctor ranking bypass.
4. A denied/stale doctor, callback failure, invalid stored projection, missing audience or SQL read error causes a typed fail-closed result for the *entire* ranking view. No falsely complete Top10/100/1000 is published.
5. `tests/file26-20261115-doctor-owner-eligibility-regression.php` executes positive and negative paths with deterministic in-memory WordPress/SQL doubles. Run the test on both exact checkout and deterministic clean-extracted ZIP; no Live DB writes.
6. Preserve File07 canonical source/verification and File24 fairness ownership. This does **not** bypass the File24 gate or authorize release.

## Remaining acceptance

- Complete native File07 connector's real latest `object_version`, File03 verified lifecycle, File00 membership, File09 credentials and File08 availability evidence.
- WordPress/MariaDB multi-version negative test with actual File07 and File26 plugins, not only mocks; doctor active→retired→legacy and owner read outage.
- File24 current fairness pass/block/unverified integration; monthly recomputation and appeal consistency.
- Real Hostinger Staging, accessibility, performance bounds for owner checks, exact deployed source/schema/options and migration reality, rollback and Founder approval.
- Other release blockers: FR005 shadow index #20; File12 owner mismatch #23; P0 durable deletion replay #37; 11-owner acceptance #32; authenticated Live access #31.

## Status

Source-only corrective Draft. Repository baseline `1b064c6ea9f81195611ed001d5457e8ced65460a`. Deployed Version UNVERIFIED; DB Version UNVERIFIED; Migration State UNVERIFIED; Live Verification NOT VERIFIED. Verified Live fixes 0.
