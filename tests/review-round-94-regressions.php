<?php
/** Round 94 future search-core regression guards. */
$root = dirname( __DIR__ );
$core = file_get_contents( $root . '/includes/trait-file26-future-search-core.php' );
$checks = 0;
function f26_r94_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r94_assert( false !== strpos( $core, 'provider_rejected_extractive_fallback' ) && false !== strpos( $core, 'called_no_usable_answer' ), 'R94: grounded-answer provider rejection is disclosed instead of looking uncalled' );
f26_r94_assert( substr_count( $core, "return strcmp( isset( \$a['key'] )" ) >= 2, 'R94: cross-language and semantic rerank ties use deterministic canonical-key ordering' );
f26_r94_assert( false !== strpos( $core, '$usable_scores = 0' ) && false !== strpos( $core, '$provider = $usable_scores > 0' ), 'R94: semantic provider availability requires at least one usable finite candidate score' );
f26_r94_assert( false !== strpos( $core, "substr( sanitize_text_field( (string) \$params['locale'] ), 0, 20 )" ), 'R94: cross-language locale input is bounded' );
echo "PASS: $checks Round 94 regression assertions\n";
