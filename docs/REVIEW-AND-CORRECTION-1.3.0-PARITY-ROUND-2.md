# File 26 v1.3.0 — Current-Main Parity Fresh Adversarial Review and Correction Round 2

Date: 2026-08-13 (Asia/Karachi)  
Baseline: corrected Round-1 parity state on `codex/file-26-future24-main-parity-v1.3.0`, preserving the independently merged v1.2 twenty-round hardening.

## Review discipline

The complete fresh adversarial review was finished before its Round-2 correction batch. This pass focused on query disclosure, provider trust, advanced-filter truthfulness, concurrent preference writes, canonical-owner boundaries, graph/evidence integrity and external-network privacy. No correction was applied mid-review.

## Proven defects found

1. **Sensitive query disclosure to optional providers.** Grounded-answer, cross-language and semantic-reranking hooks could receive a query classified as sensitive even though the base File 26 privacy model minimizes such disclosures.
2. **Query Planner filter-execution mismatch.** Smart commands such as `author:`, `source:`, `after:` and `before:` were parsed, but execution used ordinary base search, which does not guarantee all advanced constraints.
3. **Search Modes filter-execution mismatch.** The same truthfulness defect existed when smart commands were combined with intent modes.
4. **Research special-filter candidate injection.** The research constraint provider could return a provider-created record instead of merely selecting/reordering the already eligible initial candidate set.
5. **Graph path structural-integrity gap.** Provider attestation/provenance were required, but an edge could still reference a node absent from the returned eligible node set or omit a usable typed relation after sanitization.
6. **Evidence-map source-identity gap.** A provenance-bearing relation could be returned without a valid platform source key or canonical source URL.
7. **Discovery-control concurrency gap.** Recommendation-transparency and discovery-breadth preference writes were not yet using the new compare-and-swap helper and could lose a concurrent sibling update.
8. **Geo/availability provider override gap.** An attested owner adapter needed a narrow allowed filter envelope and File 26 needed to reassert the requested doctor/clinic entity type after applying owner constraints.
9. **External evidence query-disclosure consent gap.** An approved external connector could receive a non-sensitive public query without a separate explicit per-request consent signal.
10. **External sensitive-query disclosure gap.** Sensitive queries needed a hard block before any external evidence provider call.
11. **Multimodal sanitized-empty-query broadening.** An owner-authorized adapter could return text that becomes empty after File 26 sanitization, causing an unintended broad base search.
12. **Research special-filter enforcement gap.** `evidence_level`/`edition` had to be not only owner-attested but restricted to selecting keys already in File 26's currently eligible result set.

## Correction batch

- Grounded-answer, cross-language semantic-variant and semantic-reranking providers are now bypassed for sensitive queries; File 26 falls back to local/extractive handling and reports `sensitive_provider_bypassed`.
- Query Planner and Search Modes now execute parsed advanced commands through the hardened v1.2 Central Plan advanced-search contract rather than ordinary base search.
- Research special constraints now require `owner_revalidated_for_request`; the provider can only select/reorder canonical keys already present in File 26's eligible candidate set. Provider-created result payloads are discarded.
- Graph Explorer validates that every sanitized edge has a typed relation and both endpoints occur in the returned eligible node set; otherwise the request fails with an integrity error.
- Evidence-map relations require provenance plus either a valid 64-character File 26 source key or a canonical source URL.
- Recommendation-transparency and discovery-breadth preference writes now use compare-and-swap conflict handling and return HTTP 409 instead of silently overwriting a concurrent update.
- Geo/availability owner providers now require `owner_revalidated_for_request`; only bounded availability/location/language/specialization filters are accepted, and File 26 reasserts the requested `doctor` or `clinic` entity type after merging constraints.
- External evidence now rejects sensitive queries, requires explicit `external_consent`, retains approved-connector + `approved_external_public` provider attestation, HTTPS/provenance/rights requirements and remains separated from organic ranking.
- Multimodal search now rejects an adapter result whose derived query becomes empty after sanitization, so it cannot accidentally broaden to an empty-query search.

## Post-correction verification target

The corrected state must pass, on the exact final branch head and again after merge to main:

- PHP syntax on all files under PHP 7.4 and PHP 8.3;
- JavaScript syntax;
- all existing behavioral, architecture, central-plan, corrective and twenty independent review-round regressions;
- dedicated Future Superset 24 regressions covering both parity rounds;
- forbidden primitive/ranking/sensitive-table scans;
- deterministic double-build, safe ZIP and source/package manifest parity.

Only an exact-head green CI result is accepted as Automated-QA evidence. This document makes no Hostinger staging, deployed DB/migration, live deployment or operational claim.
