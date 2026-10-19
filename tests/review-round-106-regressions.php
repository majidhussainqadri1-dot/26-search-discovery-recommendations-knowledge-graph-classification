<?php
/** Round 106 recommendation consent/idempotency regression guards. */
$root = dirname( __DIR__ );
$recommendations = file_get_contents( $root . '/includes/class-file26-recommendations.php' );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$checks = 0;
function f26_r106_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r106_assert( false !== strpos( $recommendations, '$consent = $this->strict_bool( $consent )' ) && false !== strpos( $recommendations, 'file26_invalid_consent' ) && false === strpos( $recommendations, '$consent = (bool) $consent' ), 'R106: recommendation consent rejects malformed booleans instead of truthiness-casting them' );
f26_r106_assert( false !== strpos( $rest, 'if(is_float($value)){if(1.0===$value){return true;}if(0.0===$value){return false;}return null;}' ) && false === strpos( $rest, '1===(int)$value' ), 'R106: REST consent accepts only exact numeric 0/1 boolean values' );
f26_r106_assert( false !== strpos( $recommendations, 'SELECT user_id,item_key,feedback_type,scope_key,payload FROM' ) && false !== strpos( $recommendations, 'feedback_idempotency_conflict' ), 'R106: feedback idempotency is re-read and semantically verified after insert/upsert' );
f26_r106_assert( false !== strpos( $recommendations, 'file26_feedback_idempotency_conflict' ) && false !== strpos( $recommendations, "array( 'status' => 409 )" ), 'R106: conflicting reuse of an idempotency key returns a conflict rather than a false success' );
echo "PASS: $checks Round 106 regression assertions\n";
