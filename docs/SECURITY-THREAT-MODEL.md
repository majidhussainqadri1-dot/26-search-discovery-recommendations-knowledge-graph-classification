# Security and Threat Model

## Protected assets

Visibility envelopes, connector manifests, ranking/taxonomy policy, recommendation profiles, audit evidence, tombstones, reindex state and public trust.

## Principal threats and controls

- **IDOR/BOLA and stale visibility:** index/query/click checks; fail closed; eligibility-aware cache keys.
- **Event replay/out-of-order:** source version, sequence, checksum, idempotent upsert and tombstones.
- **Search injection/XSS/SQLi:** query sanitation, prepared SQL, late escaping, bounded values.
- **SSRF/open redirect:** same-origin canonical URLs and connector allowlists.
- **Autocomplete/query leakage:** public-only suggestions; sensitive query detector; no raw recent-query echo.
- **Graph inference:** allowed typed edges, visible endpoints, hop-by-hop checks, bounded depth/degree.
- **Ranking abuse:** capped popularity, diversity, versioned policy, prohibited financial/favoritism signals, dual approval.
- **Sybil feedback:** login, idempotency, rate limits, reversible bounded influence.
- **Resource exhaustion:** request limits, candidate cap, cursor pagination, bounded batches, lock/retry/dead-letter.
- **Privileged misuse:** capabilities, step-up, separation of duties, reason and audit.
- **Secrets exposure:** no credentials in manifests, options, logs, responses or repository.

Public activation remains fail-closed until connector, threat-model, migration, privacy and staging evidence is accepted.
