# Phase 26B — Public Connectors and Shadow Index Proof

## Decision

Phase 26B adds two low-risk, public-only connector implementations and a deterministic in-memory shadow projection. This phase proves safety and lifecycle rules before choosing a persistent index engine or exposing a public search route.

## Approved proof connectors

| Connector | Canonical owner | Eligible entities | Privacy class |
|---|---:|---|---|
| `file-21-publications` | File 21 | public publication, editorial news | C1 only |
| `file-10-videos` | File 10 | public recorded video, public live replay | C1 only |

These classes are connector implementations, not live owner integrations. A canonical owner must still supply an approved `SourceBatchProviderInterface` adapter and pass WordPress staging contract tests.

## Owner-provider batch law

Each page contains only:

- `records`: bounded public records;
- `tombstones`: bounded restriction/deletion changes;
- `next_cursor`: opaque continuation cursor or `null`;
- `has_more`: explicit boolean.

A continuing page requires a cursor. A terminal page must not expose one. Unknown payload keys, scalar coercion, over-limit pages, unknown record fields, non-Sabri destinations, and malformed chronology fail closed.

## Public-field allowlists

Each connector has a separate field allowlist. File 21 may expose approved title, excerpt, body text, author, topic, language, publication and correction metadata. File 10 may expose approved title, description, channel, topic, language, duration, captions and media-type metadata.

Patient identifiers, private attachments, message bodies, clinical records, identity evidence, credentials and provider secrets are not fields of either connector.

## Shadow projection lifecycle

```text
owner page
  -> strict connector validation
  -> SearchDocument / IndexTombstone
  -> ShadowIndex.applyBatch()
  -> timestamp-aware upsert or removal
  -> query-time eligibility
  -> deterministic synthetic result set
  -> parity report against owner canonical keys
```

A tombstone removes an older derivative document. An older document cannot resurrect after a newer tombstone. A genuinely newer owner correction may supersede an older tombstone. The shadow index stores canonical references only and never mutates owner records.

## Query-time authorization proof

The eligibility evaluator checks:

1. document state;
2. public versus restricted visibility;
3. authentication;
4. all required capabilities;
5. required entitlement;
6. minimum age;
7. verified guardian consent.

This is a contract-layer proof. Production results must additionally revalidate the canonical owner at click time and action time.

## Query proof limits

The shadow query is deliberately simple and deterministic:

- Unicode-aware token splitting;
- Urdu/English literal matching;
- title boost;
- bounded query and result sizes;
- stable tie-breaking;
- no personalization or hidden profile;
- no public REST endpoint.

It is not the final tokenizer, transliteration service, ranking engine, recommendation system or persistent search store.

## Reconciliation proof

`reconcileExpectedKeys()` reports:

- missing owner records not present in the shadow projection;
- orphaned shadow records not present in the supplied owner key set.

The method is bounded and rejects duplicate or malformed canonical keys.

## Exit gates

Phase 26B is not staging-accepted until:

- File 21 and File 10 expose approved real provider adapters;
- owner contract versions and field allowlists are jointly frozen;
- shadow rebuild and delta runs complete on staging data;
- private/pending/retracted/deleted leakage tests pass;
- parity, deletion propagation and rollback SLOs are measured;
- WordPress activation, background processing, browser, accessibility and rollback tests pass;
- both coding review/fix rounds are recorded with zero known unresolved critical/high defects.
