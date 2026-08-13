<?php
$src = file_get_contents( dirname( __DIR__ ) . '/includes/trait-file26-future-infra-trait.php' );
foreach ( array( 'file26_future_erasure_incomplete', 'items_retained', 'metadata_exists' ) as $needle ) { if ( false === strpos( $src, $needle ) ) { fwrite( STDERR, "FAIL: Future24 Round 5 erasure truthfulness\n" ); exit( 1 ); } }
echo "PASS: Future24 Round 5 erasure truthfulness\n";
