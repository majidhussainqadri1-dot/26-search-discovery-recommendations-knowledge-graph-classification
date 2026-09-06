<?php
/** Round 97 user-data/privacy regression guards. */
$root = dirname( __DIR__ );
$utility = file_get_contents( $root . '/includes/trait-file26-future-utility-trait.php' );
$user_data = file_get_contents( $root . '/includes/trait-file26-future-user-data.php' );
$future_js = file_get_contents( $root . '/assets/js/file26-future.js' );
$checks = 0;
function f26_r97_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r97_assert( false !== strpos( $utility, "normalize_authorization( apply_filters( 'sabri_file26_future_sensitive_query'" ), 'R97: Future sensitive-query extension uses strict boolean normalization' );
f26_r97_assert( false !== strpos( $user_data, "'file26_alert_cadence_invalid'" ) && false !== strpos( $user_data, "array( 'hourly', 'daily', 'weekly' )" ), 'R97: explicit invalid saved-alert cadence is rejected instead of silently defaulting' );
f26_r97_assert( false !== strpos( $future_js, "read().filter((item) => cleanQuery(item && item.query) !== q)" ), 'R97: local-first browser history deduplicates an existing normalized query before append' );
f26_r97_assert( false !== strpos( $future_js, "JSON.stringify(items.slice(-MAX))" ), 'R97: local-first browser history remains bounded after deduplication' );
echo "PASS: $checks Round 97 regression assertions\n";
