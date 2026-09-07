# File 26 — Second Fresh 10-Round Review Cycle — 2026-09-07

Branch: `review/file26-second-fresh-20-round-2026-09-07`
Governing sequence: **complete review → ledger freeze → fix all frozen defects → regression → exact-head CI → next round**.

## Round 1 — CLOSED after corrections
Round 1 baseline was `9067d19a82eeae59713a705cd16f3e56a3ec435e`. Its original and CI-continuation ledgers record R1-D1 through R1-D22. The final corrective HEAD `cd809b466d54855b567ddbbd7f1114c266555a76` passed File 26 exact-head GitHub Actions run `34143860744` on both PHP 7.4 and PHP 8.3. No Round 2 review began before that green exact-head gate.

## Round 2 — Security, membership, authorization and privacy lifecycle — CLEAN
Reviewed the current exact-head security primitives, File 00 membership validity handling, capability separation, step-up gate, cursor signing/verification, rate limiting, audit persistence/minimization, safe URL handling, WordPress privacy export, atomic erasure/redaction and File 26 duty-role model. No new repository defect was proven in this round. The unchanged exact HEAD remained the already-green `cd809b466d54855b567ddbbd7f1114c266555a76`.

## Round 3 — Search, filtering, retrieval and graph-ranking truth — DEFECTS FROZEN
The complete Round 3 review found the following defects. No correction was started until this review section was complete and these findings were frozen.

- **R3-D1 — High — country/location filters are normalized with `sanitize_key()` although indexed values are stored as text.** `Indexer` stores `country` and `location` with `sanitize_text_field()`, preserving spaces, Unicode and case. `Search::sanitize_filters()` instead applies `sanitize_key()` to both. `Search::eligible_row()` then compares the stored text and sanitized filter with strict PHP string equality. This breaks values such as `Pakistan` vs `pakistan`, multi-word locations such as `New York` vs `newyork`, and Urdu locations which can be stripped to an empty key. This contradicts worldwide and Urdu/English discovery requirements.

- **R3-D2 — High — explicit language filtering is intersected with the UI/request locale and can make valid cross-language discovery impossible.** Search always adds the request/default locale constraint before separately applying `filters[language]`; `eligible_row()` repeats the same locale gate before checking the explicit language filter. A user browsing in one locale and selecting another content language can therefore receive zero results even when matching content exists. Explicit language filtering must be authoritative for content-language eligibility while the request locale may still be retained for query/provider context.

- **R3-D3 — High — taxonomy classification-only topic matches are admitted by SQL and then rejected by `eligible_row()`.** The lexical SQL intentionally accepts a topic either from derivative `topic_ids` or from an approved/corrected row in File 26 `classifications`. `eligible_row()` subsequently requires the topic to exist in `topic_ids_array` only, discarding classification-only matches. `Taxonomy::classify()` stores classification state independently, so this is a real contradiction rather than redundant defense. Semantic candidate retrieval also needs the same approved/corrected classification topic constraint before the common eligibility gate stops rechecking derivative-only topic IDs.

- **R3-D4 — Medium — graph relationship ranking silently truncates each candidate chunk at 5,000 edges without disclosing partial ranking state.** `apply_graph_relationship_scores()` uses `LIMIT 5000`. When more than 5,000 active public edges touch a chunk, relationship counts and ranking signals are incomplete but the response does not add any partial/degraded marker. The governing architecture requires bounded scans to disclose truthful partial state rather than silently present incomplete ranking evidence as complete.

Status at freeze: **4 defects; corrections not yet applied.**
