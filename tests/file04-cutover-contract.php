<?php
$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$main = file_get_contents( $root . '/file-26-search-discovery.php' );
$checks = array(
	array( $plugin, "sabri_file26_accept_file04_contract_v1", 'File 04 contract acceptance filter is missing.' ),
	array( $plugin, "snfla_request_search_reindex", 'File 04 cutover reindex action is missing.' ),
	array( $plugin, "snfla_verify_cutover_search_reindex", 'File 04 reindex verification filter is missing.' ),
	array( $plugin, "file21_connector", 'File 04 cutover must bind to the canonical File 21 connector.' ),
	array( $plugin, "enqueue_reindex", 'File 04 cutover must use File 26 canonical reindex queue.' ),
	array( $plugin, "'active' !== (string) \\$connector['status']", 'Cutover verification must require the active File 21 search lane.' ),
	array( $plugin, "'completed' === (string) \\$job['status'] && 0 === \\$failed", 'Cutover verification must require completed zero-failure reindex evidence.' ),
	array( $main, "Version: 1.2.1", 'File 26 release version must be 1.2.1.' ),
);
$fail = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, "FAIL: {$check[2]}\n" );
		$fail++;
	}
}
if ( $fail ) { exit( 1 ); }
echo "File 04 cutover contract regression passed.\n";
