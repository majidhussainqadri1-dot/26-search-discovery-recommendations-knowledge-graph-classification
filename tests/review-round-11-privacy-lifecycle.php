<?php
/** Round 11 regression: privacy export paginates completely, erasure verifies atomic boundaries, and appeal retention cannot report DB failure as a zero-count success. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-privacy.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$checks = array(
	'private $page_size = 200' => 'bounded exporter page size',
	'LIMIT %d OFFSET %d' => 'feedback/appeal export pagination',
	'$done = count( $feedback ) < $this->page_size && count( $appeals ) < $this->page_size' => 'export completion follows both datasets',
	'false === $wpdb->query( \'START TRANSACTION\' )' => 'erasure verifies transaction start',
	'false === $wpdb->query( \'COMMIT\' )' => 'erasure verifies transaction commit',
	'$wpdb->query( \'ROLLBACK\' )' => 'erasure rolls back on partial failure',
	'(int) $updated !== $appeal_count' => 'appeal redaction completeness is verified',
	'\'done\' => false' => 'failed erasure remains retryable',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
$plugin_checks = array(
	'public function retain_doctor_appeals()' => 'appeal retention lifecycle exists',
	'file26_appeal_retention_failed' => 'retention DB failure has explicit fail-closed error',
	'if ( false === $withdrawn )' => 'open-appeal retention write is checked',
	'if ( false === $deleted )' => 'final-appeal retention delete is checked',
	'if ( false === $wpdb->query( \'COMMIT\' ) )' => 'appeal retention verifies commit',
);
foreach ( $plugin_checks as $needle => $label ) { if ( false === strpos( $plugin, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
if ( $failures ) { exit( 1 ); }
echo "Round 11 privacy lifecycle regression passed.\n";
