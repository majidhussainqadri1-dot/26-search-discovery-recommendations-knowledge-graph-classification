# File 26 — Master Plan v12 exact-source traceability and acceptance ledger (2026-11-08)

**Governing source:** *Sabri-Website-Master-Plan-v12.docx*, F26 section (FR-001..036; CEN-01..02; FUT-01..24), and CV-164..175.

**Repository baseline:** main `1b064c6ea9f81195611ed001d5457e8ced65460a`; new code is under draft PR #22 `audit/file26-v12-shadow-index-20261108`. This document is a source-to-plan mapping **not** deployment/staging acceptance.

**Status law:** **MAPPED** means concrete code/contract named; **SOURCE TESTED** means exact HEAD GitHub Actions passed; **BEHAVIORAL OPEN** means no real WordPress/owner-provider/host evidence. An existing route or static assertion never proves feature operational. No automatic release approval.

## Master Plan F26 functional requirements (36)

| ID | Governing requirement | Code owner | Verification status |
|---|---|---|---|
| File26-FR-001 | Connector registry | Connectors / DB | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-002 | Canonical identity & versions | Indexer | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-003 | Three-stage eligibility | Search / Security / Connectors | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-004 | Change ingestion / replay | Indexer | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-005 | Shadow generation and rollback | Shadow_Index / Indexer | P0: new isolated generation in PR #22; full/partial and staging still open |
| File26-FR-006 | Deletion tombstones | Indexer | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-007 | Unicode normalization | Normalizer | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-008 | Transliteration | Normalizer | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-009 | Synonyms / typo | Normalizer | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-010 | Safe autocomplete | Search / Security | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-011 | Facets / sorting | Search | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-012 | Hybrid retrieval / dedup | Search / Ranking | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-013 | Ranking constitution | Governance / Ranking | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-014 | Source authority | Ranking | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-015 | Freshness / bot resistance | Ranking | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-016 | Medical safety | Central_Plan / Search | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-017 | Result diversity | Ranking | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-018 | No paid/donor ranking | Central_Plan / Ranking | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-019 | Guest recommendations | Recommendations | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-020 | Consented personalization | Recommendations | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-021 | Explainability / reset | Recommendations | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-022 | Minor guardrails | Recommendations / Security | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-023 | Feedback integrity | Recommendations | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-024 | Relevance experiments | Governance / QA | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-025 | Controlled taxonomy | Taxonomy | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-026 | Classification approval | Taxonomy / Governance | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-027 | Knowledge graph edges | Graph | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-028 | Merge/split/deprecation | Taxonomy | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-029 | Graph traversal safety | Graph | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-030 | Human topic pages | Routes / templates | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-031 | Minimized telemetry | Search | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-032 | Relevance evaluation | Governance / QA | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-033 | Safe configuration | Admin / Governance | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-034 | Health/drift | Health | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-035 | Data export/audit | Privacy / Governance | MAPPED; owner integration and behavioral acceptance open |
| File26-FR-036 | Degraded mode | Search / Indexer | MAPPED; owner integration and behavioral acceptance open |

## Central Vision cross-file requirements (12)

| ID | Governing feature | Existing owner | Mandatory open proof |
|---|---|---|---|
| CV-164 | Federated search | Search / Connectors | 11 owner adapters and end-to-end discovery |
| CV-165 | Facets/filters | Search | Owner eligibility-aware facet count tests |
| CV-166 | Autocomplete/synonyms | Normalizer / Search | Real Urdu/English/Arabic gold queries |
| CV-167 | Advanced search/saved query | Central_Plan / REST | Author/source/date exclusions, privacy |
| CV-168 | Doctor top 10/100/1000 | Doctor_Ranking | File 07 owner and doctor cohorts |
| CV-169 | Why this result | Central_Plan / Ranking | Public explanation per eligible result |
| CV-170 | Symptom/remedy research | Search / File 06 and 15 owners | Three-item comparison stays canonical owner |
| CV-171 | Trend ingestion | File 15 canonical / File26 consumer | Real dedup/window and source provenance |
| CV-172 | Editorial radar | Central_Plan | Source aggregates and identity-free gap evidence |
| CV-173 | Zero-result recovery | Central_Plan | No fabricated results / feedback path |
| CV-174 | Search safety | Central_Plan / Security | Clinical emergency diversion with verified resources |
| CV-175 | Index freshness | Indexer / Health | Measured SLO and source-event lag after deployment |

## Future Search and Knowledge Intelligence (24)

