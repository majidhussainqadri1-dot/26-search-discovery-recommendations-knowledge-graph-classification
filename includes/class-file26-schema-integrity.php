<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Physical schema verification independent of stored version options. */
final class Schema_Integrity {
	private static function definitions() {
		return array(
			'connectors' => array('columns'=>array('id','slug','owner_file','contract_version','status','manifest','last_event_version','health_state','last_health','created_at','updated_at'),'indexes'=>array('PRIMARY','slug','status')),
			'documents' => array('columns'=>array('id','canonical_key','connector_slug','domain_name','object_id','object_version','entity_type','locale','state','visibility','title','excerpt','normalized_title','normalized_body','canonical_url','author_key','topic_ids','country','location','availability','quality_score','authority_score','popularity_score','freshness_at','safety_class','payload','source_event_id','source_event_sequence','checksum','indexed_at','updated_at'),'indexes'=>array('PRIMARY','canonical_key','eligible','connector_slug','domain_name','locale','freshness_at')),
			'tombstones' => array('columns'=>array('id','canonical_key','connector_slug','domain_name','object_id','object_version','reason_class','received_at','purged_at','expires_at'),'indexes'=>array('PRIMARY','canonical_key','expires_at')),
			'terms' => array('columns'=>array('id','term_uuid','slug','preferred_label','definition','language','parent_uuid','related_json','owner_file','status','version','redirect_uuid','created_at','updated_at'),'indexes'=>array('PRIMARY','term_uuid','slug_language','status','parent_uuid')),
			'term_aliases' => array('columns'=>array('id','term_uuid','alias_label','alias_normalized','language','status','created_at'),'indexes'=>array('PRIMARY','alias_language','term_uuid')),
			'classifications' => array('columns'=>array('id','object_key','term_uuid','confidence','method','method_version','reviewer_id','status','provenance','version','created_at','updated_at'),'indexes'=>array('PRIMARY','object_term','status','term_uuid')),
			'nodes' => array('columns'=>array('id','node_key','node_type','canonical_url','visibility','state','locale','version','title','payload','updated_at'),'indexes'=>array('PRIMARY','node_key','eligible')),
			'edges' => array('columns'=>array('id','edge_uuid','source_key','target_key','edge_type','provenance','owner_file','evidence_url','state','visibility','version','created_at','updated_at'),'indexes'=>array('PRIMARY','edge_uuid','source_lookup','target_lookup','edge_type')),
			'ranking_policies' => array('columns'=>array('id','policy_uuid','context_name','audience','version','status','features_json','approval_one','approval_two','effective_at','created_at','updated_at'),'indexes'=>array('PRIMARY','policy_uuid','context_version','active_policy')),
			'feedback' => array('columns'=>array('id','idempotency_key','user_id','item_key','feedback_type','scope_key','payload','active','created_at','updated_at','expires_at'),'indexes'=>array('PRIMARY','idempotency_key','user_active','expires_at')),
			'profiles' => array('columns'=>array('user_id','consent','opted_out','interests_json','negatives_json','version','updated_at'),'indexes'=>array('PRIMARY')),
			'jobs' => array('columns'=>array('id','job_uuid','job_type','status','scope_json','cursor_value','counts_json','error_code','lock_token','attempts','available_at','started_at','finished_at','created_at','updated_at'),'indexes'=>array('PRIMARY','job_uuid','runnable','job_type')),
			'audit' => array('columns'=>array('id','action_name','actor_id','object_type','object_key','reason_code','trace_id','metadata','created_at'),'indexes'=>array('PRIMARY','action_name','object_lookup','created_at')),
			'metrics' => array('columns'=>array('id','metric_date','metric_key','bucket_hash','locale','count_value','sum_value','updated_at'),'indexes'=>array('PRIMARY','metric_bucket','metric_date')),
			'rate_limits' => array('columns'=>array('id','bucket_key','window_start','count_value','expires_at'),'indexes'=>array('PRIMARY','bucket_window','expires_at')),
			'ranking_appeals' => array('columns'=>array('id','appeal_uuid','doctor_key','appellant_user_id','reason_text','evidence_json','status','reviewer_id','decision_reason','policy_version','rank_snapshot','version','submitted_at','updated_at','decided_at'),'indexes'=>array('PRIMARY','appeal_uuid','doctor_status','appellant_status','submitted_at')),
		);
	}

