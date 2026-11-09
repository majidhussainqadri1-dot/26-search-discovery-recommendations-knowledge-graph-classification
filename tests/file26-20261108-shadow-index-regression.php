<?php
/** FR-005 regression: shadow mutations cannot silently overwrite serving tables. */
$root=dirname(__DIR__);
$index=file_get_contents($root.'/includes/class-file26-indexer.php');
$shadow=file_get_contents($root.'/includes/class-file26-shadow-index.php');
$entry=file_get_contents($root.'/file-26-search-discovery.php');
$checks=array(
	'entry loads shadow before Indexer'=>strpos($entry,"includes/class-file26-shadow-index.php")<strpos($entry,"includes/class-file26-indexer.php"),
	'shadow generation has independent physical tables'=>strpos($shadow,"'shadow'=>")!==false,
	'four projections are tracked'=>strpos($shadow,"'documents'=>'d','nodes'=>'n','edges'=>'e','classifications'=>'c'")!==false,
	'worker stages instead of updating live'=>strpos($index,"$"."this->stage_for_job(")!==false,
	'queue forwards lease token to staging'=>strpos($index,"$"."this->stage_for_job($"."job['job_uuid'],$"."document,$"."token)")!==false,
	'stager requires current running worker lease'=>strpos($shadow,"SELECT status,lock_token FROM ")!==false&&strpos($shadow,"'file26_shadow_stale_worker'")!==false&&strpos($shadow,"hash_equals((string)$"."job['lock_token'],$"."worker_token)")!==false,
	'stager enters cutover lock before reading worker lease'=>preg_match('/public static function stage\\([^)]*\\)\\s*\\{[\\s\\S]*?self::lock\\(\\)[\\s\\S]*?SELECT status,lock_token FROM /',$shadow)===1,
	'stager releases lock before validation routine'=>preg_match('/public static function stage\\([\\s\\S]*?finally\\{self::unlock\\(\\$generation_lock\\);\\}[\\s\\S]*?private static function verify_locked/',$shadow)===1,
	'owner source is validated before staging'=>strpos($index,"$"."this->connectors->validate_document(")!==false,
	'canonical connector is not overwritten by scope'=>strpos($index,"array_merge($"."scope,array('connector'=>")!==false,
	'live writes acquire cutover lock'=>strpos($index,'Shadow_Index::lock()')!==false,
	'digest checks full record payload'=>strpos($shadow,"hash_update($"."h,wp_json_encode($"."row)")!==false,
	'live mutation drift blocks cutover'=>strpos($shadow,'file26_shadow_source_drift')!==false,
	'revoked items cannot enter promoted index'=>strpos($shadow,'file26_shadow_tombstones')!==false,
	'owner batch must be complete'=>strpos($shadow,'file26_shadow_incomplete')!==false,
	'false empty full snapshot blocked'=>strpos($shadow,'file26_shadow_owner_population_shrank')!==false,
	'single-object partial is owner bounded'=>strpos($shadow,'file26_shadow_partial_owner_violation')!==false,
	'partial must process an owner record'=>strpos($shadow,'file26_shadow_partial_incomplete')!==false,
	'graph nodes must match staged documents'=>strpos($shadow,'file26_shadow_graph_mismatch')!==false,
	'promotion is an explicit atomic rename'=>strpos($shadow,"'RENAME TABLE '")!==false,
	'four original tables preserved for rollback'=>strpos($shadow,"['previous']")!==false,
	'rollback rejects post-cutover writes'=>strpos($shadow,'file26_shadow_rollback_drift')!==false,
	'Founder approval and independent reviewer required'=>strpos($shadow,'sabri_file26_shadow_release_approved')!==false&&strpos($shadow,'public static function second_approve(')!==false&&strpos($shadow,'auditor_id')!==false&&strpos($shadow,'===$actor')!==false,
	'unsafe scoped rebuild fails closed'=>strpos($index,'file26_shadow_partial_scope_unverified')!==false,
	'graph writes serialized'=>strpos(file_get_contents($root.'/includes/class-file26-graph.php'),'Shadow_Index::lock()')!==false,
	'governance corrected-edge writes serialized'=>preg_match('/public function transition_edge\\([\\s\\S]*?if\\(\x27corrected\x27!==[\\s\\S]*?Shadow_Index::lock\\(\\)[\\s\\S]*?finally\\{Shadow_Index::unlock\\(\\$generation_lock\\);\\}/',file_get_contents($root.'/includes/class-file26-governance.php'))===1,
	'doctor ranking writes serialized'=>strpos(file_get_contents($root.'/includes/class-file26-doctor-ranking.php'),'Shadow_Index::lock()')!==false,
	'taxonomy classification writes serialized'=>strpos(file_get_contents($root.'/includes/class-file26-taxonomy.php'),'Shadow_Index::lock()')!==false,
	'approval is checksum bound'=>strpos($shadow,'file26_shadow_second_approval_stale')!==false,
	'generation uninstall honors destructive-only data policy'=>strpos(file_get_contents($root.'/uninstall.php'),"if ( ! $"."destructive ) { return; }")!==false,
	'generated shadow tables have bounded uninstall matching'=>strpos(file_get_contents($root.'/uninstall.php'),'f26_[spf][dnec]_[0-9a-f]{32}')!==false,
	'no dangerous automatic promotion'=>strpos($index,'Shadow_Index::swap(')===false,
	'manual administration entry is present'=>strpos($entry,'function sabri_file26_shadow_validate(')!==false,
	'atomic operations journal pre-rename checksums'=>strpos($shadow,"$"."record['transition']=array('operation'=>'swap'")!==false&&strpos($shadow,"$"."record['transition']=array('operation'=>'rollback'")!==false,
	'failed rename recovery never issues a second RENAME'=>strpos($shadow,'public static function recover_interrupted(')!==false&&strpos($shadow,"'file26_shadow_recovery_checksum_mismatch'")!==false&&strpos($entry,'function sabri_file26_shadow_recover_interrupted(')!==false
);
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
echo "PASS: FR005 source-contract checks ".count($checks)."\n";
