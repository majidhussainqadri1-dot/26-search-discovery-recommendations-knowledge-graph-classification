# Connector Contract v1.0

A connector registers through `sabri_file26_register_connector()` or `sabri_file26_connector_manifests`.

Required manifest fields:

- `slug`, `owner_file`, `contract_version`
- `entity_types`, `privacy_classes`, `visibility_fields`
- `deletion_semantics`

Optional callable fields:

- `list_batch($cursor, $limit, $scope)` — bounded rebuild batches.
- `can_view($document, $audience)` — owner-side click/query visibility.
- `health()` — state and safe details.

Indexed document requirements:

`connector_slug`, `domain`, `object_id`, `object_version`, `entity_type`, `locale`, `state`, `visibility`, `title`, `canonical_url`.

Rules:

- Canonical owner remains authoritative.
- Version must be monotonic; stale/out-of-order updates are ignored and audited.
- URLs must be same-origin canonical destinations unless separately approved.
- Payload is allowlisted and cannot carry secrets, raw clinical/message/payment data or identity documents.
- `download_allowed` and `download_url` are owner grants, revalidated by the owner at click/delivery time.
- Delete/restrict events must call the tombstone/restriction contract and be replay-safe.
