<?php
$root = dirname( __DIR__ );
$qa = file_get_contents( $root . '/qa/run-tests.sh' );
$workflow = file_get_contents( $root . '/.github/workflows/qa.yml' );

$fail = static function ( $message ) {
    fwrite( STDERR, "FAIL: {$message}\n" );
    exit( 1 );
};

if ( file_exists( $root . '/release/26-sabri-file26-search-discovery-1.0.0.zip' ) ) {
    $fail( 'A stale tracked 1.0.0 installable package must not remain beside 1.2.0 source.' );
}
if ( false === strpos( $qa, 'sha256sum "26-sabri-file26-search-discovery-1.2.0.zip" > CHECKSUMS.sha256' ) ) {
    $fail( 'Release checksum must be generated with a portable package basename.' );
}
if ( false === strpos( $workflow, 'release/CHECKSUMS.sha256' ) ) {
    $fail( 'Exact-head CI artifact must upload the package checksum with the ZIP.' );
}
if ( ! file_exists( $root . '/release/README.md' ) ) {
    $fail( 'Release truth boundary must remain documented.' );
}

echo "PASS: round 22 exact-head release artifact truth\n";
