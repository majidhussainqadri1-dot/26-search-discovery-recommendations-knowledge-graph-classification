# File 26 — Release Notes 1.1.0 Corrective Candidate

## Release identity

- Plugin version: `1.1.0`
- Contract version: `1.1`
- Schema baseline: `1.0.0` plus ranking-appeal schema `1.0.0`
- Branch: `codex/file-26-three-plan-corrective-1.1.0`
- Status: coded corrective candidate; final exact-head CI and staging acceptance remain separate.

## Principal corrections

1. Active ranking policies now alter runtime behavior.
2. Ranking rollback restores the previous active policy under distinct dual approval.
3. New/changed connectors always begin as proposed.
4. Only active connectors serve search/autocomplete.
5. Search supports exact phrase, typo tolerance and bounded Roman-Urdu transliteration.
6. Candidate scanning is chunked and truthfully reports bounded partial results.
7. Approved classification IDs control topic retrieval.
8. Active public graph edges supply a bounded relationship signal.
9. Public search includes full filters, facets and sorting.
10. Recommendation consent and all user controls are operational in the public experience.
11. Guest/session discovery does not create a hidden profile.
12. File 26 institutional duties are separated into dedicated roles.
13. Doctor ranking is versioned, explainable, contextual and appealable.
14. Doctor appeal data has export, erasure and retention laws.
15. Required owner integrations and File 00/20/24/25 evidence block activation until real acceptance exists.
16. Uninstall removes jobs/roles safely and preserves data unless destructive purge is explicitly selected.

## Compatibility

- WordPress minimum: 6.0
- Tested target: WordPress 7.0.1
- PHP minimum: 7.4
- CI matrix: PHP 7.4 and PHP 8.3
- Public API namespace remains `sabri-search/v1` with contract header `1.1`.

## Non-claims

This release note does not assert Hostinger staging acceptance, production deployment or operational readiness.
