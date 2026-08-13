<?php
$root = dirname( __DIR__ );
$files = array(
    $root . '/includes/trait-file26-future-user-discovery.php',
    $root . '/includes/trait-file26-future-user-data.php',
    $root . '/includes/trait-file26-future-multimodal.php',
);
$src = implode("\n", array_map( 'file_get_contents', $files ) );
$checks = array(
    'file26_recommendation_reset_flag_invalid' => 'recommendation reset strict boolean',
    'file26_history_disable_sync_flag_invalid' => 'history disable_sync strict boolean',
    'file26_multimodal_diagnose_flag_invalid' => 'multimodal diagnose strict boolean',
);
foreach ( $checks as $needle => $label ) { if ( false === strpos( $src, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); } }
echo "PASS: " . count( $checks ) . " Future24 Round 3 regressions\n";
