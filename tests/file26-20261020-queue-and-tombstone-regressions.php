<?php
/** Fail-closed queue progress and version-aware graph reconciliation checks.
 * Source-contract checks; host MariaDB and real owner-connector tests remain required.
 */
$root=dirname(__DIR__);
$indexer=file_get_contents($root.'/includes/class-file26-indexer.php');
$requirements=array(
    "throw new \\RuntimeException('Connector batch contained a failed search-document write" => 'An upsert failure must interrupt the batch before checkpoint persistence.',
    "if(!\$done&&(''===\$next_cursor||\$next_cursor===(string)\$job['cursor_value']))" => 'Non-terminal batches must advance the cursor.',
    "catch(\\Throwable \$e){\$this->fail_job" => 'Queue failures must enter bounded retry/dead-letter handling.',
    "t.object_version >= current_doc.object_version" => 'Graph reconciliation must reject historical tombstone versions.',
    "LEFT JOIN \$documents current_doc ON current_doc.canonical_key=t.canonical_key" => 'Graph deletions must compare tombstones with newer reindexed documents.',
);
$failed=0;
foreach($requirements as $needle=>$description){
    if(strpos($indexer,$needle)===false){fwrite(STDERR,"FAIL: $description\n");$failed++;}
}
if(substr_count($indexer,'t.object_version >= current_doc.object_version')!==3){
    fwrite(STDERR,"FAIL: edges, nodes and classifications each need version-aware tombstone filtering\n");$failed++;
}
if(strpos($indexer,"if(is_wp_error(\$result)){\$counts['failed']++;}")!==false){
    fwrite(STDERR,"FAIL: source still advances cursor when document writes fail\n");$failed++;
}
if($failed){exit(1);}echo "PASS: queue retry/cursor integrity and 3 version-aware graph projections\n";
