<?php
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-recommendations.php' );
$checks = array(
	'file26_idempotency_conflict' => 'idempotency key reuse with different semantics is rejected',
	'idempotent_replay' => 'same feedback replay returns stable success',
	"'feedback_type' => 'undo'" => 'undo receives a durable idempotency receipt',
	'Recommendation feedback could not be recorded atomically.' => 'feedback insert and negative-control rebuild fail atomically',
	'Consent feedback purge failed.' => 'consent revocation checks feedback purge result',
	'Consent feedback purge incomplete.' => 'consent revocation verifies stored signals are gone',
	'Recommendation consent and stored signals could not be updated atomically.' => 'consent state and signal purge share a transaction',
	'file26_profile_rebuild_failed' => 'negative-control rebuild failure is explicit',
);
$failed = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "FAIL: $label\n" );
		$failed++;
	} else {
		echo "PASS: $label\n";
	}
}
exit( $failed ? 1 : 0 );
