# Architecture

## Layers

1. **Owner contracts** — Files 03/05/06/07/08/10/11/12/15/18/21 and approved future owners expose versioned public/private state, object version, canonical route, deletion semantics and callbacks.
2. **Connector registry** — validates manifests, lifecycle and health; callbacks remain in memory while only public-safe metadata is persisted.
3. **Derivative index** — immutable canonical key from owner domain/object identity; monotonically increasing source version; tombstones override stale updates.
4. **Query services** — normalization, expansion, filters, facets, eligibility and bounded retrieval.
5. **Policy services** — versioned organic ranking, diversity, safety and doctor-ranking tier projection.
6. **Discovery** — guest cold start or explicitly consented personalization; no clinical/message/payment inference.
7. **Knowledge services** — controlled taxonomy, reviewed classification and bounded provenance graph.
8. **Presentation adapters** — File 20 route/surface registration and File 25 result rendering; fallback UI is accessible and green-accented.
9. **Assurance** — native controls remain effective if File 24 is absent; File 24 receives sanitized manifest and health evidence.

## Failure rules

- Unknown owner contract: no index/write.
- Identity assertion unknown: public-only; sensitive personalization disabled.
- Connector outage: partial, labeled result set; no fabricated items.
- Vector/provider outage: keyword fallback; no access broadening.
- Ranking rollback: active policy disabled and runtime activation closed pending review.
- Deleted/restricted source: priority tombstone, cache purge, graph/classification removal and reconciliation.
