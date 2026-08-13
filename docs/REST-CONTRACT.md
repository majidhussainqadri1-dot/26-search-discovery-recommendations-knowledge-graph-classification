# REST Contract v1.3

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

## Future Search & Knowledge Intelligence v1.3 endpoints

All future routes are under `/future/*` and advertise `sabri.file26.future.v1.3`.

### Public derivative discovery

- `POST /future/conversational-grounded-search` — source-grounded answer/extractive discovery; no autonomous diagnosis/prescription.
- `POST /future/query-planner` — bounded explainable subquery planner with optional owner-federated execution.
- `POST /future/cross-language-search` — normalized/transliterated plus approved cross-language semantic variants.
- `POST /future/semantic-rerank` — second-stage rerank over the immutable eligible candidate set.
- `GET|POST /future/segment-search` — owner-supplied page/paragraph/chapter/lesson/timestamp segments.
- `GET|POST /future/find-similar` — canonical-key similarity discovery through an approved seed provider.
- `GET|POST /future/research-search` — scholarly filters plus optional reproducibility snapshot envelope.
- `POST /future/result-clusters` — bounded eligible result clustering.
- `GET /future/graph-path` — bounded provenance-rich relation path.
- `GET /future/evidence-map` — supports/discusses/contradicts/corrects/retracts projection.
- `GET /future/disambiguate` — same-name/entity/edition disambiguation candidates.
- `GET|POST /future/historical-search` — actual owner snapshot only; current results are never substituted.
- `GET|POST /future/geo-availability` — doctor/clinic discovery with File 07/08 owner constraints and revalidation.
- `GET|POST /future/search-modes` — intent modes and bounded smart-command parsing.
- `POST /future/external-evidence` — approved external connector lane, separated from organic ranking.

### Authenticated valid-member future surfaces

- `POST /future/multimodal-search` — owner-reference multimodal retrieval; patient-image diagnosis prohibited.
- `POST /future/voice-search` — client/owner transcription search; File 26 retains no audio.
- `GET|POST|DELETE /future/research-trails` — account-owned canonical-reference collections.
- `GET|POST|DELETE /future/saved-search-alerts` — non-sensitive saved alert registry; File 19 owns delivery.
- `GET|POST|DELETE /future/search-history` — local-first history; server sync only by explicit opt-in.
- `GET|POST /future/recommendation-transparency` — breadth/less-personalization and explanation controls.
- `GET|POST /future/discovery-breadth` — standard/diverse/broad discovery controls.

### Step-up / audit future surfaces

- `POST /future/private-search-vault` — recent step-up plus native-owner authorization; public File 26 index is forbidden.
- `GET|POST /future/relevance-lab` — search-auditor read-only baseline/candidate evaluation; no production policy mutation.

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

## Response, authorization and cache law

Core responses use `X-Sabri-File26-Contract: 1.3`; Future endpoints additionally use `X-Sabri-File26-Future-Contract: sabri.file26.future.v1.3`. File 26 returns `X-Content-Type-Options: nosniff` where it builds the response.

All `/future/*` responses are explicitly `private, no-store, max-age=0`. This conservative rule prevents browser/intermediary persistence of voice, multimodal, research, history, private-vault or query context. Core public anonymous query responses retain the existing bounded public-cache law only where eligibility context is public-safe; personalized/authenticated/saved-query/content-gap/admin responses remain `private, no-store`.

For every non-public Future endpoint, login alone is insufficient: current File 00 membership/identity assertions must be valid and non-suspended. Private Search Vault additionally requires recent step-up verification and native-owner authorization.

Search results preserve canonical owner references and require owner click/action-time revalidation. File 26 does not convert endpoint availability, cache state, model/provider output or a derivative index record into authorization or canonical truth. Unknown/stale freshness remains explicit and never broadens access.

External evidence requires an explicitly approved connector, HTTPS source URL, source name, retrieval time, rights status and provenance. It is labelled external, never silently mixed into organic platform ranking and never declared canonical platform truth.

Errors use safe WordPress REST error codes/messages and trace IDs where applicable. No API response may expose secrets, raw clinical notes, identity evidence, private-message text, unapproved raw sensitive query history or provider-internal payloads.
