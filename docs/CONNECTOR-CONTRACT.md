# Connector Contract v1.2

A connector registers through `sabri_file26_register_connector()` or `sabri_file26_connector_manifests`. Registration is not activation: every new owner/contract combination begins in the governed lifecycle and only an `active` connector may serve public/member search.

## Required manifest fields

- `slug`, `owner_file`, `contract_version`
- `entity_types`, `privacy_classes`, `visibility_fields`
- `deletion_semantics`

Required owner adapters additionally provide callable `list_batch`, `can_view`, and `health` callbacks before production readiness can be asserted. `fetch_object` is optional.

## Callback law

- `list_batch($cursor, $limit, $scope)` — bounded rebuild batches. File 26 requests at most 100 items, rejects a batch larger than 100, requires a continuation cursor when `done=false`, and rejects a non-progressing empty batch.
- `can_view($document, $audience)` — owner-side **query-time** eligibility recheck. It must fail closed on unknown state and must never broaden the indexed visibility envelope.
- `health()` — returns only safe operational state/details; secrets and private source records are prohibited.
- Click/action/delivery authorization is **not** delegated to a cached File 26 callback result. The canonical owner reauthorizes the current object at click/action time.

## Indexed document requirements

`connector_slug`, `domain`, `object_id`, `object_version`, `entity_type`, `locale`, `state`, `visibility`, `title`, `canonical_url`.

Source-event sequence/id fields may also be supplied. The same source version + event sequence may not silently change searchable content; inconsistent event identity is rejected.

## Rules

- The canonical owner remains authoritative; File 26 stores only a derivative projection.
- Version/sequence state is monotonic; stale/out-of-order updates are ignored or rejected safely.
- URLs must resolve to safe same-origin canonical destinations. External resource URLs require a separate explicit allowlist.
- Payload is allowlisted and cannot carry secrets, raw clinical/message/payment data, identity documents, capability-bearing signed delivery URLs, or reusable download tokens.
- `download_allowed` may describe a non-authoritative display hint, but the actual delivery URL/grant is issued by the canonical owner only after current authorization.
- Delete/restrict events must call the tombstone/restriction contract and be replay-safe. Tombstone precedence, derivative purge and reconciliation are mandatory.
- `shadow` and `approved` connectors may be indexed for governed validation but cannot serve public search. `active` is the only production retrieval lane.
