# File 26 Future Search Intelligence — Privacy & Retention Addendum v1.3

This addendum extends, and does not replace, the existing File 26 privacy/retention rules and the independently merged v1.2 twenty-round privacy hardening.

## Local-first search history

- Browser/device local storage is the default history store.
- Merely loading `file26-future.js` sends no history to the network.
- Server sync requires explicit user action and current authenticated membership.
- Queries classified as sensitive by File 26 are never accepted into optional server history.
- Optional server history is de-duplicated and bounded to the latest 50 entries.
- The user may clear server history and disable sync.

## Account-owned Future data

Research Trails, Saved Search Alerts, optional server search history and discovery controls are account-owned WordPress user-meta collections. They use bounded collection sizes and compare-and-swap write protection so concurrent requests fail with a conflict instead of silently losing a sibling update.

Research Trails store canonical owner/object/version references only; they do not copy native source content. Saved Search Alerts reject sensitive query text and delegate delivery/retry/channel ownership to File 19.

## WordPress privacy lifecycle

Future personal collections are registered with WordPress personal-data export and erasure. Export is paged by collection instead of serializing all retained Future data in one response. Erasure removes the requesting user's Future collections and server-history opt-in state.

## Private Search Vault

The Private Search Vault is isolated from the public File 26 index. It requires current membership, recent step-up and an `owner_authorized` native-owner provider envelope. Results are derivative/open-contract references only and every Future response is no-store.

## Voice and multimodal privacy

File 26 does not retain source audio or media. An owner audio/media adapter must explicitly attest current authorization. Patient/clinical image diagnosis is prohibited. A multimodal adapter whose derived query becomes empty after sanitization is rejected instead of falling through to a broad search.

## Optional semantic/generative providers

Queries classified as sensitive bypass the grounded-answer provider, cross-language semantic provider and semantic reranker provider. Local/core eligible search and extractive discovery remain available under the ordinary File 26 safety rules.

## External evidence disclosure

External evidence is a separate lane. Before any query can be sent to an approved external connector:

- the query must be non-sensitive;
- the request must carry explicit `external_consent`;
- the connector must be governance-approved;
- the provider must attest `approved_external_public`;
- each returned item must carry source, HTTPS URL, retrieval time, rights status and provenance.

External results are not canonical platform truth and are never silently merged into organic ranking.

## Owner truth and action-time revalidation

File 07 remains doctor truth owner. File 08 remains clinic/appointment/availability truth owner. File 26 may consume owner-revalidated discovery constraints but cannot create appointment availability truth. Click/action time owner revalidation remains mandatory.

## Retention principle

No Future feature expands File 26's authority to retain clinical charts, private messages, identity evidence, payment data, unpublished drafts, raw external-provider secrets or hidden sensitive query histories. Existing File 26 retention bounds, deletion reconciliation and privacy-minimization rules remain controlling unless a later Founder-approved change-control amendment explicitly changes them.
