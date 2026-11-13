# File26 disposable matrix: combined File10 source exact pin, FROZEN before change

- File26 existing Draft PR #26 exact baseline: `3ca1d7f130b685848505ca307517641576fc5c0e`.
- File10 newly integrated **source-only** Draft PR #27 exact HEAD: `2da1342a03280c77c93ca3f95b228ecfa0b9124d`, combining File26 owner search guard/dormant event proposals and independent R109 patient-consent cursor fix.
- Older File26 workflow pinned File10 `2e04a6ae8a6dafb95fb90cfa3962f7c4dd8625d9`, which did **not** include R109's failure retry fix. A green run on that SHA cannot prove the newly unified source combination.

Only change the immutable checkout pin to `2da1342a03280c77c93ca3f95b228ecfa0b9124d`; retain existing disposable MariaDB WordPress 7.0.1/7.1.4 tests including File10 owner event upsert/tombstone, source DB outage and consent DB outage. No production File26 runtime changes.

Release boundary: Source+CI only; WordPress/Jetpack disconnected, actual deployed plugin/DB/schema/migration and CF04/File26 durable consumer tombstones not verified. File26 connector must remain proposed and dormant; Issue #13/#22/#26 remain external gates.
