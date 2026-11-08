<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

/** FR-005: independent generation. No change to the serving index before a guarded swap. */
final class Shadow_Index {
	const LOCK = 'sabri_file26_cutover_lock';
	private static function names($uuid) {
		global $wpdb;
		if (!is_string($uuid) || !preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/D',$uuid)) {
			return new \WP_Error('file26_shadow_uuid','Invalid index generation UUID.');
		}
		$s=str_replace('-','',$uuid);$out=array();
		foreach(array('documents'=>'d','nodes'=>'n','edges'=>'e','classifications'=>'c') as $kind=>$abbr) {
			$out[$kind]=array('live'=>DB::table($kind),'shadow'=>$wpdb->prefix.'f26_s'.$abbr.'_'.$s,'previous'=>$wpdb->prefix.'f26_p'.$abbr.'_'.$s,'failed'=>$wpdb->prefix.'f26_f'.$abbr.'_'.$s);
			foreach($out[$kind] as $table){if(strlen($table)>64||!preg_match('/^[a-zA-Z0-9_]+$/D',$table)){return new \WP_Error('file26_shadow_table','Unsafe index table identifier.');}}
		}
		return $out;
	}
	private static function opt($uuid){return 'sabri_f26_shadow_'.str_replace('-','',$uuid);}
	public static function lock(){
		global $wpdb;
		$result=$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,15)',self::LOCK));
		return '1'===(string)$result?self::LOCK:new \WP_Error('file26_shadow_busy','A serving-index mutation or cutover is in progress.');
	}
	public static function unlock($lock){
		global $wpdb;
		if(self::LOCK===$lock){$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
	}
	private static function run($sql,$code){
		global $wpdb;$wpdb->last_error='';
		return false===$wpdb->query($sql)||''!==(string)$wpdb->last_error?new \WP_Error($code,'Index generation SQL failed closed.'):true;
	}
	private static function digest($names,$side){
		global $wpdb;$h=hash_init('sha256');$counts=array();
		foreach($names as $kind=>$tables){
			$table=$tables[$side];$id=0;$count=0;hash_update($h,$kind."\n");
			do {
				$wpdb->last_error='';
				$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE id>%d ORDER BY id LIMIT 500",$id),ARRAY_A);
				if(!is_array($rows)||''!==(string)$wpdb->last_error){return new \WP_Error('file26_shadow_digest','Cannot fingerprint the complete index.');}
				foreach($rows as $row){$id=(int)$row['id'];hash_update($h,wp_json_encode($row)."\n");++$count;}
			}while(count($rows)===500);
			$counts[$kind]=$count;
		}
		return array('sha256'=>hash_final($h),'counts'=>$counts);
	}
	public static function prepare($uuid,$connector,array $scope=array()){
		global $wpdb;$names=self::names($uuid);if(is_wp_error($names)){return $names;}
		$connector=sanitize_key($connector);if(''===$connector){return new \WP_Error('file26_shadow_connector','Missing owner connector.');}
		$object_id=isset($scope['object_id'])?substr(sanitize_text_field((string)$scope['object_id']),0,191):'';
		if($scope&&(''===$object_id||count($scope)!==1)){return new \WP_Error('file26_shadow_partial_scope_unverified','Only an explicit canonical single-object scope is supported.');}
		$lock=self::lock();if(is_wp_error($lock)){return $lock;}$created=array();
		try {
			if(false!==get_option(self::opt($uuid),false)){return new \WP_Error('file26_shadow_exists','This generation already exists.');}
			foreach($names as $tables){
				if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($tables['shadow'])))){return new \WP_Error('file26_shadow_exists','An existing shadow table cannot be overwritten.');}
				$r=self::run("CREATE TABLE {$tables['shadow']} LIKE {$tables['live']}",'file26_shadow_create');if(is_wp_error($r)){return $r;}
				$created[]=$tables['shadow'];
				$r=self::run("INSERT INTO {$tables['shadow']} SELECT * FROM {$tables['live']}",'file26_shadow_copy');if(is_wp_error($r)){return $r;}
			}
			$baseline=self::digest($names,'live');if(is_wp_error($baseline)){return $baseline;}
			$d=$names['documents'];$n=$names['nodes'];
			if(''===$object_id){
			$r=self::run($wpdb->prepare("DELETE n FROM {$n['shadow']} n INNER JOIN {$d['shadow']} d ON n.node_key=d.canonical_key WHERE d.connector_slug=%s",$connector),'file26_shadow_clear_nodes');
			if(is_wp_error($r)){return $r;}
			$r=self::run($wpdb->prepare("DELETE FROM {$d['shadow']} WHERE connector_slug=%s",$connector),'file26_shadow_clear_docs');
			if(is_wp_error($r)){return $r;}
			}
			$record=array('state'=>'prepared','connector'=>$connector,'partial_object_id'=>$object_id,'baseline'=>$baseline,'started_at'=>gmdate('c'));
			if(!add_option(self::opt($uuid),$record,'',false)){return new \WP_Error('file26_shadow_provenance','Cannot persist shadow provenance.');}
			$created=array();return $baseline;
		}finally{
			foreach($created as $table){$wpdb->query("DROP TABLE IF EXISTS $table");}
			self::unlock($lock);
		}
	}
	public static function discard_unqueued($uuid){
		global $wpdb;$names=self::names($uuid);if(is_wp_error($names)){return;}
		$record=get_option(self::opt($uuid),false);
		if(!is_array($record)||'prepared'!==$record['state']){return;}
		foreach($names as $tables){$wpdb->query("DROP TABLE IF EXISTS {$tables['shadow']}");}
		delete_option(self::opt($uuid));
	}
	public static function stage($uuid,array $doc){
		global $wpdb;$names=self::names($uuid);if(is_wp_error($names)){return $names;}
		$record=get_option(self::opt($uuid),false);
		if(!is_array($record)||'prepared'!==$record['state']||$record['connector']!==$doc['connector_slug']){
			return new \WP_Error('file26_shadow_wrong_owner','No active isolated generation for this owner.');
		}
		if(!empty($record['partial_object_id'])&&$record['partial_object_id']!==$doc['object_id']){
			return new \WP_Error('file26_shadow_partial_owner_violation','Owner batch returned an object outside the approved partial scope.');
		}
		$d=$names['documents']['shadow'];$n=$names['nodes']['shadow'];$key=$doc['canonical_key'];
		$wpdb->last_error='';$revoked=$wpdb->get_var($wpdb->prepare('SELECT object_version FROM '.DB::table('tombstones').' WHERE canonical_key=%s',$key));
		if(''!==(string)$wpdb->last_error){return new \WP_Error('file26_shadow_revocation_unknown','Cannot verify active revocation.');}
		if(null!==$revoked&&(int)$revoked>=(int)$doc['object_version']){return true;}
		$wpdb->last_error='';$existing=$wpdb->get_row($wpdb->prepare("SELECT object_version,source_event_sequence,checksum FROM $d WHERE canonical_key=%s",$key),ARRAY_A);
		if(''!==(string)$wpdb->last_error){return new \WP_Error('file26_shadow_version_unknown','Cannot verify source version.');}
		if($existing){
			$old=(int)$existing['object_version'];$ver=(int)$doc['object_version'];
			$seq=(int)$doc['source_event_sequence'];$oldseq=(int)$existing['source_event_sequence'];
			if($old>$ver||($old===$ver&&$oldseq>$seq)){return true;}
			if($old===$ver&&$oldseq===$seq){
				return hash_equals((string)$existing['checksum'],(string)$doc['checksum'])?true:new \WP_Error('file26_shadow_source_conflict','Same source version has different content.');
			}
		}
		if(false===$wpdb->query('START TRANSACTION')){return new \WP_Error('file26_shadow_transaction','Staging transaction failed.');}
		try{
			if(in_array($doc['state'],array('deleted','suspended','restricted','private','rejected'),true)||'restricted'===$doc['visibility']){
				if(false===$wpdb->delete($d,array('canonical_key'=>$key))||false===$wpdb->delete($n,array('node_key'=>$key))){throw new \RuntimeException('Shadow deletion');}
			}else{
				if(false===$wpdb->replace($d,$doc)){throw new \RuntimeException('Document stage');}
				$node=array('node_key'=>$key,'node_type'=>$doc['entity_type'],'canonical_url'=>$doc['canonical_url'],'visibility'=>$doc['visibility'],'state'=>$doc['state'],'locale'=>$doc['locale'],'version'=>$doc['object_version'],'title'=>$doc['title'],'payload'=>$doc['payload'],'updated_at'=>$doc['updated_at']);
				if(false===$wpdb->replace($n,$node)){throw new \RuntimeException('Node stage');}
			}
			if(false===$wpdb->query('COMMIT')){throw new \RuntimeException('Stage commit');}
			return true;
		}catch(\Throwable $e){$wpdb->query('ROLLBACK');return new \WP_Error('file26_shadow_stage_failed','Shadow data was not staged atomically.');}
	}
	private static function verify_locked($uuid,$names){
		global $wpdb;$record=get_option(self::opt($uuid),false);
		if(!is_array($record)||'prepared'!==$record['state']){return new \WP_Error('file26_shadow_not_prepared','Shadow is not pending validation.');}
		$job=$wpdb->get_row($wpdb->prepare("SELECT status,counts_json FROM ".DB::table('jobs')." WHERE job_uuid=%s AND job_type='shadow_reindex'",$uuid),ARRAY_A);
		if(!is_array($job)||'completed'!==$job['status']){return new \WP_Error('file26_shadow_incomplete','The complete owner source has not been processed.');}
		$counts=json_decode($job['counts_json'],true);
		if(!is_array($counts)||!empty($counts['failed'])){return new \WP_Error('file26_shadow_failed_batch','Unresolved owner batch errors prohibit cutover.');}
		if(!empty($record['partial_object_id'])&&(int)($counts['processed']??0)<1){return new \WP_Error('file26_shadow_partial_incomplete','The scoped owner did not attest a current object state; refusing stale partial promotion.');}
		$live=self::digest($names,'live');if(is_wp_error($live)){return $live;}
		if(!hash_equals((string)$record['baseline']['sha256'],$live['sha256'])){
			return new \WP_Error('file26_shadow_source_drift','Active documents or graph changed since snapshot; build a fresh generation.');
		}
		$d=$names['documents']['shadow'];$n=$names['nodes']['shadow'];$e=$names['edges']['shadow'];$c=$names['classifications']['shadow'];
		foreach(array("DELETE c FROM $c c LEFT JOIN $d d ON c.object_key=d.canonical_key WHERE d.canonical_key IS NULL",
			"DELETE e FROM $e e LEFT JOIN $n s ON e.source_key=s.node_key LEFT JOIN $n t ON e.target_key=t.node_key WHERE s.node_key IS NULL OR t.node_key IS NULL") as $sql){
			$r=self::run($sql,'file26_shadow_orphan');if(is_wp_error($r)){return $r;}
		}
		$invalid=$wpdb->get_var("SELECT COUNT(*) FROM $d d LEFT JOIN $n n ON d.canonical_key=n.node_key WHERE n.node_key IS NULL OR n.version<>d.object_version");
		if(null===$invalid||(int)$invalid!==0){return new \WP_Error('file26_shadow_graph_mismatch','Shadow nodes do not match their documents.');}
		$t=DB::table('tombstones');
		$revoked=$wpdb->get_var("SELECT COUNT(*) FROM $d d INNER JOIN $t t ON d.canonical_key=t.canonical_key WHERE t.object_version>=d.object_version");
		if(null===$revoked||(int)$revoked!==0){return new \WP_Error('file26_shadow_tombstones','Revoked documents must not reappear.');}
		$shadow=self::digest($names,'shadow');if(is_wp_error($shadow)){return $shadow;}
		return array('verified'=>true,'source'=>$live,'candidate'=>$shadow,'processed'=>(int)($counts['processed']??0));
	}
	public static function validate($uuid){
		$names=self::names($uuid);if(is_wp_error($names)){return $names;}
		$lock=self::lock();if(is_wp_error($lock)){return $lock;}
		try{return self::verify_locked($uuid,$names);}finally{self::unlock($lock);}
	}
	/** A separate auditor binds approval to the actual shadow-generation checksum. */
	public static function second_approve($uuid,$operation){
		$operation=sanitize_key((string)$operation);
		if(!in_array($operation,array('swap','rollback'),true)){return new \WP_Error('file26_shadow_invalid_approval','Invalid shadow release command.');}
		$security=new Security();$actor=(int)get_current_user_id();
		if($actor<=0||!$security->can_audit()||!$security->require_step_up('shadow_second_'.$operation)){
			return new \WP_Error('file26_shadow_second_approver_forbidden','A separate authorized auditor must complete recent step-up verification.');
		}
		$names=self::names($uuid);if(is_wp_error($names)){return $names;}
		$lock=self::lock();if(is_wp_error($lock)){return $lock;}
		try{
			$record=get_option(self::opt($uuid),false);
			if(!is_array($record)){return new \WP_Error('file26_shadow_missing','No matching recorded index generation.');}
			if('swap'===$operation){
				$proof=self::verify_locked($uuid,$names);if(is_wp_error($proof)){return $proof;}
				$fingerprint=$proof['candidate']['sha256'];
			}else{
				if('swapped'!==$record['state']){return new \WP_Error('file26_shadow_not_swapped','Rollback approval requires a recorded cutover.');}
				$current=self::digest($names,'live');if(is_wp_error($current)){return $current;}
				if(!hash_equals((string)$record['candidate']['sha256'],$current['sha256'])){return new \WP_Error('file26_shadow_rollback_drift','Live writes exist; rollback approval denied.');}
				$fingerprint=$current['sha256'];
			}
			$record['approvals'][$operation]=array('auditor_id'=>$actor,'sha256'=>$fingerprint,'at_utc'=>gmdate('c'));
			if(!update_option(self::opt($uuid),$record,false)){return new \WP_Error('file26_shadow_approval_persist_failed','The independent approval was not stored.');}
			$security->audit('shadow_index_second_approval',array('object_type'=>'shadow_index','object_key'=>$uuid,'reason'=>$operation,'metadata'=>array('auditor_id'=>$actor,'sha256'=>$fingerprint)));
			return array('approved'=>true,'operation'=>$operation,'auditor_id'=>$actor);
		}finally{self::unlock($lock);}
	}
	private static function authorized($operation,$uuid){
		$actor=(int)get_current_user_id();
		if($actor<=0||!current_user_can('manage_options')){return false;}
		$s=new Security();if(!$s->require_step_up('shadow_'.$operation)){return false;}
		$record=get_option(self::opt($uuid),false);
		$approval=is_array($record)&&isset($record['approvals'][$operation])?$record['approvals'][$operation]:null;
		if(!is_array($approval)||empty($approval['sha256'])||(int)($approval['auditor_id']??0)<=0||(int)$approval['auditor_id']===$actor){return false;}
		return true===$s->normalize_authorization(apply_filters('sabri_file26_shadow_release_approved',false,$uuid,$operation,$actor));
	}
	public static function swap($uuid){
		$names=self::names($uuid);if(is_wp_error($names)){return $names;}
		if(!self::authorized('swap',$uuid)){return new \WP_Error('file26_shadow_approval_required','Founder and distinct reviewer step-up approval required.');}
		$lock=self::lock();if(is_wp_error($lock)){return $lock;}
		try{
			$proof=self::verify_locked($uuid,$names);if(is_wp_error($proof)){return $proof;}
			$approval=get_option(self::opt($uuid),array());
			if(!isset($approval['approvals']['swap']['sha256'])||!hash_equals((string)$approval['approvals']['swap']['sha256'],(string)$proof['candidate']['sha256'])){return new \WP_Error('file26_shadow_second_approval_stale','Independent approval for this generation is absent or stale.');}
			$record=get_option(self::opt($uuid),array());$record['state']='swapping';
			if(!update_option(self::opt($uuid),$record,false)){return new \WP_Error('file26_shadow_transition','Cannot record pre-cutover state.');}
			$pairs=array();foreach($names as $tables){$pairs[]=$tables['live'].' TO '.$tables['previous'];$pairs[]=$tables['shadow'].' TO '.$tables['live'];}
			$r=self::run('RENAME TABLE '.implode(',',$pairs),'file26_shadow_atomic_swap');if(is_wp_error($r)){return $r;}
			$record['state']='swapped';$record['candidate']=$proof['candidate'];$record['swapped_at']=gmdate('c');
			if(!update_option(self::opt($uuid),$record,false)){return new \WP_Error('file26_shadow_post_swap_record','Swap happened; manual provenance reconciliation is required.');}
			return array('state'=>'swapped','job'=>$uuid,'sha256'=>$proof['candidate']['sha256']);
		}finally{self::unlock($lock);}
	}
	public static function rollback($uuid){
		$names=self::names($uuid);if(is_wp_error($names)){return $names;}
		if(!self::authorized('rollback',$uuid)){return new \WP_Error('file26_shadow_approval_required','Distinct authorized rollback approval required.');}
		$lock=self::lock();if(is_wp_error($lock)){return $lock;}
		try{
			$record=get_option(self::opt($uuid),false);
			if(!is_array($record)||'swapped'!==$record['state']){return new \WP_Error('file26_shadow_no_cutover','No accepted cutover is recorded.');}
			$now=self::digest($names,'live');if(is_wp_error($now)){return $now;}
			if(!hash_equals((string)$record['candidate']['sha256'],$now['sha256'])){return new \WP_Error('file26_shadow_rollback_drift','New writes exist; rollback would lose data. Reconcile instead.');}
			if(!isset($record['approvals']['rollback']['sha256'])||!hash_equals((string)$record['approvals']['rollback']['sha256'],$now['sha256'])){return new \WP_Error('file26_shadow_rollback_approval_stale','Separate auditor approval for this rollback is absent or stale.');}
			$record['state']='rolling_back';if(!update_option(self::opt($uuid),$record,false)){return new \WP_Error('file26_shadow_rollback_record','Cannot record rollback transition.');}
			$pairs=array();foreach($names as $tables){$pairs[]=$tables['live'].' TO '.$tables['failed'];$pairs[]=$tables['previous'].' TO '.$tables['live'];}
			$r=self::run('RENAME TABLE '.implode(',',$pairs),'file26_shadow_atomic_rollback');if(is_wp_error($r)){return $r;}
			$record['state']='rolled_back';$record['rolled_back_at']=gmdate('c');
			if(!update_option(self::opt($uuid),$record,false)){return new \WP_Error('file26_shadow_rollback_audit','Rollback happened; provenance reconciliation required.');}
			return array('state'=>'rolled_back','job'=>$uuid);
		}finally{self::unlock($lock);}
	}
}
