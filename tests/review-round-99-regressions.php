<?php
/** Round 99 Future REST/privacy registration regression guards. */
$root = dirname( __DIR__ );
$infra = file_get_contents( $root . '/includes/trait-file26-future-infra-trait.php' );
$privacy = file_get_contents( $root . '/includes/class-file26-privacy.php' );
$plugin = file_get_contents( $root . '/includes/class-file26-plugin.php' );
$rest = file_get_contents( $root . '/includes/trait-file26-future-rest-trait.php' );
$utility = file_get_contents( $root . '/includes/trait-file26-future-utility-trait.php' );
$checks = 0;
function f26_r99_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r99_assert( false === strpos( $infra, "add_filter( 'wp_privacy_personal_data_exporters'" ) && false === strpos( $infra, "add_filter( 'wp_privacy_personal_data_erasers'" ), 'R99: Future orchestration does not double-register WordPress privacy handlers' );
f26_r99_assert( false !== strpos( $plugin, '$this->privacy->register()' ) && false !== strpos( $privacy, 'sabri_file26_search_history_sync_v1' ) && false !== strpos( $privacy, 'sabri_file26_discovery_controls_v1' ), 'R99: canonical Privacy service remains the single registrar and covers Future account data' );
f26_r99_assert( false !== strpos( $rest, "'private-search-vault' => array( 'id' => 'F26-FUT-22', 'methods' => 'POST', 'auth' => 'step_up'" ) && false !== strpos( $rest, "'relevance-lab' => array( 'id' => 'F26-FUT-24', 'methods' => 'GET,POST', 'auth' => 'audit'" ), 'R99: sensitive Future routes retain step-up/audit authorization classes' );
f26_r99_assert( false !== strpos( $utility, "'Cache-Control', 'private, no-store, max-age=0'" ), 'R99: all Future responses retain private no-store cache policy' );
echo "PASS: $checks Round 99 regression assertions\n";
