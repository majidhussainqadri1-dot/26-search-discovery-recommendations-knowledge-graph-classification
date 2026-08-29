<?php
/** Round 79 regression guards. */
$root = dirname( __DIR__ );
$multi = file_get_contents( $root . '/includes/trait-file26-future-multimodal.php' );
$advanced = file_get_contents( $root . '/includes/trait-file26-future-advanced.php' );
$checks = 0;
function f26_r79_assert( $condition, $message ) {
    global $checks;
    $checks++;
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
f26_r79_assert( false !== strpos( $multi, '$seed_provenance' ) && false !== strpos( $multi, "'' === $seed_provenance" ), 'R79: find-similar requires sanitized seed provenance' );
f26_r79_assert( false !== strpos( $multi, "'' === $owner || '' === $object_id || '' === $provenance" ), 'R79: segment owner/object/provenance must remain non-empty after sanitization' );
f26_r79_assert( false !== strpos( $advanced, "'' === $owner || '' === $object_id" ), 'R79: private-vault owner/object references must remain non-empty after sanitization' );
f26_r79_assert( false !== strpos( $advanced, '$retrieved_ts' ) && false !== strpos( $advanced, "'' === $source_name || '' === $rights_status || '' === $provenance" ), 'R79: external evidence validates sanitized provenance/rights and retrieval time' );
echo "PASS: $checks Round 79 regression assertions\n";
