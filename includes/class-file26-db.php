<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class DB {
	const OPTION_SCHEMA='sabri_file26_schema_version';
	const OPTION_SETTINGS='sabri_file26_settings';
	const CRON_QUEUE='sabri_file26_process_queue';
	const CRON_RECONCILE='sabri_file26_reconcile';
	const CRON_RETENTION='sabri_file26_retention';
	const CRON_DOCTOR_RANKING='sabri_file26_doctor_ranking';

	public static function table($name){global $wpdb;$allowed=array('connectors','documents','tombstones','terms','term_aliases','classifications','nodes','edges','ranking_policies','feedback','profiles','jobs','audit','metrics','rate_limits');if(!in_array($name,$allowed,true)){throw new \InvalidArgumentException('Unknown File 26 table.');}return $wpdb->prefix.'f26_'.$name;}
	public static function activate(){if(!self::install_schema()){return false;}if(!self::install_defaults()){return false;}if(!self::schedule()){return false;}flush_rewrite_rules(false);return true;}
	public static function deactivate(){wp_clear_scheduled_hook(self::CRON_QUEUE);wp_clear_scheduled_hook(self::CRON_RECONCILE);wp_clear_scheduled_hook(self::CRON_RETENTION);wp_clear_scheduled_hook(self::CRON_DOCTOR_RANKING);flush_rewrite_rules(false);}

	private static function install_defaults(){
		$defaults=array('activated'=>false,'public_search_enabled'=>true,'personalization_enabled'=>false,'telemetry_enabled'=>true,'query_text_sampling'=>false,'max_query_length'=>200,'results_per_page'=>20,'max_results_per_page'=>30,'candidate_limit'=>200,'graph_max_depth'=>2,'graph_max_degree'=>20,'tombstone_retention_days'=>180,'feedback_retention_days'=>365,'audit_retention_days'=>760,'primary_color'=>'#087A4E','policy_version'=>'organic-1.0','doctor_ranking_policy_version'=>'doctor-global-1.0','doctor_ranking_last_run'=>'','unsafe_auto_synonyms'=>array(),'synonyms'=>array(),'transliteration_aliases'=>array());
		$current=get_option(self::OPTION_SETTINGS,array());$merged=array_merge($defaults,is_array($current)?$current:array());$saved=update_option(self::OPTION_SETTINGS,$merged,false);if(!$saved&&get_option(self::OPTION_SETTINGS,array())!==$merged){return false;}return true;
	}

	private static function ensure_schedule($hook,$timestamp,$recurrence){
		$event=function_exists('wp_get_scheduled_event')?wp_get_scheduled_event($hook):false;
		if($event&&isset($event->schedule)&&$recurrence===(string)$event->schedule){return true;}
		if($event||wp_next_scheduled($hook)){wp_clear_scheduled_hook($hook);}
		return false!==wp_schedule_event($timestamp,$recurrence,$hook);
	}

	public static function schedule(){
		$ok=true;
		$ok=self::ensure_schedule(self::CRON_QUEUE,time()+300,'hourly')&&$ok;
		$ok=self::ensure_schedule(self::CRON_RECONCILE,time()+HOUR_IN_SECONDS,'twicedaily')&&$ok;
		$ok=self::ensure_schedule(self::CRON_RETENTION,time()+DAY_IN_SECONDS,'daily')&&$ok;
		$ok=self::ensure_schedule(self::CRON_DOCTOR_RANKING,time()+DAY_IN_SECONDS,'sabri_file26_monthly')&&$ok;
		return $ok;
	}

	public static function required_columns(){
		return array(
			'connectors'=>array('id','slug','owner_file','contract_version','status','manifest','last_event_version','health_state','last_health','created_at','updated_at'),
			'documents'=>array('id','canonical_key','connector_slug','domain_name','object_id','object_version','entity_type','locale','state','visibility','title','excerpt','normalized_title','normalized_body','canonical_url','author_key','topic_ids','country','location','availability','quality_score','authority_score','popularity_score','freshness_at','safety_class','payload','source_event_id','source_event_sequence','checksum','indexed_at','updated_at'),
			'tombstones'=>array('id','canonical_key','connector_slug','domain_name','object_id','object_version','reason_class','received_at','purged_at','expires_at'),
			'terms'=>array('id','term_uuid','slug','preferred_label','definition','language','parent_uuid','related_json','owner_file','status','version','redirect_uuid','created_at','updated_at'),
			'term_aliases'=>array('id','term_uuid','alias_label','alias_normalized','language','status','created_at'),
			'classifications'=>array('id','object_key','term_uuid','confidence','method','method_version','reviewer_id','status','provenance','version','created_at','updated_at'),
			'nodes'=>array('id','node_key','node_type','canonical_url','visibility','state','locale','version','title','payload','updated_at'),
			'edges'=>array('id','edge_uuid','source_key','target_key','edge_type','provenance','owner_file','evidence_url','state','visibility','version','created_at','updated_at'),
			'ranking_policies'=>array('id','policy_uuid','context_name','audience','version','status','features_json','approval_one','approval_two','effective_at','created_at','updated_at'),
			'feedback'=>array('id','idempotency_key','user_id','item_key','feedback_type','scope_key','payload','active','created_at','updated_at','expires_at'),
			'profiles'=>array('user_id','consent','opted_out','interests_json','negatives_json','version','updated_at'),
			'jobs'=>array('id','job_uuid','job_type','status','scope_json','cursor_value','counts_json','error_code','lock_token','attempts','available_at','started_at','finished_at','created_at','updated_at'),
			'audit'=>array('id','action_name','actor_id','object_type','object_key','reason_code','trace_id','metadata','created_at'),
			'metrics'=>array('id','metric_date','metric_key','bucket_hash','locale','count_value','sum_value','updated_at'),
			'rate_limits'=>array('id','bucket_key','window_start','count_value','expires_at'),
		);
	}

	public static function schema_physical_ok(){
		global $wpdb;
		foreach(self::required_columns() as $name=>$required){
			$table=self::table($name);
			$wpdb->last_error='';
			$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)));
			if($exists!==$table||''!==(string)$wpdb->last_error){return false;}
			$wpdb->last_error='';
			$columns=$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if(!is_array($columns)||''!==(string)$wpdb->last_error||array_diff($required,$columns)){return false;}
		}
		return true;
	}

	public static function install_schema(){
		global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$charset=$wpdb->get_charset_collate();$sql=array();
		$sql[]='CREATE TABLE '.self::table('connectors')." (id bigint unsigned NOT NULL AUTO_INCREMENT,slug varchar(191) NOT NULL,owner_file varchar(64) NOT NULL,contract_version varchar(32) NOT NULL,status varchar(24) NOT NULL DEFAULT 'proposed',manifest longtext NOT NULL,last_event_version bigint unsigned NOT NULL DEFAULT 0,health_state varchar(24) NOT NULL DEFAULT 'unknown',last_health datetime NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY slug (slug),KEY status (status)) $charset;";
		$sql[]='CREATE TABLE '.self::table('documents')." (id bigint unsigned NOT NULL AUTO_INCREMENT,canonical_key char(64) NOT NULL,connector_slug varchar(191) NOT NULL,domain_name varchar(64) NOT NULL,object_id varchar(191) NOT NULL,object_version bigint unsigned NOT NULL DEFAULT 1,entity_type varchar(64) NOT NULL,locale varchar(20) NOT NULL DEFAULT 'en-US',state varchar(24) NOT NULL DEFAULT 'published',visibility varchar(24) NOT NULL DEFAULT 'public',title text NOT NULL,excerpt text NULL,normalized_title text NOT NULL,normalized_body longtext NULL,canonical_url text NOT NULL,author_key varchar(191) NULL,topic_ids longtext NULL,country varchar(64) NULL,location varchar(191) NULL,availability varchar(32) NULL,quality_score decimal(7,4) NOT NULL DEFAULT 0,authority_score decimal(7,4) NOT NULL DEFAULT 0,popularity_score decimal(7,4) NOT NULL DEFAULT 0,freshness_at datetime NULL,safety_class varchar(32) NOT NULL DEFAULT 'general',payload longtext NULL,source_event_id varchar(191) NULL,source_event_sequence bigint unsigned NOT NULL DEFAULT 0,checksum char(64) NOT NULL,indexed_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY canonical_key (canonical_key),KEY eligible (state,visibility,entity_type),KEY connector_slug (connector_slug),KEY domain_name (domain_name),KEY locale (locale),KEY freshness_at (freshness_at)) $charset;";
		$sql[]='CREATE TABLE '.self::table('tombstones')." (id bigint unsigned NOT NULL AUTO_INCREMENT,canonical_key char(64) NOT NULL,connector_slug varchar(191) NOT NULL,domain_name varchar(64) NOT NULL,object_id varchar(191) NOT NULL,object_version bigint unsigned NOT NULL,reason_class varchar(64) NOT NULL,received_at datetime NOT NULL,purged_at datetime NULL,expires_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY canonical_key (canonical_key),KEY expires_at (expires_at)) $charset;";
		$sql[]='CREATE TABLE '.self::table('terms')." (id bigint unsigned NOT NULL AUTO_INCREMENT,term_uuid char(36) NOT NULL,slug varchar(191) NOT NULL,preferred_label varchar(255) NOT NULL,definition text NULL,language varchar(20) NOT NULL DEFAULT 'en-US',parent_uuid char(36) NULL,related_json longtext NULL,owner_file varchar(64) NOT NULL,status varchar(24) NOT NULL DEFAULT 'draft',version bigint unsigned NOT NULL DEFAULT 1,redirect_uuid char(36) NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY term_uuid (term_uuid),UNIQUE KEY slug_language (slug,language),KEY status (status),KEY parent_uuid (parent_uuid)) $charset;";
		$sql[]='CREATE TABLE '.self::table('term_aliases')." (id bigint unsigned NOT NULL AUTO_INCREMENT,term_uuid char(36) NOT NULL,alias_label varchar(255) NOT NULL,alias_normalized varchar(255) NOT NULL,language varchar(20) NOT NULL DEFAULT 'en-US',status varchar(24) NOT NULL DEFAULT 'active',created_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY alias_language (alias_normalized,language),KEY term_uuid (term_uuid)) $charset;";
		$sql[]='CREATE TABLE '.self::table('classifications')." (id bigint unsigned NOT NULL AUTO_INCREMENT,object_key char(64) NOT NULL,term_uuid char(36) NOT NULL,confidence decimal(6,5) NOT NULL DEFAULT 0,method varchar(32) NOT NULL,method_version varchar(64) NOT NULL,reviewer_id bigint unsigned NULL,status varchar(24) NOT NULL DEFAULT 'suggested',provenance longtext NULL,version bigint unsigned NOT NULL DEFAULT 1,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY object_term (object_key,term_uuid),KEY status (status),KEY term_uuid (term_uuid)) $charset;";
		$sql[]='CREATE TABLE '.self::table('nodes')." (id bigint unsigned NOT NULL AUTO_INCREMENT,node_key char(64) NOT NULL,node_type varchar(64) NOT NULL,canonical_url text NULL,visibility varchar(24) NOT NULL DEFAULT 'public',state varchar(24) NOT NULL DEFAULT 'active',locale varchar(20) NOT NULL DEFAULT 'en-US',version bigint unsigned NOT NULL DEFAULT 1,title text NOT NULL,payload longtext NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY node_key (node_key),KEY eligible (state,visibility,node_type)) $charset;";
		$sql[]='CREATE TABLE '.self::table('edges')." (id bigint unsigned NOT NULL AUTO_INCREMENT,edge_uuid char(36) NOT NULL,source_key char(64) NOT NULL,target_key char(64) NOT NULL,edge_type varchar(64) NOT NULL,provenance longtext NOT NULL,owner_file varchar(64) NOT NULL,evidence_url text NULL,state varchar(24) NOT NULL DEFAULT 'draft',visibility varchar(24) NOT NULL DEFAULT 'public',version bigint unsigned NOT NULL DEFAULT 1,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY edge_uuid (edge_uuid),KEY source_lookup (source_key,state,visibility),KEY target_lookup (target_key,state,visibility),KEY edge_type (edge_type)) $charset;";
		$sql[]='CREATE TABLE '.self::table('ranking_policies')." (id bigint unsigned NOT NULL AUTO_INCREMENT,policy_uuid char(36) NOT NULL,context_name varchar(64) NOT NULL,audience varchar(64) NOT NULL,version varchar(64) NOT NULL,status varchar(24) NOT NULL DEFAULT 'draft',features_json longtext NOT NULL,approval_one bigint unsigned NULL,approval_two bigint unsigned NULL,effective_at datetime NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY policy_uuid (policy_uuid),UNIQUE KEY context_version (context_name,audience,version),KEY active_policy (context_name,audience,status)) $charset;";
		$sql[]='CREATE TABLE '.self::table('feedback')." (id bigint unsigned NOT NULL AUTO_INCREMENT,idempotency_key char(64) NOT NULL,user_id bigint unsigned NOT NULL,item_key char(64) NULL,feedback_type varchar(32) NOT NULL,scope_key varchar(191) NULL,payload longtext NULL,active tinyint(1) NOT NULL DEFAULT 1,created_at datetime NOT NULL,updated_at datetime NOT NULL,expires_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY idempotency_key (idempotency_key),KEY user_active (user_id,active),KEY expires_at (expires_at)) $charset;";
		$sql[]='CREATE TABLE '.self::table('profiles')." (user_id bigint unsigned NOT NULL,consent tinyint(1) NOT NULL DEFAULT 0,opted_out tinyint(1) NOT NULL DEFAULT 1,interests_json longtext NULL,negatives_json longtext NULL,version bigint unsigned NOT NULL DEFAULT 1,updated_at datetime NOT NULL,PRIMARY KEY  (user_id)) $charset;";
		$sql[]='CREATE TABLE '.self::table('jobs')." (id bigint unsigned NOT NULL AUTO_INCREMENT,job_uuid char(36) NOT NULL,job_type varchar(64) NOT NULL,status varchar(24) NOT NULL DEFAULT 'pending',scope_json longtext NULL,cursor_value text NULL,counts_json longtext NULL,error_code varchar(64) NULL,lock_token char(64) NULL,attempts int unsigned NOT NULL DEFAULT 0,available_at datetime NOT NULL,started_at datetime NULL,finished_at datetime NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY job_uuid (job_uuid),KEY runnable (status,available_at),KEY job_type (job_type)) $charset;";
		$sql[]='CREATE TABLE '.self::table('audit')." (id bigint unsigned NOT NULL AUTO_INCREMENT,action_name varchar(96) NOT NULL,actor_id bigint unsigned NULL,object_type varchar(64) NULL,object_key varchar(191) NULL,reason_code varchar(96) NULL,trace_id char(32) NOT NULL,metadata longtext NULL,created_at datetime NOT NULL,PRIMARY KEY  (id),KEY action_name (action_name),KEY object_lookup (object_type,object_key),KEY created_at (created_at)) $charset;";
		$sql[]='CREATE TABLE '.self::table('metrics')." (id bigint unsigned NOT NULL AUTO_INCREMENT,metric_date date NOT NULL,metric_key varchar(96) NOT NULL,bucket_hash char(64) NOT NULL,locale varchar(20) NOT NULL DEFAULT 'und',count_value bigint unsigned NOT NULL DEFAULT 0,sum_value decimal(18,4) NOT NULL DEFAULT 0,updated_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY metric_bucket (metric_date,metric_key,bucket_hash,locale),KEY metric_date (metric_date)) $charset;";
		$sql[]='CREATE TABLE '.self::table('rate_limits')." (id bigint unsigned NOT NULL AUTO_INCREMENT,bucket_key char(64) NOT NULL,window_start bigint unsigned NOT NULL,count_value int unsigned NOT NULL DEFAULT 0,expires_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY bucket_window (bucket_key,window_start),KEY expires_at (expires_at)) $charset;";
		foreach($sql as $statement){dbDelta($statement);}
		if(!self::schema_physical_ok()){delete_option(self::OPTION_SCHEMA);return false;}
		$schema_saved=update_option(self::OPTION_SCHEMA,SABRI_FILE26_SCHEMA_VERSION,false);return $schema_saved||SABRI_FILE26_SCHEMA_VERSION===(string)get_option(self::OPTION_SCHEMA);
	}

	public static function settings(){$value=get_option(self::OPTION_SETTINGS,array());return is_array($value)?$value:array();}
	public static function setting($key,$default=null){$settings=self::settings();return array_key_exists($key,$settings)?$settings[$key]:$default;}
	public static function update_settings(array $new){$current=self::settings();$allowed=array_keys($current);$clean=array();foreach($new as $key=>$value){if(in_array($key,$allowed,true)){$clean[$key]=$value;}}$merged=array_merge($current,$clean);$saved=update_option(self::OPTION_SETTINGS,$merged,false);if(!$saved&&get_option(self::OPTION_SETTINGS,array())!==$merged){return false;}return $merged;}
	public static function now(){return current_time('mysql',true);}
	public static function uuid(){return function_exists('wp_generate_uuid4')?wp_generate_uuid4():sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',wp_rand(0,0xffff),wp_rand(0,0xffff),wp_rand(0,0x0fff)|0x4000,wp_rand(0,0x3fff)|0x8000,wp_rand(0,0xffff),wp_rand(0,0xffff),wp_rand(0,0xffff),wp_rand(0,0xffff));}
}
