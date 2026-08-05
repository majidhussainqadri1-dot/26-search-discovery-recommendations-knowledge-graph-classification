<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-file26-normalizer.php';
require dirname( __DIR__ ) . '/includes/class-file26-ranking.php';
require dirname( __DIR__ ) . '/includes/class-file26-security.php';
require dirname( __DIR__ ) . '/includes/class-file26-doctor-ranking.php';

use Sabri\File26\Normalizer;
use Sabri\File26\Ranking;
use Sabri\File26\Security;
use Sabri\File26\Doctor_Ranking;

$passed = 0;
$failed = 0;
function check_case( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) { $passed++; echo "PASS: $label\n"; }
	else { $failed++; echo "FAIL: $label\n"; }
}

$n = new Normalizer();
check_case( 'کیا' === $n->normalize( 'كِيَا' ), 'Arabic/Urdu forms and diacritics normalize safely.' );
check_case( '123' === $n->normalize( '۱۲۳' ), 'Urdu numerals normalize.' );
check_case( array( 'جگر', 'سوزش' ) === $n->tokens( 'جگر کی سوزش' ), 'Short stop-like token is excluded without losing domain tokens.' );
check_case( false === $n->prefix_is_safe( '03001234567' ), 'Phone-like autocomplete prefix is rejected.' );
check_case( true === $n->prefix_is_safe( 'جگر' ), 'Ordinary Urdu autocomplete prefix is accepted.' );
check_case( in_array( 'جگر کی سوزش', $n->phrases( '"جگر کی سوزش"' ), true ), 'Quoted exact phrase is parsed and normalized.' );
check_case( count( $n->expansions( 'jigar' ) ) >= 2, 'Roman Urdu receives a bounded Urdu-script transliteration candidate.' );
check_case( $n->token_similarity( 'jigar', 'jiger' ) >= 0.70, 'Bounded spelling similarity tolerates a minor typo.' );
check_case( in_array( 'jig', $n->retrieval_terms( 'jigar' ), true ), 'Long tokens provide a bounded prefix-retrieval term.' );

$r = new Ranking( $n );
$base = array(
	'canonical_key' => str_repeat( 'a', 64 ), 'connector_slug' => 'test', 'author_key' => '',
	'normalized_title' => 'جگر کی سوزش', 'normalized_body' => 'تعلیمی مضمون',
	'authority_score' => .8, 'quality_score' => .9, 'popularity_score' => 10,
	'freshness_at' => gmdate( 'Y-m-d H:i:s' ), 'entity_type' => 'article', 'safety_class' => 'general',
	'payload' => array(),
);
$score = $r->score( $base, 'جگر سوزش' );
$paid = $base; $paid['donation'] = 1000000; $paid['payment'] = 1000000;
check_case( $score === $r->score( $paid, 'جگر سوزش' ), 'Donation and payment cannot alter organic score.' );
$blocked = $base; $blocked['safety_class'] = 'blocked';
check_case( $r->score( $blocked, 'جگر سوزش' ) < 0, 'Blocked safety class is excluded by score gate.' );
check_case( true === $r->matches_query( $base, 'جگر سوزش' ), 'Query matching accepts relevant Urdu content.' );
check_case( $r->score( $base, '"جگر کی سوزش"' ) > $r->score( $base, 'نامعلوم' ), 'Exact phrase receives the configured policy boost.' );
check_case( 'top_10' === $r->doctor_tier( array( 'verified_doctor' => true, 'global_doctor_rank' => 7 ) )['key'], 'Top 10 verified doctor tier.' );
check_case( 'all_verified' === $r->doctor_tier( array( 'verified_doctor' => true, 'global_doctor_rank' => 1200 ) )['key'], 'All Verified Doctors tier uses dignified wording.' );

$security = new Security();
$GLOBALS['f26_test_logged_in'] = false;
check_case( true === $security->audience()['valid'], 'Anonymous public audience is valid for public-only retrieval.' );
$GLOBALS['f26_test_logged_in'] = true;
check_case( false === $security->audience()['valid'], 'Authenticated audience without File 00 contract fails closed.' );
$GLOBALS['f26_test_filter_values']['sabri_file26_membership_assertions'] = array(
	'contract_version' => '1.1.2', 'user_id' => 10, 'authenticated' => true, 'suspended' => false,
	'is_minor' => false, 'guardian_verified' => false, 'entitlements' => array(), 'consents' => array(), 'roles' => array( 'member' ),
);
check_case( true === $security->audience()['valid'], 'Supported File 00 membership assertion enables eligible private retrieval.' );
$GLOBALS['f26_test_filter_values'] = array();
$GLOBALS['f26_test_logged_in'] = false;

$doctor = new Doctor_Ranking( new Security() );
$doctor_base = array(
	'qualification_score' => .8, 'experience_score' => .7, 'patient_verified_review_score' => .9,
	'ethical_conduct_score' => .95, 'knowledge_contribution_score' => .8, 'responsiveness_score' => .7,
	'profile_completeness_score' => .9, 'complaint_appeal_outcome_score' => .85,
	'manipulation_resistant_engagement_score' => .6,
);
$doctor_paid = $doctor_base; $doctor_paid['donation'] = 1000000; $doctor_paid['payment'] = 1000000;
check_case( $doctor->score( $doctor_base ) === $doctor->score( $doctor_paid ), 'Doctor ranking excludes donation and payment.' );

printf( "Passed: %d\nFailed: %d\n", $passed, $failed );
exit( $failed ? 1 : 0 );
