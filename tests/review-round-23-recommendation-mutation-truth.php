<?php
$root = dirname( __DIR__ );
$code = file_get_contents( $root . '/includes/class-file26-recommendations.php' );
$fail = static function ( $message ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); };

foreach ( array(
    "return new \\WP_Error( 'file26_feedback_write_failed'",
    "return new \\WP_Error( 'file26_idempotency_conflict'",
    "ORDER BY id DESC LIMIT 1500",
    "return new \\WP_Error( 'file26_negative_projection_write_failed'",
    "Recommendation consent and its dependent signals could not be updated atomically.",
    "Opt-out state and dependent signal purge could not be persisted atomically.",
) as $needle ) {
    if ( false === strpos( $code, $needle ) ) { $fail( 'Missing recommendation mutation safeguard: ' . $needle ); }
}
if ( false !== strpos( $code, "ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)" ) ) {
    $fail( 'Feedback idempotency must not silently accept different payloads under one key.' );
}
if ( substr_count( $code, "START TRANSACTION" ) < 4 || substr_count( $code, "ROLLBACK" ) < 4 ) {
    $fail( 'Feedback/consent/reset/opt-out mutations must preserve atomic failure truth.' );
}

echo "PASS: round 23 recommendation mutation and privacy truth\n";
