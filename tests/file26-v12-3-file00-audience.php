<?php
/** File00 source-contract security regression. Pure PHP isolated identity owner stub. */
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/includes/class-file26-security.php';

function smc_membership_assertions($subject) {
    if (!empty($GLOBALS['f26_native_throws'])) { throw new \RuntimeException('Synthetic owner failure'); }
    return $GLOBALS['f26_native_claims'] ?? null;
}
function f26_identity_require($ok,$name) {
    if (!$ok) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    echo "PASS: $name\n";
}
$security = new \Sabri\File26\Security();
$GLOBALS['f26_test_logged_in'] = true;
$base = array(
    'contract_version'=>'1.2.3',
    'user_id'=>10,
    'approved'=>true,
    'eligible'=>true,
    'suspended'=>false,
    'guardian_verified'=>true,
    'age_context'=>array('known'=>true,'minor'=>false,'guardian_required'=>false),
    'entitlements'=>array('policy_version'=>'free','base_services'=>array('education'=>true,'clinic'=>true,'marketplace'=>true,'premium'=>false)),
);

// A forged WP filter cannot override a canonical File00 owner denial.
$GLOBALS['f26_test_filter_values']['sabri_file26_membership_assertions'] = array(
    'contract_version'=>'1.1.2','user_id'=>10,'approved'=>true,'eligible'=>true,
    'suspended'=>false,'guardian_verified'=>true,
);
$GLOBALS['f26_native_claims'] = $base;
$allowed = $security->audience();
f26_identity_require($allowed['valid'] === true, 'Current File00 1.2.3 approved & eligible member is accepted');
f26_identity_require($allowed['user_id'] === 10 && $allowed['contract_version'] === '1.2.3', 'Authenticated subject and owner contract are canonical');
f26_identity_require($security->can_view_visibility('members',$allowed), 'Only canonically eligible members see member content');
f26_identity_require($security->can_view_visibility('entitled',$allowed,array('required_entitlement'=>'education')), 'File00 native single-free-tier base_services education entitlement is respected');
f26_identity_require(!$security->can_view_visibility('entitled',$allowed,array('required_entitlement'=>'premium')), 'File00 disabled/paid/premium entitlement must never be inferred');

$GLOBALS['f26_native_claims'] = array_merge($base,array('approved'=>false,'eligible'=>false));
$denied = $security->audience();
f26_identity_require($denied['valid'] === false, 'Pending/unapproved owner membership is denied even if forged filter approves');
f26_identity_require(!$security->can_view_visibility('members',$denied), 'Pending subject cannot read member-only index');

$GLOBALS['f26_native_claims'] = array_merge($base,array('eligible'=>false,'approved'=>true));
f26_identity_require(!$security->audience()['valid'], 'Approved but ineligible File00 owner is denied');

$GLOBALS['f26_native_claims'] = array_merge($base,array('user_id'=>11));
f26_identity_require(!$security->audience()['valid'], 'Owner assertion for another subject cannot authorize current user');

$GLOBALS['f26_native_claims'] = array_merge($base,array('contract_version'=>'1.3.0'));
f26_identity_require(!$security->audience()['valid'], 'Unknown future File00 major/minor contract fails closed');

$GLOBALS['f26_native_claims'] = array_merge($base,array('suspended'=>true));
f26_identity_require(!$security->audience()['valid'], 'Suspended member fails closed');

$GLOBALS['f26_native_claims'] = array_merge($base,array(
    'age_context'=>array('known'=>true,'minor'=>true,'guardian_required'=>true),
    'guardian_verified'=>false,
));
$minor = $security->audience();
f26_identity_require($minor['is_minor'] === true, 'File00 age_context.minor is reflected in File26');
f26_identity_require(!$minor['valid'], 'Minor without mandatory guardian is not authorized for member content');
f26_identity_require(!$security->can_view_visibility('minor_guarded',$minor), 'Minor lacks restricted data access without guardian');

$GLOBALS['f26_native_claims'] = array_merge($base,array(
    'age_context'=>array('known'=>false,'minor'=>false,'guardian_required'=>false),
    'guardian_verified'=>false,
));
$unknown = $security->audience();
f26_identity_require($unknown['is_minor'] === true && !$security->can_view_visibility('minor_guarded',$unknown), 'Unknown age is not silently treated as verified adulthood');

$GLOBALS['f26_native_claims'] = $base;
$GLOBALS['f26_native_throws'] = true;
f26_identity_require(!$security->audience()['valid'], 'File00 owner runtime exception fails closed; no filter fallback');
unset($GLOBALS['f26_native_throws']);

$GLOBALS['f26_test_logged_in'] = false;
$guest = $security->audience();
f26_identity_require($guest['valid'] === true && !$guest['authenticated'], 'Guest remains public-only without owner session');
f26_identity_require(!$security->can_view_visibility('members',$guest), 'Guest cannot read member-only content');

echo "PASS: V12.3 canonical File00 identity-source binding tests\n";
