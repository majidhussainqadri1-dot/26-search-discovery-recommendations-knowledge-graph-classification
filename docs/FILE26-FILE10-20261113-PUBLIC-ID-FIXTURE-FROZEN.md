# File26 ↔ File10 V12.1: Frozen public-ID fixture review — 2026-11-13

## Exact baseline and scope
- Target repository: `majidhussainqadri1-dot/26-search-discovery-recommendations-knowledge-graph-classification`.
- Immutable reviewed base: Draft File26 PR #22 exact SHA `c0dc5ac09fdf068f5e77dab0e7f0862a21f33cb1`.
- Cross-checked advanced File10 Draft PR #18 SHA `0cb0b24c4734271baf61417d5f534dd1ec185b67`, with modern source-only proposed integration PR #21 SHA `e668f39a6dc2549e49f98274f6416fe76b75220e`.
- Real WordPress/MariaDB integration, File10 deployment, File26 activation, site version/DB/migration and live runtime **unverified**.

## Frozen verified gap F26-FIXTURE-001
File26's disposable WordPress/MariaDB test `tests/file26-20261109-native-file10-public-owner.php` creates File10 owner rows with **noncanonical** public IDs:
- `f26_channel_demo_20261109`
- `f26_video_demo_20261109`
- `f26_private_demo_20261109`
- `f26_live_demo_20261109`.

The *modern* canonical File10 `VWLB_Helpers::is_public_id` accepts exactly `^[a-z][a-z0-9]*_[a-z0-9]+$` (single underscore, source owner prefix + lowercase opaque token), whereas the older File26 fixtures contain several underscores. The newly reviewed File10 adapter correctly rejects such IDs, so the File26 acceptance test could otherwise fail for the wrong reason. This is an isolated **test-fixture contract defect**, not evidence of a live service bug.

## Smallest correction and controls
1. Replace the four fixture IDs only with valid owner-compatible IDs preserving type meaning.
2. Assert `VWLB_Helpers::is_public_id` validates each before writing any fixture row.
3. Do not relax File10 canonical grammar, owner authorization, media visibility/consent policy or File26 status gates.
4. Keep File26 PR #22 Draft; do not claim the test proves real cross-plugin staging. With File10 PR #21 connector intentionally dormant, a real integration test should continue to require explicitly authorized registration and eventually versioned tombstone acceptance.

## No production claim
Changes are source-only in a new Draft PR. No File26 production plugin, staging/live database or File10 code is modified. Exact deployed code is unverified; repository-based diagnosis provisional.
