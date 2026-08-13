<?php
$root = dirname( __DIR__ );
$knowledge = file_get_contents( $root . '/includes/trait-file26-future-knowledge.php' );
$advanced = file_get_contents( $root . '/includes/trait-file26-future-advanced.php' );
foreach ( array( 'snapshot_provider_invalid_evidence', 'valid_provider_timestamp', '$snapshot_policy === $current_policy_version' ) as $needle ) {
    if ( false === strpos( $knowledge, $needle ) ) { fwrite( STDERR, "FAIL: Round7 research snapshot evidence\n" ); exit( 1 ); }
}
if ( false === strpos( $advanced, 'valid_provider_timestamp( $retrieved_at )' ) ) { fwrite( STDERR, "FAIL: Round7 external freshness evidence\n" ); exit( 1 ); }
echo "PASS: Future24 Round 7 provider freshness regressions\n";
