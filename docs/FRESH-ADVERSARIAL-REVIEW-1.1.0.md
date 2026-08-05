# File 26 — Fresh Adversarial Review 1.1.0

## Review independence and scope

This review was performed after the first corrective implementation. It did not assume that a green earlier test run proved semantic completion. The branch was re-read against the three governing plans, with emphasis on negative paths, privacy minimization, privilege boundaries, lifecycle bypasses, rollback behavior and truthful status claims.

## Adversarial questions

1. Can code or a companion plugin silently activate its own connector?
2. Can shadow or approved data enter public results?
3. Can an administrator receive all institutional powers by implication?
4. Can a CLI or internal call bypass high-risk step-up authorization?
5. Can a ranking policy be active in name but inert in behavior?
6. Can rollback disable the whole service rather than restore a prior safe policy?
7. Can topic labels replace approved classification decisions?
8. Can graph data grant access or expose private edges?
9. Can recommendation personalization occur without explicit consent?
10. Can guest session context become a hidden persistent profile?
11. Can donation, payment, followers or Founder preference affect ranking?
12. Can a doctor appeal be reviewed by the appellant or overwrite a concurrent decision?
13. Can internal doctor identity references leak through the public ranking API?
14. Can missing cross-file contracts be represented as complete?
15. Can appeal records remain forever or be silently destroyed without a retention law?
16. Can uninstall purge user data without an explicit destructive choice?

## Adversarial findings and final corrections

### AR-01 — first-registration connector self-promotion

A manifest could previously propose an advanced status on first registration. The persistence layer now overrides every new or owner/contract-changed connector to `proposed`. Code reload preserves an existing governed status but cannot promote or unsuspend it.

### AR-02 — public doctor-ranking internal reference exposure

The first corrective version included `author_key` in a public doctor-ranking result structure. It was not required for public explanation. It has been removed from the public response. Appeal authorization uses the internal canonical reference only inside the appeal service.

### AR-03 — invalid active doctor policy availability risk

A zero-weight active policy could make ranking unavailable. The service now enters a disclosed `safe-fallback-{version}` baseline instead of silently failing or using the invalid policy. The fallback is auditable and test-covered.

### AR-04 — appeal ownership and copied profile data

Appeal authorization originally considered a copied payload user identifier. The final implementation uses the canonical `author_key` contract and a narrowly scoped owner filter. Extra private profile identifiers are not required in the public derivative payload.

### AR-05 — appeal retention gap

A dedicated appeal table had no final retention lifecycle. Final/withdrawn appeals now expire after the configured bounded period; stale open appeals are closed, redacted and later deleted. Only aggregate audit counts are retained.

### AR-06 — uninstall lifecycle gap

The initial uninstall path did not remove dedicated File 26 roles or the appeal schema. Final uninstall removes jobs and plugin-specific roles/capabilities while preserving data by default. Destructive purge remains explicit.

### AR-07 — test suite semantic weakness

The earlier contract suite relied heavily on string-presence checks. The final suite adds behavior-level proof that an active policy materially changes ranking, an invalid doctor policy falls back safely and prohibited financial/follower signals do not affect score. Corrective architecture assertions separately guard lifecycle and ownership invariants.

## Negative-path acceptance outcomes

| Threat / failure | Final source behavior |
|---|---|
| unknown or changed connector | proposed; not searchable |
| shadow/approved connector | index-validation only; not retrievable |
| active connector unhealthy | partial/degraded state disclosed |
| corpus scan bound reached | partial result with scan-limit evidence |
| File 00 assertion missing for authenticated private retrieval | fail closed |
| ranking policy invalid | safe disclosed fallback or rejected activation |
| rollback requested | distinct approver + step-up + prior policy restoration |
| graph edge draft/private | no discovery signal |
| classification suggested/rejected | no canonical topic inclusion |
| personalization consent absent | general/session-only discovery |
| guest session topics | request-bound; no profile persistence |
| appeal reviewer equals appellant | forbidden |
| appeal version stale | conflict; reload required |
| required owner connector missing | activation blocked |
| staging/rollback evidence missing | activation blocked |
| uninstall without destructive opt-in | user/index/audit data retained |

## Review conclusion

At the reviewed source level, no known unresolved **critical or high** defect remains in the corrective scope covered by the automated and adversarial checks. This is not a claim of absolute defectlessness and is not a staging, live or operational acceptance statement.

Medium/operational risks deliberately remain gated rather than falsely resolved in source:

- real companion adapters are unavailable until their owner modules publish accepted contracts;
- relevance and performance depend on representative staging data;
- File 20/24/25 runtime integration requires their installed candidates;
- browser, accessibility, backup/restore and rollback evidence require Hostinger-equivalent staging;
- production activation requires Founder approval.

Any new evidence reopens review under the platform’s review → fix → fresh review → retest rule.
