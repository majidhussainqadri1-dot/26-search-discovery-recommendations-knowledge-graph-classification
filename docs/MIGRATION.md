# Migration and Cutover

1. Inventory existing WordPress/native searches, taxonomies, rankings, profile/search routes and every owner schema.
2. Resolve duplicate owners and freeze connector contracts.
3. Fresh install creates File 26-owned tables idempotently; no foreign table is changed.
4. Register connectors as `proposed`, pass contract tests, then `shadow`.
5. Run bounded shadow reindex; compare counts, checksums, visibility fixtures, golden queries and deletion fixtures.
6. Reconcile stale objects and tombstones.
7. Approve/activate connector lifecycle only after owner and privacy/security acceptance.
8. Enable public runtime activation only after File 20 placement and File 25 rendering acceptance.
9. Retain legacy search until observation window and rollback criteria pass.

No canonical content is copied as a new source of truth. All migrations are restartable and produce safe reports.
