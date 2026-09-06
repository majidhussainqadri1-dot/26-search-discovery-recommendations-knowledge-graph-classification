<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Physical schema verification independent of stored version options. */
final class Schema_Integrity {
	private static function definitions() {
		return array(
			'connectors' => array(
				'columns' => array( 'id','slug','owner_file','contract_version','status','manifest','last_event_version','health_state','last_health','created_at','updated_at' ),
				'indexes' => array( 'PRIMARY','slug','status' ),
			),
			'documents' => array(
				'columns' => array( 'id','canonical_key','connector_slug','domain_name','object_id','object_version','entity_type','locale','state','visibility','title','excerpt','normalized_title','normalized_body','canonical_url','author_key','topic_ids','country','location','availability','quality_score','authority_score','popularity_score','freshness_at','safety_class','payload','source_event_id','source_event_sequence','checksum','indexed_at','updated_at' ),
				'indexes' => array( 'PRIMARY','canonical_key','eligible','connector_slug','domain_name','locale','freshness_at' ),
			),
			'tombstones' => array(
				'columns' => array( 'id','canonical_key','connector_slug','domain_name','object_id','object_version','reason_class','received_at','purged_at','expires_at' ),
				'indexes' => array( 'PRIMARY','canonical_key','expires_at' ),
			),
			'terms' => array(
				'columns' => array( 'id','term_uuid','slug','preferred_label','definition','language','parent_uuid','related_json','owner_file','status','version','redirect_uuid','created_at','updated_at' ),
				'indexes' => array( 'PRIMARY','term_uuid','slug_language','status','parent_uuid' ),
			),
			'term_aliases' => array(
				'columns' => array( 'id','term_uuid','alias_label','alias_normalized','language','status','created_at' ),
				'indexes' => array( 'PRIMARY','alias_language','term_uuid' ),
			),
			'classifications' => array(
				'columns' => array( 'id','object_key','term_uuid','confidence','method','method_version','reviewer_id','status','provenance','version','created_at','updated_at' ),
				'indexes' => array( 'PRIMARY','object_term','status','term_uuid' ),
			),
			'nodes' => array(
				'columns' => array( 'id','node_key','node_type','canonical_url','visibility','state','locale','version','title','payload','updated_at' ),
				'indexes' => array( 'PRIMARY','node_key','eligible' ),
			),
			'edges' => array(
				'columns' => array( 'id','edge_uuid','source_key','target_key','edge_type','provenance','owner_file','evidence_url','state','visibility','version','created_at','updated_at' ),
				'indexes' => array( 'PRIMARY','edge_uuid','source_lookup','target_lookup','edge_type' ),
			),
			'ranking_policies' => array(
				'columns' => array( 'id','policy_uuid','context_name','audience','version','status','features_json','approval_one','approval_two','effective_at','created_at','updated_at' ),
				'indexes' => array( 'PRIMARY','policy_uuid','context_version','active_policy' ),
			),
			'feedback' => array(
				'columns' => array( 'id','idempotency_key','user_id','item_key','feedback_type','scope_key','payload','active','created_at','updated_at','expires_at' ),
				'indexes' => array( 'PRIMARY','idempotency_key','user_active','expires_at' ),
			),
			'profiles' => array(
				'columns' => array( 'user_id','consent','opted_out','interests_json','negatives_json','version','updated_at' ),
				'indexes' => array( 'PRIMARY' ),
			),
			'jobs' => array(
				'columns' => array( 'id','job_uuid','job_type','status','scope_json','cursor_value','counts_json','error_code','lock_token','attempts','available_at','started_at','finished_at','created_at','updated_at' ),
				'indexes' => array( 'PRIMARY','job_uuid','runnable','job_type' ),
			),
			'audit' => array(
				'columns' => array( 'id','action_name','actor_id','object_type','object_key','reason_code','trace_id','metadata','created_at' ),
				'indexes' => array( 'PRIMARY','action_name','object_lookup','created_at' ),
			),
			'metrics' => array(
				'columns' => array( 'id','metric_date','metric_key','bucket_hash','locale','count_value','sum_value','updated_at' ),
				'indexes' => array( 'PRIMARY','metric_bucket','metric_date' ),
			),
			'rate_limits' => array(
				'columns' => array( 'id','bucket_key','window_start','count_value','expires_at' ),
				'indexes' => array( 'PRIMARY','bucket_window','expires_at' ),
			),
			'ranking_appeals' => array(
				'columns' => array( 'id','appeal_uuid','doctor_key','appellant_user_id','reason_text','evidence_json','status','reviewer_id','decision_reason','policy_version','rank_snapshot','version','submitted_at','updated_at','decided_at' ),
				'indexes' => array( 'PRIMARY','appeal_uuid','doctor_status','appellant_status','submitted_at' ),
			),
		);
	}

