# File 26 Future Search Intelligence REST Contract v1.3

Namespace: `sabri-search/v1`  
Future contract: `sabri.file26.future.v1.3`

This is the additive v1.3 Future contract. The existing File 26 core/central-plan REST contract remains in force for the v1.2 search foundation and its independently merged twenty-round corrective hardening.

## Future endpoints

All routes are under `/future/*`:

1. `POST /future/conversational-grounded-search` — F26-FUT-01
2. `POST /future/query-planner` — F26-FUT-02
3. `POST /future/cross-language-search` — F26-FUT-03
4. `POST /future/semantic-rerank` — F26-FUT-04
5. `POST /future/multimodal-search` — F26-FUT-05
6. `POST /future/voice-search` — F26-FUT-06
7. `GET|POST /future/segment-search` — F26-FUT-07
8. `GET|POST /future/find-similar` — F26-FUT-08
9. `GET|POST /future/research-search` — F26-FUT-09
10. `POST /future/result-clusters` — F26-FUT-10
11. `GET /future/graph-path` — F26-FUT-11
12. `GET /future/evidence-map` — F26-FUT-12
13. `GET /future/disambiguate` — F26-FUT-13
14. `GET|POST /future/historical-search` — F26-FUT-14
15. `GET|POST|DELETE /future/research-trails` — F26-FUT-15
16. `GET|POST|DELETE /future/saved-search-alerts` — F26-FUT-16
17. `GET|POST|DELETE /future/search-history` — F26-FUT-17
18. `GET|POST /future/recommendation-transparency` — F26-FUT-18
19. `GET|POST /future/discovery-breadth` — F26-FUT-19
20. `GET|POST /future/geo-availability` — F26-FUT-20
21. `GET|POST /future/search-modes` — F26-FUT-21
22. `POST /future/private-search-vault` — F26-FUT-22
23. `POST /future/external-evidence` — F26-FUT-23
24. `GET|POST /future/relevance-lab` — F26-FUT-24

## Authorization classes

- Public derivative retrieval: FUT-01/02/03/04/07/08/09/10/11/12/13/14/20/21/23.
- Authenticated member with current non-suspended File 00 assertion: FUT-05/06/15/16/17/18/19.
- Recent step-up plus native-owner authorization: FUT-22.
- Search-auditor capability: FUT-24.

Login never substitutes for current membership/identity state. Provider data never substitutes for native-owner authorization.

## Provider-attestation law

Provider-dependent routes fail closed or return an explicit unavailable state. Where owner/private/public eligibility is material, the provider envelope must attest one of the exact approved states used by the runtime, including `owner_authorized`, `owner_revalidated_for_request`, `approved_external_public`, `grounded_non_prescriptive`, or `immutable_query_snapshot` as applicable.

Multimodal, owner voice transcription, segments, find-similar seed, research special constraints, graph paths, evidence maps, historical snapshots, private-vault results and external evidence all retain a native-owner/provider boundary. File 26 does not infer missing evidence.

## Query disclosure and external evidence

Sensitive queries bypass optional grounded-answer, cross-language and semantic-reranking providers. External evidence rejects sensitive queries entirely and requires explicit `external_consent` before the query can be sent to a connector. The connector must be separately approved, returned evidence must be HTTPS/provenance/rights-complete, and the lane is never silently merged into organic platform ranking.

## Advanced command truthfulness

FUT-02 Query Planner, FUT-09 Research Mode and FUT-21 Search Modes reuse the hardened File 26 central advanced-search contract for supported author/source/date/language/location/type constraints. Research `evidence_level` or `edition` requires an owner-revalidated constraint provider, which may only select/reorder already eligible File 26 result keys.

## Response/cache law

Every `/future/*` response is `private, no-store, max-age=0` and carries the Future contract header. This conservative policy prevents intermediary/browser caching of voice, multimodal, history, research or private-vault context. Click/action time must revalidate canonical-owner visibility/authority.

## Safety invariants

- no autonomous diagnosis, prescription, dose or potency;
- patient/clinical image diagnosis prohibited;
- current results never substituted for historical snapshot truth;
- private-vault retrieval never uses the public File 26 index;
- external evidence never becomes canonical platform truth;
- donation/payment/advertising/followers/Founder favoritism never become organic ranking signals;
- File 19 owns alert delivery; File 07 owns doctor truth; File 08 owns clinic/appointment/availability truth.
