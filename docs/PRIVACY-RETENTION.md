# Privacy and Retention

- Query text sampling is disabled. Sensitive query classes are dropped/redacted before metrics.
- Metrics are aggregated by a keyed hash, coarse locale and result class; no raw IP or clinical/message/payment text.
- Recommendation profile requires explicit consent and excludes clinical charts, private messages and payment signals by default.
- User controls: consent, not interested/hide, reset and opt out; reset/erasure removes derived profile and feedback within bounded processing time.
- Tombstones: default 180 days for replay safety.
- Feedback: default 365 days unless user reset/erasure occurs earlier.
- Audit: default 760 days; high-risk policy history is retained according to approved governance.
- Index documents, nodes, edges and classifications are derivative and purged/rebuilt with canonical owner deletion/restriction.
- WordPress privacy exporter/eraser exports only the user’s retained personalization data; no other user or raw query data.
