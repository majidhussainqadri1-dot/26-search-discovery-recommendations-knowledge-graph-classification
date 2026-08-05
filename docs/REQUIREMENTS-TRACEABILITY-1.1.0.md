# File 26 — Requirements-to-Code Traceability 1.1.0

## Evidence law

`Implemented` means reviewable source exists. It does not mean staging-accepted, live-deployed or operational. External owner contracts and staging evidence remain separate gates.

| Requirement | Governing source | Implementation | Automated evidence | External gate |
|---|---|---|---|---|
| Canonical derivative indexing only | Master v3.0; File 26 §§1–2 | `Connectors`, `Indexer`, owner references | contract tests; foreign-table scan | real owner adapters |
| New connector cannot self-activate | File 26 lifecycle/governance | `Connectors::persist()` forces `proposed` | corrective regression | operator/approver staging workflow |
| Shadow isolation | File 26 activation gates | `Connectors::can_view()`, active SQL joins | corrective regression | shadow corpus exercise |
| Exact phrase | File 26 §2.1 | `Normalizer::phrases()`, ranking phrase boost | behavioral test | Urdu/English benchmark |
| Spelling tolerance | File 26 §2.1 | prefix retrieval + bounded token similarity | behavioral test | benchmark false-positive review |
| Urdu/English/Arabic/transliteration | File 26 §2.1 | Unicode maps, aliases and bounded Roman-Urdu candidates | behavioral test | domain glossary acceptance |
| Full filters/facets/sort | File 26 §2.1 | REST, Routes, Search, `templates/search.php` | syntax/contract suite | File 25 visual acceptance |
| Cursor bound to context/policy | File 26 pagination/security | signed cursor context hash | contract suite | large-result staging |
| Ranking policy controls runtime | File 26 ranking governance | `Ranking::policy()` and `score()` | behavior-level active-policy test | policy reviewer approval |
| Ranking rollback | File 26 experiments/rollback | prior active policy restoration | corrective regression | dual-user staging rehearsal |
| No payment/donation/favoritism ranking | Master v3.0; Chats §5; File 26 §2.2 | forbidden features + scoring omission | static and behavioral tests | fairness review |
| Consent-first personalization | Chats privacy charter; File 26 §2.1 | Recommendations, public controls | corrective regression | user journey acceptance |
| Guest/session cold start | File 26 §2.1 | request-bound `session_topics` | corrective regression | privacy review |
| Hide/not interested/undo/reset/opt-out | File 26 §2.1 | Recommendations, JS, templates | corrective regression | browser/no-JS acceptance |
| Approved taxonomy/classification | File 26 §2.1 | classification status filter; term UUID route | corrective regression | curator workflow staging |
| Owner-sourced knowledge graph | File 26 §§2–3 | active public edges, bounded signal | corrective regression | graph provenance dataset |
| Private/deleted leakage prevention | Master/File 26 | active lane, state/visibility gates, tombstones, click-time callbacks | security/contract scans | real-role leakage test |
| Truthful degraded state | Master v3.0 status law; File 26 | connector health and scan-limit `partial_domains` | corrective regression | outage simulation |
| Separation of duties | Master/File 24/File 26 | dedicated roles; no manager super-capability | corrective regression | distinct-user role test |
| Global doctor tiers | Chats §5 | Top 10/100/1000/All Verified | behavior/static tests | File 25 cards |
| Contextual doctor views | Chats §5 | country/city/language/specialization/educator/researcher | corrective regression | owner metadata contract |
| Doctor ranking explainability | Chats §5 | top components + prohibited-signal disclosure | static regression | public copy review |
| Doctor ranking appeals | Chats §5; File 26 governance | `Doctor_Appeals`, REST, privacy lifecycle | corrective regression | independent reviewer journey |
| Required owner connectors | File 26 §2.4 | `Owner_Contracts` catalogue | corrective regression | Files 03/05/06/07/08/10/11/12/15/18/21 |
| File 00/20/24/25 gates | Master/File 26 | explicit activation evidence filter | corrective regression | installed companion candidates |
| Appeal retention | privacy lifecycle | retention cron and redaction/deletion | corrective regression | retention policy approval |
| Safe uninstall | Master rollback law | roles/jobs removed; data opt-in purge | corrective regression | staging uninstall/reinstall |
| Deterministic package | Master/File 26 DoD | build script + manifest | double build + clean extract | final artifact upload |

## Status summary

- Source implementation: corrective candidate.
- Automated exact-head evidence: must be read from the final GitHub Actions run.
- Staging, live and operational statuses: pending and not inferred from this matrix.
