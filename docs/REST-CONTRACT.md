# REST Contract v1.2

Namespace: `sabri-search/v1`

## Public/query endpoints

- `GET /search` — federated eligible search.
- `GET /advanced-search` — bounded advanced search with exact phrase, author, source, connector, date, allowed-field, exclusion, verification, format and access constraints.
- `GET /suggest` — safe autocomplete.
- `GET /discover` — public or consent-aware discovery/recommendations.
- `GET /topics/{term}` — governed topic projection.
- `GET /graph/{key}` — bounded public knowledge-graph traversal.
- `GET /doctors/ranking` — Top 10/100/1000/All Verified and contextual doctor-ranking views.
- `GET /ranking-constitution` — current organic and doctor-ranking policy constitution, prohibited signals and user-control law.

## Authenticated user endpoints

- `POST /feedback`
- `POST /personalization/consent`
- `POST /personalization/interests`
- `POST /personalization/reset`
- `POST /personalization/opt-out`
- `GET|POST /saved-queries`
- `DELETE /saved-queries/{uuid}`
- `POST /content-gap` — explicit, non-sensitive content-gap submission; user identity is not persisted in the gap registry.
- `POST /doctors/ranking/appeals`
- `GET /doctors/ranking/appeals/mine`

Sensitive saved-query text requires an approved encryption provider and is never persisted as plaintext. Reading protected query text requires fresh `saved_query_decrypt` step-up authorization. Sensitive identifiers are rejected from saved-query filter/advanced metadata.

## Restricted operational/governance endpoints

- health, reindex and reconciliation;
- taxonomy create/submit/approve/deprecate/merge/split;
- graph-edge lifecycle;
- connector lifecycle;
- ranking policy stage/second-approve/activate/rollback;
- classification review;
- doctor-ranking appeal review;
- reports;
- `GET /admin/editorial-radar` — aggregate File 26 telemetry plus explicit non-sensitive identity-free content gaps; File 15 remains canonical trend owner;
- `GET /admin/central-plan-status` — repository/runtime contract status without a live-deployment claim.

## Response and cache law

File 26 responses carry the versioned contract and safe content-type headers where File 26 builds the response. Dynamic eligibility-sensitive public responses are **`no-store` by default**. Public HTTP/object caching is permitted only when an explicitly approved revocation/cache-purge integration proves that access revocation, deletion, connector suspension and policy changes can invalidate stale derivatives; a separate explicit cache-allow gate must also approve the surface and TTL. Authenticated, personalized, saved-query, content-gap and admin responses remain private/no-store.

The ranking constitution contains public policy data, but it is still subject to the same late File 26 cache-safety gate; public cacheability is never inferred merely from the data being public.

Search results preserve the canonical owner reference and require owner click/action-time revalidation. File 26 does not convert endpoint availability, cache state, a successful prior authorization, or a derivative index record into current authorization. Unknown/stale freshness remains explicit and never broadens access.

## Error law

- Database/read failures are not converted into empty-success, false 404, or partial-success responses.
- Optimistic-concurrency conflicts remain distinct from persistence failures.
- Public topic/search/graph/ranking surfaces preserve the underlying safe HTTP error class where available.
- Errors use safe WordPress REST error codes/messages and trace IDs where applicable.
- No API response may expose secrets, raw clinical notes, identity evidence, private-message text or unapproved raw sensitive query history.
