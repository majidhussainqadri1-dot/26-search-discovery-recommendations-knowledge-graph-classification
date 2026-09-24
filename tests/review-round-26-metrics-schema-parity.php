<?php
$root = dirname(__DIR__);
$db = file_get_contents($root . '/includes/class-file26-db.php');
$plugin = file_get_contents($root . '/includes/class-file26-plugin.php');
$runtime = file_get_contents($root . '/includes/class-file26-search.php') . file_get_contents($root . '/includes/class-file26-central-plan.php');
$columns = array('metric_date','metric_key','bucket_hash','locale','count_value','sum_value','updated_at');
foreach ($columns as $column) {
    if (strpos($db, $column) === false) { fwrite(STDERR, "Metrics schema is missing $column\n"); exit(1); }
    if (strpos($plugin, "'$column'") === false) { fwrite(STDERR, "Schema verifier is missing $column\n"); exit(1); }
}
foreach (array('metric_name','dimensions_hash','bucket_start','metric_value') as $stale) {
    $verification = substr($plugin, strpos($plugin, "'metrics' =>"), 500);
    if (strpos($verification, "'$stale'") !== false) { fwrite(STDERR, "Stale metrics verifier column remains: $stale\n"); exit(1); }
}
if (strpos($runtime, 'metric_key') === false || strpos($runtime, 'count_value') === false) { fwrite(STDERR, "Runtime metrics contract no longer matches canonical schema\n"); exit(1); }
echo "File 26 metrics schema/runtime verifier parity: PASS\n";
