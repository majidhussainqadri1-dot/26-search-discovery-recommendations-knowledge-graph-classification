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
f26_r79_assert( false !== strpos( $multi, '$seed_provenance' ) && false !== strpos( $multi, "seed_provenance' => $seed_provenance" ) === false && false !== strpos( $multi, 'seed_provider_unavailable_or_not_authorized' ), 'R79: find-similar requires sanitized seed provenance' );
f26_r79_assert( false !== strpos( $multi, '$owner = sanitize_key' ) && false !== strpos( $multi, '$object_id = substr' ) && false !== strpos( $multi, '$provenance = substr' ), 'R79: segment owner/object/provenance are sanitized before acceptance' );
f26_r79_assert( false !== strpos( $advanced, '$owner = sanitize_key' ) && false !== strpos( $advanced, '$object_id = substr' ), 'R79: private-vault owner/object references are sanitized before acceptance' );
f26_r79_assert( false !== strpos( $advanced, '$retrieved_ts' ) && false !== strpos( $advanced, '$rights_status = sanitize_key' ) && false !== strpos( $advanced, '$provenance = $this->bounded_future_text' ), 'R79: external evidence validates sanitized provenance/rights and retrieval time' );
echo "PASS: $checks Round 79 regression assertions\n";
