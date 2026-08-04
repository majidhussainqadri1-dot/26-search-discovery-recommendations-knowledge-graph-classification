# File 26 Reconciliation and Recovery Runbook

## Scope and safety law

This runbook operates File 26 derivative search infrastructure only. It must not directly edit canonical owner records, change membership authority, publish content, alter clinical/support/payment decisions or broaden visibility.

Before every repair:

1. identify the exact version, schema, generation and connector;
2. capture redacted health, queue and reconciliation evidence;
3. preserve the current active generation and predecessor;
4. use owner contracts and File 26 commands—not direct companion-table writes;
5. prefer dry/read-only diagnostics before mutation;
6. keep public search on the last validated active generation;
7. record reason, actor, timestamps, affected canonical keys and result;
8. verify rollback and owner visibility after correction.

## Status collection

```bash
wp sabri-file26 operations status
wp sabri-file26 reconcile --limit=100
```

Review:

- active generation ID;
- queue counts by state;
- dead-letter count and current error codes;
- scheduler lag and missed-run state;
- registered connector count and health;
- failed change events;
- overdue purges;
- duplicate active canonical keys.

Do not mark a system healthy when data is stale or unavailable. Use `unknown`/`degraded` rather than assuming secure or complete state.

## Connector probe

```bash
wp sabri-file26 connector probe \
  --connector=file-21-publications \
  --batch=50 \
  --pages=50
```

The probe is read-only and must prove:

- canonical ownership matches the connector;
- pages remain within the requested bound;
- continuing cursors are non-empty and non-repeating;
- terminal page has no continuation cursor;
- document and tombstone identities are unique;
- owner checksum is deterministic;
- no private/restricted field appears.

A failing connector must be marked unavailable or excluded from the candidate generation. Never fabricate an empty success or silently broaden its fields.

## Running bounded jobs

WP-Cron normally schedules bounded work every five minutes. A real server cron may execute:

```bash
wp sabri-file26 jobs run --max=20 --batch=100
```

Limits:

- maximum jobs per invocation: 50;
- maximum connector items per page: 200;
- lease-based single-worker execution;
- bounded retry policy;
- stale job/cursor suppression.

Repeated invocations are safe because job identity, checkpoint cursor and connector chronology are idempotent/versioned.

## Missed-run recovery

```bash
wp sabri-file26 jobs recover
```

Recovery is appropriate when pending jobs exist and the scheduler is overdue or has never run. The recovery path schedules bounded work; it does not process an unbounded queue inside an administrator page request.

Investigate recurring missed runs:

- WordPress cron disabled or no traffic;
- server cron absent;
- loopback/API restrictions;
- database lock contention;
- fatal PHP error;
- plugin/schema degraded health.

Use a real cron command for low-traffic production, while retaining the same worker loop and queue contracts.

## Dead-letter investigation

List status:

```bash
wp sabri-file26 operations status
```

For each dead-letter job:

1. record job ID, connector, generation, cursor, attempt count and exact error code;
2. probe the connector read-only;
3. correct owner contract/data or File 26 defect;
4. verify the generation is still `building` and checkpoint incomplete;
5. replay using exact confirmation:

```bash
wp sabri-file26 dead-letter replay \
  --job=<64-character-job-id> \
  --error=<current-error-code>
```

Replay is rejected when the current error differs, the generation is no longer building, the checkpoint is complete or the replay ceiling is exhausted. Never edit the job row manually to bypass this gate.

## Generation rebuild

A normal rebuild sequence is:

1. create a candidate generation;
2. enqueue each expected connector;
3. ingest bounded document/tombstone pages;
4. persist opaque checkpoints;
5. require every connector terminal checkpoint;
6. reconcile expected connector set;
7. validate document/tombstone counts and divergence policy;
8. compute deterministic checksum;
9. promote atomically;
10. retain predecessor for rollback.

The active generation remains readable while a candidate builds. Failed, incomplete, unvalidated, unexpectedly empty or excessively divergent generations must never be promoted.

