<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Privacy {
	private $page_size = 200;
	public function register(){add_filter('wp_privacy_personal_data_exporters',array($this,'exporters'));add_filter('wp_privacy_personal_data_erasers',array($this,'erasers'));}
	public function exporters($exporters){$exporters['sabri-file26']=array('exporter_friendly_name'=>__('Sabri Search, Recommendations and Ranking Appeals','sabri-file26'),'callback'=>array($this,'export'));return $exporters;}
	public function erasers($erasers){$erasers['sabri-file26']=array('eraser_friendly_name'=>__('Sabri Search, Recommendations and Ranking Appeals','sabri-file26'),'callback'=>array($this,'erase'));return $erasers;}

	public function export($email,$page=1){
		global $wpdb;$user=get_user_by('email',$email);$page=max(1,(int)$page);if(!$user){return array('data'=>array(),'done'=>true);}$offset=($page-1)*$this->page_size;
		$profile=1===$page?$wpdb->get_row($wpdb->prepare('SELECT * FROM '.DB::table('profiles').' WHERE user_id=%d',$user->ID),ARRAY_A):null;if(1===$page&&null===$profile&&''!==$wpdb->last_error){return array('data'=>array(),'done'=>false,'messages'=>array(__('File 26 profile data could not be read for export.','sabri-file26')));}
		$feedback=$wpdb->get_results($wpdb->prepare('SELECT item_key,feedback_type,scope_key,active,created_at,updated_at FROM '.DB::table('feedback').' WHERE user_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',$user->ID,$this->page_size,$offset),ARRAY_A);if(null===$feedback){return array('data'=>array(),'done'=>false,'messages'=>array(__('File 26 feedback data could not be read for export.','sabri-file26')));}
		$appeals=$wpdb->get_results($wpdb->prepare('SELECT appeal_uuid,doctor_key,reason_text,evidence_json,status,decision_reason,policy_version,rank_snapshot,submitted_at,updated_at,decided_at FROM '.Doctor_Appeals::table().' WHERE appellant_user_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',$user->ID,$this->page_size,$offset),ARRAY_A);if(null===$appeals){return array('data'=>array(),'done'=>false,'messages'=>array(__('File 26 ranking appeal data could not be read for export.','sabri-file26')));}
		$data=array();
		if($profile){$data[]=array('group_id'=>'sabri-file26-controls','group_label'=>__('Search and Recommendation Controls','sabri-file26'),'item_id'=>'profile-'.$user->ID,'data'=>array(array('name'=>__('Consent','sabri-file26'),'value'=>$profile['consent']?'yes':'no'),array('name'=>__('Opted out','sabri-file26'),'value'=>$profile['opted_out']?'yes':'no'),array('name'=>__('Interests','sabri-file26'),'value'=>$profile['interests_json']),array('name'=>__('Negative controls','sabri-file26'),'value'=>$profile['negatives_json'])));}
		foreach($feedback as $row){$data[]=array('group_id'=>'sabri-file26-feedback','group_label'=>__('Recommendation Feedback','sabri-file26'),'item_id'=>'feedback-'.hash('sha256',wp_json_encode($row)),'data'=>array(array('name'=>__('Item key','sabri-file26'),'value'=>$row['item_key']),array('name'=>__('Type','sabri-file26'),'value'=>$row['feedback_type']),array('name'=>__('Scope','sabri-file26'),'value'=>$row['scope_key']),array('name'=>__('Active','sabri-file26'),'value'=>$row['active']?'yes':'no'),array('name'=>__('Created','sabri-file26'),'value'=>$row['created_at'])));}
		foreach($appeals as $row){$data[]=array('group_id'=>'sabri-file26-ranking-appeals','group_label'=>__('Doctor Ranking Appeals','sabri-file26'),'item_id'=>'ranking-appeal-'.$row['appeal_uuid'],'data'=>array(array('name'=>__('Appeal ID','sabri-file26'),'value'=>$row['appeal_uuid']),array('name'=>__('Doctor reference','sabri-file26'),'value'=>$row['doctor_key']),array('name'=>__('Reason','sabri-file26'),'value'=>$row['reason_text']),array('name'=>__('Evidence','sabri-file26'),'value'=>$row['evidence_json']),array('name'=>__('Status','sabri-file26'),'value'=>$row['status']),array('name'=>__('Decision reason','sabri-file26'),'value'=>$row['decision_reason']),array('name'=>__('Policy version','sabri-file26'),'value'=>$row['policy_version']),array('name'=>__('Rank snapshot','sabri-file26'),'value'=>$row['rank_snapshot']),array('name'=>__('Submitted','sabri-file26'),'value'=>$row['submitted_at']),array('name'=>__('Decided','sabri-file26'),'value'=>$row['decided_at'])));}
		$done=count($feedback)<$this->page_size&&count($appeals)<$this->page_size;return array('data'=>$data,'done'=>$done);
	}

	public function erase($email,$page=1){
		global $wpdb;$user=get_user_by('email',$email);if(!$user||(int)$page>1){return array('items_removed'=>false,'items_retained'=>false,'messages'=>array(),'done'=>true);}
		$appeal_count_raw=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Doctor_Appeals::table().' WHERE appellant_user_id=%d',$user->ID));if(null===$appeal_count_raw&&''!==$wpdb->last_error){return array('items_removed'=>false,'items_retained'=>true,'messages'=>array(__('File 26 could not determine ranking-appeal retention state; erasure was not started.','sabri-file26')),'done'=>false);}$appeal_count=(int)$appeal_count_raw;$removed_any=false;$retained=false;
		if(false===$wpdb->query('START TRANSACTION')){return array('items_removed'=>false,'items_retained'=>(bool)$appeal_count,'messages'=>array(__('File 26 erasure transaction could not be started.','sabri-file26')),'done'=>false);}
		try{
			$removed_feedback=$wpdb->delete(DB::table('feedback'),array('user_id'=>$user->ID),array('%d'));$removed_profile=$wpdb->delete(DB::table('profiles'),array('user_id'=>$user->ID),array('%d'));if(false===$removed_feedback||false===$removed_profile){throw new \RuntimeException('Recommendation erasure failed.');}$removed_any=(bool)($removed_feedback||$removed_profile);
			if($appeal_count){$redacted='[redacted after verified data-erasure request]';$updated=$wpdb->query($wpdb->prepare('UPDATE '.Doctor_Appeals::table()." SET appellant_user_id=0,reason_text=%s,evidence_json='[]',decision_reason=CASE WHEN decision_reason IS NULL THEN NULL ELSE %s END,status=CASE WHEN status IN ('submitted','under_review','changes_requested') THEN 'withdrawn' ELSE status END,version=version+1,updated_at=%s WHERE appellant_user_id=%d",$redacted,$redacted,DB::now(),$user->ID));if(false===$updated||(int)$updated!==$appeal_count){throw new \RuntimeException('Ranking appeal redaction was incomplete.');}$retained=true;$removed_any=true;}
			if(false===$wpdb->query('COMMIT')){throw new \RuntimeException('Erasure commit failed.');}
		}catch(\Throwable $e){$wpdb->query('ROLLBACK');return array('items_removed'=>false,'items_retained'=>(bool)$appeal_count,'messages'=>array(__('File 26 erasure could not be completed atomically; no partial success is reported.','sabri-file26')),'done'=>false);}
		$messages=array(__('File 26 recommendation controls and feedback were erased.','sabri-file26'));if($retained){$messages[]=__('Ranking appeal text and identity were redacted; a minimal policy, status and fairness record was retained for audit integrity.','sabri-file26');}
		return array('items_removed'=>$removed_any,'items_retained'=>$retained,'messages'=>$messages,'done'=>true);
	}
}
