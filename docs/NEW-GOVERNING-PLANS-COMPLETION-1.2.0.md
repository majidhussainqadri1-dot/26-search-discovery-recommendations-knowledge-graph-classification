# File 26 v1.2.0 — New Governing Plans Completion Record

Date: 2026-08-13 (Asia/Karachi)

## Governing basis

This coding batch uses the newly supplied **Three Central Plans Consolidated Governing Master Plan 2026** and the newly supplied **File 26 — Search, Discovery, Recommendations, Knowledge Graph and Content Classification — Four-Round Reviewed and Corrected Final** plan as the current requirements baseline.

The implementation preserves the one-canonical-owner rule. File 26 owns derivative search/recommendation/index/taxonomy/graph/ranking services; it does not take canonical write ownership from Files 03/05/06/07/08/10/11/12/15/18/21, the File 20 shell, the File 24 assurance layer or File 25 visual system.

## Repository baseline and parity decision

The corrective branch `codex/file-26-three-plan-corrective-1.1.0` at `6e2130491012e442bccb1adcd050661b8910fb0d` was selected as the source baseline because it contains the later corrective implementation for ranking-policy runtime control, connector production-lane isolation, exact-phrase/transliteration/typo handling, bounded corpus scans, taxonomy/classification integration, graph signals, recommendation controls, doctor ranking/appeals, separation of duties and owner-contract gates.

The older large Draft PR branch remains a separate historical branch and was not merged wholesale, avoiding regression to an earlier divergent implementation line.

## New-plan implementation mapping

| Requirement | v1.2.0 implementation |
|---|---|
| CV-164 Federated search | Existing required owner connectors + active-lane retrieval retained; integrity augmentation preserves canonical owner reference and click-time revalidation law. |
| CV-165 Facets/filters | Existing eligible facets retained; advanced endpoint adds source/verification/format/access field constraints without bypassing owner eligibility. |
| CV-166 Autocomplete/synonyms | Existing safe suggestions, bounded typo tolerance and Urdu/Roman-Urdu/Arabic handling retained. |
| CV-167 Advanced search | `/advanced-search`: exact phrase, author, source, dates, allowed fields, exclusions; account-owned saved queries with versioning and retention. |
| CV-168 Doctor ranking | Existing Top 10/100/1000/All Verified, contextual ranking, monthly recompute, policy versioning and appeals retained. |
| CV-169 Why this result | Existing explanation codes plus public `/ranking-constitution`; exact policy version and signal weights are inspectable. |
| CV-170 Symptom/remedy research | Consumer boundary only. Canonical knowledge/research remains with native knowledge/Radar owners; File 26 searches/projects approved owner records. |
| CV-171 Trend ingestion | Consumer boundary only. Trend truth remains File 15-owned; File 26 does not create a parallel trend store. |
| CV-172 Editorial radar | Aggregate metrics + explicitly submitted, non-sensitive, identity-free content-gap registry; no hidden user-history profiling. |
| CV-173 Zero-result recovery | spelling/transliteration candidates, related active topics, adjust-filter action, governed gap submission; no fabricated result. |
| CV-174 Search safety | emergency/harm/clinical classification with educational/diversion copy; local emergency details appear only through a verified owner filter. |
| CV-175 Index freshness/SLO | per-result index evidence; source-to-index lag is computed only when owner `source_updated_at` exists; otherwise status is honestly `unknown`. |
| F26-CEN-01 | result integrity states canonical owner reference, owner click revalidation, rights revalidation and freshness evidence; existing search already enforces state/visibility/connector eligibility and tombstones. |
| F26-CEN-02 | public/versioned ranking constitution, explanations, recommendation controls, free-tier parity and explicit prohibition of paid/donor/favoritism signals. |

## Central constitutional changes carried into code

- Single free tier: no search/ranking paywall and no donation signal.
- Sabri Green `#087A4E`: used only as a fallback for the File 25-owned visual token; File 26 does not create a second visual system.
- File 20 remains the sole global shell/navigation owner.
- File 25 remains the public visual/result-card owner.
- Sensitive commands remain server-side capability/state governed.
- No paid/sponsored organic result path is enabled.
- Saved queries are never silently reused as recommendation/ranking signals.
- Editorial gap submissions retain no user identity and reject sensitive queries.
- Unknown freshness remains `unknown`; no false-green status is invented.

## API additions

- `GET /wp-json/sabri-search/v1/advanced-search`
- `GET|POST /wp-json/sabri-search/v1/saved-queries`
- `DELETE /wp-json/sabri-search/v1/saved-queries/{uuid}`
- `GET /wp-json/sabri-search/v1/ranking-constitution`
- `POST /wp-json/sabri-search/v1/content-gap`
- `GET /wp-json/sabri-search/v1/admin/editorial-radar`
- `GET /wp-json/sabri-search/v1/admin/central-plan-status`

The existing `/search` REST response and `sabri_file26_search()` compatibility wrapper are augmented with safety, free-tier, ranking-constitution, zero-result and freshness contracts.

## Privacy and safety decisions

Saved searches are explicit user data, exportable/erasable through WordPress privacy tools. Sensitive saved queries require explicit confirmation and expire earlier. They are not recommendation signals.

Editorial radar does not receive raw hidden query history. It reads aggregate File 26 metrics and the explicit non-sensitive content-gap registry. The gap registry intentionally stores no user ID.

Emergency resource contact details are not hardcoded. A companion owner can supply a current verified resource through `sabri_file26_verified_emergency_resource`; otherwise File 26 gives generic qualified-care guidance and labels local details as unavailable.

## Secure delivery changes

GitHub Actions dependencies used by the File 26 QA workflow are pinned to exact commits. The package target and runtime version are v1.2.0. A dedicated `central-plan-contract-tests.php` regression gate locks the requirements above.

## Truthful completion boundary

This document records **repository coding scope**. It does not claim Hostinger staging acceptance, deployed database parity, real owner adapters in all companion repositories, browser/device/accessibility evidence, backup/restore rehearsal, live deployment, live re-test or operational SLO acceptance. Those remain separate evidence gates under the governing plans.
