#!/usr/bin/env bash
# Re-run the entire File 26 source, contract, security and clean-package suite
# forty consecutive times. This is automated repeated verification, NOT forty
# independent human code reviews or staging/live acceptance.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
mkdir -p release
LEDGER="$ROOT/release/FILE26-20261020-FRESH40-RESULTS.tsv"
TRACE="$(git rev-parse HEAD)"
cat >"$LEDGER" <<EOF
round\tfocus\tgit_sha\tresult\tverified_package
EOF
FOCI=(
  'Connector registry'
  'Canonical identity and versions'
  'Visibility and privacy gating'
  'Change-event ingestion'
  'Rebuild and shadow indexing'
  'Tombstones and erasure'
  'Unicode and Urdu normalization'
  'Transliteration accuracy'
  'Spelling and synonyms'
  'Autocomplete leakage'
  'Filters and facets'
  'Hybrid retrieval'
  'Ranking policy versioning'
  'Source quality and authority'
  'Freshness and popularity'
  'Safety status enforcement'
  'Diversity limits'
  'No paid or donor rankings'
  'Cold-start recommendations'
  'Consent-first personalization'
  'Why-this explanations'
  'Minor and guardian protection'
  'Feedback integrity'
  'Ranking policy rollback'
  'Taxonomy stable identifiers'
  'Classification approval'
  'Knowledge graph provenance'
  'Taxonomy merge and split'
  'Graph bounded traversal'
  'Topic status and links'
  'Privacy-safe telemetry'
  'Evaluation benchmarks'
  'Admin authorization'
  'Operations and health'
  'Privacy export and erasure'
  'Degraded service states'
  'File 00 membership authorization'
  'File 20 and 25 shell/visual contracts'
  'Files 04/19 event-owner migration'
  'Future 24 manifest and build'
)
if [[ ${#FOCI[@]} -ne 40 ]]; then
  echo 'FAIL: exactly 40 scope labels must exist' >&2
  exit 1
fi
for (( round=1; round<=40; round++ )); do
  topic="${FOCI[round-1]}"
  php -d error_reporting=E_ALL "$ROOT/tests/file26-20261020-fr36-and-owner40.php" "$round" || { echo "FAIL: targeted File26 plan contract round $round" >&2; exit 1; }
  printf 'FILE26_REVIEW_START %02d/40 — %s — %s\n' "$round" "$topic" "$TRACE"
  log="$(mktemp)"
  if bash "$ROOT/qa/run-tests.sh" >"$log" 2>&1; then
    if ! grep -q 'ALL LOCAL QA CHECKS PASSED' "$log"; then
      tail -80 "$log" >&2
      echo "FAIL: full QA omitted package acceptance in review $round" >&2
      rm -f "$log"
      exit 1
    fi
    if [[ ! -s "$ROOT/release/26-sabri-file26-search-discovery-1.3.0.zip" || ! -s "$ROOT/release/CHECKSUMS.sha256" ]]; then
      echo "FAIL: package/checksum missing after review $round" >&2
      rm -f "$log"
      exit 1
    fi
    printf '%02d\t%s\t%s\tPASS\tYES\n' "$round" "$topic" "$TRACE" >> "$LEDGER"
    printf 'FILE26_REVIEW_PASS %02d/40 — all 15 gates, source + clean ZIP\n' "$round"
    rm -f "$log"
  else
    tail -100 "$log" >&2
    printf '%02d\t%s\t%s\tFAIL\tNO\n' "$round" "$topic" "$TRACE" >> "$LEDGER"
    echo "FILE26_REVIEW_FAILED $round — investigation required before continuing" >&2
    rm -f "$log"
    exit 1
  fi
done
if [[ $(grep -c $'\tPASS\tYES' "$LEDGER") -ne 40 ]]; then
  echo 'FAIL: fewer than forty complete automated review passes' >&2
  exit 1
fi
echo "ALL 40 AUTOMATED FULL-SUITE REVIEWS PASSED AT $TRACE"
cat "$LEDGER"
