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
	'owner source is validated before staging'=>strpos($index,"$"."this->connectors->validate_document(")!==false,
	'canonical connector is not overwritten by scope'=>strpos($index,"array_merge($"."scope,array('connector'=>")!==false,
	'live writes acquire cutover lock'=>strpos($index,'Shadow_Index::lock()')!==false,
	'digest checks full record payload'=>strpos($shadow,"hash_update($"."h,wp_json_encode($"."row)")!==false,
	'live mutation drift blocks cutover'=>strpos($shadow,'file26_shadow_source_drift')!==false,
	'revoked items cannot enter promoted index'=>strpos($shadow,'file26_shadow_tombstones')!==false,
	'owner batch must be complete'=>strpos($shadow,'file26_shadow_incomplete')!==false,
	'graph nodes must match staged documents'=>strpos($shadow,'file26_shadow_graph_mismatch')!==false,
	'promotion is an explicit atomic rename'=>strpos($shadow,"'RENAME TABLE '")!==false,
	'four original tables preserved for rollback'=>strpos($shadow,"['previous']")!==false,
	'rollback rejects post-cutover writes'=>strpos($shadow,'file26_shadow_rollback_drift')!==false,
	'Founder approval and independent reviewer required'=>strpos($shadow,'sabri_file26_shadow_release_approved')!==false&&strpos($shadow,'public static function second_approve(')!==false&&strpos($shadow,'auditor_id')!==false&&strpos($shadow,'===$actor')!==false,
	'unsafe scoped rebuild fails closed'=>strpos($index,'file26_shadow_partial_scope_unverified')!==false,
	'graph writes serialized'=>strpos(file_get_contents($root.'/includes/class-file26-graph.php'),'Shadow_Index::lock()')!==false,
	'doctor ranking writes serialized'=>strpos(file_get_contents($root.'/includes/class-file26-doctor-ranking.php'),'Shadow_Index::lock()')!==false,
	'taxonomy classification writes serialized'=>strpos(file_get_contents($root.'/includes/class-file26-taxonomy.php'),'Shadow_Index::lock()')!==false,
	'approval is checksum bound'=>strpos($shadow,'file26_shadow_second_approval_stale')!==false,
	'no dangerous automatic promotion'=>strpos($index,'Shadow_Index::swap(')===false,
	'manual administration entry is present'=>strpos($entry,'function sabri_file26_shadow_validate(')!==false
);
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
echo "PASS: FR005 source-contract checks ".count($checks)."\n";
