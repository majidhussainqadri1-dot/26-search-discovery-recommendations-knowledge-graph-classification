# File 26 — Round 1 CI Continuation Ledger — 2026-09-07

Parent ledger: `docs/SECOND-FRESH-20-ROUND-REVIEW-2026-09-07.md`
Branch: `review/file26-second-fresh-20-round-2026-09-07`
Baseline HEAD: `9067d19a82eeae59713a705cd16f3e56a3ec435e`

This file continues the frozen Round-1 regression-gate findings after R1-D15. The same governing sequence applies: **evidence → freeze finding → correction → exact-head CI**. Round 2 remains prohibited until Round 1 exact-head CI is green.

- **R1-D16 — High — Round-20 interpolation scanner falsely classifies escaped or single-quoted literals as executable interpolation:** exact-head run `34087807240` on `8dc844f08c60448e3f39c2b4d6d89dd55a664c80` passed every sequential review regression through both Round-19 gates, then `tests/review-round-20-release-evidence.php` rejected four already-passing regression files as “interpolation-prone.” Inspection proves the flagged `$document`, `$manifest`, and `$this` occurrences are explicitly escaped inside double-quoted PHP strings, while the `$audience` occurrence is inside a single-quoted regex literal. Those forms do not interpolate at runtime. The scanner regex does not distinguish escaped dollars or PHP single-quoted literals and therefore produces false positives against safe test code.

- **R1-D17 — High — Round-24 search eligibility/index atomicity regression is source-format coupled:** exact-head run `34087942821` on `6402fa84f9a65104764f1dc91672c8ecb7f1957e` passed all sequential regression gates through Round 23, then failed on the first Round-24 safeguard with `Missing search safeguard: 'audience' => $audience_fingerprint`. Inspection of `class-file26-search.php` confirms the cursor context includes the audience fingerprint as `'audience'=>$audience_fingerprint`, so cursor validity is bound to the current eligibility context. The regression compares a spaced source literal and exits on first mismatch. The same test also contains additional formatting-sensitive source needles across Search, REST and Indexer; the whole Round-24 regression must be normalized semantically rather than repaired one literal at a time.

R1-D16 and R1-D17 were frozen here before their corrections.
