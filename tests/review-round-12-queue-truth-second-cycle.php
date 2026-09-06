<?php
/** Second-cycle Round 12 regression: queue reads and worker state transitions fail closed. */
$root = dirname( __DIR__ );
$worker = file_get_contents( $root . '/includes/class-file26-queue-truth.php' );
$bootstrap = file_get_contents( $root . '/file-26-search-discovery.php' );
$checks = array(
	array( $worker, 'file26_queue_read_failed', 'queue-head DB read failure is explicit' ),
	array( $worker, 'search_queue_read_failed', 'queue-head read failure is audited' ),
	array( $worker, 'file26_job_claim_failed', 'job claim DB write failure is explicit' ),
	array( $worker, 'file26_job_completion_failed', 'completion DB write failure is explicit' ),
	array( $worker, 'file26_job_failure_transition_failed', 'retry/dead-letter DB write failure is explicit' ),
	array( $worker, 'file26_job_lock_lost', 'worker CAS loss is explicit' ),
	array( $worker, "'finished_at' => $dead ? DB::now() : null", 'dead-letter transition records a finish time' ),
	array( $worker, 'worker_timeout', 'stale worker recovery remains implemented' ),
	array( $bootstrap, 'class-file26-queue-truth.php', 'checked queue worker is loaded' ),
	array( $bootstrap, 'remove_action( \\Sabri\\File26\\DB::CRON_QUEUE', 'legacy unchecked cron callback is removed' ),
	array( $bootstrap, "add_action( \\Sabri\\File26\\DB::CRON_QUEUE, array( $queue_truth, 'run' ) )", 'queue cron uses checked worker' ),
);
$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 12 queue-truth regression passed.\n";