	private static function physical_names() {
		$names = array();
		foreach ( array_keys( self::definitions() ) as $logical ) {
			$names[ $logical ] = 'ranking_appeals' === $logical ? Doctor_Appeals::table() : DB::table( $logical );
		}
		return $names;
	}

	/** Return one consistent physical-shape snapshot using metadata tables, not version options. */
	public static function snapshot() {
		global $wpdb;
		$definitions = self::definitions();
		$physical = self::physical_names();
		$table_names = array_values( $physical );
		$placeholders = implode( ',', array_fill( 0, count( $table_names ), '%s' ) );
		$columns_sql = $wpdb->prepare(
			"SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($placeholders)",
			$table_names
		);
		$indexes_sql = $wpdb->prepare(
			"SELECT TABLE_NAME,INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($placeholders)",
			$table_names
		);
		$column_rows = $wpdb->get_results( $columns_sql, ARRAY_A );
		$index_rows = $wpdb->get_results( $indexes_sql, ARRAY_A );
		if ( ! is_array( $column_rows ) || ! is_array( $index_rows ) ) {
			return array( 'complete' => false, 'main_complete' => false, 'appeals_complete' => false, 'tables' => array(), 'query_failed' => true );
		}
		$actual_columns = array();
		foreach ( $column_rows as $row ) { $actual_columns[ $row['TABLE_NAME'] ][ $row['COLUMN_NAME'] ] = true; }
		$actual_indexes = array();
		foreach ( $index_rows as $row ) { $actual_indexes[ $row['TABLE_NAME'] ][ $row['INDEX_NAME'] ] = true; }
		$tables = array();
		$main_complete = true;
		$appeals_complete = true;
		foreach ( $definitions as $logical => $definition ) {
			$table = $physical[ $logical ];
			$columns = isset( $actual_columns[ $table ] ) ? $actual_columns[ $table ] : array();
			$indexes = isset( $actual_indexes[ $table ] ) ? $actual_indexes[ $table ] : array();
			$missing_columns = array_values( array_filter( $definition['columns'], static function ( $column ) use ( $columns ) { return ! isset( $columns[ $column ] ); } ) );
			$missing_indexes = array_values( array_filter( $definition['indexes'], static function ( $index ) use ( $indexes ) { return ! isset( $indexes[ $index ] ); } ) );
			$ok = empty( $missing_columns ) && empty( $missing_indexes );
			$tables[ $logical ] = array( 'physical_name' => $table, 'complete' => $ok, 'missing_columns' => $missing_columns, 'missing_indexes' => $missing_indexes );
			if ( 'ranking_appeals' === $logical ) { $appeals_complete = $ok; } elseif ( ! $ok ) { $main_complete = false; }
		}
		return array( 'complete' => $main_complete && $appeals_complete, 'main_complete' => $main_complete, 'appeals_complete' => $appeals_complete, 'tables' => $tables, 'query_failed' => false );
	}

	public static function complete() { $snapshot = self::snapshot(); return ! empty( $snapshot['complete'] ); }
	public static function main_complete() { $snapshot = self::snapshot(); return ! empty( $snapshot['main_complete'] ); }
	public static function appeals_complete() { $snapshot = self::snapshot(); return ! empty( $snapshot['appeals_complete'] ); }
}
