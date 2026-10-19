# File 26 — Sequential Review Ledger R62–R81 — 2026-08-29

## Governing method

Each round followed the same mandatory order:

1. complete the full review scope;
2. do not patch during the unfinished review;
3. freeze the round defect ledger;
4. correct all confirmed defects from that round;
5. add/retain regression protection where applicable;
6. verify the corrected exact head before starting the next round.

Repository evidence is separate from staging/live evidence.

## Ten-round checkpoint — R62–R71

- **R62 — Clean.** No new confirmed repository defect in its reviewed scope.
- **R63 — Defects.** Index upsert, tombstone and reconciliation transaction-start paths were hardened to fail closed.
- **R64 — Defects.** Persisted connector manifest fields were allowlisted and health detail was bounded/redacted against credential-like content.
- **R65 — Defects.** File 00 membership/guardian/minor/suspension assertions were typed and malformed values moved to restrictive states; claim lists/text were normalized and bounded.
- **R66 — Defects.** Recommendation feedback, consent, reset and opt-out mutation transaction-start failures were made fail closed.
- **R67 — Defects.** Taxonomy merge/split transaction-start failures were made fail closed.
- **R68 — Defects.** Graph source-owner verification, evidence URL default-deny behavior and activation-time owner revalidation were hardened.
- **R69 — Defects.** Ranking-policy activation and rollback transaction-start failures were made fail closed.
- **R70 — Defects.** Personalization consent switched from PHP truthiness to strict boolean parsing.
- **R71 — Defects.** Schema markers were bound to physical table reality; appeal schema marker/table parity and appeal-retention transaction-start handling were hardened.

**Checkpoint result:** defect rounds R63–R71; clean round R62.

## R72–R81

- **R72 — Defects.** Doctor ranking now fails closed on transaction/read failure and clears stale derivative rank metadata before rebuilding the verified cohort.
- **R73 — Defects.** Doctor appeals were aligned with `doctor_directory_projection` ranking identity and restricted to the active File 07 production lane.
- **R74 — Defects.** Future account-owned metadata—research trails, saved alerts, server history/opt-in and discovery controls—was added to WordPress privacy export/erasure with deletion verification.
- **R75 — Defects.** Central-plan migration failure now blocks activation; saved-query/content-gap consent and concurrency paths were hardened; existing saved-query updates require version awareness.
- **R76 — Defects.** Saved-alert and history controls use strict booleans; stored opt-in cannot be misread through PHP truthiness; server-history save/clear mutations are serialized.
- **R77 — Defects.** Discovery controls use strict booleans, invalid breadth fails closed, stored controls are normalized, conflicting File07/File08 geo constraints fail closed and source/author diversification is independently bounded. A post-fix static-test formatting defect was corrected before closing the round.
- **R78 — Defects.** Query-planner execution uses explicit boolean semantics; cross-language provider disclosure is bypassed for sensitive/clinical-intent queries; provider availability reflects usable safe variants. A regression-test interpolation defect was corrected before closing the round.
- **R79 — Defects.** Find-similar requires sanitized seed provenance; segment/private-vault owner references survive sanitization; external evidence validates sanitized provenance/rights and retrieval time. Regression-test interpolation defects were corrected before closing the round.
- **R80 — Defects.** Research/historical snapshot IDs must remain non-empty after sanitization; graph paths bind requested endpoints, enforce maximum six-edge depth and retain provenance after sanitization.
- **R81 — Defects.** Final release-evidence review found stale v1.2 QA reporting, a stale tracked repository manifest, non-explicit current-round regression presence gates and a missing R62–R81 release ledger. These were corrected in the R81 correction batch; exact-head CI/package evidence remains the authority for final green status.

## Final round classification

- **Clean rounds:** R62
- **Defect rounds:** R63, R64, R65, R66, R67, R68, R69, R70, R71, R72, R73, R74, R75, R76, R77, R78, R79, R80, R81
- **Total:** 20 rounds = 19 defect rounds + 1 clean round

## Release-truth boundary

This ledger proves only the repository review process and its committed corrections. It does not prove deployment, database migration, staging acceptance or live operation.

Repository `main`, this review branch, staging and live are separate realities until exact parity is demonstrated.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
