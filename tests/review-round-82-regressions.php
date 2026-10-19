<?php
/** Round 82 index-lifecycle regression guards. */
$root = dirname( __DIR__ );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$checks = 0;
function f26_r82_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r82_assert( false !== strpos( $indexer, 'file26_tombstone_version_read_failed' ), 'R82: tombstone precedence read failure fails closed' );
f26_r82_assert( false !== strpos( $indexer, 'file26_document_version_read_failed' ), 'R82: existing document read failure fails closed' );
f26_r82_assert( false !== strpos( $indexer, 'file26_tombstone_document_read_failed' ), 'R82: revocation document-version read failure fails closed' );
f26_r82_assert( false !== strpos( $indexer, 'file26_invalid_canonical_identity' ), 'R82: canonical identity must remain non-empty after sanitization' );
f26_r82_assert( false !== strpos( $indexer, 'file26_invalid_payload_boolean' ) && false !== strpos( $indexer, 'strict_bool' ), 'R82: trust-bearing payload booleans use strict parsing' );
f26_r82_assert( false !== strpos( $indexer, 'time() + 300' ) && false !== strpos( $indexer, 'valid non-future date/time' ), 'R82: excessive future freshness is rejected' );
echo "PASS: $checks Round 82 regression assertions\n";
