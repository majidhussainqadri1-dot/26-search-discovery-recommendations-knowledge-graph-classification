<?php
/** Round 90 privacy/uninstall regression guards. */
$root = dirname( __DIR__ );
$privacy = file_get_contents( $root . '/includes/class-file26-privacy.php' );
$central = file_get_contents( $root . '/includes/class-file26-central-plan.php' );
$uninstall = file_get_contents( $root . '/uninstall.php' );
$checks = 0;
function f26_r90_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r90_assert( false !== strpos( $uninstall, '$destructive_raw' ) && false !== strpos( $uninstall, "true === \$destructive_raw" ) && false !== strpos( $uninstall, "'1' === \$destructive_raw" ), 'R90: destructive uninstall requires an explicit opt-in value' );
f26_r90_assert( false === strpos( $uninstall, "\$destructive = (bool) get_option" ), 'R90: destructive uninstall no longer uses PHP truthiness' );
f26_r90_assert( false !== strpos( $privacy, 'future_meta_keys' ) && false !== strpos( $privacy, 'START TRANSACTION' ) && false !== strpos( $privacy, 'Ranking appeal text and identity were redacted' ), 'R90: account-owned Future data and ranking appeals retain privacy lifecycle coverage' );
f26_r90_assert( false !== strpos( $central, 'privacy_export' ) && false !== strpos( $central, 'privacy_erase' ) && false !== strpos( $central, 'META_SAVED_QUERIES' ), 'R90: saved queries retain dedicated privacy export/erase coverage' );
echo "PASS: $checks Round 90 regression assertions\n";
