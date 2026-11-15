<?php
/**
 * Direct test against immutable original File24 RankingFairnessPolicy source.
 * Synthetic evidence only; NEVER a real approval, ranking write, or Live claim.
 */
if ( 'yes' !== getenv( 'FILE26_CI_EPHEMERAL_DB' ) ||
	false === strpos( home_url( '/' ), 'file26-ci.local' ) ) {
	fwrite( STDERR, "REFUSED: File24 acceptance requires disposable CI WordPress.\n" );
	exit( 2 );
}
$external_root = getenv( 'FILE26_FILE24_SOURCE' );
if ( ! is_string( $external_root ) || '' === $external_root ) {
	fwrite( STDERR, "FAIL: exact pinned File24 external source is mandatory.\n" );
	exit( 1 );
}
$root = rtrim( $external_root, DIRECTORY_SEPARATOR );
$sanitizer_file = $root . '/plugin/sabri-security-center/src/Support/Sanitizer.php';
$policy_file = $root . '/plugin/sabri-security-center/src/Policy/RankingFairnessPolicy.php';
if ( ! is_file( $sanitizer_file ) || ! is_file( $policy_file ) ) {
	fwrite( STDERR, "FAIL: independently pinned File24 source files are missing.\n" );
	exit( 1 );
}
require_once $sanitizer_file;
require_once $policy_file;
function f26_file24_sql_assert( $yes, $description ) {
	if ( ! $yes ) {
		fwrite( STDERR, "FAIL: " . $description . "\n" );
		exit( 1 );
	}
	echo "PASS: " . $description . "\n";
}
$policy = \Sabri\File26\Plugin::instance()->doctor_ranking()->policy();
f26_file24_sql_assert( is_array( $policy ) && empty( $policy['policy_read_failed'] ),
	'File26 disposable current doctor-policy configuration has no source read error' );
$exact_version = $policy['version'];
$controls = array(
	'file26_owner_contract', 'versioned_policy', 'explainability', 'audit_log',
	'appeal_path', 'manipulation_resistance', 'verified_review_weighting',
	'monthly_recomputation', 'donation_independence', 'payment_independence',
	'founder_non_favoritism',
);
$now = time();
$evidence = array(
	'controls' => $controls,
	'influences' => array(),
	'policy_version' => $exact_version,
	'evidence_ref' => 'file26:synthetic-ci-verification-v1',
	'tested_at' => gmdate( 'Y-m-d\TH:i:s+00:00', $now ),
	'recomputed_at' => gmdate( 'Y-m-d\TH:i:s+00:00', $now ),
);
$evaluator = '\\Sabri\\Platform\\Security\\Policy\\RankingFairnessPolicy';
$evaluated = $evaluator::evaluate( $evidence, $now );
f26_file24_sql_assert( $exact_version === '1.0.0' &&
	! empty( $evaluated['policy_version_valid'] ) &&
	'verified' === $evaluated['state'], 'REAL File24 policy engine accepts File26 numeric-version **synthetic** evidence' );
$old = $evidence;
$old['policy_version'] = 'doctor-global-1.0';
$legacy = $evaluator::evaluate( $old, $now );
f26_file24_sql_assert( 'blocked' === $legacy['state'] &&
	empty( $legacy['policy_version_valid'] ), 'REAL File24 policy engine rejects original File26 legacy default' );
$paid = $evidence;
$paid['influences'] = array( 'donation' );
$donor = $evaluator::evaluate( $paid, $now );
f26_file24_sql_assert( 'blocked' === $donor['state'] &&
	in_array( 'donation', $donor['forbidden_influences'], true ),
	'REAL File24 engine still forbids donor influence under valid numeric version' );
$missing = $evidence;
$missing['controls'] = array( 'versioned_policy' );
$incomplete = $evaluator::evaluate( $missing, $now );
f26_file24_sql_assert( 'blocked' === $incomplete['state'] &&
	count( $incomplete['missing_controls'] ) > 0,
	'REQUIRED File24 assurance controls cannot be bypassed by version change' );
$stale = $evidence;
$stale['recomputed_at'] = gmdate( 'Y-m-d\TH:i:s+00:00', $now - 45 * DAY_IN_SECONDS );
$expired = $evaluator::evaluate( $stale, $now );
f26_file24_sql_assert( 'blocked' === $expired['state'] && empty( $expired['recomputation_fresh'] ),
	'current File24 freshness still blocks a 45-day-old recomputation' );
echo "PASS: pinned actual File24 assurance compatibility, no real approval or deployment.\n";
