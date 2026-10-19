<?php
/** Round 102 source-event/version integrity regression guards. */
$root = dirname( __DIR__ );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$checks = 0;
function f26_r102_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r102_assert( false !== strpos( $indexer, 'file26_invalid_object_version' ) && false !== strpos( $indexer, 'strict_integer( $document[\'object_version\'], 1 )' ), 'R102: indexed object versions must be explicit positive integers' );
f26_r102_assert( false !== strpos( $indexer, 'file26_invalid_source_event_sequence' ) && false !== strpos( $indexer, 'strict_integer( $document[\'source_event_sequence\'], 0 )' ), 'R102: supplied source event sequences must be non-negative integers' );
f26_r102_assert( false !== strpos( $indexer, 'file26_conflicting_source_event' ) && false !== strpos( $indexer, 'same_version_sequence_different_checksum' ), 'R102: same version and sequence with conflicting content fails closed' );
f26_r102_assert( false !== strpos( $indexer, "unset( \$checksum_data['indexed_at'], \$checksum_data['updated_at'] )" ) && false !== strpos( $indexer, "unset( \$checksum_data['freshness_at'] )" ), 'R102: idempotency checksum excludes runtime-generated timestamps' );
f26_r102_assert( false !== strpos( $indexer, '$version=$this->strict_integer($object_version,1)' ) && false === strpos( $indexer, 'max(1,(int)$object_version)' ), 'R102: direct tombstones reject malformed versions instead of clamping them' );
echo "PASS: $checks Round 102 regression assertions\n";
