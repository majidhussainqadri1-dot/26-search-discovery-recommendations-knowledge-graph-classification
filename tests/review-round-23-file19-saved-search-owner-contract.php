<?php
/** File 26 remains the sole saved-query storage owner while File 19 receives only ownership assertions. */
$root = dirname(__DIR__);
$src = (string) file_get_contents($root . '/includes/class-file26-central-plan.php');
$required = array(
    "add_filter( 'sun_validate_saved_search_ownership'",
    'public function validate_saved_search_ownership',
    '$this->load_saved_queries( $user_id )',
    'file26_saved_query_not_owned',
);
foreach ($required as $needle) {
    if (false === strpos($src, $needle)) {
        fwrite(STDERR, "Missing File26/File19 saved-search owner invariant: {$needle}\n");
        exit(1);
    }
}
if (substr_count($src, "add_filter( 'sun_validate_saved_search_ownership'") !== 1) {
    fwrite(STDERR, "Saved-search ownership bridge must be registered exactly once.\n");
    exit(1);
}
echo "PASS: review round 23 File19 saved-search owner contract\n";
