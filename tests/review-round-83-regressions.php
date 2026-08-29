<?php
/** Round 83 connector-governance regression guards. */
$root = dirname( __DIR__ );
$owners = file_get_contents( $root . '/includes/class-file26-owner-contracts.php' );
$health = file_get_contents( $root . '/includes/class-file26-health.php' );
$checks = 0;
function f26_r83_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r83_assert( false !== strpos( $owners, 'strict_bool' ) && false !== strpos( $owners, 'true !== $this->strict_bool( $approved )' ), 'R83: activation approval uses strict boolean semantics' );
f26_r83_assert( false !== strpos( $owners, 'array_key_exists($key,$evidence)' ) && false !== strpos( $owners, 'true!==$this->strict_bool($evidence[$key])' ), 'R83: every cross-file activation evidence flag must be explicitly true' );
f26_r83_assert( false !== strpos( $health, "array('healthy','ok')" ) && false !== strpos( $health, '!in_array($connector[\'state\']' ), 'R83: unknown active connector health states fail closed to degraded' );
echo "PASS: $checks Round 83 regression assertions\n";
