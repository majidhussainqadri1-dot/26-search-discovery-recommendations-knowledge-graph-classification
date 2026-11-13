# File26 disposable native-owner consent snapshot integrity — frozen 2026-11-13

**Exact File26 baseline:** PR #27 `ac0c8b597d5c0096ac7fcff006a39d7d3abd4d3e`; **File10 proposed source fix:** Draft PR #29 `3120b7a53f86ef44caa34332c854323c4f68517d`.

## Defect source and scope
File10 proposed File26 connector `list_batch()` historically silently excluded public video when R109 consent SQL failed and could advance its owner snapshot cursor. Source-review evidence frozen at File10 `reviews/F10-F26-SNAPSHOT-CONSENT-READ-20261113-FROZEN.md`. File10 newly returns typed WP_Error on authority uncertainty. This is NOT evidence of a Live bug.

## Disposable real MariaDB acceptance
Within existing `FILE26_CI_EPHEMERAL_DB=yes`, `file26-ci.local` test guard:
- Seed real File10 video/public rows.
- Temporarily rename File10 `consent_links` table, run **native File10 `list_batch('',50)`**, restore original table in `finally` before assertions.
- Require WP_Error for the whole source batch; a successful `items=[]/done=true` is unacceptable.
- Verify restored SQL owner snapshot succeeds with public video present; no silent pagination loss.
- Keep existing direct fetch, Safe Mode, privacy, kill-switch, real consent DB errors, and File26 shadow swap tests.
- Only repin exact File10 checkout to new candidate; no runtime File26 code changes.

## Remaining
This covers disposable WP 7.0.1/7.1.4 SQL execution, not actual Hostinger/Staging/Live access, File26 production indexing, event delivery ACK, CDN purging, rights/consent restoration, Founder approval. Issue #13/#22/#26 remain.