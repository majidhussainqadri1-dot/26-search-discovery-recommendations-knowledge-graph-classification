# File 26 — Hostinger-Equivalent Staging Acceptance 1.1.0

## Environment evidence

Record WordPress, PHP, database, object cache, locale/timezone, active theme, File 00/20/24/25 versions, all owner-module versions, package checksum and exact commit.

## Installation and upgrade

- fresh install succeeds with public gates closed;
- upgrade from 1.0.0 succeeds without destructive table changes;
- ranking-appeal schema is created idempotently;
- dedicated institutional roles exist and administrator does not inherit operational powers;
- reinstall over retained data succeeds;
- uninstall/reinstall preserves data by default;
- explicit destructive uninstall is tested only on a disposable clone.

## Distinct-role journeys

Use separate accounts for configuration manager, search operator, taxonomy curator, ranking approver A, ranking approver B and auditor. Verify no role can perform another role’s sensitive action by label, crafted REST request or direct form submission.

## Connector journeys

For Files 03, 05, 06, 07, 08, 10, 11, 12, 15, 18 and 21:

1. missing connector is reported missing;
2. first registration is proposed even if code asks for active;
3. contract-tested and shadow lanes do not appear in public results;
4. approved remains non-public;
5. active appears only after governed transition;
6. unhealthy active connector produces partial/degraded state;
7. owner/contract change returns to proposed;
8. deletion/restriction propagates within the approved SLO.

## Search relevance matrix

Test Urdu, English, Arabic-script variants, Roman Urdu, exact quoted phrase, one-character typo, ambiguous Founder/domain query, zero-result query and medical-safety query. Record expected top results and false-positive/false-negative review.

## Corpus and performance

- representative corpus from all required domains;
- result correctness beyond the former 500-document boundary;
- truthful scan-limit partial behavior;
- p50/p75/p95/p99 latency and database query counts;
- concurrent anonymous and authenticated load;
- cache hit/miss and invalidation;
- no unbounded graph or facet query.

## Privacy and security

- private, pending, rejected, suspended, deleted and restricted records never appear in search, suggest, facets, graph or deep links;
- File 00 missing/unknown contract fails closed for private retrieval;
- minor/guardian restrictions are honored;
- raw query text is absent from ordinary logs/metrics;
- recommendation consent is explicit and reversible;
- guest session topics are not persisted;
- appeal reason/evidence is not exposed publicly;
- CSRF, IDOR, cursor tampering, replay, rate-limit and stale-authorization tests pass.

## Ranking governance

- active search policy materially changes results;
- invalid policy is rejected or safely disclosed;
- rollback restores the previous policy and keeps File 26 available;
- distinct second approver is required;
- donation/payment/paid promotion/followers/Founder preference do not change ranking;
- global doctor tiers and contextual views preserve the global rank;
- appeal submit/review/conflict/concurrency/privacy/retention journeys pass.

## UI/accessibility

At 320, 360, 390, 768, 1024, 1366, 1440 and 1920 CSS pixels:

- no horizontal page overflow;
- RTL/LTR/mixed text order correct;
- all controls keyboard-operable;
- visible focus and 44×44 targets;
- screen-reader labels and status announcements;
- 200%/400% zoom completion;
- reduced motion and forced colors;
- filters/facets, partial warning, why-this, consent and undo controls work;
- File 20 owns shell placement and File 25 owns result/doctor card visuals.

## Recovery

- backup restore verified;
- migration dry run repeated idempotently;
- policy rollback rehearsed;
- connector suspension/rebuild/reactivation rehearsed;
- plugin rollback package rehearsed;
- no stale authorization or deletion reversal after restore.

## Acceptance law

Every failed item is corrected and retested before Founder acceptance. Passing this checklist changes status only to **Staging-Accepted**; it does not by itself mean Live-Deployed or Operational.
