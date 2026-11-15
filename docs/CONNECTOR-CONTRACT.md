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
- `fetch_object($entity_type, $object_id)` — authoritative, current, read-only source object projection. **Mandatory** for every one of the 11 canonical owner adapters before Production activation and for every cold index/shadow upsert. It must return a current complete eligible projection or null/WP_Error on deletion, denial or outage; the consumer fails closed. A previous `list_batch` row or File26's own stale cache is never a substitute.

Indexed document requirements:

`connector_slug`, `domain`, `object_id`, `object_version`, `entity_type`, `locale`, `state`, `visibility`, `title`, `canonical_url`.

Rules:

- Canonical owner remains authoritative.
- Version must be monotonic; stale/out-of-order updates are ignored and audited.
- A cold record (no serving document) **always** requires a fresh canonical owner fetch. Source identity, current version/sequence, URL, title, public-safe excerpt, state, visibility and locale must match the proposed projection, even after the finite tombstone has expired. Shadow rebuild also checks it. No permanent storage of deleted identifiers is introduced by this contract.
- The contract is a mitigation, **not** an atomic owner outbox/revocation ACK; races between owner read and derivative write still require a separately certified durable protocol.
- URLs must be same-origin canonical destinations unless separately approved.
- Payload is allowlisted and cannot carry secrets, raw clinical/message/payment data or identity documents.
- `download_allowed` and `download_url` are owner grants, revalidated by the owner at click/delivery time.
- Delete/restrict events must call the tombstone/restriction contract and be replay-safe.
