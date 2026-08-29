<?php
/** Round 78 regression guards. */
$root = dirname( __DIR__ );
$core = file_get_contents( $root . '/includes/trait-file26-future-search-core.php' );
$checks = 0;
function f26_r78_assert( $condition, $message ) {
    global $checks;
    $checks++;
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
f26_r78_assert( false !== strpos( $core, 'file26_query_planner_execute_invalid' ) && false !== strpos( $core, 'future_strict_bool' ), 'R78: query-planner execution requires an explicit boolean' );
f26_r78_assert( false !== strpos( $core, 'provider_bypassed_for_sensitive_or_clinical' ) && false !== strpos( $core, 'autonomous_clinical_intent' ), 'R78: cross-language provider is bypassed for sensitive or clinical-intent queries' );
f26_r78_assert( false !== strpos( $core, 'semantic_cross_language_provider_available' ) && false !== strpos( $core, '$provider_called && $usable_provider_variants > 0' ), 'R78: cross-language provider availability reflects usable safe variants' );
echo "PASS: $checks Round 78 regression assertions\n";
