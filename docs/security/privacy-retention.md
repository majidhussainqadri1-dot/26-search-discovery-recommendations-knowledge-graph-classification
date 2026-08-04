# File 26 Privacy and Retention Specification

## Purpose limitation

File 26 processes only the minimum derivative data required for public search, safe recommendations, controlled taxonomy/classification, knowledge-graph navigation, evaluation and operational reconciliation.

It does not create a general activity warehouse or canonical user/content record.

## Data domains

| Domain | Typical data | Classification | Source of truth |
|---|---|---|---|
| Public search documents | canonical key/version, locale, public title/summary/topics, canonical URL | C1 public derivative | numbered owner module |
| Tombstones | key/version/reason/time | operational derivative | numbered owner module |
| Feedback | actor hash, target key, control type, context hash, state | private preference | File 26 |
| Taxonomy/classification | concept IDs, labels, evidence version, reviewer status | controlled governance | File 26 governance |
| Graph edges | endpoint references, type, source owner/version, evidence URL | public or capability-bound derivative | owner/approved curator |
| Query telemetry | aggregate metric and hashed dimensions | minimized analytics | File 26 |
| Operations | jobs, locks, checkpoints, errors, purge evidence | restricted operational | File 26 |
| Exports | short-lived scoped token and generated File 26 package | private rights/operations | File 26 |

## Prohibited general-index data

- private messages and call content;
- patient clinical records, prescriptions and attachments;
- national identity/passport evidence;
- passwords, OTPs, session/provider/payment secrets;
- card/bank/payment details;
- unpublished drafts, rejected content and review-only attachments;
- raw support, appeal, security incident or moderation evidence;
- unrelated contact details and precise private locations.

## Query privacy

Queries are normalized for retrieval. PII-like, clinical or abusive/sensitive queries are classified before telemetry.

Raw query text is not retained for sensitive classes. Public telemetry uses bounded allowlisted dimensions and stable hashes rather than unrestricted text.

Search/recommendation endpoints must not expose another user's history, preferences, saves, feedback or entitlement context.

## Recommendation privacy

- cold-start recommendations require no personal signals;
- personalization requires authentication and explicit consent;
- canonical audience context supplies age and guardian status;
- private messages, clinical records and payment signals are prohibited;
- hidden item/creator/topic and opt-out controls take effect without ranking penalty or public disclosure;
- feedback uses an actor hash and is reversible/purgable.

## Retention baseline

| Data | Baseline | Trigger/exception |
|---|---|---|
| Active search generation | while active | replaced only by validated promotion |
| Predecessor generation | rollback window | remove after accepted rollback/restore window and reconciliation |
| Failed candidate generation | bounded investigation window | preserve longer only for incident/legal evidence |
| Tombstones/purge evidence | through deletion verification and audit window | owner/legal hold may extend |
| Queue jobs/checkpoints | through completion/reconciliation | dead-letter retained for bounded investigation |
| Feedback/preferences | until user reset/opt-out/erasure or policy expiry | legal/security hold where applicable |
| Telemetry aggregates | default 90 days | shorter privacy policy or approved bounded extension |
| Export tokens | until expiry/use, then bounded cleanup | security incident hold |
| Policies/evaluations/audit | governance history | immutable/retained under approved policy |

The runtime schedules telemetry and expired export-token cleanup. Generation/job retention remains an operator policy because rollback, incident and reconciliation state must be considered.

## Deletion propagation

Canonical owner deletion/restriction produces an owner event/tombstone. File 26 must:

1. record the event idempotently;
2. apply the tombstone with chronology checks;
3. remove or suppress the document in active/candidate generations;
4. remove it from suggestions, facets and recommendations;
5. suppress graph edges/nodes where required;
6. purge caches/provider indexes;
7. record completion in the purge ledger;
8. verify absence;
9. retain click-time canonical denial until convergence is proven.

A verified purge must not remain overdue. Failure beyond the approved SLO is an alert/release blocker.

## Data subject and actor controls

File 26 supports scoped export and deletion of File 26-owned preference/telemetry data. It must not claim to export or erase canonical data owned by another file; those requests are dispatched to the appropriate native owner.

Exports exclude:

- other users' data;
- private messages;
- clinical records;
- payment/identity/authentication secrets;
- unrestricted operational logs;
- canonical content bodies not owned by File 26.

## Minors

Minor status is never accepted from an untrusted request parameter. The canonical identity/membership context supplies age and verified guardian consent.

For minors:

- personalization is disabled without verified guardian consent;
- adult-only and persuasive-commerce candidates are excluded;
- private contact/location data is not indexed;
- user controls and opt-out remain available.

## Access control

- public API reads only public-eligible derivatives;
- authenticated context does not automatically broaden access;
- admin/governance endpoints require current capability checks;
- high-risk decisions require independent approval as defined;
- support/analytics roles receive no blanket content access;
- database and CLI access are named, least-privilege operational responsibilities.

## Caching

Guest public query responses may use a short cache. Authenticated, personalized, feedback, export and operations responses are private/no-store.

Cache keys must include generation and normalized request context. Public and authenticated caches must never share personalized or entitlement-dependent output.

## Logging

Logs may include:

- trace/correlation ID;
- stable error code;
- connector/job/generation identifiers;
- bounded timing/count data;
- actor ID/hash only where justified and access-controlled.

Logs must not include raw queries classified as sensitive, private record bodies, secrets, identity documents, payment data or unrestricted stack/SQL details in public responses.

## Backup and restore

Backups containing File 26 private/operational data inherit the same access and retention controls. Deletion verification must document backup-expiry behavior; a backup copy must not be silently restored into an active searchable generation without reconciliation.

## Provider exit

Any future external search, vector, analytics or recommendation provider requires:

- purpose and field inventory;
- contractual security/privacy review;
- region/transfer assessment;
- deletion/export APIs and measured propagation;
- encryption/key and credential controls;
- full rebuild and provider-exit plan;
- post-exit deletion evidence;
- updated threat model, SBOM and File 24 assurance record.

## Truthful compliance boundary

This specification and code do not create a blanket GDPR, HIPAA, ISO or other legal certification claim. Jurisdictional applicability, notices, agreements, records of processing and regulatory duties require qualified organizational review and File 24 governance evidence.
