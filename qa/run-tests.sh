#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

printf '[1/10] PHP syntax\n'
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$ROOT" -type f -name '*.php' -print0)

printf '[2/10] JavaScript syntax\n'
if command -v node >/dev/null 2>&1; then node --check "$ROOT/assets/js/file26.js"; else echo 'SKIP: node unavailable'; fi

printf '[3/10] Pure normalization and ranking tests\n'
php "$ROOT/tests/test-normalizer-ranking.php"

printf '[4/10] Architecture, policy and traceability contracts\n'
php "$ROOT/tests/contract-tests.php"

printf '[5/10] Dangerous/forbidden primitive scan\n'
if grep -RInE --include='*.php' '(eval\s*\(|shell_exec\s*\(|passthru\s*\(|proc_open\s*\(|popen\s*\()' "$ROOT"; then echo 'FAIL: dangerous execution primitive'; exit 1; fi
if grep -RInE --include='*.php' '(10% commission|donation_score|payment_score|founder_favoritism_score)' "$ROOT"; then echo 'FAIL: forbidden ranking/business rule'; exit 1; fi
if grep -RInE --include='*.php' '(SELECT|UPDATE|DELETE|INSERT).*(smc_|clinical_|message_body|payment_card)' "$ROOT/includes"; then echo 'FAIL: direct sensitive foreign-table access'; exit 1; fi

printf '[6/10] Required release files\n'
for file in README.md readme.txt CHANGELOG.md DECISION-LOG.md LICENSE docs/ARCHITECTURE.md docs/CONNECTOR-CONTRACT.md docs/REST-CONTRACT.md docs/SECURITY-THREAT-MODEL.md docs/PRIVACY-RETENTION.md docs/MIGRATION.md docs/ROLLBACK.md docs/STAGING-ACCEPTANCE.md docs/REQUIREMENTS-TRACEABILITY.md docs/REVIEW-AND-CORRECTION-1.0.0.md docs/QA-REPORT.md docs/SBOM.md; do test -s "$ROOT/$file"; done

printf '[7/10] Deterministic double build\n'
python3 "$ROOT/tools/build-package.py" --root "$ROOT" --output "$TMP/a.zip"
python3 "$ROOT/tools/build-package.py" --root "$ROOT" --output "$TMP/b.zip"
cmp "$TMP/a.zip" "$TMP/b.zip"

printf '[8/10] ZIP integrity and path safety\n'
python3 - "$TMP/a.zip" <<'PY'
import sys, zipfile
p=sys.argv[1]
with zipfile.ZipFile(p) as z:
    bad=[n for n in z.namelist() if n.startswith('/') or '..' in n.split('/')]
    roots={n.split('/')[0] for n in z.namelist() if n}
    assert not bad, bad
    assert roots == {'sabri-file26-search-discovery'}, roots
    assert z.testzip() is None
print('PASS: safe single-root deterministic ZIP')
PY

printf '[9/10] Clean-extract QA\n'
unzip -q "$TMP/a.zip" -d "$TMP/extract"
php "$TMP/extract/sabri-file26-search-discovery/tests/test-normalizer-ranking.php" >/dev/null
php "$TMP/extract/sabri-file26-search-discovery/tests/contract-tests.php" >/dev/null

printf '[10/10] Version and manifest parity\n'
grep -q 'Version: 1.0.0' "$ROOT/file-26-search-discovery.php"
grep -q 'Stable tag: 1.0.0' "$ROOT/readme.txt"
(cd "$ROOT" && sha256sum -c MANIFEST.sha256 >/dev/null)

mkdir -p "$ROOT/release"
cp "$TMP/a.zip" "$ROOT/release/26-sabri-file26-search-discovery-1.0.0.zip"
sha256sum "$ROOT/release/26-sabri-file26-search-discovery-1.0.0.zip" > "$ROOT/release/CHECKSUMS.sha256"
printf 'ALL LOCAL QA CHECKS PASSED\n'
cat "$ROOT/release/CHECKSUMS.sha256"
