# Changelog

All notable File 26 changes are recorded here. Runtime versions are separate from the File 26 plan version.

## 1.0.0 — Coded, Packaged and Automated-QA Candidate

### Added

- Complete runtime composition for public search, suggestions, facets, recommendations, feedback and topic projections.
- Administrator APIs for health, taxonomy, graph, classification, policy, evaluation, telemetry and export.
- Nine public-only owner-adapter contracts for Files 05, 06, 09, 10, 11, 12, 15, 18 and 21.
- Urdu/English Unicode normalization, approved transliteration, controlled synonyms and sensitive-query classification.
- Versioned ranking with relevance, authority, quality, freshness, popularity, safety explanations and diversity ceilings.
- Consented personalization, cold start, recommendation explanations and user controls.
- Controlled versioned taxonomy, merge preview, collision/cycle prevention and persistent storage.
- Independent reviewed classification workflow with high-impact separation of duties and appeals.
- Provenance-rich, typed, visibility-aware knowledge graph and persistent edge integrity verification.
- Ordered owner change-event ingestion, sequence ledger, idempotency, tombstones and purge evidence.
- Privacy-minimized telemetry, evaluation sets, rollbackable policies, audit records and scoped signed exports.
- Nineteen-table schema `1.0.0` and locked upgrades from schema versions 0.1.0 through 0.4.0.
- WP-Cron, real-cron/WP-CLI, bounded workers, lease locks, retries, dead letters, missed-run recovery and reconciliation.
- Deterministic install/source ZIP builder, canonical manifests, SHA-256 checksums and SPDX SBOM.
- Mandatory PHP 8.1/8.3 complete, first-review and fresh-adversarial suites.
- Expanded WordPress 7.0.2/MariaDB 11.4 integration smoke over all nineteen tables and public/admin route execution.

### Corrected during review round 1

- Aligned the legacy schema test with the approved additive upgrade chain.
- Corrected the public suggestion destination accessor to the canonical URL accessor.
- Added an explicit public visibility factory and maintained fail-closed restricted factories.
- Replaced source-format-dependent assertions with contract-chain verification.
- Reconciled Phase 26E tests with the canonical runtime contracts.
- Removed temporary transfer workflow/payload fragments and duplicate prototype implementations.
- Made Phase 26E complete/review/adversarial tests permanent Composer and CI gates.

### Corrected during fresh adversarial review

- Preserved numeric-string query tokens, synonyms, transliteration expansions, API identifiers and recommendation maps without PHP key coercion.
- Stabilized evaluation recall as a floating-point API metric.
- Enforced graph provenance: source owner must own the source endpoint or be the approved curator.
- Revalidated graph provenance at construction, graph insertion and persistent hydration.
- Hardened public cursor, boolean and list parsing; associative objects and invalid bounds fail closed.
- Added explicit click-time owner revalidation metadata to topic responses.
- Preserved recommendation click-time revalidation and prohibited-signal declarations.
- Corrected CI unresolved-marker detection so it cannot match its own rule.

### Removed

- One-time `apply-phase26e` transfer workflow.
- All `tools/phase26e-payload.part*` files.
- Duplicate `src/Complete/*` prototype implementations.

### Explicit non-claims

Version 1.0.0 does not itself prove Hostinger staging acceptance, live owner-provider data, File 20/File 25 visual acceptance, production deployment or operational monitoring. Those remain separate gates.

## 0.4.0

- Added internal active-generation query service.
- Added signed generation-bound cursors and strict persistent hydration.
- Added WP-Cron/real-cron, operations diagnostics, guarded replay and connector probes.
- Added isolated WordPress/MariaDB integration workflow.

## 0.3.0

- Added persistent blue/green generations, checkpoints, jobs, locks, retries, validation, atomic promotion and rollback.

## 0.2.0

- Added File 21 and File 10 public connector proofs, tombstones, eligibility evaluation, deterministic shadow search and parity reports.

## 0.1.0

- Added fail-closed plugin bootstrap, connector registry, canonical search documents, visibility envelopes, manifests, threat model and foundation tests.