	/** Safety-critical physical column signatures that must match the executable schema contract. */
	private static function critical_columns() {
		return array(
			'connectors'=>array('slug'=>array('type'=>'varchar','length'=>191,'nullable'=>'NO'),'last_event_version'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO')),
			'documents'=>array('canonical_key'=>array('type'=>'char','length'=>64,'nullable'=>'NO'),'object_version'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO'),'state'=>array('type'=>'varchar','length'=>24,'nullable'=>'NO'),'visibility'=>array('type'=>'varchar','length'=>24,'nullable'=>'NO'),'source_event_sequence'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO'),'checksum'=>array('type'=>'char','length'=>64,'nullable'=>'NO')),
			'tombstones'=>array('canonical_key'=>array('type'=>'char','length'=>64,'nullable'=>'NO'),'object_version'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO'),'expires_at'=>array('type'=>'datetime','nullable'=>'NO')),
			'terms'=>array('term_uuid'=>array('type'=>'char','length'=>36,'nullable'=>'NO'),'slug'=>array('type'=>'varchar','length'=>191,'nullable'=>'NO'),'language'=>array('type'=>'varchar','length'=>20,'nullable'=>'NO'),'version'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO')),
			'term_aliases'=>array('term_uuid'=>array('type'=>'char','length'=>36,'nullable'=>'NO'),'alias_normalized'=>array('type'=>'varchar','length'=>255,'nullable'=>'NO'),'language'=>array('type'=>'varchar','length'=>20,'nullable'=>'NO')),
			'classifications'=>array('object_key'=>array('type'=>'char','length'=>64,'nullable'=>'NO'),'term_uuid'=>array('type'=>'char','length'=>36,'nullable'=>'NO'),'version'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO')),
			'nodes'=>array('node_key'=>array('type'=>'char','length'=>64,'nullable'=>'NO'),'state'=>array('type'=>'varchar','length'=>24,'nullable'=>'NO'),'visibility'=>array('type'=>'varchar','length'=>24,'nullable'=>'NO'),'version'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO')),
			'edges'=>array('edge_uuid'=>array('type'=>'char','length'=>36,'nullable'=>'NO'),'source_key'=>array('type'=>'char','length'=>64,'nullable'=>'NO'),'target_key'=>array('type'=>'char','length'=>64,'nullable'=>'NO'),'state'=>array('type'=>'varchar','length'=>24,'nullable'=>'NO'),'visibility'=>array('type'=>'varchar','length'=>24,'nullable'=>'NO'),'version'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO')),
			'ranking_policies'=>array('policy_uuid'=>array('type'=>'char','length'=>36,'nullable'=>'NO'),'context_name'=>array('type'=>'varchar','length'=>64,'nullable'=>'NO'),'audience'=>array('type'=>'varchar','length'=>64,'nullable'=>'NO'),'version'=>array('type'=>'varchar','length'=>64,'nullable'=>'NO'),'status'=>array('type'=>'varchar','length'=>24,'nullable'=>'NO')),
			'feedback'=>array('idempotency_key'=>array('type'=>'char','length'=>64,'nullable'=>'NO'),'user_id'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO'),'active'=>array('type'=>'tinyint','nullable'=>'NO'),'expires_at'=>array('type'=>'datetime','nullable'=>'NO')),
			'profiles'=>array('user_id'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO'),'consent'=>array('type'=>'tinyint','nullable'=>'NO'),'opted_out'=>array('type'=>'tinyint','nullable'=>'NO'),'version'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO')),
			'jobs'=>array('job_uuid'=>array('type'=>'char','length'=>36,'nullable'=>'NO'),'status'=>array('type'=>'varchar','length'=>24,'nullable'=>'NO'),'lock_token'=>array('type'=>'char','length'=>64,'nullable'=>'YES'),'attempts'=>array('type'=>'int','unsigned'=>true,'nullable'=>'NO'),'available_at'=>array('type'=>'datetime','nullable'=>'NO')),
			'audit'=>array('trace_id'=>array('type'=>'char','length'=>32,'nullable'=>'NO')),
			'metrics'=>array('metric_date'=>array('type'=>'date','nullable'=>'NO'),'bucket_hash'=>array('type'=>'char','length'=>64,'nullable'=>'NO'),'count_value'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO')),
			'rate_limits'=>array('bucket_key'=>array('type'=>'char','length'=>64,'nullable'=>'NO'),'window_start'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO'),'count_value'=>array('type'=>'int','unsigned'=>true,'nullable'=>'NO'),'expires_at'=>array('type'=>'datetime','nullable'=>'NO')),
			'ranking_appeals'=>array('appeal_uuid'=>array('type'=>'char','length'=>36,'nullable'=>'NO'),'doctor_key'=>array('type'=>'char','length'=>64,'nullable'=>'NO'),'appellant_user_id'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO'),'status'=>array('type'=>'varchar','length'=>32,'nullable'=>'NO'),'version'=>array('type'=>'bigint','unsigned'=>true,'nullable'=>'NO')),
		);
	}

	/** Exact index semantics; a surviving name alone is not sufficient integrity evidence. */
	private static function index_signatures() {
		return array(
			'connectors'=>array('PRIMARY'=>array(true,array('id')),'slug'=>array(true,array('slug')),'status'=>array(false,array('status'))),
			'documents'=>array('PRIMARY'=>array(true,array('id')),'canonical_key'=>array(true,array('canonical_key')),'eligible'=>array(false,array('state','visibility','entity_type')),'connector_slug'=>array(false,array('connector_slug')),'domain_name'=>array(false,array('domain_name')),'locale'=>array(false,array('locale')),'freshness_at'=>array(false,array('freshness_at'))),
			'tombstones'=>array('PRIMARY'=>array(true,array('id')),'canonical_key'=>array(true,array('canonical_key')),'expires_at'=>array(false,array('expires_at'))),
			'terms'=>array('PRIMARY'=>array(true,array('id')),'term_uuid'=>array(true,array('term_uuid')),'slug_language'=>array(true,array('slug','language')),'status'=>array(false,array('status')),'parent_uuid'=>array(false,array('parent_uuid'))),
			'term_aliases'=>array('PRIMARY'=>array(true,array('id')),'alias_language'=>array(true,array('alias_normalized','language')),'term_uuid'=>array(false,array('term_uuid'))),
			'classifications'=>array('PRIMARY'=>array(true,array('id')),'object_term'=>array(true,array('object_key','term_uuid')),'status'=>array(false,array('status')),'term_uuid'=>array(false,array('term_uuid'))),
			'nodes'=>array('PRIMARY'=>array(true,array('id')),'node_key'=>array(true,array('node_key')),'eligible'=>array(false,array('state','visibility','node_type'))),
			'edges'=>array('PRIMARY'=>array(true,array('id')),'edge_uuid'=>array(true,array('edge_uuid')),'source_lookup'=>array(false,array('source_key','state','visibility')),'target_lookup'=>array(false,array('target_key','state','visibility')),'edge_type'=>array(false,array('edge_type'))),
			'ranking_policies'=>array('PRIMARY'=>array(true,array('id')),'policy_uuid'=>array(true,array('policy_uuid')),'context_version'=>array(true,array('context_name','audience','version')),'active_policy'=>array(false,array('context_name','audience','status'))),
			'feedback'=>array('PRIMARY'=>array(true,array('id')),'idempotency_key'=>array(true,array('idempotency_key')),'user_active'=>array(false,array('user_id','active')),'expires_at'=>array(false,array('expires_at'))),
			'profiles'=>array('PRIMARY'=>array(true,array('user_id'))),
			'jobs'=>array('PRIMARY'=>array(true,array('id')),'job_uuid'=>array(true,array('job_uuid')),'runnable'=>array(false,array('status','available_at')),'job_type'=>array(false,array('job_type'))),
			'audit'=>array('PRIMARY'=>array(true,array('id')),'action_name'=>array(false,array('action_name')),'object_lookup'=>array(false,array('object_type','object_key')),'created_at'=>array(false,array('created_at'))),
			'metrics'=>array('PRIMARY'=>array(true,array('id')),'metric_bucket'=>array(true,array('metric_date','metric_key','bucket_hash','locale')),'metric_date'=>array(false,array('metric_date'))),
			'rate_limits'=>array('PRIMARY'=>array(true,array('id')),'bucket_window'=>array(true,array('bucket_key','window_start')),'expires_at'=>array(false,array('expires_at'))),
			'ranking_appeals'=>array('PRIMARY'=>array(true,array('id')),'appeal_uuid'=>array(true,array('appeal_uuid')),'doctor_status'=>array(false,array('doctor_key','status')),'appellant_status'=>array(false,array('appellant_user_id','status')),'submitted_at'=>array(false,array('submitted_at'))),
		);
	}

	private static function physical_names() {
		$names=array(); foreach(array_keys(self::definitions()) as $logical){$names[$logical]='ranking_appeals'===$logical?Doctor_Appeals::table():DB::table($logical);} return $names;
	}

	private static function column_matches( array $actual, array $expected ) {
		if ( strtolower((string)$actual['DATA_TYPE']) !== $expected['type'] ) { return false; }
		if ( isset($expected['nullable']) && strtoupper((string)$actual['IS_NULLABLE']) !== $expected['nullable'] ) { return false; }
		if ( isset($expected['length']) && (int)$actual['CHARACTER_MAXIMUM_LENGTH'] !== (int)$expected['length'] ) { return false; }
		if ( isset($expected['unsigned']) && (bool)$expected['unsigned'] !== (false !== strpos(strtolower((string)$actual['COLUMN_TYPE']),'unsigned')) ) { return false; }
		return true;
	}

	/** Return one consistent physical-shape snapshot using metadata tables, not version options. */
	public static function snapshot() {
		global $wpdb;
		$definitions=self::definitions(); $critical=self::critical_columns(); $signatures=self::index_signatures(); $physical=self::physical_names(); $table_names=array_values($physical); $placeholders=implode(',',array_fill(0,count($table_names),'%s'));
		$columns_sql=$wpdb->prepare("SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($placeholders)",$table_names);
		$indexes_sql=$wpdb->prepare("SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($placeholders) ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX",$table_names);
		$column_rows=$wpdb->get_results($columns_sql,ARRAY_A); $index_rows=$wpdb->get_results($indexes_sql,ARRAY_A);
		if(!is_array($column_rows)||!is_array($index_rows)){return array('complete'=>false,'main_complete'=>false,'appeals_complete'=>false,'tables'=>array(),'query_failed'=>true);}
		$actual_columns=array(); foreach($column_rows as $row){$actual_columns[$row['TABLE_NAME']][$row['COLUMN_NAME']]=$row;}
		$actual_indexes=array(); foreach($index_rows as $row){$name=$row['INDEX_NAME']; if(!isset($actual_indexes[$row['TABLE_NAME']][$name])){$actual_indexes[$row['TABLE_NAME']][$name]=array('unique'=>0===(int)$row['NON_UNIQUE'],'columns'=>array());}$actual_indexes[$row['TABLE_NAME']][$name]['columns'][(int)$row['SEQ_IN_INDEX']]=$row['COLUMN_NAME'];}
		foreach($actual_indexes as &$table_indexes){foreach($table_indexes as &$index){ksort($index['columns']);$index['columns']=array_values($index['columns']);}} unset($table_indexes,$index);
		$tables=array(); $main_complete=true; $appeals_complete=true;
		foreach($definitions as $logical=>$definition){
			$table=$physical[$logical]; $columns=isset($actual_columns[$table])?$actual_columns[$table]:array(); $indexes=isset($actual_indexes[$table])?$actual_indexes[$table]:array();
			$missing_columns=array_values(array_filter($definition['columns'],static function($column)use($columns){return !isset($columns[$column]);}));
			$missing_indexes=array_values(array_filter($definition['indexes'],static function($name)use($indexes){return !isset($indexes[$name]);}));
			$incompatible_columns=array(); foreach(isset($critical[$logical])?$critical[$logical]:array() as $column=>$expected){if(isset($columns[$column])&&!self::column_matches($columns[$column],$expected)){$incompatible_columns[]=$column;}}
			$incompatible_indexes=array(); foreach(isset($signatures[$logical])?$signatures[$logical]:array() as $name=>$expected){if(!isset($indexes[$name])){continue;} $expected_unique=(bool)$expected[0]; $expected_columns=$expected[1]; if($indexes[$name]['unique']!==$expected_unique||$indexes[$name]['columns']!==$expected_columns){$incompatible_indexes[]=$name;}}
			$ok=empty($missing_columns)&&empty($missing_indexes)&&empty($incompatible_columns)&&empty($incompatible_indexes);
			$tables[$logical]=array('physical_name'=>$table,'complete'=>$ok,'missing_columns'=>$missing_columns,'missing_indexes'=>$missing_indexes,'incompatible_columns'=>$incompatible_columns,'incompatible_indexes'=>$incompatible_indexes);
			if('ranking_appeals'===$logical){$appeals_complete=$ok;}elseif(!$ok){$main_complete=false;}
		}
		return array('complete'=>$main_complete&&$appeals_complete,'main_complete'=>$main_complete,'appeals_complete'=>$appeals_complete,'tables'=>$tables,'query_failed'=>false);
	}

	public static function complete(){ $snapshot=self::snapshot(); return !empty($snapshot['complete']); }
	public static function main_complete(){ $snapshot=self::snapshot(); return !empty($snapshot['main_complete']); }
	public static function appeals_complete(){ $snapshot=self::snapshot(); return !empty($snapshot['appeals_complete']); }
}
