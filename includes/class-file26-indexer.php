<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Indexer {
	private $connectors; private $normalizer; private $security;
	public function __construct( Connectors $connectors, Normalizer $normalizer, Security $security ) { $this->connectors = $connectors; $this->normalizer = $normalizer; $this->security = $security; }
	public static function canonical_key( $connector, $domain, $object_id ) { return hash( 'sha256', sanitize_key( $connector ) . '|' . sanitize_key( $domain ) . '|' . (string) $object_id ); }

	public function upsert( array $document ) {
		global $wpdb;
		$validation = $this->connectors->validate_document( $document ); if ( is_wp_error( $validation ) ) { return $validation; }
		$document = $this->sanitize_document( $document ); if ( is_wp_error( $document ) ) { return $document; }
		if ( in_array( $document['state'], array( 'deleted','suspended','restricted','rejected','private' ), true ) || 'restricted' === $document['visibility'] ) { return $this->tombstone( $document['connector_slug'], $document['domain_name'], $document['object_id'], $document['object_version'], $document['state'] ); }
		$lock = $this->acquire_object_lock( $document['canonical_key'] ); if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$tombstone_version = $wpdb->get_var( $wpdb->prepare( 'SELECT object_version FROM ' . DB::table( 'tombstones' ) . ' WHERE canonical_key=%s', $document['canonical_key'] ) );
			if ( null === $tombstone_version && $wpdb->last_error ) { return new \WP_Error( 'file26_tombstone_read_failed', 'Deletion precedence could not be verified; index write fails closed.' ); }
			$tombstone_version = (int) $tombstone_version;
			if ( $tombstone_version >= (int) $document['object_version'] ) { $this->security->audit( 'index_event_ignored', array( 'object_type'=>'document','object_key'=>$document['canonical_key'],'reason'=>'tombstone_precedence','metadata'=>array('tombstone_version'=>$tombstone_version,'incoming_version'=>(int)$document['object_version']) ) ); return true; }
			$table = DB::table( 'documents' );
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT object_version,source_event_sequence,checksum FROM $table WHERE canonical_key=%s", $document['canonical_key'] ), ARRAY_A );
			if ( null === $existing && $wpdb->last_error ) { return new \WP_Error( 'file26_index_state_read_failed', 'Current index state could not be verified; index write fails closed.' ); }
			if ( $existing ) {
				if ( (int) $document['object_version'] < (int) $existing['object_version'] ) { $this->security->audit( 'index_event_ignored', array( 'object_type'=>'document','object_key'=>$document['canonical_key'],'reason'=>'stale_object_version' ) ); return true; }
				if ( (int)$document['object_version'] === (int)$existing['object_version'] && (int)$document['source_event_sequence'] < (int)$existing['source_event_sequence'] ) { return true; }
				if ( hash_equals( (string)$existing['checksum'], $document['checksum'] ) ) { return true; }
			}
			$sql = $wpdb->prepare(
				"INSERT INTO $table (canonical_key,connector_slug,domain_name,object_id,object_version,entity_type,locale,state,visibility,title,excerpt,normalized_title,normalized_body,canonical_url,author_key,topic_ids,country,location,availability,quality_score,authority_score,popularity_score,freshness_at,safety_class,payload,source_event_id,source_event_sequence,checksum,indexed_at,updated_at) VALUES (" . implode( ',', array_fill( 0, 30, '%s' ) ) . ") ON DUPLICATE KEY UPDATE object_version=VALUES(object_version),entity_type=VALUES(entity_type),locale=VALUES(locale),state=VALUES(state),visibility=VALUES(visibility),title=VALUES(title),excerpt=VALUES(excerpt),normalized_title=VALUES(normalized_title),normalized_body=VALUES(normalized_body),canonical_url=VALUES(canonical_url),author_key=VALUES(author_key),topic_ids=VALUES(topic_ids),country=VALUES(country),location=VALUES(location),availability=VALUES(availability),quality_score=VALUES(quality_score),authority_score=VALUES(authority_score),popularity_score=VALUES(popularity_score),freshness_at=VALUES(freshness_at),safety_class=VALUES(safety_class),payload=VALUES(payload),source_event_id=VALUES(source_event_id),source_event_sequence=VALUES(source_event_sequence),checksum=VALUES(checksum),indexed_at=VALUES(indexed_at),updated_at=VALUES(updated_at)",
				$document['canonical_key'],$document['connector_slug'],$document['domain_name'],$document['object_id'],$document['object_version'],$document['entity_type'],$document['locale'],$document['state'],$document['visibility'],$document['title'],$document['excerpt'],$document['normalized_title'],$document['normalized_body'],$document['canonical_url'],$document['author_key'],$document['topic_ids'],$document['country'],$document['location'],$document['availability'],$document['quality_score'],$document['authority_score'],$document['popularity_score'],$document['freshness_at'],$document['safety_class'],$document['payload'],$document['source_event_id'],$document['source_event_sequence'],$document['checksum'],$document['indexed_at'],$document['updated_at']
			);
			$wpdb->query( 'START TRANSACTION' );
			try {
				if ( false === $wpdb->query( $sql ) ) { throw new \RuntimeException( 'Search document write failed.' ); }
				if ( false === $wpdb->delete( DB::table( 'tombstones' ), array( 'canonical_key'=>$document['canonical_key'] ), array('%s') ) ) { throw new \RuntimeException( 'Superseded tombstone cleanup failed.' ); }
				if ( ! $this->upsert_node( $document ) ) { throw new \RuntimeException( 'Search graph-node projection failed.' ); }
				$wpdb->query( 'COMMIT' );
			} catch ( \Throwable $e ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error( 'file26_index_write_failed', 'Search document and graph projection could not be indexed atomically.' );
			}
			$this->security->audit( 'search_document_indexed', array( 'object_type'=>'document','object_key'=>$document['canonical_key'],'metadata'=>array('connector'=>$document['connector_slug'],'entity_type'=>$document['entity_type'],'version'=>$document['object_version']) ) );
			do_action( 'sabri_file26_event', 'SearchDocumentIndexed', array( 'contract_version'=>SABRI_FILE26_CONTRACT_VERSION,'canonical_key'=>$document['canonical_key'],'object_version'=>$document['object_version'] ) );
			return $document['canonical_key'];
		} finally { $this->release_object_lock( $lock ); }
	}

	private function sanitize_document( array $document ) {
		$state=sanitize_key($document['state']); $visibility=sanitize_key($document['visibility']);
		$allowed_states=array('published','active','corrected','retracted','restricted','suspended','deleted','private','rejected'); $allowed_visibility=array('public','members','entitled','minor_guarded','restricted');
		if(!in_array($state,$allowed_states,true)||!in_array($visibility,$allowed_visibility,true)){return new \WP_Error('file26_invalid_visibility_state','Unknown state or visibility; fail closed.');}
		$url=$this->security->safe_url($document['canonical_url']); if(!$url){return new \WP_Error('file26_invalid_canonical_url','Canonical URL must be a safe same-origin route.');}
		$freshness=$this->normalize_time(isset($document['freshness_at'])?$document['freshness_at']:''); if(is_wp_error($freshness)){return $freshness;}
		$connector=sanitize_key($document['connector_slug']); $domain=sanitize_key($document['domain']); $object_id=sanitize_text_field((string)$document['object_id']); $title=sanitize_text_field($document['title']); $excerpt=isset($document['excerpt'])?wp_strip_all_tags($document['excerpt'],true):''; $body=isset($document['search_text'])?wp_strip_all_tags($document['search_text'],true):$excerpt; $payload=isset($document['payload'])&&is_array($document['payload'])?$this->sanitize_payload($document['payload']):array(); $version=max(1,(int)$document['object_version']); $sequence=isset($document['source_event_sequence'])?max(0,(int)$document['source_event_sequence']):$version; $now=DB::now();
		$data=array('canonical_key'=>self::canonical_key($connector,$domain,$object_id),'connector_slug'=>$connector,'domain_name'=>$domain,'object_id'=>$object_id,'object_version'=>$version,'entity_type'=>sanitize_key($document['entity_type']),'locale'=>substr(sanitize_text_field($document['locale']),0,20),'state'=>$state,'visibility'=>$visibility,'title'=>$title,'excerpt'=>$excerpt,'normalized_title'=>$this->normalizer->normalize($title),'normalized_body'=>$this->normalizer->normalize($body),'canonical_url'=>$url,'author_key'=>isset($document['author_key'])?sanitize_text_field($document['author_key']):'','topic_ids'=>wp_json_encode(array_values(array_unique(array_map('sanitize_text_field',isset($document['topic_ids'])?(array)$document['topic_ids']:array())))),'country'=>isset($document['country'])?sanitize_text_field($document['country']):'','location'=>isset($document['location'])?sanitize_text_field($document['location']):'','availability'=>isset($document['availability'])?sanitize_key($document['availability']):'','quality_score'=>isset($document['quality_score'])?min(1,max(0,(float)$document['quality_score'])):0,'authority_score'=>isset($document['authority_score'])?min(1,max(0,(float)$document['authority_score'])):0,'popularity_score'=>isset($document['popularity_score'])?max(0,(float)$document['popularity_score']):0,'freshness_at'=>$freshness?:$now,'safety_class'=>isset($document['safety_class'])?sanitize_key($document['safety_class']):'general','payload'=>wp_json_encode($payload),'source_event_id'=>isset($document['source_event_id'])?sanitize_text_field($document['source_event_id']):'','source_event_sequence'=>$sequence,'indexed_at'=>$now,'updated_at'=>$now); $data['checksum']=hash('sha256',wp_json_encode($data)); return $data;
	}

	private function normalize_time($value){if(null===$value||''===trim((string)$value)){return '';} $ts=strtotime((string)$value); if(false===$ts){return new \WP_Error('file26_invalid_freshness_time','Invalid freshness timestamp; index write fails closed.');} return gmdate('Y-m-d H:i:s',$ts);}

	private function sanitize_payload( array $payload ) {
		$allowed=array('image_url','duration','verified_author','verified_doctor','global_doctor_rank','correction_label','retraction_label','required_entitlement','download_allowed','download_url','download_label','download_reason','source_count','language','content_type_label','ai_generated','ai_provider_label','institutional_account_class','qualification_score','experience_score','patient_verified_review_score','ethical_conduct_score','knowledge_contribution_score','responsiveness_score','profile_completeness_score','complaint_appeal_outcome_score','manipulation_resistant_engagement_score','doctor_rank_score','doctor_rank_policy_version'); $clean=array();
		foreach($allowed as $key){if(!array_key_exists($key,$payload)){continue;} if(in_array($key,array('image_url','download_url'),true)){$clean[$key]=$this->security->safe_resource_url($payload[$key],$key);}elseif(in_array($key,array('verified_author','verified_doctor','download_allowed','ai_generated'),true)){$clean[$key]=(bool)$payload[$key];}elseif('global_doctor_rank'===$key||'source_count'===$key){$clean[$key]=max(0,(int)$payload[$key]);}elseif('doctor_rank_score'===$key){$clean[$key]=min(100,max(0,(float)$payload[$key]));}elseif(preg_match('/_score$/',$key)){$clean[$key]=min(1,max(0,(float)$payload[$key]));}else{$clean[$key]=sanitize_text_field($payload[$key]);}}
		if(empty($clean['download_allowed'])){unset($clean['download_url']);} return $clean;
	}

	public function restrict($connector,$domain,$object_id,$object_version,$reason='restricted'){return $this->tombstone($connector,$domain,$object_id,$object_version,$reason);}
	public function tombstone($connector,$domain,$object_id,$object_version,$reason='deleted'){
		global $wpdb; $key=self::canonical_key($connector,$domain,$object_id); $lock=$this->acquire_object_lock($key); if(is_wp_error($lock)){return $lock;}
		try{
			$table=DB::table('tombstones');$document_table=DB::table('documents');$existing=$wpdb->get_var($wpdb->prepare("SELECT object_version FROM $document_table WHERE canonical_key=%s",$key));if(null===$existing&&$wpdb->last_error){return new \WP_Error('file26_revocation_state_read_failed','Current projection state could not be verified; revocation must retry.');}$existing_version=(int)$existing;if($existing_version>(int)$object_version){return true;}
			$now=DB::now();$days=max(90,min(180,(int)DB::setting('tombstone_retention_days',180)));$expires=gmdate('Y-m-d H:i:s',time()+($days*DAY_IN_SECONDS));$wpdb->query('START TRANSACTION');
			try{
				$sql=$wpdb->prepare("INSERT INTO $table (canonical_key,connector_slug,domain_name,object_id,object_version,reason_class,received_at,purged_at,expires_at) VALUES (%s,%s,%s,%s,%d,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE object_version=GREATEST(object_version,VALUES(object_version)),reason_class=IF(VALUES(object_version) >= object_version,VALUES(reason_class),reason_class),received_at=IF(VALUES(object_version) >= object_version,VALUES(received_at),received_at),purged_at=IF(VALUES(object_version) >= object_version,VALUES(purged_at),purged_at),expires_at=IF(VALUES(object_version) >= object_version,VALUES(expires_at),expires_at)",$key,sanitize_key($connector),sanitize_key($domain),sanitize_text_field($object_id),max(1,(int)$object_version),sanitize_key($reason),$now,$now,$expires);
				if(false===$wpdb->query($sql)){throw new \RuntimeException('Tombstone write failed.');}
				if(false===$wpdb->delete($document_table,array('canonical_key'=>$key),array('%s'))){throw new \RuntimeException('Document purge failed.');}
				if(false===$wpdb->delete(DB::table('nodes'),array('node_key'=>$key),array('%s'))){throw new \RuntimeException('Node purge failed.');}
				if(false===$wpdb->delete(DB::table('classifications'),array('object_key'=>$key),array('%s'))){throw new \RuntimeException('Classification purge failed.');}
				if(false===$wpdb->query($wpdb->prepare('DELETE FROM '.DB::table('edges').' WHERE source_key=%s OR target_key=%s',$key,$key))){throw new \RuntimeException('Edge purge failed.');}
				$remaining=(int)$wpdb->get_var($wpdb->prepare('SELECT (SELECT COUNT(*) FROM '.DB::table('documents').' WHERE canonical_key=%s)+(SELECT COUNT(*) FROM '.DB::table('nodes').' WHERE node_key=%s)+(SELECT COUNT(*) FROM '.DB::table('classifications').' WHERE object_key=%s)+(SELECT COUNT(*) FROM '.DB::table('edges').' WHERE source_key=%s OR target_key=%s)',$key,$key,$key,$key,$key));
				if($remaining){throw new \RuntimeException('Derivative purge verification failed.');}
				$wpdb->query('COMMIT');
			}catch(\Throwable $e){$wpdb->query('ROLLBACK');return new \WP_Error('file26_tombstone_failed','Document revocation could not be completed atomically.');}
			if(function_exists('wp_cache_flush_group')){wp_cache_flush_group('sabri_file26');}else{wp_cache_flush();}$this->security->audit('search_document_tombstoned',array('object_type'=>'document','object_key'=>$key,'reason'=>$reason,'metadata'=>array('version'=>(int)$object_version)));do_action('sabri_file26_event','SearchDocumentTombstoned',array('contract_version'=>SABRI_FILE26_CONTRACT_VERSION,'canonical_key'=>$key,'object_version'=>(int)$object_version,'reason_class'=>sanitize_key($reason)));return true;
		}finally{$this->release_object_lock($lock);}
	}

	private function upsert_node(array $document){global $wpdb;$table=DB::table('nodes');$sql=$wpdb->prepare("INSERT INTO $table (node_key,node_type,canonical_url,visibility,state,locale,version,title,payload,updated_at) VALUES (%s,%s,%s,%s,%s,%s,%d,%s,%s,%s) ON DUPLICATE KEY UPDATE node_type=VALUES(node_type),canonical_url=VALUES(canonical_url),visibility=VALUES(visibility),state=VALUES(state),locale=VALUES(locale),version=VALUES(version),title=VALUES(title),payload=VALUES(payload),updated_at=VALUES(updated_at)",$document['canonical_key'],$document['entity_type'],$document['canonical_url'],$document['visibility'],$document['state'],$document['locale'],$document['object_version'],$document['title'],$document['payload'],$document['updated_at']);return false!==$wpdb->query($sql);}

	public function enqueue_reindex($connector_slug,array $scope=array()){
		global $wpdb;$connector_slug=sanitize_key($connector_slug);if(!$this->connectors->get($connector_slug)){return new \WP_Error('file26_unknown_connector','Unknown connector.');}$uuid=DB::uuid();$inserted=$wpdb->insert(DB::table('jobs'),array('job_uuid'=>$uuid,'job_type'=>'shadow_reindex','status'=>'pending','scope_json'=>wp_json_encode(array_merge(array('connector'=>$connector_slug),$scope)),'cursor_value'=>'','counts_json'=>wp_json_encode(array('processed'=>0,'failed'=>0)),'attempts'=>0,'available_at'=>DB::now(),'created_at'=>DB::now(),'updated_at'=>DB::now()));if(false===$inserted){return new \WP_Error('file26_job_enqueue_failed','Reindex job could not be queued.');}return $uuid;
	}

	public function process_queue(){
		global $wpdb;$table=DB::table('jobs');$timeout=max(300,min(DAY_IN_SECONDS,(int)DB::setting('job_lock_timeout_seconds',1800)));$stale_before=gmdate('Y-m-d H:i:s',time()-$timeout);$now=DB::now();
		$wpdb->query($wpdb->prepare("UPDATE $table SET status=IF(attempts>=8,'dead_letter','retry'),error_code='worker_timeout',lock_token=NULL,available_at=%s,finished_at=IF(attempts>=8,%s,NULL),updated_at=%s WHERE status='running' AND started_at<%s",$now,$now,$now,$stale_before));
		$job=$wpdb->get_row("SELECT * FROM $table WHERE status IN ('pending','retry') AND available_at <= UTC_TIMESTAMP() ORDER BY id ASC LIMIT 1",ARRAY_A);if(!$job){return;}$token=hash('sha256',$job['job_uuid'].'|'.microtime(true));$locked=$wpdb->query($wpdb->prepare("UPDATE $table SET status='running',lock_token=%s,started_at=%s,attempts=attempts+1,updated_at=%s WHERE id=%d AND status IN ('pending','retry')",$token,DB::now(),DB::now(),$job['id']));if(1!==(int)$locked){return;}$scope=json_decode($job['scope_json'],true);$connector=is_array($scope)&&isset($scope['connector'])?$this->connectors->get($scope['connector']):null;if(!$connector||empty($connector['list_batch'])||!is_callable($connector['list_batch'])){$this->fail_job($job['id'],$token,'connector_unavailable',(int)$job['attempts']+1);return;}
		try{$batch=call_user_func($connector['list_batch'],$job['cursor_value'],100,$scope);if(!is_array($batch)||!isset($batch['items'])||!is_array($batch['items'])){throw new \RuntimeException('Invalid connector batch.');}$counts=json_decode($job['counts_json'],true);$counts=is_array($counts)?$counts:array('processed'=>0,'failed'=>0);foreach($batch['items'] as $document){$result=$this->upsert($document);if(is_wp_error($result)){$counts['failed']++;throw new \RuntimeException('Batch item failed; cursor must not advance.');}else{$counts['processed']++;}}$done=!empty($batch['done']);$saved=$wpdb->update($table,array('status'=>$done?'completed':'pending','cursor_value'=>isset($batch['next_cursor'])?sanitize_text_field($batch['next_cursor']):'','counts_json'=>wp_json_encode($counts),'lock_token'=>null,'available_at'=>DB::now(),'finished_at'=>$done?DB::now():null,'updated_at'=>DB::now()),array('id'=>$job['id'],'lock_token'=>$token),array('%s','%s','%s','%s','%s','%s','%s'),array('%d','%s'));if(1!==(int)$saved){$this->security->audit('search_job_lock_lost',array('object_type'=>'job','object_key'=>$job['job_uuid'],'reason'=>'completion_cas_failed'));}}
		catch(\Throwable $e){$this->fail_job($job['id'],$token,'job_exception',(int)$job['attempts']+1);}
	}

	private function fail_job($id,$token,$code,$attempts){global $wpdb;$retry=min(DAY_IN_SECONDS,(int)pow(2,min(10,$attempts))*60);$updated=$wpdb->update(DB::table('jobs'),array('status'=>$attempts>=8?'dead_letter':'retry','error_code'=>sanitize_key($code),'lock_token'=>null,'available_at'=>gmdate('Y-m-d H:i:s',time()+$retry),'updated_at'=>DB::now()),array('id'=>(int)$id,'lock_token'=>$token));if(1!==(int)$updated){$this->security->audit('search_job_failure_state_lost',array('object_type'=>'job','object_key'=>(string)$id,'reason'=>'failure_cas_failed'));}}
	private function acquire_object_lock($canonical_key){global $wpdb;$lock_name='file26:'.substr(hash('sha256',(string)$canonical_key),0,48);$acquired=$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock_name));if('1'!==(string)$acquired){return new \WP_Error('file26_object_busy','Search object is busy; retry safely.');}return $lock_name;}
	private function release_object_lock($lock_name){global $wpdb;if(is_string($lock_name)&&''!==$lock_name){$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock_name));}}

	public function reconcile(){
		global $wpdb;$documents=DB::table('documents');$tombstones=DB::table('tombstones');$nodes=DB::table('nodes');$classes=DB::table('classifications');$edges=DB::table('edges');$wpdb->query('START TRANSACTION');
		try{
			$queries=array(
				"DELETE e FROM $edges e INNER JOIN $tombstones t ON (e.source_key=t.canonical_key OR e.target_key=t.canonical_key)",
				"DELETE c FROM $classes c INNER JOIN $tombstones t ON c.object_key=t.canonical_key",
				"DELETE n FROM $nodes n INNER JOIN $tombstones t ON n.node_key=t.canonical_key",
				"DELETE d FROM $documents d INNER JOIN $tombstones t ON d.canonical_key=t.canonical_key WHERE t.object_version >= d.object_version",
				"DELETE e FROM $edges e LEFT JOIN $nodes s ON e.source_key=s.node_key LEFT JOIN $nodes t ON e.target_key=t.node_key WHERE s.node_key IS NULL OR t.node_key IS NULL"
			);
			foreach($queries as $sql){if(false===$wpdb->query($sql)){throw new \RuntimeException('Reconciliation query failed.');}}
			$wpdb->query('COMMIT');return array('reconciled'=>true,'timestamp_utc'=>DB::now());
		}catch(\Throwable $e){$wpdb->query('ROLLBACK');return new \WP_Error('file26_reconcile_failed','Deletion and graph reconciliation failed atomically.');}
	}

	public function retention(){global $wpdb;$wpdb->query('DELETE FROM '.DB::table('tombstones').' WHERE expires_at < UTC_TIMESTAMP()');$wpdb->query('DELETE FROM '.DB::table('feedback').' WHERE expires_at < UTC_TIMESTAMP()');$wpdb->query('DELETE FROM '.DB::table('rate_limits').' WHERE expires_at < UTC_TIMESTAMP()');$audit_days=max(365,(int)DB::setting('audit_retention_days',760));$wpdb->query($wpdb->prepare('DELETE FROM '.DB::table('audit').' WHERE created_at < %s',gmdate('Y-m-d H:i:s',time()-($audit_days*DAY_IN_SECONDS))));}
}
