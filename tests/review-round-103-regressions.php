<?php
/** Round 103 connector/owner-contract governance regression guards. */
$root = dirname( __DIR__ );
$connectors = file_get_contents( $root . '/includes/class-file26-connectors.php' );
$owners = file_get_contents( $root . '/includes/class-file26-owner-contracts.php' );
$checks = 0;
function f26_r103_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r103_assert( false !== strpos( $connectors, 'file26_connector_governance_read_failed' ) && false !== strpos( $connectors, "null === \$existing && '' !== \$wpdb->last_error" ), 'R103: connector persisted-governance reads fail closed on database error' );
f26_r103_assert( false !== strpos( $owners, '$privacy_classes=' ) && false !== strpos( $owners, '$visibility_fields=' ) && false !== strpos( $owners, '$deletion_semantics=' ), 'R103: owner adapters explicitly normalize required privacy/visibility/deletion contract fields' );
f26_r103_assert( false !== strpos( $owners, 'if(empty($privacy_classes)||empty($visibility_fields)||\'\'===$deletion_semantics){continue;}' ), 'R103: incomplete owner privacy/deletion contracts are rejected rather than defaulted' );
f26_r103_assert( false === strpos( $owners, "array('public')" ) && false === strpos( $owners, "array('state','visibility')" ) && false === strpos( $owners, ":'versioned_tombstone'" ), 'R103: canonical owner collector no longer fabricates required contract defaults' );
echo "PASS: $checks Round 103 regression assertions\n";
