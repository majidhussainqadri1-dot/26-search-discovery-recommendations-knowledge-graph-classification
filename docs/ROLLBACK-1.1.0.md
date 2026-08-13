# File 26 — Rollback Plan 1.1.0

## Rollback objectives

Rollback must preserve canonical owner data, File 00 authority, privacy restrictions, tombstones and audit evidence. It must not broaden access or silently discard an appeal or ranking decision.

## Policy rollback

- Search/doctor ranking policy rollback is a governed in-place action.
- A distinct authorized second approver and fresh step-up are required.
- The most recent previously active compatible policy is restored.
- File 26 is not globally disabled as a policy rollback side effect.

## Connector rollback

1. transition the affected active connector to `degraded` or `suspended`;
2. public search reports truthful partial state;
3. canonical owner remains untouched;
4. rebuild/reconcile the derivative index;
5. return through shadow and approved lifecycle before reactivation.

## Plugin rollback

1. close public search/personalization gates;
2. capture exact health, job, connector, policy and appeal status;
3. restore the approved previous plugin package;
4. retain additive 1.1 data unless its compatibility has been proven otherwise;
5. run schema and contract health checks;
6. verify File 00, File 20, File 24 and File 25 behavior;
7. smoke-test anonymous public reading and authenticated protected actions;
8. reopen only under Founder-approved change control.

## Database rollback

Database restore is a last resort because it can reintroduce stale visibility, deleted content or old ranking decisions. Before restore:

- suspend public retrieval;
- export post-backup tombstones, connector states and open appeals;
- restore the verified backup;
- replay privacy/deletion restrictions and reconcile owner versions;
- verify no stale private result is reachable;
- document the restoration window and lost/non-lost events.

## Uninstall rollback

Normal uninstall retains File 26 data by default. Destructive uninstall is prohibited during rollback and requires an explicit separate decision. Dedicated roles/jobs are recreated by reinstall; data is recovered from retained tables or verified backup.

## Success criteria

- no canonical owner mutation;
- no access broadening;
- no shadow connector exposed;
- previous safe policy active;
- all open appeals preserved or restored;
- no duplicate/stale public result;
- health status truthful;
- audit trail complete.
