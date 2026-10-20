<?php
/** Version-aware graph and progress checkpoint regression (source-level, no live DB). */
$src=file_get_contents(dirname(__DIR__).'/includes/class-file26-indexer.php');
$checks=array(
  'if($batch_failed>0)' => 'Latest-main failure aggregation must be preserved',
  'Connector batch cursor did not advance; retry from prior checkpoint.' => 'A nonprogressing cursor must fail safely',
  'LEFT JOIN $documents current_doc ON current_doc.canonical_key=t.canonical_key' => 'Tombstone deletions must compare current document version',
  't.object_version >= current_doc.object_version' => 'Old tombstones must not remove newer projections',
  'if(!$done&&' => 'Cursor progress must be required for incomplete batches',
);
$failed=0;
foreach($checks as $needle=>$why){if(false===strpos($src,$needle)){fwrite(STDERR,"FAIL: $why\n");$failed++;}}
if(substr_count($src,'t.object_version >= current_doc.object_version')!==3){
 fwrite(STDERR,"FAIL: missing version protection for edges, classifications or nodes\n");$failed++;
}
if($failed){exit(1);}
echo "PASS: latest-main failed-batch preservation, cursor progress, stale tombstone graph controls\n";
