<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

final class Health {
	private $connectors; private $owner_contracts;
	public function __construct( Connectors $connectors, Owner_Contracts $owner_contracts ) { $this->connectors=$connectors; $this->owner_contracts=$owner_contracts; }
	private function count_value( $sql ) { global $wpdb; $value=$wpdb->get_var($sql); return null===$value&&''!==$wpdb->last_error?null:(int)$value; }
	public function snapshot() {
		global $wpdb; $tables=array(); $db_read_failed=false;
		foreach(array('connectors','documents','tombstones','terms','term_aliases','classifications','nodes','edges','ranking_policies','feedback','profiles','jobs','audit','metrics','rate_limits') as $name){$table=DB::table($name);$value=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)));if(null===$value&&''!==$wpdb->last_error){$db_read_failed=true;}$tables[$name]=$value===$table?'present':'missing';}
		$appeals_table=Doctor_Appeals::table();$appeal_exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($appeals_table)));if(null===$appeal_exists&&''!==$wpdb->last_error){$db_read_failed=true;}$tables['ranking_appeals']=$appeal_exists===$appeals_table?'present':'missing';
		$counts=array();foreach(array('documents','tombstones','terms','edges','jobs') as $name){if('present'===$tables[$name]){$counts[$name]=$this->count_value('SELECT COUNT(*) FROM '.DB::table($name));if(null===$counts[$name]){$db_read_failed=true;}}}
		if('present'===$tables['ranking_appeals']){$counts['ranking_appeals']=$this->count_value('SELECT COUNT(*) FROM '.$appeals_table);if(null===$counts['ranking_appeals']){$db_read_failed=true;}}
		$dead_letter='present'===$tables['jobs']?$this->count_value("SELECT COUNT(*) FROM ".DB::table('jobs')." WHERE status='dead_letter'"):null;if('present'===$tables['jobs']&&null===$dead_letter){$db_read_failed=true;}
		$stale_before=gmdate('Y-m-d H:i:s',time()-max(300,min(DAY_IN_SECONDS,(int)DB::setting('job_lock_timeout_seconds',1800))));$stale_running='present'===$tables['jobs']?$this->count_value($wpdb->prepare("SELECT COUNT(*) FROM ".DB::table('jobs')." WHERE status='running' AND started_at<%s",$stale_before)):null;if('present'===$tables['jobs']&&null===$stale_running){$db_read_failed=true;}
		$unknown=in_array('missing',$tables,true);$connector_health=$this->connectors->health_snapshot();$degraded=false;foreach($connector_health as $connector){if(in_array($connector['state'],array('degraded','unavailable','unknown'),true)&&'active'===$connector['status']){$degraded=true;}}
		$owner_readiness=$this->owner_contracts->readiness();$owner_contracts_ready=$this->owner_contracts->all_required_active();$activated=(bool)DB::setting('activated',false);
		$cron=array('queue'=>wp_next_scheduled(DB::CRON_QUEUE)?:null,'reconcile'=>wp_next_scheduled(DB::CRON_RECONCILE)?:null,'retention'=>wp_next_scheduled(DB::CRON_RETENTION)?:null,'doctor_ranking'=>wp_next_scheduled(DB::CRON_DOCTOR_RANKING)?:null);$cron_missing=$activated&&in_array(null,$cron,true);
		$schema_drift=SABRI_FILE26_SCHEMA_VERSION!==(string)get_option(DB::OPTION_SCHEMA)||Doctor_Appeals::SCHEMA_VERSION!==(string)get_option(Doctor_Appeals::OPTION_SCHEMA);
		$status=$unknown||$schema_drift?'unavailable':($db_read_failed||$degraded||$dead_letter||$stale_running||$cron_missing||($activated&&!$owner_contracts_ready)?'degraded':($activated?'healthy':'inactive'));
		return array('status'=>$status,'plugin_version'=>SABRI_FILE26_VERSION,'schema_version'=>get_option(DB::OPTION_SCHEMA),'contract_version'=>SABRI_FILE26_CONTRACT_VERSION,'activated'=>$activated,'tables'=>$tables,'counts'=>$counts,'database_read_failed'=>$db_read_failed,'dead_letter_jobs'=>$dead_letter,'stale_running_jobs'=>$stale_running,'schema_drift'=>$schema_drift,'connectors'=>$connector_health,'owner_contracts'=>$owner_readiness,'owner_contracts_ready'=>$owner_contracts_ready,'cron'=>$cron,'cron_missing'=>$cron_missing,'doctor_ranking'=>array('policy_version'=>DB::setting('doctor_ranking_policy_version','doctor-global-1.0'),'last_run'=>DB::setting('doctor_ranking_last_run',''),'appeals_schema'=>get_option(Doctor_Appeals::OPTION_SCHEMA)),'claims'=>array('staging_accepted'=>false,'live_deployed'=>false,'operational'=>false),'timestamp_utc'=>DB::now());
	}
}
