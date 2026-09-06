#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

run_php_test() {
    local script="$1" out err
    out="$(mktemp "$TMP/php-out.XXXXXX")"
    err="$(mktemp "$TMP/php-err.XXXXXX")"
    if ! php -d display_errors=1 -d error_reporting=E_ALL "$script" >"$out" 2>"$err"; then
        cat "$out"
        cat "$err" >&2
        echo "FAIL: PHP test failed: $script" >&2
        return 1
    fi
    cat "$out"
    if [[ -s "$err" ]]; then
        cat "$err" >&2
        echo "FAIL: PHP test emitted warning/notice/deprecation output: $script" >&2
        return 1
    fi
}

printf '[1/15] PHP syntax\n'
while IFS= read -r -d '' file; do php -l "$file" || { echo "FAIL: PHP syntax: $file" >&2; exit 1; }; done < <(find "$ROOT" -type f -name '*.php' -print0)

printf '[2/15] JavaScript syntax\n'
if ! command -v node >/dev/null 2>&1; then echo 'FAIL: node is required for JavaScript syntax verification' >&2; exit 1; fi
node --check "$ROOT/assets/js/file26.js"
node --check "$ROOT/assets/js/file26-future.js"

printf '[3/15] Pure normalization and ranking tests\n'
run_php_test "$ROOT/tests/test-normalizer-ranking.php"
printf '[4/15] Architecture, policy and traceability contracts\n'
run_php_test "$ROOT/tests/contract-tests.php"
printf '[5/15] Corrective architecture regressions\n'
run_php_test "$ROOT/tests/corrective-contract-tests.php"
printf '[6/15] New governing-plan completion regressions\n'
run_php_test "$ROOT/tests/central-plan-contract-tests.php"
printf '[7/15] Sequential 20-round review regressions\n'
shopt -s nullglob
ROUND_TESTS=("$ROOT"/tests/review-round-*.php)
for test_file in "${ROUND_TESTS[@]}"; do run_php_test "$test_file"; done
printf '[8/15] Future and current review-cycle regressions\n'
run_php_test "$ROOT/tests/future-intelligence-contract-tests.php"
run_php_test "$ROOT/tests/review-second-forty-round-contract-tests.php"
run_php_test "$ROOT/tests/review-r62-r81-contract-tests.php"

printf '[9/15] Dangerous execution primitive scan\n'
if grep -RInE --include='*.php' '(eval\s*\(|shell_exec\s*\(|passthru\s*\(|proc_open\s*\(|popen\s*\()' "$ROOT"; then echo 'FAIL: dangerous execution primitive'; exit 1; fi
printf '[10/15] Forbidden ranking/business and sensitive-table scans\n'
if grep -RInE --include='*.php' '(10% commission|donation_score|payment_score|founder_favoritism_score|paid_rank_score|sponsor_score)' "$ROOT"; then echo 'FAIL: forbidden ranking/business rule'; exit 1; fi
if grep -RInE --include='*.php' '(SELECT|UPDATE|DELETE|INSERT).*(smc_|clinical_|message_body|payment_card)' "$ROOT/includes"; then echo 'FAIL: direct sensitive foreign-table access'; exit 1; fi

printf '[11/15] Required release files and current-cycle evidence\n'
for file in README.md readme.txt CHANGELOG.md DECISION-LOG.md LICENSE docs/ARCHITECTURE.md docs/CONNECTOR-CONTRACT.md docs/REST-CONTRACT.md docs/SECURITY-THREAT-MODEL.md docs/PRIVACY-RETENTION.md docs/MIGRATION.md docs/ROLLBACK.md docs/STAGING-ACCEPTANCE.md docs/REQUIREMENTS-TRACEABILITY.md docs/REVIEW-AND-CORRECTION-1.0.0.md docs/REVIEW-AND-CORRECTION-1.1.0.md docs/NEW-GOVERNING-PLANS-COMPLETION-1.2.0.md docs/REVIEW-AND-CORRECTION-1.2.0-ROUND-1.md docs/REVIEW-AND-CORRECTION-1.2.0-ROUND-2.md docs/FILE26-20-ROUND-CORRECTIVE-AUDIT-2026-08-13.md docs/FUTURE-SEARCH-KNOWLEDGE-INTELLIGENCE-SUPERSET-24-1.3.0.md docs/REVIEW-AND-CORRECTION-1.3.0-PARITY-ROUND-1.md docs/REVIEW-AND-CORRECTION-1.3.0-PARITY-ROUND-2.md docs/FILE26-R62-R81-SEQUENTIAL-REVIEW-2026-08-29.md docs/QA-REPORT.md docs/SBOM.md tests/review-round-77-regressions.php tests/review-round-78-regressions.php tests/review-round-79-regressions.php tests/review-round-80-regressions.php tests/review-round-81-regressions.php; do test -s "$ROOT/$file" || { echo "FAIL: required release evidence missing: $file" >&2; exit 1; }; done
for round in 82 83 84 85 87 88 89 90 91 92 93 94 95 96 97 98 99 100; do
    file="tests/review-round-${round}-regressions.php"
    test -s "$ROOT/$file" || { echo "FAIL: current R82-R100 regression evidence missing: $file" >&2; exit 1; }
