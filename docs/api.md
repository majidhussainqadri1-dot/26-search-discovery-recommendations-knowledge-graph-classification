# File 26 API Reference 1.0.0

Base namespace:

```text
/wp-json/sabri-search/v1
```

## Global API laws

- Canonical owner modules remain the source of truth.
- Result destinations require click-time owner visibility and authorization revalidation.
- Public responses contain derivative public metadata only.
- Authenticated or personalized responses use private/no-store caching.
- Request lists and numeric values are bounded and type-checked.
- Associative objects are not silently accepted as list parameters.
- Cursor tokens are opaque, signed, query-bound and generation-bound.
- Errors use stable `sabri_file26_*` codes and do not expose SQL, secrets or stack traces.
- Route availability never grants authorization.

## Public query routes

### `GET /query`

Federated active-generation search.

Parameters:

| Parameter | Type | Bound | Meaning |
|---|---|---:|---|
| `q` | string | required, max 2,000 raw bytes | Search query |
| `limit` | integer | 1–50, default 20 | Results per page |
| `cursor` | opaque string | max 1,024 bytes | Signed continuation cursor |
| `domains` | CSV or list of strings | max 20 | Canonical domain filters |
| `locales` | CSV or list of strings | max 20 | Locale filters |

Representative response:

```json
{
  "generation_id": "generation-identity",
  "results": [
    {
      "document": {
        "canonical_domain": "file21",
        "canonical_object_id": "publication-123",
        "canonical_url": "https://sabrihomeopathy.com/news/example/"
      },
      "score": 720,
      "explanations": ["exact-title", "authoritative-source"],
      "policy_version": "1.0.0",
      "click_visibility_recheck_required": true
    }
  ],
  "next_cursor": null
}
```

Guest responses may use a short public cache. Authenticated responses are private/no-store.

### `GET /suggest`

Prefix suggestions from eligible active-generation documents.

Parameters:

- `q`: required prefix; at least two normalized characters.
- `limit`: 1–20, default 10.

Only visible public-safe candidates are returned. Raw recent queries are not echoed or retained.

### `GET /facets`

Eligibility-aware facet counts derived from an actual query snapshot.

Parameters:

- `q`: required.
- `domains`: optional, maximum 20.
- `locales`: optional, maximum 20.

Facets include bounded domain, locale, state and content-type counts. Restricted documents are filtered before counting.

### `GET /recommendations`

Cold-start or consented recommendations.

Parameters:

| Parameter | Meaning |
|---|---|
| `personalization_consent` | Explicit boolean; personalization requires authentication |
| `interests` | Consented declared topics; maximum 100 |
| `follows` | Consented creator IDs; maximum 100 |
| `learning_topics` | Consented learning topics; maximum 100 |
| `saved_topics` | Consented saved topics; maximum 100 |
| `hidden_items` | Items excluded by the user; maximum 100 |
| `hidden_creators` | Creators excluded by the user; maximum 100 |
| `hidden_topics` | Topics excluded by the user; maximum 100 |
| `limit` | 1–50, default 20 |

Rules:

- guest personalization is rejected;
- minors require verified guardian consent from the canonical audience context;
- private messages, clinical data and payment signals are not recommendation inputs;
- response explanations include reasons such as source quality, declared interest, continuing learning or cold-start curation;
- every result exposes hide, not-interested, creator/topic controls, reset and opt-out affordances;
- personalized responses are private/no-store.

### `POST /recommendation-feedback`

Authenticated feedback operation.

Required fields:

```json
{
  "target_key": "file21:publication-123",
  "type": "hide_item",
  "idempotency_key": "64-character-sha256",
  "context_hash": "64-character-sha256"
}
```

Supported feedback types:

`like`, `save`, `hide_item`, `hide_creator`, `hide_topic`, `not_interested`, `reset`, `opt_out`.

Feedback is idempotent, reversible, actor-hashed and purgable by actor context.

