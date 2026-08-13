# File 26 Future24 — Independent Review Round 2

Baseline: `9227025d10bd860835cdf40ec29b123cf924a98b`, after Round 1 corrections and green QA run `31703437026` on PHP 7.4 and 8.3.

The complete round was finished before this correction batch.

## Defects proved in the completed review

1. Query-planner `execute` treated non-empty string `false` as true.
2. Recommendation `less_personalization` treated string `false` as true.
3. Saved-alert `enabled` used unsafe scalar-to-boolean casting.
4. Search-history `sync_opt_in` accepted string `false` as an affirmative opt-in.
5. Segment-search forwarded the complete raw REST parameter bag to an owner provider instead of a minimized context.
6. Historical-search forwarded the complete raw REST parameter bag to an owner provider instead of a minimized context.
7. Geo/availability forwarded the complete raw parameter bag even though bounded fields were already separated.
8. Semantic reranking accepted an empty query and could launch a broad candidate/reranking path inconsistent with the query-oriented capability contract.

The review also rechecked query-planner and cross-language fan-out. Their internal calls pass through the existing native Search service, whose own independent search rate limiter and bounded scan cap already apply; therefore no separate defect was recorded for that point.

All eight proved defects are corrected in the same post-review batch and must pass full QA before Round 3 starts.
