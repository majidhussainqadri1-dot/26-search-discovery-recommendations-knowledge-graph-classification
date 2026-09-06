<?php
/** Round 105 ranking/security regression guards. */
$root = dirname( __DIR__ );
$security = file_get_contents( $root . '/includes/class-file26-security.php' );
$ranking = file_get_contents( $root . '/includes/class-file26-ranking.php' );
$search = file_get_contents( $root . '/includes/class-file26-search.php' );
$connectors = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$owners = file_get_contents( $root . '/includes/class-file26-owner-contracts.php' );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$checks = 0;
function f26_r105_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r105_assert( false !== strpos( $security, 'if(is_float($value)){if(1.0===$value){return true;}if(0.0===$value){return false;}return null;}' ), 'R105: Security accepts only exact 0.0/1.0 numeric authorization booleans' );
f26_r105_assert( false === strpos( $security, '1===(int)$value' ) && false === strpos( $connectors, '1 === (int) $value' ) && false === strpos( $owners, '1 === (int) $value' ) && false === strpos( $indexer, '1 === (int) $value' ), 'R105: reviewed strict-boolean helpers no longer integer-cast arbitrary floats' );
f26_r105_assert( false !== strpos( $ranking, "\$wpdb->last_error = ''" ) && false !== strpos( $ranking, "'policy_read_failed' => false" ) && false !== strpos( $ranking, "\$defaults['version'] = 'policy-unavailable'" ), 'R105: core ranking policy distinguishes database read failure from no active row' );
f26_r105_assert( false !== strpos( $ranking, 'public function policy_read_failed' ), 'R105: ranking exposes policy-read failure to retrieval callers' );
f26_r105_assert( false !== strpos( $search, 'file26_ranking_policy_read_failed' ) && false !== strpos( $search, '$this->ranking->policy_read_failed()' ), 'R105: search fails closed with 503 when active ranking policy cannot be read safely' );
echo "PASS: $checks Round 105 regression assertions\n";
