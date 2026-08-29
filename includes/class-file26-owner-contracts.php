<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Canonical owner-connector contract catalogue and activation gate. */
final class Owner_Contracts {
	private $connectors;
	public function __construct( Connectors $connectors ) { $this->connectors = $connectors; }

	public function requirements() {
		return array(
			'file03' => array( 'owner_file'=>'File 03','entity_types'=>array('founder','doctor','member_profile') ),
			'file05' => array( 'owner_file'=>'File 05','entity_types'=>array('lesson','course','book') ),
			'file06' => array( 'owner_file'=>'File 06','entity_types'=>array('encyclopedia_entry','concept') ),
			'file07' => array( 'owner_file'=>'File 07','entity_types'=>array('doctor_directory_projection') ),
			'file08' => array( 'owner_file'=>'File 08','entity_types'=>array('clinic','appointment_availability') ),
			'file10' => array( 'owner_file'=>'File 10','entity_types'=>array('video','live_video','channel') ),
			'file11' => array( 'owner_file'=>'File 11','entity_types'=>array('reel') ),
			'file12' => array( 'owner_file'=>'File 12','entity_types'=>array('pdf','book_pack') ),
			'file15' => array( 'owner_file'=>'File 15','entity_types'=>array('radar_study','research_signal') ),
			'file18' => array( 'owner_file'=>'File 18','entity_types'=>array('marketplace_listing') ),
			'file21' => array( 'owner_file'=>'File 21','entity_types'=>array('post','news','article') ),
		);
	}

	public function collect( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$adapters = apply_filters( 'sabri_file26_owner_connector_adapters', array() ); if ( ! is_array( $adapters ) ) { return $manifests; }
		$requirements = $this->requirements();
		foreach ( $adapters as $owner_key => $adapter ) {
			$owner_key=sanitize_key($owner_key); if(!isset($requirements[$owner_key])||!is_array($adapter)){continue;} $required=$requirements[$owner_key];
			$provided_types=isset($adapter['entity_types'])?array_values(array_unique(array_filter(array_map('sanitize_key',(array)$adapter['entity_types'])))):array(); if(array_diff($required['entity_types'],$provided_types)){continue;}
			if(empty($adapter['contract_version'])||empty($adapter['list_batch'])||!is_callable($adapter['list_batch'])||empty($adapter['can_view'])||!is_callable($adapter['can_view'])||empty($adapter['health'])||!is_callable($adapter['health'])){continue;}
			$manifests[]=array(
				'slug'=>isset($adapter['slug'])?sanitize_key($adapter['slug']):$owner_key.'-search-owner','owner_file'=>$required['owner_file'],'contract_version'=>substr(sanitize_text_field($adapter['contract_version']),0,64),'entity_types'=>$provided_types,
				'privacy_classes'=>isset($adapter['privacy_classes'])?(array)$adapter['privacy_classes']:array('public'),'visibility_fields'=>isset($adapter['visibility_fields'])?(array)$adapter['visibility_fields']:array('state','visibility'),'deletion_semantics'=>isset($adapter['deletion_semantics'])?sanitize_key($adapter['deletion_semantics']):'versioned_tombstone','status'=>isset($adapter['status'])?sanitize_key($adapter['status']):'proposed',
				'list_batch'=>$adapter['list_batch'],'can_view'=>$adapter['can_view'],'health'=>$adapter['health'],'fetch_object'=>isset($adapter['fetch_object'])&&is_callable($adapter['fetch_object'])?$adapter['fetch_object']:null,'event_contract'=>isset($adapter['event_contract'])?substr(sanitize_text_field($adapter['event_contract']),0,191):'','index_schema'=>isset($adapter['index_schema'])?substr(sanitize_text_field($adapter['index_schema']),0,191):'sabri.file26.document.v1.1',
			);
		}
		return $manifests;
	}

	public function readiness() {
		$requirements=$this->requirements(); $registry=$this->connectors->all(); $result=array();
		foreach($requirements as $key=>$required){
			$matches=array();
			foreach($registry as $connector){if(!is_array($connector)||!isset($connector['owner_file'],$connector['entity_types'])||$connector['owner_file']!==$required['owner_file']||array_diff($required['entity_types'],(array)$connector['entity_types'])){continue;}$callbacks=isset($connector['list_batch'],$connector['can_view'],$connector['health'])&&is_callable($connector['list_batch'])&&is_callable($connector['can_view'])&&is_callable($connector['health']);$matches[]=array('connector'=>$connector,'callbacks'=>$callbacks,'ready'=>$callbacks&&isset($connector['status'])&&'active'===$connector['status']);}
			usort($matches,static function($a,$b){if($a['ready']!==$b['ready']){return $a['ready']?-1:1;}if($a['callbacks']!==$b['callbacks']){return $a['callbacks']?-1:1;}return strcmp(isset($a['connector']['slug'])?$a['connector']['slug']:'',isset($b['connector']['slug'])?$b['connector']['slug']:'');});
			$selected=$matches?$matches[0]:null; $matched=$selected?$selected['connector']:null;
			$result[$key]=array('owner_file'=>$required['owner_file'],'required_entity_types'=>$required['entity_types'],'registered'=>(bool)$matched,'contract_version'=>$matched?$matched['contract_version']:null,'status'=>$matched?$matched['status']:'missing','callbacks_complete'=>$selected?(bool)$selected['callbacks']:false,'production_ready'=>$selected?(bool)$selected['ready']:false,'matching_connector_count'=>count($matches));
		}
		return $result;
	}

	public function all_required_active() { foreach($this->readiness() as $owner){if(empty($owner['production_ready'])){return false;}}return true; }

	/** Default activation decision; every listed evidence item and explicit approval are mandatory. */
	public function activation_gate( $approved, array $health, array $connectors ) {
		if ( ! $approved ) { return false; }
		$evidence=apply_filters('sabri_file26_cross_file_gate_evidence',array('file00_identity_contract'=>false,'file20_shell_contract'=>false,'file24_assurance_contract'=>false,'file25_visual_contract'=>false,'staging_acceptance'=>false,'migration_rehearsal'=>false,'rollback_rehearsal'=>false));$evidence=is_array($evidence)?$evidence:array();
		foreach(array('file00_identity_contract','file20_shell_contract','file24_assurance_contract','file25_visual_contract','staging_acceptance','migration_rehearsal','rollback_rehearsal') as $key){if(empty($evidence[$key])){return false;}}
		$status=isset($health['status'])?sanitize_key((string)$health['status']):'';
		return $this->all_required_active()&&!empty($connectors)&&in_array($status,array('inactive','healthy'),true);
	}
}
