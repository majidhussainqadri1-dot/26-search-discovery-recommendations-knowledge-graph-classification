# File 26 — Migration Plan 1.1.0

## Supported paths

1. Fresh install of File 26 1.1.0.
2. Upgrade from File 26 1.0.0.
3. Reinstall over retained File 26 data.

## Pre-migration gates

- verified database backup and restore proof;
- exact current plugin/database inventory;
- no duplicate File 26 plugin copy;
- File 00, 20, 24 and 25 contract versions recorded;
- owner connector inventory recorded;
- public search remains disabled;
- rollback package and database restore point available.

## Additive changes

- dedicated File 26 institutional roles/capabilities;
- ranking-appeal table and schema option;
- new 1.1 REST/provider contracts;
- new settings consumed with bounded defaults;
- owner-contract readiness evidence;
- no destructive canonical-content migration.

## Connector migration law

Every new connector and every owner/contract version change returns to `proposed`. Existing lifecycle status is retained only when owner and contract version are unchanged. No migration script may auto-promote a connector to active.

## Ranking-policy migration

- Existing active organic policy remains active if valid.
- Doctor ranking uses the documented baseline until an approved `doctor_global/public` policy is activated.
- Invalid zero-weight doctor policy uses a disclosed safe fallback.
- Policy activation and rollback require distinct approvers and fresh step-up evidence.

## Data migration sequence

1. install/upgrade code with public gates closed;
2. install additive schemas and roles;
3. validate existing File 26 tables and counts;
4. register owner adapters as proposed;
5. contract-test and shadow-index each adapter;
6. run reconciliation and tombstone checks;
7. compare shadow results with canonical owners;
8. approve and activate connectors one by one;
9. run Urdu/English relevance and leakage benchmarks;
10. enable public search only after the external activation gate passes.

## Acceptance evidence

- before/after row counts;
- connector lifecycle audit;
- no private/pending/deleted leakage;
- duplicate and stale index reconciliation;
- File 00 assertion behavior;
- File 20/25 rendering evidence;
- backup restore and rollback rehearsal;
- exact package checksum and commit.

Production migration is prohibited until Hostinger-equivalent staging accepts this sequence.
