#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

printf '[1/15] PHP syntax\n'
while IFS= read -r -d '' file; do
  if ! php -l "$file"; then
    echo "FAIL: PHP syntax: $file" >&2
    exit 1
  fi
done < <(find "$ROOT" -type f -name '*.php' -not -path "$ROOT/release/*" -print0)

printf '[2/15] JavaScript syntax\n'
if command -v node >/dev/null 2>&1; then node --check "$ROOT/assets/js/file26.js"; else echo 'SKIP: node unavailable'; fi

printf '[3/15] Pure normalization and ranking tests\n'
php "$ROOT/tests/test-normalizer-ranking.php"

printf '[4/15] Architecture, policy and traceability contracts\n'
php "$ROOT/tests/contract-tests.php"

printf '[5/15] Corrective architecture regressions\n'
php "$ROOT/tests/corrective-contract-tests.php"

printf '[6/15] New governing-plan completion regressions\n'
php "$ROOT/tests/central-plan-contract-tests.php"

printf '[7/15] Sequential review regressions\n'
shopt -s nullglob
ROUND_TESTS=("$ROOT"/tests/review-round-*.php)
for test_file in "${ROUND_TESTS[@]}"; do php "$test_file"; done

printf '[8/15] Dangerous execution primitive scan\n'
if grep -RInE --include='*.php' '(eval\s*\(|shell_exec\s*\(|passthru\s*\(|proc_open\s*\(|popen\s*\()' "$ROOT/includes" "$ROOT/file-26-search-discovery.php"; then
  echo 'FAIL: dangerous execution primitive' >&2
  exit 1
fi

printf '[9/15] Forbidden ranking/business and sensitive-table scans\n'
if grep -RInE --include='*.php' '(10% commission|donation_score|payment_score|founder_favoritism_score|paid_rank_score|sponsor_score)' "$ROOT/includes" "$ROOT/file-26-search-discovery.php"; then
  echo 'FAIL: forbidden ranking/business rule' >&2
  exit 1
fi
if grep -RInE --include='*.php' '(SELECT|UPDATE|DELETE|INSERT).*(smc_|clinical_|message_body|payment_card)' "$ROOT/includes"; then
  echo 'FAIL: direct sensitive foreign-table access' >&2
  exit 1
fi

printf '[10/15] Required source/release-control files\n'
for file in README.md readme.txt CHANGELOG.md DECISION-LOG.md LICENSE docs/ARCHITECTURE.md docs/CONNECTOR-CONTRACT.md docs/REST-CONTRACT.md docs/SECURITY-THREAT-MODEL.md docs/PRIVACY-RETENTION.md docs/MIGRATION.md docs/ROLLBACK.md docs/STAGING-ACCEPTANCE.md docs/REQUIREMENTS-TRACEABILITY.md docs/REVIEW-AND-CORRECTION-1.0.0.md docs/REVIEW-AND-CORRECTION-1.1.0.md docs/NEW-GOVERNING-PLANS-COMPLETION-1.2.0.md docs/REVIEW-AND-CORRECTION-1.2.0-ROUND-1.md docs/REVIEW-AND-CORRECTION-1.2.0-ROUND-2.md docs/FILE26-20-ROUND-CORRECTIVE-AUDIT-2026-08-13.md docs/QA-REPORT.md docs/SBOM.md release/README.md; do
  test -s "$ROOT/$file" || { echo "FAIL: missing required source file: $file" >&2; exit 1; }
done

printf '[11/15] Version and governance parity\n'
grep -q 'Version: 1.2.0' "$ROOT/file-26-search-discovery.php"
grep -q "SABRI_FILE26_VERSION', '1.2.0'" "$ROOT/file-26-search-discovery.php"
grep -q "SABRI_FILE26_CONTRACT_VERSION', '1.2'" "$ROOT/file-26-search-discovery.php"
grep -q 'Stable tag: 1.2.0' "$ROOT/readme.txt"
grep -qi '#087a4e' "$ROOT/assets/css/file26.css"

printf '[12/15] Deterministic double build\n'
python3 "$ROOT/tools/build-package.py" --root "$ROOT" --output "$TMP/a.zip"
python3 "$ROOT/tools/build-package.py" --root "$ROOT" --output "$TMP/b.zip"
cmp "$TMP/a.zip" "$TMP/b.zip"

printf '[13/15] ZIP integrity, path safety and runtime-only allowlist\n'
python3 - "$TMP/a.zip" <<'PY'
import sys, zipfile
p=sys.argv[1]
with zipfile.ZipFile(p) as z:
    names=z.namelist()
    bad=[n for n in names if n.startswith('/') or '..' in n.split('/')]
    roots={n.split('/')[0] for n in names if n}
    assert not bad, bad
    assert roots == {'sabri-file26-search-discovery'}, roots
    assert z.testzip() is None
    forbidden=('/.github/','/tests/','/tools/','/qa/','/release/','/docs/','/.git/')
    leaked=[n for n in names if any(part in n for part in forbidden)]
    assert not leaked, leaked
    required={
        'sabri-file26-search-discovery/file-26-search-discovery.php',
        'sabri-file26-search-discovery/readme.txt',
        'sabri-file26-search-discovery/LICENSE',
        'sabri-file26-search-discovery/MANIFEST.sha256',
    }
    assert required.issubset(set(names)), required-set(names)
print('PASS: safe single-root runtime-only deterministic ZIP')
PY

printf '[14/15] Clean-extract runtime QA and package manifest parity\n'
unzip -q "$TMP/a.zip" -d "$TMP/extract"
PACKAGE="$TMP/extract/sabri-file26-search-discovery"
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$PACKAGE" -type f -name '*.php' -print0)
if command -v node >/dev/null 2>&1; then node --check "$PACKAGE/assets/js/file26.js"; fi
(cd "$PACKAGE" && sha256sum -c MANIFEST.sha256 >/dev/null)
test ! -e "$ROOT/MANIFEST.sha256" || { echo 'FAIL: tracked/source MANIFEST.sha256 is stale-prone release state' >&2; exit 1; }

printf '[15/15] Exact-build release artifact generation\n'
mkdir -p "$ROOT/release"
cp "$TMP/a.zip" "$ROOT/release/26-sabri-file26-search-discovery-1.2.0.zip"
(
  cd "$ROOT/release"
  sha256sum "26-sabri-file26-search-discovery-1.2.0.zip" > CHECKSUMS.sha256
  sha256sum -c CHECKSUMS.sha256 >/dev/null
)
printf 'ALL LOCAL QA CHECKS PASSED\n'
cat "$ROOT/release/CHECKSUMS.sha256"
