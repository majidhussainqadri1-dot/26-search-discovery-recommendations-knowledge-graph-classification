=== Sabri Search, Discovery and Knowledge Graph ===
Contributors: majidhussainqadri1-dot
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: Proprietary

Canonical federated search, discovery, recommendations, controlled taxonomy, reviewed classification and owner-sourced knowledge-graph runtime for the Sabri Social Homeopathy Platform.

== Description ==

File 26 version 1.0.0 provides the complete coded runtime scope defined by the approved File 26 plan while preserving canonical ownership in the numbered source modules.

Core capabilities include:

* Versioned public-only owner connectors and blue/green derivative generations.
* Urdu and English Unicode normalization, approved transliteration and synonym expansion.
* Signed cursor pagination, filters, facets, suggestions and deterministic ranking.
* Consented recommendations with cold start, explanations, hide, not-interested, reset and opt-out controls.
* Controlled taxonomy, independent classification review and appeal.
* Typed, provenance-rich and visibility-aware knowledge-graph projections.
* Ordered change-event ingestion, tombstone propagation, purge evidence and reconciliation.
* Privacy-minimized telemetry, evaluation sets, versioned policies and guarded exports.
* Public query, suggestion, facet, recommendation, feedback and topic REST endpoints.
* Administrator health, taxonomy, graph, classification, policy, evaluation, telemetry, export and operations endpoints.
* Bounded WP-Cron, real-cron/WP-CLI, retries, dead letters, locks, rollback and non-destructive uninstall.

File 26 does not own canonical posts, doctors, lessons, videos, PDFs, listings, profiles, messages, clinical charts, payment records, global navigation or public result-card design. Those remain with their numbered owner modules. Private messages, clinical records, identity evidence, payment secrets, unpublished drafts and restricted attachments are not general search domains.

== Installation ==

1. Verify PHP 8.1 or newer and WordPress 6.0 or newer.
2. Back up files and database.
3. Install `sabri-search-discovery-1.0.0.zip` on an isolated staging environment first.
4. Activate the plugin and verify the administrator health endpoint.
5. Register approved owner adapters and run connector probes before building a candidate generation.
6. Validate, promote and test the active generation before enabling File 20 and File 25 integrations.

Activation is fail-closed when the database schema or required secret material cannot be established safely.

== Frequently Asked Questions ==

= Does version 1.0.0 mean the platform is live or operational? =

No. Version 1.0.0 establishes the complete coded, packaged and automated-QA candidate. Hostinger staging acceptance, real owner-data readiness, File 20/File 25 visual integration, backup/restore, rollback, Founder approval, production deployment and monitoring remain separate evidence gates.

= Can File 26 index private messages or clinical records? =

No. General indexing of private messages, clinical charts, identity evidence, payment secrets, unpublished drafts and restricted attachments is prohibited.

= Does search become the source of truth? =

No. Search documents, taxonomy projections, graph nodes, telemetry and caches are rebuildable derivatives. Every destination and protected action must be revalidated by its canonical owner.

= Is personalized recommendation enabled for guests? =

No. Personalized recommendations require authentication and explicit consent. Minor personalization additionally requires verified guardian consent from the canonical identity context.

== Security and Privacy ==

* Public connector fields are explicit allowlists.
* Destination URLs must be credential-free HTTPS URLs on the canonical Sabri host.
* Stored payload hashes and canonical JSON shapes are revalidated during reads.
* Query cursors and export tokens are signed, bounded and context-bound.
* Sensitive and PII-like queries are classified and raw query telemetry is not retained.
* Capability, entitlement, age, guardian consent, record state and destination visibility are rechecked.
* High-risk policy and classification changes require independent review and audit evidence.
* Uninstall is intentionally non-destructive.

== Changelog ==

= 1.0.0 =
* Completed canonical runtime composition and versioned public/admin REST interfaces.
* Added nine approved public owner-adapter contracts for Files 05, 06, 09, 10, 11, 12, 15, 18 and 21.
* Added Urdu/English query understanding, deterministic ranking, safe suggestions and facets.
* Added consented recommendation controls and privacy-preserving feedback.
* Added controlled taxonomy, reviewed classification and provenance-enforced knowledge graph.
* Added ordered event ingestion, purge ledger, policies, evaluation, telemetry, audit and signed exports.
* Expanded the schema to nineteen derivative tables with locked upgrades from 0.1.0 through 0.4.0.
* Added deterministic install/source package generation, checksums and SPDX SBOM.
* Added mandatory PHP 8.1/8.3 complete, review and fresh-adversarial suites.
* Expanded isolated WordPress 7.0.2/MariaDB 11.4 integration to complete runtime scope.
* Removed temporary transfer payloads and duplicate prototype implementations.
* Corrected numeric-string coercion, graph provenance, metric type stability and public API parsing defects found during the two review rounds.

= 0.4.0 =
* Added internal active-generation query reading, signed generation-bound cursors, worker scheduling, diagnostics, guarded replay and isolated database verification.

= 0.3.0 =
* Added persistent blue/green generations, jobs, locks, retries, validation, promotion and rollback.

= 0.2.0 =
* Added public connector proofs, tombstones, eligibility evaluation and deterministic shadow search.

= 0.1.0 =
* Added fail-closed bootstrap, connector registry, canonical search documents and foundation tests.
