# File 26 v1.3.0 — Fresh Adversarial Review and Correction Round 2

Date: 2026-08-13 (Asia/Karachi)  
Baseline: corrected Round-1 state. This pass specifically targeted authorization, privacy, provider substitution, clinical safety, resource behavior and measurement integrity.

## Review discipline

The adversarial review was completed before any Round-2 correction. All proven defects were then corrected together and re-tested.

## Defects found

1. **Membership fail-closed gap:** authenticated Future endpoints initially checked login but did not also reject invalid/stale/suspended File 00 membership assertions.
2. **Step-up integration gap:** Private Search Vault used only the future-specific step-up provider rather than also honoring the existing File 26 step-up authorization contract.
3. **Sensitive-query classifier coverage:** local Future history/alert blocking had a narrower detector than the existing File 26 security classifier.
4. **Grounded-answer provider attestation:** citations were checked, but a provider also needed an explicit non-prescriptive grounded-output safety attestation before its answer text could be accepted.
5. **Multimodal clinical-kind bypass:** a caller could label an asset as `patient_image`/`clinical_image`/`patient_photo` without setting the separate patient-image flag.
6. **Multimodal adapter filter boundary:** provider-derived filters required bounded sanitization before retrieval.
7. **Cross-language duplicate fan-out:** normalizer and provider variants could duplicate the same normalized query/locale and waste bounded retrieval budget.
8. **Future response cache privacy:** only selected future routes had explicit no-store; voice/multimodal/conversational requests could also contain user-sensitive request context.
9. **Relevance Laboratory metric denominator:** Top-10 overlap ratio used a fixed denominator of 10 even when fewer baseline results existed.

## Corrections

- All non-public Future routes now require valid, non-suspended membership assertions after login.
- Private-vault step-up accepts the established File 26 `require_step_up()` contract and the future integration hook.
- Sensitive query blocking now incorporates `Security::contains_sensitive_query()` plus future-sensitive extensions.
- Conversational provider answers require `safety_attestation=grounded_non_prescriptive`; otherwise File 26 falls back to source extracts.
- Multimodal patient/clinical image kinds are explicitly rejected for diagnosis scope; provider filters are sanitized.
- Cross-language variants are de-duplicated by normalized query + locale and capped.
- Every `/future/*` REST response now carries explicit `private, no-store` and the Future contract header.
- Relevance overlap uses the actual bounded baseline count as denominator.

## Re-test

- PHP syntax: PASS.
- JavaScript syntax: PASS.
- Future-24 static contract assertions: scheduled in the complete repository QA gate.
- Exact-head PHP 7.4/8.3 CI is the authoritative automated-QA gate after commit.

No Hostinger staging, live deployment or operational acceptance is claimed.
