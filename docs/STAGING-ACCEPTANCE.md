# Hostinger Staging Acceptance

Release is not staging-accepted until all items pass and the evidence is attached to the exact package/build being evaluated:

- Repository branch and exact commit SHA recorded.
- Exact installable package filename and SHA-256 recorded; package must come from the same exact-head QA run.
- Staging deployed plugin/runtime version and, where possible, deployed artifact checksum recorded independently from GitHub.
- Staging File 26 database schema version, ranking-appeals schema version and migration state recorded from the deployed database.
- Repository/package/deployed-file parity explicitly checked; any mismatch triggers a deployment-parity audit before defect diagnosis continues.
- Verified database/files/config backup and restore rehearsal.
- Fresh install, upgrade, deactivate/reactivate and non-destructive uninstall.
- Supported WordPress/PHP/MySQL/LiteSpeed behavior verified on the actual staging stack; do not infer this from CI runtime versions.
- Real File 00 assertions; File 20 route placement; File 25 result cards; File 24 assurance.
- Approved connectors for every launched domain; unknown/incompatible connectors fail closed.
- Urdu/English/Roman Urdu golden queries, spelling, transliteration and zero-result cases.
- Guest/member/minor/doctor/Founder/operator/curator/auditor allow/deny journeys.
- Private/pending/suspended/deleted/retracted objects never leak via result, facet, suggest, graph, cache or deep link.
- Tombstone propagation, queue retry/dead-letter, shadow rebuild and rollback.
- Donation/payment/favoritism do not affect organic rank; doctor tiers are explainable and auditable.
- Keyboard, screen reader, 200/400% zoom, RTL/LTR, reduced motion, contrast and 320–1920px views.
- Measured latency/load budgets and provider outage/degraded recovery.
- Revocation/cache-purge integration tested before enabling any File 26 public/object cache opt-in; otherwise responses remain no-store/cache-disabled.
- Founder approval with exact package checksum and observation window.

## Acceptance record

Record these fields separately; one field is never proof of another:

- **Repository HEAD:**
- **QA run ID / result:**
- **Package SHA-256:**
- **Deployed Version:**
- **Deployed artifact checksum:**
- **DB Version:**
- **Migration State:**
- **Deployment Parity:**
- **Staging Verification Status:**
- **Live Verification Status:** not applicable until independently tested on live.

A green CI run or installable ZIP does not itself mean staging accepted, live deployed or operational.
