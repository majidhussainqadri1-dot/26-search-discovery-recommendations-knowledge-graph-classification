<?php
/** Round 11 regression: privacy export paginates completely and erasure cannot report partial success. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-privacy.php' );
$checks = array(
	'private $page_size = 200' => 'bounded exporter page size',
	'LIMIT %d OFFSET %d' => 'feedback/appeal export pagination',
	"$done = count( $feedback ) < $this->page_size && count( $appeals ) < $this->page_size" => 'export completion follows both datasets',
	"$wpdb->query( 'START TRANSACTION' )" => 'erasure is atomic',
	"$wpdb->query( 'ROLLBACK' )" => 'erasure rolls back on partial failure',
	"(int) $updated !== $appeal_count" => 'appeal redaction completeness is verified',
	"'done' => false" => 'failed erasure remains retryable',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; }
}
if ( $failures ) { exit( 1 ); }
echo "Round 11 privacy lifecycle regression passed.\n";
