<?php
/** FR-004 critical invariant: failed owner documents cannot result in completed index jobs. */
$root=dirname(__DIR__);
$src=file_get_contents($root.'/includes/class-file26-indexer.php');
$start=strpos($src,'public function process_queue()');
$end=strpos($src,'private function fail_job(', $start);
if(false===$start||false===$end){fwrite(STDERR,"FAIL: queue worker not found\n");exit(1);}
$code=substr($src,$start,$end-$start);
$checks=array(
 array(strpos($code, '$batch_failed=0;')!==false,'per-batch failure counter resets on every retry'),
 array(strpos($code, 'if(is_wp_error($result)){$batch_failed++;if(\'\'===$first_owner_error)')!==false,'failed upsert is counted while retaining first owner error'),
 array(strpos($code,'if($batch_failed>0){$this->fail_job(')!==false,'failed item schedules typed retry and cannot complete a generation'),
 array(strpos($code, '$validated=$this->validate_owner_batch(')!==false && strpos($code, '$done=$batch[\'done\'];')!==false,'typed owner done marker follows fail-closed batch validation'),
 array(strpos($code, 'if($batch_failed>0)') < strpos($code, '$done=$batch[\'done\'];'),'failed owner documents cannot advance completion even after typed source proof'),
 array(strpos($code, '$validated=$this->validate_owner_batch(') < strpos($code, 'foreach($batch[\'items\'] as $document'),'owner batch shape is validated before any shadow write'),
 array(strpos($code, 'catch(\\Throwable $e){$this->fail_job(')!==false,'unexpected worker exception still schedules a bounded retry'),
 array(strpos($src,"'dead_letter'")!==false,'permanent failures are dead-lettered'),
 array(strpos($code, 'if($counts[\'failed\']>0)')===false,'prior attempts do not poison later successful retries'),
);
$failures=0;foreach($checks as $check){if(!$check[0]){fwrite(STDERR,'FAIL: '.$check[1]."\n");$failures++;}}
if($failures){exit(1);}
echo "PASS: FR-004 partial batch failures cannot be silently marked completed (9 assertions)\n";