### `GET /topics/{concept}`

Public controlled-taxonomy topic projection with active graph relations.

Response invariants:

- taxonomy state is `approved` or `active`;
- graph edges are hash-verified and provenance-validated;
- generated medical claims are explicitly false;
- canonical ownership is not replaced;
- click-time owner revalidation remains required.

## Administrator governance routes

Administrative routes require an authenticated operator and the capability enforced by the controller. High-risk actions additionally require reason, policy state, versioning, independent approvals or exact current evidence as applicable.

### Health and evidence

- `GET /admin/health`
- `GET /health`
- `GET /operations`

The module health route reports code/schema identity and explicitly keeps:

```json
{
  "status": "coded-complete-candidate",
  "staging_accepted": false,
  "live_deployed": false,
  "operational": false
}
```

This prevents code or CI evidence from being misrepresented as production acceptance.

### Taxonomy

- `GET /admin/taxonomy`
- `POST /admin/taxonomy`

Controlled terms are versioned. Active labels/aliases must be unambiguous. Parent cycles are prohibited. Merge/split states require a distinct redirect target and an impacted-edge/result review.

### Knowledge graph

- `POST /admin/graph-edge`

An edge requires existing endpoint identities, a controlled edge type, exact owner/version provenance and an HTTPS evidence URL. `source_owner` must own the source endpoint or be the approved `file26-curated` authority.

### Classification

- `POST /admin/classification`

High-impact classifications cannot be self-reviewed. Suggestion, review, rejection, appeal and evidence versions remain auditable.

### Policy configuration

- `POST /admin/policy`

Policies are versioned and rollbackable. High-risk policies require two independent approvals and an exact predecessor identity.

### Evaluation

- `POST /admin/evaluation`

Evaluation sets contain reviewed Urdu/English cases, expected canonical results, forbidden results and safety-critical flags. Any forbidden hit or incomplete safety-critical case blocks release.

### Telemetry

- `GET /admin/telemetry`

Telemetry is aggregate, bounded and privacy-minimized. Raw PII/clinical queries are not retained. Dimensions are allowlisted, hashed and subject to retention purge.

### Exports

- `POST /admin/export-token`
- `GET /export`

Export tokens are signed, short-lived, scoped and single-use at the persistent boundary. Exports exclude other-user data, private messages, clinical records, secrets and canonical owner content not belonging to File 26.

## Operations routes

### `POST /operations/dead-letter/replay`

Required:

```json
{
  "job_id": "64-character-sha256",
  "expected_error_code": "connector-execution-failed"
}
```

Replay is rejected unless the job is currently dead-lettered, the exact current error matches, the generation is still building, the connector checkpoint is incomplete and the replay ceiling has not been reached.

### `POST /operations/connectors/{connector}/probe`

Optional parameters:

- `batch_limit`: 1–200, default 50.
- `maximum_pages`: bounded, default 50.

Probe is read-only. It checks terminal pagination, repeated cursors, canonical ownership, duplicate document/tombstone identities and deterministic checksum.

## WP-CLI

```bash
wp sabri-file26 jobs run --max=20 --batch=100
wp sabri-file26 jobs recover
wp sabri-file26 operations status
wp sabri-file26 dead-letter replay --job=<sha256> --error=<current-code>
wp sabri-file26 connector probe --connector=file-21-publications --batch=50 --pages=50
wp sabri-file26 reconcile --limit=100
wp sabri-file26 telemetry-purge --days=90
```

CLI commands reuse the same runtime, queue, locks, registry and database state as WP-Cron and REST operations. They do not create a parallel execution path.

## Integration with File 20 and File 25

File 20 owns the global search field, routing and shell. File 25 owns result-card and topic/profile presentation. They consume File 26 responses but must not:

- query File 26 tables directly;
- remove `click_visibility_recheck_required`;
- treat cached visibility as authorization;
- mutate canonical owner records;
- create a second ranking, recommendation or taxonomy backend.
