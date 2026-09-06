<?php
$root = dirname( __DIR__ );
$search = file_get_contents( $root . '/includes/class-file26-search.php' );
$indexer = file_get_contents( $root . '/includes/class-file26-indexer.php' );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$fail = static function ( $m ) { fwrite( STDERR, "FAIL: {$m}\n" ); exit( 1 ); };

foreach ( array(
    "'audience' => \$audience_fingerprint",
    "d.safety_class NOT IN ('blocked','restricted')",
    "file26_search_read_failed",
    "file26_suggest_read_failed",
    "unset( \$payload['download_url'] )",
) as $needle ) {
    if ( false === strpos( $search, $needle ) ) { $fail( 'Missing search safeguard: ' . $needle ); }
}
if ( false === strpos( $rest, 'if ( is_wp_error( $suggestions ) ) { return $suggestions; }' ) ) {
    $fail( 'Suggestion read failures must propagate through REST.' );
}
foreach ( array(
    "Capability-bearing signed delivery URLs are intentionally excluded",
    "START TRANSACTION",
    "file26_node_write_failed",
    "Stale tombstone cleanup failed.",
    "Document revocation and all derivatives could not be completed atomically.",
    "JSON_REMOVE(payload,'$.download_url')",
) as $needle ) {
    if ( false === strpos( $indexer, $needle ) ) { $fail( 'Missing index/revocation safeguard: ' . $needle ); }
}
if ( false !== strpos( $indexer, "'download_url'" ) ) {
    $fail( 'Capability-bearing download_url must not remain in the indexed payload allowlist.' );
}

echo "PASS: round 24 search eligibility, cursor and index atomicity\n";
