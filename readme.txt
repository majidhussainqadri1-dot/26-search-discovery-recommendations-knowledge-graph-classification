=== Sabri Search, Discovery and Knowledge Graph ===
Contributors: majidhussainqadri1-dot
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 0.4.0
License: Proprietary

Canonical File 26 foundation for search, discovery, recommendations, controlled taxonomy, classification and owner-sourced knowledge-graph projections.

== Description ==

Version 0.4.0 adds an internal-only persistent active-generation query reader, signed snapshot cursors, strict stored-payload hydration, WP-Cron and real-cron worker adapters, missed-run recovery, administrator diagnostics, guarded dead-letter replay, owner-connector probes and a locked schema upgrade path. It does not expose a public search route or claim live owner adapters, Hostinger staging acceptance, release packaging or production deployment.

== Changelog ==

= 0.4.0 =
* Added internal active-generation query reading with signed generation-bound cursors.
* Added payload-hash verification, strict ISO-8601 hydration and query-time audience enforcement.
* Added bounded WP-Cron, real-cron/WP-CLI and missed-run recovery controls.
* Added administrator diagnostics and guarded, auditable dead-letter replay.
* Added read-only bounded owner-connector probes and schema 0.3.0 to 0.4.0 upgrade locking.
* Added Phase 26D query, operations and fresh adversarial suites.
* Added a pull-request-gated isolated WordPress 7.0.2/MariaDB 11.4 smoke; 21 database assertions passed.
* Corrected nullable persistent job cursors discovered by the real database smoke.

= 0.3.0 =
* Added blue/green persistent shadow generations and active alias state.
* Added WordPress tables for documents, tombstones, checkpoints, jobs and leases.
* Added bounded rebuild jobs, durable cursors, retries and dead-letter handling.
* Added deterministic counts/checksums and divergence-gated promotion.
* Added atomic promotion and rollback-predecessor restoration.
* Added Phase 26C persistence and fresh adversarial regression suites.

= 0.2.0 =
* Added public-only File 21 publications and File 10 videos connectors.
* Added strict owner-provider page, field-allowlist and canonical-host validation.
* Added tombstone-bearing batches and stale-event resistance.
* Added audience eligibility evaluation and deterministic shadow-index queries.
* Added owner/shadow parity reporting and adversarial regression tests.

= 0.1.0 =
* Added fail-closed plugin bootstrap.
* Added versioned connector manifest contract and registry.
* Added canonical search-document and visibility-envelope value objects.
* Added foundation tests, threat model, data-flow baseline and CI.
