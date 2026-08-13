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
- taxonomy create/submit/approve/deprecate/split;
- graph-edge lifecycle;
- connector lifecycle;
- ranking policy stage/activate/rollback;
- classification review;
- doctor-ranking appeal review;
- reports;
- `GET /admin/editorial-radar` — aggregate File 26 telemetry plus explicit non-sensitive identity-free content gaps; File 15 remains canonical trend owner;
- `GET /admin/central-plan-status` — repository/runtime contract status without a live-deployment claim.

## Response and cache law

Responses use `X-Sabri-File26-Contract: 1.2` and `X-Content-Type-Options: nosniff` where File 26 builds the response. Ordinary public anonymous query responses use bounded public caching only where their eligibility context is public-safe. Personalized, authenticated, saved-query, content-gap and admin responses are `private, no-store`. The public ranking constitution may use a bounded anonymous public cache because it contains only public policy configuration.

Search results preserve the canonical owner reference and require owner click-time revalidation. File 26 does not convert endpoint availability, cache state or a derivative index record into authorization. Unknown/stale freshness remains explicit and never broadens access.

Errors use safe WordPress REST error codes/messages and trace IDs where applicable. No API response may expose secrets, raw clinical notes, identity evidence, private-message text or unapproved raw sensitive query history.
