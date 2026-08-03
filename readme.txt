=== Sabri Search, Discovery and Knowledge Graph ===
Contributors: majidhussainqadri1-dot
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 0.3.0
License: Proprietary

Canonical File 26 foundation for search, discovery, recommendations, controlled taxonomy, classification and owner-sourced knowledge-graph projections.

== Description ==

Version 0.3.0 adds persistent shadow-generation storage contracts, WordPress schema adapters, bounded rebuild/delta workers, durable checkpoints, retry/dead-letter handling, leases, deterministic checksums, validation thresholds, atomic active-generation promotion and rollback. It does not claim live owner adapters, public search routes, Hostinger staging acceptance, a release package, or production deployment.

== Changelog ==

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
