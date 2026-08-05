# File 26 — Software Bill of Materials 1.1.0

## Runtime

- WordPress plugin: File 26 — Search, Discovery, Recommendations, Knowledge Graph and Classification
- Version: 1.1.0
- Contract: 1.1
- Primary language: PHP 7.4-compatible
- Browser code: vanilla JavaScript
- Styles: native CSS
- Database: WordPress `$wpdb` / MySQL-compatible tables

## Bundled components

No third-party PHP Composer package, JavaScript npm runtime package, external font, CDN script or binary executable is bundled.

Internal components include:

- database/migrations and scheduled jobs;
- institutional role model;
- security/authorization helpers;
- query normalizer and ranking;
- connector and owner-contract registries;
- indexing/reconciliation;
- federated search and recommendations;
- taxonomy/classification and knowledge graph;
- ranking governance and doctor ranking appeals;
- REST, public routes, admin, privacy and health;
- templates, JavaScript and CSS;
- deterministic package builder and QA scripts.

## Build-time tools

- PHP CLI
- Python 3 standard library (`zipfile`, `hashlib`, filesystem modules)
- Node.js only for JavaScript syntax checking when available
- POSIX shell tools: `find`, `grep`, `sha256sum`, `unzip`, `cmp`

## External runtime dependencies

These are integrations, not bundled software:

- WordPress core APIs;
- canonical owner modules Files 03/05/06/07/08/10/11/12/15/18/21;
- File 00 membership assertions;
- File 20 shell placement;
- File 24 assurance manifest;
- File 25 visual/result-card provider;
- object cache/database/hosting supplied by the deployment environment.

## Supply-chain controls

- deterministic single-root ZIP;
- generated SHA-256 manifest;
- source/package parity verification;
- clean-extract test rerun;
- dangerous execution primitive scan;
- forbidden ranking/business signal scan;
- sensitive foreign-table scan;
- no repository secrets or provider credentials.

Final package checksum must be taken from the exact green GitHub Actions artifact, not inferred from this document.
