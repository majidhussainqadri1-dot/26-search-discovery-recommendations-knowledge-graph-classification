# File 26 — Three-Plan Corrective Review and Correction Record 1.1.0

## Governing scope

This corrective release was reviewed against three governing baselines:

1. **Sabri Social Homeopathy Platform — Definitive Master Plan v3.0**;
2. **Consolidated All-Chats Recovered Directive Register v2.1**;
3. **File 26 — Search, Discovery, Recommendations, Knowledge Graph and Content Classification — Four-Round Reviewed Master Plan v1.0**.

The review preserves canonical ownership: File 26 owns derivative indexing, federated retrieval, query understanding, ranking policies, consent-first recommendations, taxonomy/classification orchestration and owner-sourced graph projections. It does not own canonical content, identity, the global shell or File 25 visual truth.

## Truthful status

| Status | File 26 1.1.0 corrective candidate |
|---|---|
| Specified | Complete within the approved File 26 scope |
| Coded | Corrective source implemented on the review branch |
| Packaged | Pending final green deterministic build at the reviewed head |
| Automated-QA Green | Pending final exact-head GitHub Actions result |
| Staging-Accepted | Not claimed |
| Live-Deployed | Not claimed |
| Operational | Not claimed |

## Round 1 — findings and corrections

### F26-COR-001 — ranking policy did not control runtime ranking

**Finding:** policy rows and versions could be activated while runtime scoring continued to use hard-coded weights.

**Correction:** `Ranking::policy()` now loads the active `search/public` policy and bounds its weights, diversity cap and safety floor. The scoring service consumes those values. Behavioral QA proves that an active policy materially changes the score.

**Rollback:** governance restores the most recent previously active policy under dual approval. File 26 remains available and is not globally disabled as a rollback side effect.

### F26-COR-002 — shadow connector data could enter public retrieval

**Finding:** shadow, approved and active connectors were all treated as retrieval-eligible.

**Correction:** shadow and approved states remain index-validation lanes; only `active` is a public/member retrieval lane. Search and autocomplete both join only active connectors.

### F26-COR-003 — connector code could self-activate

**Finding:** a newly registered manifest could declare itself active on first insertion.

**Correction:** every new connector and every owner/contract change is persisted as `proposed`. Existing governance state survives code reloads. Only the governed lifecycle may transition a connector to active.

### F26-COR-004 — hidden 200–500 candidate ceiling

**Finding:** search ranked only a small recent candidate subset, causing older relevant records to disappear without disclosure.

**Correction:** search performs configurable chunked scans with bounded maximums and reports `scan_limit` as a truthful partial result when the bound is reached. It never represents a bounded search as complete.

**Staging gate:** representative large-corpus relevance and latency testing remains required before public activation.

### F26-COR-005 — exact phrase, typo tolerance and transliteration were incomplete

**Correction:** bounded exact-phrase parsing, Roman-Urdu/Urdu candidates, prefix retrieval terms and edit-distance token similarity were added without storing raw query histories.

### F26-COR-006 — classification decisions did not control topic retrieval

**Correction:** topic filtering consumes only `approved` or `corrected` classification assignments. Public topic routes query by the canonical stable term UUID instead of label coincidence.

### F26-COR-007 — knowledge graph was isolated from discovery

**Correction:** only active, public and provenance-governed edges contribute a bounded relationship score. Graph data never grants access or replaces owner authority.

### F26-COR-008 — degraded source state was always empty

**Correction:** active/degraded connector health and bounded-scan state now populate `partial_domains`. Public interfaces disclose partial results.

### F26-COR-009 — recommendation controls were API-only or incomplete

**Correction:** the public experience now includes explicit consent, selected interests, helpful, not interested, hide item, hide author, hide topic, undo, reset and opt-out controls. Guest/session context is request-bound and is not persisted as a hidden profile.

### F26-COR-010 — separation of duties was ineffective

**Finding:** configuration authority implied operator, curator, ranking-approver and auditor powers.

**Correction:** dedicated institutional roles and capabilities were created. The administrator receives configuration authority only; operational powers require explicit assignment. Silent CLI step-up bypass was removed.

### F26-COR-011 — doctor ranking was hard-coded and non-appealable

**Correction:** doctor ranking now supports a versioned `doctor_global/public` policy, safe disclosed fallback, global Top 10/100/1000/All Verified tiers, contextual country/city/language/specialization/educator/researcher views and evidence-bound appeals.

**Fairness law:** donation, payment, paid promotion, follower count and Founder favoritism are excluded and disclosed.

### F26-COR-012 — doctor ranking appeal lacked lifecycle/privacy law

**Correction:** the appeal system has a dedicated table, verified affected-party authorization, conflict checks, optimistic concurrency, bounded evidence, reasoned final decisions, privacy export, pseudonymizing erasure and retention expiry.

### F26-COR-013 — required owner integrations were asserted without evidence

**Correction:** Files 03, 05, 06, 07, 08, 10, 11, 12, 15, 18 and 21 now have a strict owner-contract catalogue. Missing modules remain explicitly missing; no synthetic connector is marked active.

**Activation gate:** all required owner contracts must be active and File 00, 20, 24 and 25 evidence, staging acceptance, migration rehearsal and rollback rehearsal must be explicitly supplied.

### F26-COR-014 — uninstall omitted new institutional roles and appeal data

**Correction:** scheduled jobs and File 26 roles/capabilities are always removed. Data is retained by default. Tables, including ranking appeals, are purged only after explicit destructive-uninstall opt-in.

## Automated evidence added

- exact phrase, transliteration, typo tolerance and query matching;
- active policy runtime behavior;
- prohibited ranking signal exclusion;
- safe doctor-policy fallback;
- connector production-lane isolation;
- forced proposed lifecycle for new/changed connectors;
- approved classification and active public graph semantics;
- dual-approved policy rollback without module disablement;
- separation of duties and no CLI step-up bypass;
- recommendation controls and non-persistent session context;
- doctor contextual views and appeals;
- owner-contract and activation evidence gates;
- privacy export/erasure, appeal retention and uninstall boundaries;
- deterministic double build and clean-extract rerun.

## Remaining external acceptance gates

The following are not source-code defects and remain separate acceptance obligations:

1. real owner-module adapters and frozen contract versions;
2. File 00 membership assertions;
3. File 20 shell/search placement;
4. File 24 assurance evidence;
5. File 25 result-card/doctor-card rendering;
6. Urdu/English relevance benchmark approval;
7. large-corpus performance/load evidence;
8. browser/device/RTL/accessibility acceptance;
9. fresh install, upgrade and migration dry run;
10. backup/restore and rollback rehearsal;
11. Founder staging approval;
12. controlled production deployment and operational SLO evidence.

No staging, live or operational completion is claimed by this document.
