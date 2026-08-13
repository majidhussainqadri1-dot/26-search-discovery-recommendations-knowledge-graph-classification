<?php
/** Round 17 regression: admin operations cannot redirect to success after a proven queue/reconcile error. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-admin.php' );
$checks = array(
	"$job = $this->indexer->enqueue_reindex" => 'reindex result is captured',
	"if ( is_wp_error( $job ) )" => 'reindex error is surfaced',
	"$result = $this->indexer->reconcile()" => 'reconcile result is captured',
	"if ( is_wp_error( $result ) )" => 'reconcile error is surfaced',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
if ( $failures ) { exit( 1 ); }
echo "Round 17 admin operation truth regression passed.\n";
