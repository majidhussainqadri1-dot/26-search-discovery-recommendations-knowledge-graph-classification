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

## v1.3 Future Search Intelligence personal-data rules

- Search history is **local-first**: browser/device `localStorage` is the default store. Loading the Future JavaScript creates no automatic history network request.
- Optional server history sync requires an explicit user action/opt-in. File 26 rejects queries caught by the established sensitive-query classifier, de-duplicates by query hash, keeps only the latest 50 entries and allows explicit clearing/disable-sync.
- Research Trails are account-owned and contain only bounded canonical owner/object/version references plus user labels; they do not copy canonical source content.
- Saved Search Alerts are account-owned, bounded to 50 entries and reject sensitive server-side query text. File 26 stores the alert/query registry; File 19 remains notification delivery/retry/channel owner.
- Discovery breadth/less-personalization controls are account-owned preferences and are never donor/payment/favoritism signals.
- Future user meta (`research trails`, `saved-search alerts`, optional `server history`, `discovery controls`) is registered with WordPress privacy export and erasure.
- Private Search Vault never stores its result corpus in File 26’s public index. It requires current membership, recent step-up and native-owner authorization; responses are no-store.
- Voice Search does not retain audio in File 26. Multimodal Search stores no source media in File 26 and prohibits patient-image diagnosis.
- All `/future/*` responses are `private, no-store, max-age=0` to prevent intermediary/browser persistence of query, voice, multimodal, research or private-vault context.
- Approved external evidence connectors are a separate retrieval lane. File 26 stores no secret/provider credentials in result payloads and requires source/provenance/rights metadata before returning a record.
- WordPress privacy exporter/eraser exposes only the requesting user’s retained File 26 personal data; no other user data or unapproved raw query history is exported.
