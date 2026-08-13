# Privacy and Retention

- Raw query text sampling is disabled by default. Sensitive query classes are dropped/redacted before aggregate metrics.
- Metrics are aggregated by keyed hash/coarse locale and bounded result/safety classes; raw IP, clinical-note, private-message, identity-evidence and payment text are not analytics inputs.
- Recommendation profiles require explicit consent and exclude clinical charts, private messages, payment, donation and identity-evidence signals by default.
- User controls include consent, hide item/creator/topic, not interested, undo, reset and opt out; reset/erasure removes derived personalization and feedback within bounded processing time.
- Account-owned saved queries are an explicit convenience feature, not a hidden recommendation/ranking signal.
- Non-sensitive saved queries default to 365-day retention; the governed setting is bounded to 7–1095 days.
- Sensitive saved queries default to 90-day retention; the governed setting is bounded to 1–365 days. Sensitive query text is stored only through an approved encryption-provider contract. If that provider is unavailable the save fails closed. Sensitive filter/advanced metadata is rejected rather than retained in plaintext.
- Protected saved-query decryption requires fresh step-up authorization. WordPress privacy export does not opportunistically decrypt protected text; erasure deletes the saved-query collection.
- Explicit editorial content-gap submissions require consent, reject sensitive queries, retain no submitting user identity and default to 90-day retention with a 7–365 day governed bound.
- Editorial radar consumes aggregate File 26 metrics and unexpired explicit content-gap records only. It is not a raw user-history browser and does not become File 15 trend truth.
- Tombstones default to 180 days for replay/deletion reconciliation safety.
- Recommendation feedback defaults to 365 days unless reset/erasure occurs earlier.
- Audit defaults to 760 days; high-risk policy history remains subject to approved governance/legal-hold rules.
- Index documents, graph nodes/edges and classifications are derivative. Canonical owner deletion/restriction triggers bounded purge/reconciliation; click-time owner authorization remains mandatory.
- Emergency/safety resource details are not inferred from query telemetry and are not hardcoded by File 26. An owner-supplied resource must carry current verification evidence and pass URL safety rules before File 26 returns it.
- WordPress privacy exporter/eraser exposes only the requesting user’s retained File 26 personal data; no other user data or unapproved raw query history is exported.
