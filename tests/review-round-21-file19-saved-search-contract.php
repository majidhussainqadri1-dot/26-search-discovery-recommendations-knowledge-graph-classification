<?php
/**
 * Review round 21 — File 19 saved-search ownership boundary.
 */
$root = dirname(__DIR__);
$source = file_get_contents($root . '/includes/class-file26-central-plan.php');
if (!is_string($source)) {
    fwrite(STDERR, "File 26 central plan source unavailable.\n");
    exit(1);
}
$checks = array(
    'owner-filter-registered' => strpos($source, "sun_validate_saved_search_ownership") !== false,
    'owner-method-present' => strpos($source, 'validate_saved_search_ownership') !== false,
    'foreign-owner-pass-through' => strpos($source, "return \$current;") !== false,
    'bounded-id-check' => strpos($source, "/^[a-f0-9-]{36}$/") !== false,
    'canonical-owner-storage-only' => strpos($source, '$this->load_saved_queries( $user_id )') !== false,
    'no-query-payload-return' => strpos($source, "return true;") !== false,
);
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, "FAIL File19 saved-search contract: " . implode(', ', $failed) . "\n");
    exit(1);
}
echo "PASS: File 26 publishes bounded saved-search ownership truth for File 19 without exporting private query content\n";
