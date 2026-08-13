# File 26 v1.2.0 — Fresh Adversarial Review Round 2

Date: 2026-08-13 (Asia/Karachi)
Method: the complete fresh/adversarial review was finished before this corrective batch was applied; fixes were then made together and the exact corrected state is sent through the full regression/package gate.

## Frozen review baseline

The review started from the v1.2.0 candidate after Round 1 corrections, including advanced search, protected saved queries, ranking constitution, zero-result recovery, safety diversion, freshness evidence, owner-boundary enforcement and the corrected doctor-ranking public contract.

## Adversarial dimensions reviewed

- deep and replayed pagination/cursor behavior;
- resource-exhaustion and expensive advanced queries;
- stale/cached authorization and private REST caching;
- sensitive query persistence through secondary metadata fields;
- decrypt/read access after authentication without fresh step-up;
- exact-phrase semantics when field-scoped search is requested;
- emergency, self-harm, violence/abuse and medical-risk search classes;
- owner-provided resource freshness and destination safety;
- unknown/stale index evidence and false-green risks;
- free-tier, paid/donor/favoritism ranking corruption;
- File 20 shell / File 25 visual / File 15 trend / File 06 knowledge ownership boundaries.

## Defects found in the completed fresh review

1. A bounded advanced-search continuation could repeat an empty continuation state after the scan ceiling instead of terminating truthfully.
2. New v1.2 central REST routes did not all set an explicit cache/security response policy; authenticated or sensitive responses therefore needed a uniform no-store gate.
3. A saved query could protect the main query text while still allowing sensitive identifiers to survive in saved filter/advanced metadata.
4. Protected saved-query decryption could be attempted for an authenticated user without requiring a fresh step-up assertion.
5. Field-scoped advanced search did not require the requested exact phrase to occur inside the selected field set.
6. The advanced endpoint depended on the underlying search rate limits but lacked its own stricter abuse/resource-exhaustion budget.
7. Search-safety classification covered medical emergencies and malicious harm but needed explicit self-harm and abuse/violence-support classes.

## Corrective batch applied after review completion

- Added continuation-limited termination and a monotonic next-offset rule so bounded deep pagination cannot self-loop.
- Added a central route response-security filter: all saved-query, advanced-search, content-gap and admin central routes are `private, no-store`; the public ranking constitution alone receives a bounded public cache policy.
- Reject sensitive identifiers in saved-query filters/advanced metadata rather than storing them in plaintext.
- Require `saved_query_decrypt` step-up before protected query decryption; privacy export never opportunistically decrypts protected text.
- Enforce field-scoped exact-phrase occurrence when field restrictions are requested.
- Added a dedicated advanced-search rate gate before any bounded federated scan.
- Expanded safety classification for self-harm and violence/abuse/harassment support in English and Urdu while still refusing to fabricate local emergency details.
- Preserved verified/current owner-resource checks, File 15 trend ownership, File 20 shell ownership, File 25 visual ownership and the no-paid/no-donor organic-ranking law.
- Expanded the central-plan regression test file so these defects cannot silently return.

## Acceptance boundary after this review

This review closes the second required post-coding repository review/fix cycle. The candidate is not called Automated-QA Green until the complete PHP 7.4/8.3 matrix, deterministic double package build, clean-extract rerun, manifest parity and all static/behavioral/contract gates pass on the final candidate.

Hostinger staging, deployed package/DB/schema parity, real companion-owner connectors, browser/device/accessibility evidence, backup/restore, rollback rehearsal, Founder staging acceptance, production deployment and live re-test remain separate evidence gates.
