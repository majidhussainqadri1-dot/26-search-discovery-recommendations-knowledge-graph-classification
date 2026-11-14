<?php
/** Round 104 search owner-visibility/partial-state regression guards. */
$root = dirname( __DIR__ );
$connectors = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$checks = 0;
function f26_r104_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r104_assert( false !== strpos( $connectors, "return true === \$decision" ) && false === strpos( $connectors, 'return (bool) call_user_func' ) && false === strpos( $connectors, 'return $this->security->can_view_visibility(' ), 'R104: owner visibility requires exact typed true and never a generic ACL fallback' );
f26_r104_assert( false !== strpos( $connectors, 'is_wp_error( $value )' ) && false !== strpos( $connectors, "\$state = 'degraded'; \$detail = array( 'error_code'" ), 'R104: WP_Error health callback results are degraded, never healthy' );
f26_r104_assert( false !== strpos( $connectors, 'connector_health_read_failed' ) && false !== strpos( $connectors, "\$wpdb->last_error = ''" ), 'R104: connector-health database read failures surface as degraded partial-state evidence' );
f26_r104_assert( false !== strpos( $connectors, "true === \$this->strict_bool( \$value ) ? 'healthy' : 'degraded'" ), 'R104: scalar health callbacks use strict boolean semantics' );
echo "PASS: $checks Round 104 regression assertions\n";
