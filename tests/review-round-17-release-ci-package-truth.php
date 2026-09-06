<?php
/** Round 17 regression: release artifacts are exact-head, deterministic, runtime-only and non-stale. */
$root = dirname( __DIR__ );
$builder = file_get_contents( $root . '/tools/build-package.py' );
$qa = file_get_contents( $root . '/qa/run-tests.sh' );
$workflow = file_get_contents( $root . '/.github/workflows/qa.yml' );
$readme = file_get_contents( $root . '/README.md' );
$release = file_get_contents( $root . '/release/README.md' );
$checks = array(
	array( $builder, 'RUNTIME_DIRS', 'package builder uses a runtime allowlist' ),
	array( $builder, 'manifest_bytes', 'package manifest is generated in memory' ),
	array( $builder, 'info.external_attr = 0o644 << 16', 'zip permissions are host-independent' ),
	array( $qa, 'cmp "$TMP/a.zip" "$TMP/b.zip"', 'QA compares two deterministic builds' ),
	array( $qa, "forbidden=('/.github/','/tests/','/tools/','/qa/','/release/','/docs/','/.git/')", 'runtime package rejects development/source-only directories' ),
	array( $qa, 'test ! -e "$ROOT/MANIFEST.sha256"', 'QA rejects stale tracked package manifest state' ),
	array( $workflow, "- 'review/**'", 'review-branch pushes receive exact-head CI' ),
	array( $workflow, 'persist-credentials: false', 'checkout does not retain write credentials' ),
	array( $workflow, 'timeout-minutes: 20', 'CI has a bounded timeout' ),
	array( $readme, 'Do **not** treat the repository ZIP', 'installation guidance distinguishes source from deployment truth' ),
	array( $release, 'no installable ZIP, checksum file or package manifest as release truth', 'release directory documents generated-only artifacts' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" ); $failures++; }
}
if ( file_exists( $root . '/MANIFEST.sha256' ) ) { fwrite( STDERR, "FAIL: stale-prone tracked MANIFEST.sha256 exists\n" ); $failures++; }
if ( $failures ) { exit( 1 ); }
echo "Round 17 release, CI and package-truth regression passed.\n";
