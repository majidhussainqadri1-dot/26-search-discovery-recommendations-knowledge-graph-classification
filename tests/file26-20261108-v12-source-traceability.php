<?php
/** V12 source-to-plan traceability safeguard; NOT live behavioral acceptance. */
$root=dirname(__DIR__);
$matrix=file_get_contents($root.'/docs/FILE26-V12-REQUIREMENTS-TRACEABILITY-AND-ACCEPTANCE-2026-11-08.md');
$owners=file_get_contents($root.'/docs/FILE26-CROSSREPO-OWNER-HEAD-AUDIT-2026-11-08.md');
$failed=0;$checks=0;
function v12check($condition,$message){
	global $checks,$failed;
	$checks++;if(!$condition){fwrite(STDERR,"FAIL: $message\n");$failed++;}
}
for($i=1;$i<=36;$i++){v12check(strpos($matrix,sprintf('File26-FR-%03d',$i))!==false,'FR '.sprintf('%03d',$i));}
for($i=1;$i<=24;$i++){v12check(strpos($matrix,sprintf('F26-FUT-%02d',$i))!==false,'FUT '.sprintf('%02d',$i));}
for($i=164;$i<=175;$i++){v12check(strpos($matrix,'CV-'.$i)!==false,'CV '.$i);}
foreach(array('05','06','11','12','18') as $file){
	v12check(strpos($owners,'File '.$file)!==false,'Owner main '.$file.' audited');
}
v12check(strpos($matrix,'Deployed Version = unverified')!==false,'No unverified production declaration');
v12check(strpos($matrix,'full/partial and staging still open')!==false,'FR005 real acceptance remains open');
v12check(strpos($owners,'WPVibe returned HTTP 530')!==false,'Live inspection blocker disclosed');
if($failed){exit(1);}
echo "PASS: $checks v12 requirement and evidence-boundary assertions\n";