done
if git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1 && git -C "$ROOT" ls-files --error-unmatch MANIFEST.sha256 >/dev/null 2>&1; then
    echo 'FAIL: MANIFEST.sha256 must be generated from the exact build tree, not tracked as stale source evidence' >&2
    exit 1
fi

printf '[12/15] Version and governance parity\n'
grep -q 'Version: 1.3.0' "$ROOT/file-26-search-discovery.php"
grep -q "SABRI_FILE26_VERSION', '1.3.0'" "$ROOT/file-26-search-discovery.php"
grep -q "SABRI_FILE26_CONTRACT_VERSION', '1.3'" "$ROOT/file-26-search-discovery.php"
grep -q 'Stable tag: 1.3.0' "$ROOT/readme.txt"
grep -q 'F26-FUT-24' "$ROOT/docs/FUTURE-SEARCH-KNOWLEDGE-INTELLIGENCE-SUPERSET-24-1.3.0.md"
grep -q 'R62–R81' "$ROOT/docs/QA-REPORT.md"
grep -q 'R82–R101' "$ROOT/docs/QA-REPORT.md"
grep -qi '#087a4e' "$ROOT/assets/css/file26.css"

printf '[13/15] Deterministic double build\n'
python3 "$ROOT/tools/build-package.py" --root "$ROOT" --output "$TMP/a.zip"
python3 "$ROOT/tools/build-package.py" --root "$ROOT" --output "$TMP/b.zip"
cmp "$TMP/a.zip" "$TMP/b.zip"
printf '[14/15] ZIP integrity, path and metadata safety\n'
python3 - "$TMP/a.zip" <<'PY'
import stat, sys, zipfile
p=sys.argv[1]
with zipfile.ZipFile(p) as z:
    bad=[n for n in z.namelist() if n.startswith('/') or '..' in n.split('/')]
    roots={n.split('/')[0] for n in z.namelist() if n}
    assert not bad, bad
    assert roots == {'sabri-file26-search-discovery'}, roots
    assert z.testzip() is None
    for info in z.infolist():
        mode=(info.external_attr >> 16) & 0o777777
        assert not stat.S_ISLNK(mode), info.filename
        assert (mode & 0o777) == 0o644, (info.filename, oct(mode))
print('PASS: safe single-root deterministic ZIP with fixed regular-file metadata')
PY
printf '[15/15] Clean-extract QA and manifest parity\n'
unzip -q "$TMP/a.zip" -d "$TMP/extract"
PACKAGE="$TMP/extract/sabri-file26-search-discovery"
run_php_test "$PACKAGE/tests/test-normalizer-ranking.php"
run_php_test "$PACKAGE/tests/contract-tests.php"
run_php_test "$PACKAGE/tests/corrective-contract-tests.php"
run_php_test "$PACKAGE/tests/central-plan-contract-tests.php"
for test_file in "$PACKAGE"/tests/review-round-*.php; do run_php_test "$test_file"; done
run_php_test "$PACKAGE/tests/future-intelligence-contract-tests.php"
run_php_test "$PACKAGE/tests/review-second-forty-round-contract-tests.php"
run_php_test "$PACKAGE/tests/review-r62-r81-contract-tests.php"
(cd "$PACKAGE" && sha256sum -c MANIFEST.sha256 >/dev/null)
(cd "$ROOT" && sha256sum -c MANIFEST.sha256 >/dev/null)
mkdir -p "$ROOT/release"
cp "$TMP/a.zip" "$ROOT/release/26-sabri-file26-search-discovery-1.3.0.zip"
sha256sum "$ROOT/release/26-sabri-file26-search-discovery-1.3.0.zip" > "$ROOT/release/CHECKSUMS.sha256"
printf 'ALL LOCAL QA CHECKS PASSED\n'
cat "$ROOT/release/CHECKSUMS.sha256"
