# Decision Log

| ID | Decision | Reason |
|---|---|---|
| F26-D001 | File 26 stores derivative search documents only. | Preserve one canonical owner per entity. |
| F26-D002 | Runtime activation defaults to false. | Owner connectors and staging acceptance are external gates. |
| F26-D003 | Organic rank excludes donation, payment and Founder favoritism. | Ranking fairness and current Founder policy. |
| F26-D004 | Public search uses three-stage visibility. | Prevent stale-index and cache leakage. |
| F26-D005 | Personalization defaults off; sensitive domains excluded. | Privacy, minors and anti-surveillance law. |
| F26-D006 | File 20 owns global search placement; File 25 may override result rendering. | No second shell or visual system. |
| F26-D007 | Green is the primary accent, with semantic colors where appropriate. | Current platform visual constitution. |
| F26-D008 | Download actions appear only when canonical owner supplies an authorized download grant. | Universal Download Availability Rule with rights/consent gates. |
| F26-D009 | High-risk connector and ranking transitions require step-up and auditable approval. | Separation of duties and rollbackability. |
| F26-D010 | Index upsert, tombstone and ranking/appeal lifecycle races are serialized and stale versions fail closed. | Deletion precedence and ranking fairness cannot depend on request timing. |
| F26-D011 | Taxonomy merge/split/deprecation and graph activation require preview/state checks, owner governance, audit and reconciliation signals. | File 26 is derivative; semantic ownership remains with the affected canonical domain. |
| F26-D012 | A high-risk ranking activation/rollback requires a separately recorded action by a distinct authorized second approver; merely supplying another user ID is insufficient. | Two-person approval must be real, auditable and non-spoofable. |
| F26-D013 | Sensitive search or session-context discovery responses are never shared/public HTTP-cache artifacts. | Search terms may reveal health or identity information even when result documents are public. |
| F26-D014 | Schema migration is serialized and verified before File 26 runtime contracts are exposed; failure disables activation/search. | Prevent old-schema runtime behavior and partial deployment ambiguity. |
| F26-D015 | GitHub repository, Hostinger staging and live deployment remain separate evidence realities. | Green CI/package evidence does not prove deployed code, DB migration state or operational behavior. |