| ID | Feature | Code | Mandatory open proof |
|---|---|---|---|
| F26-FUT-01 | Conversational grounded search | Future_Search_Core_Trait | Approved answer provider and same-answer citation keys |
| F26-FUT-02 | Query planner | Future_Search_Core_Trait | Bounded plan, owner outage partial status |
| F26-FUT-03 | Cross-language search | Future_Search_Core_Trait | Provider, safe synonym and translation claims |
| F26-FUT-04 | Semantic rerank | Future_Search_Core_Trait | Eligibility-immutable provider rerank |
| F26-FUT-05 | Multimodal search | Future_Multimodal_Trait | Owner media adapter with no patient diagnosis |
| F26-FUT-06 | Voice search | Future_Multimodal_Trait | Transcription adapter and sanitization |
| F26-FUT-07 | Segment search | Future_Multimodal_Trait | Real owner page/chapter/timestamp provenance |
| F26-FUT-08 | Find similar | Future_Multimodal_Trait | Approved similarity owner reference |
| F26-FUT-09 | Research mode | Future_Knowledge_Trait | Real editions/evidence filters / snapshot |
| F26-FUT-10 | Result clusters | Future_Knowledge_Trait | Eligible-only clustering |
| F26-FUT-11 | Graph explorer | Future_Knowledge_Trait | Canonical owner-vetted path |
| F26-FUT-12 | Evidence map | Future_Knowledge_Trait | Support/contradiction provenance |
| F26-FUT-13 | Entity disambiguation | Future_Knowledge_Trait | Same-name author/remedy ambiguity |
| F26-FUT-14 | Historical as-of search | Future_Knowledge_Trait | Real owner snapshot; never current substitute |
| F26-FUT-15 | Research trails | Future_User_Data_Trait | Privacy exports and real user session |
| F26-FUT-16 | Saved-search alerts | Future_User_Data_Trait | File19 notification delivery |
| F26-FUT-17 | Local-first history | Future_User_Data_Trait / JS | No default sync, erase |
| F26-FUT-18 | Recommendation transparency | Future_User_Discovery_Trait | Consent and next-request effect |
| F26-FUT-19 | Diversity modes | Future_User_Discovery_Trait | Eligible-only breadth |
| F26-FUT-20 | Geo / availability | Future_User_Discovery_Trait | Real File 07/08 availability truth |
| F26-FUT-21 | Smart search modes | Future_User_Discovery_Trait | Bounded command parsing |
| F26-FUT-22 | Private search vault | Future_Advanced_Trait | Step-up / native owner private authorization |
| F26-FUT-23 | External evidence connectors | Future_Advanced_Trait | Provenance / rights / separation |
| F26-FUT-24 | Relevance laboratory | Future_Advanced_Trait | Auditor-only read-only experiments |

## Mandatory owner / integration truth

File 26 only holds derivative records. Real canonical owner contracts require verified runtime manifests and can_view/list_batch/health callbacks: **File 03, 05, 06, 07, 08, 10, 11, 12, 15, 18, 21**. Additional boundaries: identity **File 00**, global shell **File 20**, assurance **File 24**, visual output **File 25**, saved-search alert delivery **File 19**, File 04 cutover. No current real WordPress adapter readiness was measured; all are unverified rather than falsely failed.

## FR-005 source correction in PR #22

1. Four physical shadow projection tables (documents/nodes/edges/classifications) are created separately from live tables and are never returned through normal Search::run.
2. Reindex jobs use `Shadow_Index::stage` and refuse unsafe partial scopes. Version/tombstone conflict checks are fail closed.
3. A candidate cannot be promoted unless the complete owner job was marked complete, the serving generation still matches its starting fingerprint, node/edge constraints and tombstones are checked, and an independent auditor has approved the **candidate checksum** from a separate authenticated account.
4. Founder/admin step-up and external Founder approval remain required; `RENAME TABLE` moves all four projections together. Previous tables are retained for explicitly approved, no-post-cutover-write rollback.
5. Source writes from Indexer, Graph, Taxonomy and Doctor Ranking are serialized with the cutover advisory lock; real MySQL transaction/race/load acceptance must still be measured.
6. **Not complete:** owner-certified bounded partial index generation; independently verified source-to-shadow counts and golden-query equivalence; real host-level staging and disaster-recovery rehearsal; UX/admin acceptance and live deploy/retest.

## Blockers, distinct realities and release gates

- **Live read** attempted through WPVibe, but HTTP 530 blocked precise deployed source/version and DB/schema/migration inspection. This is a connection symptom, not a proven site root cause.
- **GitHub** source-version `1.3.0`; declared schema `1.0.0`. These numbers **must not** be copied into Deployed Version or DB Version fields.
- **Staging:** no access to actual Hostinger PHP/MySQL/WordPress owner providers, browser, load, multilingual gold query data, or backup restore evidence.
- **Old ZIP:** tracked v1.0.0 artifact and its checksum removed from review branch; exact-HEAD v1.3.0 package generated only by QA.
- **Two approvals:** separate auditor step-up, recorded auditor identity, binding to generation SHA-256, distinct Founder step-up and Founder authorization.
- **Release approval:** freeze deployed runtime/DB/version/migration/checksum; audit parity; stage and verify owner data; pass security, deletion, reindex, revert and performance tests; Founder authorizes exact artifact; deploy; live re-test; confirm parity.

**Required final status fields:** Repository HEAD = PR head (recheck at acceptance); Deployed Version = unverified; DB Version = unverified; Migration State = unverified; Live Verification Status = not verified.
