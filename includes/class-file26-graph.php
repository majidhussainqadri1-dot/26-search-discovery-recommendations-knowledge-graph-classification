<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Graph {
	private $security;
	private $allowed_edges = array( 'author-of','lesson-about','remedy-related','doctor-published','book-chapter','video-explains','research-supports','research-contradicts','topic-related' );
	public function __construct( Security $security ) { $this->security = $security; }

	public function create_edge( array $input ) {
		global $wpdb;
		if ( ! $this->security->can_curate() ) { return new \WP_Error( 'file26_forbidden', 'Graph curator capability is required.' ); }
		$source = preg_replace( '/[^a-f0-9]/', '', strtolower( isset( $input['source_key'] ) ? $input['source_key'] : '' ) );
		$target = preg_replace( '/[^a-f0-9]/', '', strtolower( isset( $input['target_key'] ) ? $input['target_key'] : '' ) );
		$type = sanitize_key( isset( $input['edge_type'] ) ? $input['edge_type'] : '' );
		if ( strlen( $source ) !== 64 || strlen( $target ) !== 64 || ! in_array( $type, $this->allowed_edges, true ) || hash_equals( $source, $target ) ) { return new \WP_Error( 'file26_invalid_edge', 'Invalid graph edge.' ); }
		$source_visible = $this->public_node_state( $source ); $target_visible = $this->public_node_state( $target );
		if ( is_wp_error( $source_visible ) ) { return $source_visible; } if ( is_wp_error( $target_visible ) ) { return $target_visible; }
		if ( ! $source_visible || ! $target_visible ) { return new \WP_Error( 'file26_invalid_edge_endpoint', 'Both graph endpoints must be production-active visible nodes.' ); }
		$owner_file = $this->source_owner_file( $source ); if ( is_wp_error( $owner_file ) ) { return $owner_file; }
		$provenance = isset( $input['provenance'] ) && is_array( $input['provenance'] ) ? $this->sanitize_provenance( $input['provenance'] ) : array();
		if ( is_wp_error( $provenance ) ) { return $provenance; } if ( empty( $provenance ) ) { return new \WP_Error( 'file26_provenance_required', 'Graph provenance is required.' ); }
		$encoded_provenance = wp_json_encode( $provenance );
		if ( false === $encoded_provenance || strlen( $encoded_provenance ) > 16384 ) { return new \WP_Error( 'file26_provenance_too_large', 'Graph provenance exceeds the allowed size.', array( 'status' => 413 ) ); }
		$evidence_url = $this->sanitize_evidence_url( isset( $input['evidence_url'] ) ? $input['evidence_url'] : '' ); if ( is_wp_error( $evidence_url ) ) { return $evidence_url; }
		$identity = hash( 'sha256', $source . '|' . $target . '|' . $type ); $lock = $this->acquire_edge_lock( $identity ); if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT edge_uuid FROM ' . DB::table( 'edges' ) . " WHERE source_key=%s AND target_key=%s AND edge_type=%s AND state IN ('draft','active') LIMIT 1", $source, $target, $type ) );
			if ( ! empty( $wpdb->last_error ) ) { return new \WP_Error( 'file26_edge_read_failed', 'Existing graph relationship state could not be read safely.', array( 'status' => 500 ) ); }
			if ( $existing ) { return new \WP_Error( 'file26_duplicate_edge', 'An equivalent current graph edge already exists.', array( 'status' => 409 ) ); }
			$uuid = DB::uuid();
			$inserted = $wpdb->insert( DB::table( 'edges' ), array(
				'edge_uuid'=>$uuid,'source_key'=>$source,'target_key'=>$target,'edge_type'=>$type,'provenance'=>$encoded_provenance,'owner_file'=>$owner_file,'evidence_url'=>$evidence_url,
				'state'=>'draft','visibility'=>'public','version'=>1,'created_at'=>DB::now(),'updated_at'=>DB::now(),
			) );
			if ( ! $inserted ) { return new \WP_Error( 'file26_edge_insert_failed', 'Graph edge could not be created.', array( 'status' => 409 ) ); }
		} finally { $this->release_edge_lock( $lock ); }
		$audit = $this->security->audit( 'knowledge_edge_created', array( 'object_type'=>'knowledge_edge','object_key'=>$uuid,'metadata'=>array( 'edge_type'=>$type,'source_key'=>$source,'target_key'=>$target,'owner_file'=>$owner_file ) ) );
		if ( is_wp_error( $audit ) ) { return $this->audit_failure_after_mutation( $audit, 'edge_created' ); }
		do_action( 'sabri_file26_event', 'KnowledgeEdgeCreated', array( 'edge_uuid'=>$uuid,'edge_type'=>$type ) ); return $uuid;
	}

	public function approve_edge( $edge_uuid, $expected_version = 1 ) {
		global $wpdb;
		if ( ! $this->security->can_curate() || ! $this->security->require_step_up( 'graph_edge_approve' ) ) { return new \WP_Error( 'file26_step_up_required', 'Fresh graph approval authorization is required.', array( 'status'=>403 ) ); }
		$edge_uuid = sanitize_text_field( $edge_uuid ); $edge = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DB::table( 'edges' ) . ' WHERE edge_uuid=%s', $edge_uuid ), ARRAY_A );
		if ( null === $edge && ! empty( $wpdb->last_error ) ) { return new \WP_Error( 'file26_edge_read_failed', 'Graph edge state could not be read safely.', array( 'status'=>500 ) ); }
		if ( ! $edge || 'draft' !== $edge['state'] || (int)$edge['version'] !== (int)$expected_version ) { return new \WP_Error( 'file26_edge_conflict', 'Graph edge is missing or changed concurrently.', array( 'status'=>409 ) ); }
		$source_visible = $this->public_node_state( $edge['source_key'] ); $target_visible = $this->public_node_state( $edge['target_key'] );
		if ( is_wp_error( $source_visible ) ) { return $source_visible; } if ( is_wp_error( $target_visible ) ) { return $target_visible; }
		if ( ! $source_visible || ! $target_visible || empty( json_decode( $edge['provenance'], true ) ) ) { return new \WP_Error( 'file26_edge_endpoint_stale', 'Graph endpoints or provenance are no longer eligible for activation.', array( 'status'=>409 ) ); }
		$current_owner = $this->source_owner_file( $edge['source_key'] ); if ( is_wp_error( $current_owner ) ) { return $current_owner; }
		if ( (string)$current_owner !== (string)$edge['owner_file'] ) { return new \WP_Error( 'file26_graph_owner_stale', 'The source-domain owner changed; recreate the edge under the current owner contract.', array( 'status'=>409 ) ); }
		$approved = (bool)apply_filters( 'sabri_file26_graph_edge_owner_approved', 'File 26' === $edge['owner_file'], $edge, get_current_user_id() );
		if ( ! $approved ) { return new \WP_Error( 'file26_graph_owner_approval_required', 'The source-domain owner must approve this graph edge.', array( 'status'=>403 ) ); }
		$updated = $wpdb->update( DB::table( 'edges' ), array( 'state'=>'active','version'=>(int)$edge['version']+1,'updated_at'=>DB::now() ), array( 'edge_uuid'=>$edge_uuid,'state'=>'draft','version'=>(int)$edge['version'] ), array('%s','%d','%s'), array('%s','%s','%d') );
		if ( false === $updated ) { return new \WP_Error( 'file26_edge_write_failed', 'Graph edge approval could not be persisted.', array( 'status'=>500 ) ); }
		if ( 1 !== $updated ) { return new \WP_Error( 'file26_edge_conflict', 'Graph edge changed concurrently.', array( 'status'=>409 ) ); }
		$audit = $this->security->audit( 'knowledge_edge_approved', array( 'object_type'=>'knowledge_edge','object_key'=>$edge_uuid,'metadata'=>array( 'version'=>(int)$edge['version']+1 ) ) );
		if ( is_wp_error( $audit ) ) { return $this->audit_failure_after_mutation( $audit, 'edge_approved' ); }
		do_action( 'sabri_file26_event', 'KnowledgeEdgeApproved', array( 'edge_uuid'=>$edge_uuid,'version'=>(int)$edge['version']+1 ) ); return true;
	}

	public function remove_edge( $edge_uuid, $expected_version, $reason = '' ) {
		global $wpdb;
		if ( ! $this->security->can_curate() || ! $this->security->require_step_up( 'graph_edge_remove' ) ) { return new \WP_Error( 'file26_step_up_required', 'Fresh graph removal authorization is required.', array( 'status'=>403 ) ); }
		$edge_uuid = sanitize_text_field( $edge_uuid );
		$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . DB::table( 'edges' ) . " SET state='removed',version=version+1,updated_at=%s WHERE edge_uuid=%s AND version=%d AND state IN ('draft','active')", DB::now(), $edge_uuid, (int)$expected_version ) );
		if ( false === $updated ) { return new \WP_Error( 'file26_edge_write_failed', 'Graph edge removal could not be persisted.', array( 'status'=>500 ) ); }
		if ( 1 !== (int)$updated ) { return new \WP_Error( 'file26_edge_conflict', 'Graph edge is missing or changed concurrently.', array( 'status'=>409 ) ); }
		$audit = $this->security->audit( 'knowledge_edge_removed', array( 'object_type'=>'knowledge_edge','object_key'=>$edge_uuid,'reason'=>sanitize_text_field($reason) ) );
		if ( is_wp_error( $audit ) ) { return $this->audit_failure_after_mutation( $audit, 'edge_removed' ); }
		do_action( 'sabri_file26_event', 'KnowledgeEdgeRemoved', array( 'edge_uuid'=>$edge_uuid ) ); return true;
	}

	public function query( $start_key, $depth = 1, $degree = 10, array $allowed_types = array() ) {
		global $wpdb; $start_key = preg_replace( '/[^a-f0-9]/', '', strtolower( $start_key ) );
		if ( strlen( $start_key ) !== 64 ) { return new \WP_Error( 'file26_graph_node_not_found', 'Graph node not found.', array( 'status'=>404 ) ); }
		$start_visible = $this->public_node_state( $start_key ); if ( is_wp_error( $start_visible ) ) { return $start_visible; }
		if ( ! $start_visible ) { return new \WP_Error( 'file26_graph_node_not_found', 'Graph node not found.', array( 'status'=>404 ) ); }
		$depth=max(1,min((int)DB::setting('graph_max_depth',2),(int)$depth)); $degree=max(1,min((int)DB::setting('graph_max_degree',20),(int)$degree));
		$allowed_types=array_values(array_intersect(array_map('sanitize_key',$allowed_types),$this->allowed_edges)); if(!$allowed_types){$allowed_types=$this->allowed_edges;}
		$visited=array($start_key=>true);$frontier=array($start_key);$edges=array();$nodes=array();
		for($level=0;$level<$depth&&$frontier;$level++){$next=array();foreach($frontier as $node_key){$placeholders=implode(',',array_fill(0,count($allowed_types),'%s'));$args=array_merge(array($node_key),$allowed_types,array($degree));$sql=$wpdb->prepare('SELECT * FROM '.DB::table('edges')." WHERE source_key=%s AND state='active' AND visibility='public' AND edge_type IN ($placeholders) ORDER BY edge_type,edge_uuid LIMIT %d",$args);$rows=$wpdb->get_results($sql,ARRAY_A);if(null===$rows&&!empty($wpdb->last_error)){return new \WP_Error('file26_graph_read_failed','Graph relationships could not be read safely.',array('status'=>503));}foreach((array)$rows as $edge){$target_visible=$this->public_node_state($edge['target_key']);if(is_wp_error($target_visible)){return $target_visible;}if(!$target_visible){continue;}$edge['provenance']=json_decode($edge['provenance'],true);$edges[]=$edge;if(!isset($visited[$edge['target_key']])){$visited[$edge['target_key']]=true;$next[]=$edge['target_key'];}}}$frontier=array_slice(array_values(array_unique($next)),0,$degree*$degree);}
		if($visited){$keys=array_keys($visited);$placeholders=implode(',',array_fill(0,count($keys),'%s'));$documents=DB::table('documents');$connectors=DB::table('connectors');$nodes_table=DB::table('nodes');$sql=$wpdb->prepare("SELECT n.node_key,n.node_type,n.canonical_url,n.locale,n.version,n.title FROM $nodes_table n INNER JOIN $documents d ON d.canonical_key=n.node_key INNER JOIN $connectors c ON c.slug=d.connector_slug AND c.status='active' WHERE n.node_key IN ($placeholders) AND n.state IN ('active','published','corrected') AND n.visibility='public' AND d.state IN ('active','published','corrected') AND d.visibility='public'",$keys);$nodes=$wpdb->get_results($sql,ARRAY_A);if(null===$nodes&&!empty($wpdb->last_error)){return new \WP_Error('file26_graph_read_failed','Graph nodes could not be read safely.',array('status'=>503));}}
		$visible_keys=array();foreach((array)$nodes as $node){$visible_keys[$node['node_key']]=true;}if(!isset($visible_keys[$start_key])){return new \WP_Error('file26_graph_node_not_found','Graph node is no longer public.',array('status'=>404));}
		$edges=array_values(array_filter($edges,static function($edge)use($visible_keys){return isset($visible_keys[$edge['source_key']],$visible_keys[$edge['target_key']]);}));
		return array('contract_version'=>SABRI_FILE26_CONTRACT_VERSION,'start_key'=>$start_key,'depth'=>$depth,'nodes'=>$nodes,'edges'=>$edges);
	}

	private function sanitize_provenance( array $value, $depth = 0 ) {
		if($depth>3||count($value)>50){return new \WP_Error('file26_provenance_complexity','Graph provenance is too complex.',array('status'=>400));}$clean=array();
		foreach($value as $raw_key=>$item){$key=sanitize_key((string)$raw_key);if(''===$key){continue;}if(is_array($item)){$item=$this->sanitize_provenance($item,$depth+1);if(is_wp_error($item)){return $item;}}elseif(is_bool($item)||is_int($item)||is_float($item)){}elseif(is_scalar($item)){$item=sanitize_text_field((string)$item);$item=function_exists('mb_substr')?mb_substr($item,0,512,'UTF-8'):substr($item,0,512);}else{continue;}$clean[$key]=$item;}return $clean;
	}

	private function sanitize_evidence_url( $url ) {
		$url=trim((string)$url);if(''===$url){return '';}
		if(0===strpos($url,'/')){$safe=$this->security->safe_url($url);return $safe?$safe:new \WP_Error('file26_invalid_evidence_url','Invalid same-origin graph evidence URL.',array('status'=>400));}
		$url=esc_url_raw($url,array('https'));$parts=$url?wp_parse_url($url):false;
		if(!$parts||empty($parts['scheme'])||'https'!==strtolower($parts['scheme'])||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])){return new \WP_Error('file26_invalid_evidence_url','External graph evidence must use a valid HTTPS URL.',array('status'=>400));}
		$host=strtolower($parts['host']);
		if('localhost'===$host||substr($host,-6)==='.local'||(filter_var($host,FILTER_VALIDATE_IP)&&!filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))){return new \WP_Error('file26_unsafe_evidence_url','Unsafe graph evidence URL.',array('status'=>400));}
		if(!apply_filters('sabri_file26_allowed_evidence_url',false,$url,$host)){return new \WP_Error('file26_evidence_url_not_allowed','The external graph evidence host is not explicitly approved.',array('status'=>403));}
		return $url;
	}

	private function public_node_state($key){
		global $wpdb;
		$nodes=DB::table('nodes');$documents=DB::table('documents');$connectors=DB::table('connectors');
		$value=$wpdb->get_var($wpdb->prepare("SELECT 1 FROM $nodes n INNER JOIN $documents d ON d.canonical_key=n.node_key INNER JOIN $connectors c ON c.slug=d.connector_slug AND c.status='active' WHERE n.node_key=%s AND n.state IN ('active','published','corrected') AND n.visibility='public' AND d.state IN ('active','published','corrected') AND d.visibility='public' LIMIT 1",$key));
		if(!empty($wpdb->last_error)){return new \WP_Error('file26_graph_read_failed','Graph node visibility and production-lane state could not be read safely.',array('status'=>503));}
		return(bool)$value;
	}
	private function source_owner_file($source_key){global $wpdb;$owner=$wpdb->get_var($wpdb->prepare('SELECT c.owner_file FROM '.DB::table('documents').' d INNER JOIN '.DB::table('connectors')." c ON c.slug=d.connector_slug AND c.status='active' WHERE d.canonical_key=%s AND d.state IN ('active','published','corrected') AND d.visibility='public' LIMIT 1",$source_key));if(!empty($wpdb->last_error)){return new \WP_Error('file26_graph_owner_read_failed','Source-domain ownership could not be read safely.',array('status'=>500));}if(!$owner){return new \WP_Error('file26_graph_owner_missing','An active canonical source-domain owner is required for this edge.',array('status'=>409));}return substr(sanitize_text_field($owner),0,64);}
	private function audit_failure_after_mutation($audit,$stage){return new \WP_Error('file26_required_audit_failed','Graph mutation was persisted but required audit evidence failed; File 26 is degraded until audit storage is repaired.',array('status'=>500,'mutation_persisted'=>true,'stage'=>sanitize_key($stage),'audit_error'=>is_wp_error($audit)?$audit->get_error_code():'unknown'));}
	private function acquire_edge_lock($identity){global $wpdb;$name='file26:edge:'.substr((string)$identity,0,40);$acquired=$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$name));if('1'!==(string)$acquired){return new \WP_Error('file26_edge_busy','Equivalent graph relationship is busy; retry safely.',array('status'=>409));}return $name;}
	private function release_edge_lock($name){global $wpdb;if(is_string($name)&&''!==$name){$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name));}}
}
