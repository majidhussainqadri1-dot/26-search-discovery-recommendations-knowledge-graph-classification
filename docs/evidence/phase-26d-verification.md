# Phase 26D Verification Evidence

## Scope

This record covers File 26 version/schema `0.4.0` and runtime stage `phase-26d-query-operations` on the draft pull-request branch. It records repository test evidence and isolated WordPress/MariaDB integration evidence; it does not claim Hostinger staging or production acceptance.

## Repository matrix

Workflow: `File 26 Foundation CI`

Matrix:

- PHP 8.1
- PHP 8.3

Required checks:

- Composer strict metadata validation;
- syntax lint over all PHP source, tests and integration scripts;
- contract, shadow-index, persistence, query, operations and adversarial suites;
- connector manifest, JSON Schema and golden-query fixture parsing.

Assertions per PHP version:

```text
Foundation and shadow-index suite: 41
Phase 26B review suite:           13
Phase 26C persistence suite:      32
Phase 26C adversarial suite:      22
Phase 26D query suite:            28
Phase 26D operations suite:       24
Phase 26D adversarial suite:      16
Total:                           176
```

## Isolated database integration

Workflow: `File 26 WordPress MariaDB Integration`

Environment:

```text
WordPress: 7.0.2
MariaDB:   11.4.12
PHP:       8.3.33
WP-CLI:    2.12.0
```

Smoke assertions: `21`

Verified behaviors:

1. plugin activation;
2. schema version `0.4.0`;
3. all seven derivative tables;
4. `replay_count` and `last_replayed_at` columns;
5. persistent generation creation;
6. durable checkpoint creation and completion;
7. document persistence;
8. deterministic generation validation checksum;
9. active-generation promotion;
10. Urdu active-generation query;
11. persistent queue enqueue and claim;
12. dead-letter transition;
13. guarded replay and replay-count increment.

## Defects exposed by integration

The real database workflow exposed defects not visible in the in-memory suites:

- WP-CLI `eval-file` harness incompatibility with a top-level `strict_types` declaration;
- nullable `cursor_value` parity, where database hydration could yield an empty string instead of `null`.

Corrections:

- the integration harness was made compatible with the WP-CLI evaluation context while plugin source remains strictly typed;
- null cursors are written as SQL `NULL` explicitly;
- hydrated `NULL` and legacy empty-string cursors normalize to canonical `null`;
- legacy retry lookup tolerates historical `NULL/''` rows only at the persistence boundary.

The full database smoke was rerun after correction and passed all 21 assertions.

## Review doctrine applied

1. implementation;
2. first review and correction;
3. fresh adversarial review and correction;
4. clean PHP matrix;
5. real WordPress/MariaDB execution;
6. correction of database-only defects;
7. complete rerun.

## Remaining gates

- Hostinger-equivalent activation and in-place upgrade rehearsal;
- advisory-lock and concurrent-worker stress;
- backup, restore and rollback rehearsal;
- approved File 21 and File 10 live owner adapters;
- staging parity, leakage, deletion-lag and latency SLOs;
- public query API and File 20/File 25 integration;
- package/source parity and installable release ZIP.
