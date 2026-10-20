<?php
/** Exact-HEAD File 26 2026-10-20: 40 scoped source/owner plan checks. */
$round = isset($argv[1]) ? (int) $argv[1] : 0;
if ($round < 1 || $round > 40) { fwrite(STDERR,"FAIL: invalid fresh round\n");exit(2); }
require __DIR__.'/file26-20261019-fresh40-focus-contract.php';
if (4 === $round || 28 === $round) {
    require __DIR__.'/file26-20261020-queue-partial-failure-regression.php';
}
echo "PASS 20261020 F26 independently scoped audit round ".$round."\n";
