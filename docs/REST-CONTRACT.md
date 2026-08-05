# REST Contract v1

Namespace: `sabri-search/v1`

Public/query endpoints: `search`, `suggest`, `discover`, `topics/{term}`, `graph/{key}`.
Authenticated user controls: `feedback`, `personalization/consent`, `personalization/reset`, `personalization/opt-out`.
Operational endpoints: reindex, reconcile, taxonomy, graph-edge lifecycle, connector lifecycle, ranking-policy lifecycle, classification review, reports and health.

All responses include `X-Sabri-File26-Contract: 1.0`. Public anonymous responses receive bounded cache headers; user-specific and admin responses are `private, no-store`. Errors use safe WordPress REST error codes and trace IDs where applicable. Availability does not grant authorization.
