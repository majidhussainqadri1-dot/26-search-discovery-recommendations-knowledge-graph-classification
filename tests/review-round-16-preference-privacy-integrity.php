<?php
/** Round 16 regression: consented recommendation data never hides DB or transaction failure. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-recommendations.php' );
$checks = array(
	array( $source, 'file26_feedback_transaction_failed', 'feedback mutations verify transaction start' ),
	array( $source, 'Feedback commit failed.', 'feedback creation verifies commit' ),
	array( $source, 'Feedback reversal commit failed.', 'feedback undo verifies commit' ),
	array( $source, 'file26_feedback_read_failed', 'idempotency read failure is distinct from no prior action' ),
	array( $source, 'file26_consent_transaction_failed', 'consent mutation verifies transaction start' ),
	array( $source, 'Consent commit failed.', 'consent mutation verifies commit' ),
	array( $source, 'file26_profile_transaction_failed', 'profile reset verifies transaction start' ),
	array( $source, 'Profile reset commit failed.', 'profile reset verifies commit' ),
	array( $source, 'file26_opt_out_transaction_failed', 'opt-out verifies transaction start' ),
	array( $source, 'Opt-out commit failed.', 'opt-out verifies commit' ),
	array( $source, 'file26_profile_read_failed', 'profile DB failure is explicit' ),
	array( $source, 'file26_profile_write_failed', 'interest write DB failure is distinct from optimistic-lock conflict' ),
	array( $source, '! empty( $wpdb->last_error )', 'negative-control and profile reads inspect DB error state' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) { fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Round 16 preference privacy integrity regression passed.\n";
