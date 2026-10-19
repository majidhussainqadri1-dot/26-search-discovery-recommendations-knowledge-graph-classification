<?php
/** Round 85 ranking-authorization regression guards. */
$root = dirname( __DIR__ );
$security = file_get_contents( $root . '/includes/class-file26-security.php' );
$bootstrap = file_get_contents( $root . '/file-26-search-discovery.php' );
$ranking = file_get_contents( $root . '/includes/class-file26-ranking.php' );
$checks = 0;
function f26_r85_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r85_assert( false !== strpos( $security, 'normalize_claim_bool(apply_filters' ) && false !== strpos( $security, 'return true===$decision' ), 'R85: high-risk step-up requires explicit true authorization' );
f26_r85_assert( false !== strpos( $security, 'normalize_authorization' ) && false !== strpos( $bootstrap, 'sabri_file26_validate_ranking_approver' ) && false !== strpos( $bootstrap, 'PHP_INT_MAX' ), 'R85: ranking-approver filter is normalized at the final authorization boundary' );
f26_r85_assert( false !== strpos( $ranking, 'Forbidden financial, advertising and favoritism signals are never read.' ) && false === strpos( $ranking, "['donation']" ) && false === strpos( $ranking, "['payment']" ), 'R85: organic ranking remains free of financial/favoritism inputs' );
echo "PASS: $checks Round 85 regression assertions\n";
