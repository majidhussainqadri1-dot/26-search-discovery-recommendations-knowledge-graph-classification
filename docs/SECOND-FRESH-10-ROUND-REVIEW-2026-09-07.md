# File 26 — Second Fresh 10-Round Review Cycle — 2026-09-07

Branch: `review/file26-second-fresh-20-round-2026-09-07`
Governing sequence: **complete review → ledger freeze → fix all frozen defects → regression → exact-head CI → next round**.

## Round 1 — CLOSED after corrections
Round 1 baseline was `9067d19a82eeae59713a705cd16f3e56a3ec435e`. Its original and CI-continuation ledgers record R1-D1 through R1-D22. The final corrective HEAD `cd809b466d54855b567ddbbd7f1114c266555a76` passed File 26 exact-head GitHub Actions run `34143860744` on both PHP 7.4 and PHP 8.3. No Round 2 review began before that green exact-head gate.

## Round 2 — Security, membership, authorization and privacy lifecycle — CLEAN
Reviewed the current exact-head security primitives, File 00 membership validity handling, capability separation, step-up gate, cursor signing/verification, rate limiting, audit persistence/minimization, safe URL handling, WordPress privacy export, atomic erasure/redaction and File 26 duty-role model. No new repository defect was proven in this round. The unchanged exact HEAD remained the already-green `cd809b466d54855b567ddbbd7f1114c266555a76`.

## Round 3 — Search, filtering, retrieval and graph-ranking truth — CLOSED after corrections
The complete Round 3 review found four defects. No correction was started until this review section was complete and the findings below were frozen.

- **R3-D1 — High — country/location filters were normalized with `sanitize_key()` although indexed values are stored as text.** This broke case, spaces and Urdu/Unicode location values.
- **R3-D2 — High — explicit language filtering was intersected with the UI/request locale.** Cross-language discovery could therefore be suppressed even when the user explicitly selected another content language.
- **R3-D3 — High — taxonomy classification-only topic matches were admitted by SQL and then rejected by derivative-only `topic_ids_array` eligibility.** Semantic candidates also lacked equivalent classification-topic filtering.
- **R3-D4 — Medium — graph relationship ranking silently truncated each candidate chunk at 5,000 edges without disclosing partial ranking state.**

Corrections preserve country/location as bounded text and compare them with Unicode-aware text folding; make explicit language authoritative for content-language filtering; enforce approved/corrected taxonomy classification topics consistently in lexical and semantic retrieval; and detect the 5,001st graph edge as a sentinel so a 5,000-edge bounded signal is disclosed through `graph_signal_limit` partial state. Regression `tests/review-round-29-search-filter-and-graph-truth.php` locks these invariants. Exact-head GitHub Actions run `34144427633` on `f44c9f946d8e6dbe31c3668ba536869555834551` completed successfully. Round 4 did not begin before that green gate.

## Round 4 — Index lifecycle, connectors, shadow rebuild, revocation and reconciliation — DEFECTS FROZEN
The complete Round 4 review covered incremental indexing/tombstones, connector eligibility, governed shadow rebuild, job claiming/retry/dead-letter behavior, cutover, reconciliation, cache purge and retention. No correction was started until all findings below were frozen.

- **R4-D1 — Critical — shadow rebuild drops the authoritative version of restrictive/deletion records.** `Shadow_Reindex::stage()` currently returns success without staging a source item whose state is `deleted`, `suspended`, `restricted`, `rejected`, `private`, or whose visibility is `restricted`. At cutover, an older active document absent from the shadow receives a `reindex_absent` tombstone using the **old active document version**, not the newer restrictive source version that caused its absence. A delayed or replayed publish event with a version greater than that old tombstone but not greater than the actual restriction/deletion version can therefore resurrect a record that the canonical owner had already revoked. Shadow rebuild must retain revocation-version evidence and use it when cutover creates tombstones, while keeping restrictive rows out of the searchable promoted index.

- **R4-D2 — High — shadow and incremental indexing use two independently maintained document/payload sanitizers that have already diverged.** Incremental `Indexer::sanitize_payload()` allows one bounded set and treats ordinary `_score` fields as 0..1 (with `doctor_rank_score` as 0..100). `Shadow_Reindex::safe_payload()` permits additional source/publisher/access keys not accepted by incremental indexing and treats every `_score` as 0..100. The duplicated `sanitize_document()` logic likewise creates two projection contracts. The same canonical source document can therefore produce materially different derivative payload/checksum/ranking inputs depending on whether it arrived incrementally or through rebuild. Shadow staging must consume the Indexer’s single canonical preparation contract rather than duplicate it.

- **R4-D3 — Critical — a connector’s shadow batch is not proven to belong to the requested connector or requested partial scope before cutover.** `enqueue()` binds a job to connector A and optional domain/entity/locale/version scope, but `stage()` merely validates each returned item according to the item’s own `connector_slug`. A defective or compromised connector-A callback can return an otherwise valid connector-B document or an out-of-scope document. The shadow table accepts it, while cutover deletes only connector A’s requested active scope and then promotes **all** shadow rows. This crosses canonical-owner/scope boundaries. Every staged item must be proven to match the job connector and all requested scope constraints before it can enter the candidate index.

Status at freeze: **3 defects; corrections not yet applied.**