## Promotion decision

Promotion requires:

- all expected connectors complete;
- no cross-owner canonical identity;
- no document/tombstone collision;
- no unresolved critical leakage;
- accepted minimum count and divergence;
- deterministic checksum;
- bounded deletion/restriction lag;
- query and click-time visibility tests;
- a retained rollback predecessor.

Capture generation ID, counts, checksum, policy version, reviewer and time in release evidence.

## Rollback

Rollback changes only the active alias to the retained validated predecessor. It does not invent a new generation or destructively downgrade owner data.

After rollback:

1. purge File 26 caches;
2. execute guest and authenticated Urdu/English smoke queries;
3. verify restricted/deleted keys remain absent;
4. verify File 20 and File 25 point to canonical owner URLs;
5. record cause and affected candidate generation;
6. do not retry the rejected candidate without correction and complete revalidation.

If no validated predecessor exists, disable public File 26 query surfaces and preserve canonical public owner routes; do not promote an unsafe generation merely to restore search.

## Change-event reconciliation

```bash
wp sabri-file26 reconcile --limit=100
```

Investigate:

- failed ordered events;
- owner sequence gaps;
- duplicate/replayed idempotency keys;
- stale object versions;
- document/tombstone chronology conflicts.

Events are past-tense facts, not commands or authorization grants. Owner state at click/action time remains authoritative.

## Deletion and purge verification

The purge ledger tracks requested, completed and verified-absent times. A deletion/restriction workflow must remove the key from:

- active/candidate documents as applicable;
- suggestions;
- facets;
- recommendations;
- graph projections;
- caches;
- derived telemetry where policy requires;
- external provider/CDN indexes if later added.

An overdue purge is a release blocker when the approved deletion SLO has passed. Confirm absence through public query, internal active-generation lookup and canonical owner denial.

## Telemetry retention

```bash
wp sabri-file26 telemetry-purge --days=90
```

The command deletes aggregate telemetry older than the selected bounded retention period. It must not be used to erase audit, canonical owner or legal-hold records outside File 26 ownership.

## Database/schema recovery

Schema upgrades are protected by a MariaDB advisory lock and support versions `0.1.0`, `0.2.0`, `0.3.0` and `0.4.0` to `1.0.0`.

If schema health fails:

1. disable public File 26 query surfaces;
2. keep canonical owner public pages available;
3. capture the schema option and actual table/column inventory;
4. verify backup restore capability;
5. run the supported migration in isolated staging;
6. do not set the schema-version option manually;
7. reactivate only after all nineteen tables and required columns verify.

Uninstall is non-destructive. A data purge requires a separate guarded, approved workflow with export, dependency and deletion-reconciliation evidence.

## Incident severity

### Critical

- private/restricted result leakage;
- cross-owner data mutation;
- compromised signing/secret material;
- active-generation corruption with no validated predecessor;
- deletion failure beyond approved SLO for sensitive data.

Action: disable affected public surfaces, preserve evidence, rotate secrets where required, notify File 24 assurance/incident coordination, and use canonical owner pages only.

### High

- repeated failed owner connector;
- graph provenance corruption;
- incorrect recommendation eligibility;
- persistent sequence gaps;
- rollback failure.

Action: stop candidate promotion, isolate the connector/policy, correct, rerun two reviews and repeat staging acceptance.

### Medium/Low

- relevance regression;
- elevated zero-result rate;
- noncritical latency/queue lag;
- presentation mismatch without security/privacy impact.

Action: record, correct through normal change control, evaluate, rebuild and measure.

## Closure evidence

An incident or reconciliation task closes only when:

- root cause is recorded;
- source/owner boundary remains correct;
- defect is fixed;
- regression test exists;
- first review and fresh adversarial review pass;
- affected generation is rebuilt or rolled back;
- canonical owner and public click tests pass;
- queues/dead letters/purges are reconciled;
- File 24 evidence is updated where applicable;
- no known unresolved blocker remains.
