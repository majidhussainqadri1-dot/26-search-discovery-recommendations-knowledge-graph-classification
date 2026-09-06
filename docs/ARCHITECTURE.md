# Architecture

## Layers

1. **Owner contracts** — Files 03/05/06/07/08/10/11/12/15/18/21 and approved future owners expose versioned eligible state, object version, canonical route, deletion semantics and callbacks. Canonical records never move into File 26 ownership.
2. **Connector registry** — validates manifests, lifecycle and health; callbacks remain in memory while only public-safe metadata is persisted. Only `active` connectors serve production retrieval; `shadow`/`approved` are validation/indexing lanes.
3. **Derivative index** — immutable canonical key from owner domain/object identity; monotonic source version/sequence; inconsistent same-event content is rejected; tombstones override stale updates.
4. **Query services** — normalization, expansion, filters, facets, eligibility and bounded retrieval. Search/graph DB failures are not silently converted to complete output.
5. **Policy services** — versioned organic ranking, diversity, safety and doctor-ranking tier projection.
6. **Discovery** — guest cold start or explicitly consented personalization; no clinical/message/payment inference. Preference/profile DB failure is fail-closed rather than silently de-personalizing an authenticated request.
7. **Knowledge services** — controlled taxonomy, reviewed classification and bounded provenance graph.
8. **Presentation adapters** — File 20 route/surface registration and File 25 result rendering; fallback UI is accessible and green-accented. HTML search/topic surfaces use the same central governed search augmentation contract as API consumers.
9. **Assurance** — native controls remain effective if File 24 is absent; File 24 receives sanitized manifest and health evidence.
10. **Release boundary** — exact source, deterministic runtime-only package, staging deployment and live runtime are distinct realities and must not be conflated.

## Failure rules

- Unknown owner contract: no index/write.
- Identity assertion unknown for an authenticated user: public-only retrieval; sensitive personalization disabled.
- Connector outage: partial, labeled result set; no fabricated items.
- Vector/provider outage: keyword fallback only where access/ranking safety remains intact; no access broadening.
- Ranking rollback: active policy transition is audited/serialized and runtime activation may close pending review.
- Deleted/restricted source: priority tombstone, derivative purge, graph/classification removal, reconciliation and cache invalidation.
- Dynamic public HTTP/object caches are `no-store`/disabled by default unless an approved revocation-purge integration and a separate cache-allow gate are both present.
- A queue/transaction/commit failure is not success; retry/dead-letter state must remain explicit.
