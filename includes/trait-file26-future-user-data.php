<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

trait Future_User_Data_Trait {
	public function research_trails( \WP_REST_Request $request ) {
		$user_id = get_current_user_id(); $method = strtoupper( $request->get_method() );
		$stored = get_user_meta( $user_id, self::META_TRAILS, true ); $trails = is_array( $stored ) ? $stored : array();
		if ( 'GET' === $method ) { return array( 'trails' => array_values( $trails ), 'stores_references_only' => true ); }
		$params = $this->params( $request ); $id = isset( $params['trail_id'] ) ? sanitize_key( (string) $params['trail_id'] ) : '';
		if ( 'DELETE' === $method ) { if ( ! $id || ! isset( $trails[ $id ] ) ) { return array( 'deleted' => false, 'trails' => array_values( $trails ) ); } unset( $trails[ $id ] ); $saved = $this->save_user_meta_cas( $user_id, self::META_TRAILS, $stored, $trails ); if ( is_wp_error( $saved ) ) { return $saved; } return array( 'deleted' => true, 'trails' => array_values( $trails ) ); }
		$action = isset( $params['action'] ) ? sanitize_key( (string) $params['action'] ) : 'create';
		if ( 'create' === $action ) { if ( count( $trails ) >= 50 ) { return new \WP_Error( 'file26_trail_limit', 'Research trail limit reached.', array( 'status' => 409 ) ); } $id = wp_generate_uuid4(); $trails[ $id ] = array( 'trail_id' => $id, 'title' => substr( sanitize_text_field( isset( $params['title'] ) ? (string) $params['title'] : 'Research Trail' ), 0, 120 ), 'refs' => array(), 'updated_at' => gmdate( 'c' ) ); }
		elseif ( in_array( $action, array( 'add', 'remove', 'rename' ), true ) ) {
			if ( ! $id || ! isset( $trails[ $id ] ) ) { return new \WP_Error( 'file26_trail_not_found', 'Research trail not found.', array( 'status' => 404 ) ); }
			if ( 'rename' === $action ) { $trails[ $id ]['title'] = substr( sanitize_text_field( isset( $params['title'] ) ? (string) $params['title'] : '' ), 0, 120 ); }
			if ( in_array( $action, array( 'add', 'remove' ), true ) ) { $ref = isset( $params['ref'] ) && is_array( $params['ref'] ) ? $this->sanitize_reference( $params['ref'] ) : array(); if ( empty( $ref['owner'] ) || empty( $ref['object_id'] ) ) { return new \WP_Error( 'file26_reference_required', 'A canonical owner/object reference is required.', array( 'status' => 400 ) ); } $key = hash( 'sha256', $ref['owner'] . '|' . $ref['object_id'] . '|' . $ref['object_version'] ); $refs = array(); foreach ( (array) $trails[ $id ]['refs'] as $old ) { if ( is_array( $old ) && ! empty( $old['ref_key'] ) ) { $refs[ $old['ref_key'] ] = $old; } } if ( 'add' === $action ) { if ( count( $refs ) >= 200 && ! isset( $refs[ $key ] ) ) { return new \WP_Error( 'file26_trail_reference_limit', 'Research trail reference limit reached.', array( 'status' => 409 ) ); } $ref['ref_key'] = $key; $refs[ $key ] = $ref; } else { unset( $refs[ $key ] ); } $trails[ $id ]['refs'] = array_values( $refs ); }
			$trails[ $id ]['updated_at'] = gmdate( 'c' );
		} else { return new \WP_Error( 'file26_trail_action_invalid', 'Invalid research trail action.', array( 'status' => 400 ) ); }
		$saved = $this->save_user_meta_cas( $user_id, self::META_TRAILS, $stored, $trails ); if ( is_wp_error( $saved ) ) { return $saved; }
		return array( 'saved' => true, 'trail' => isset( $trails[ $id ] ) ? $trails[ $id ] : null, 'stores_references_only' => true );
	}

	public function saved_search_alerts( \WP_REST_Request $request ) {
		$user_id = get_current_user_id(); $method = strtoupper( $request->get_method() ); $stored = get_user_meta( $user_id, self::META_ALERTS, true ); $alerts = is_array( $stored ) ? $stored : array();
		if ( 'GET' === $method ) { return array( 'alerts' => array_values( $alerts ), 'delivery_owner' => 'File 19', 'file26_sends_notifications' => false ); }
		$params = $this->params( $request ); $id = isset( $params['alert_id'] ) ? sanitize_key( (string) $params['alert_id'] ) : '';
		if ( 'DELETE' === $method ) { if ( ! $id || ! isset( $alerts[ $id ] ) ) { return array( 'deleted' => false, 'delivery_owner' => 'File 19' ); } unset( $alerts[ $id ] ); $saved = $this->save_user_meta_cas( $user_id, self::META_ALERTS, $stored, $alerts ); if ( is_wp_error( $saved ) ) { return $saved; } do_action( 'sabri_file26_saved_search_alert_changed', $user_id, $id, null ); return array( 'deleted' => true, 'delivery_owner' => 'File 19' ); }
		$q = $this->query( $params ); if ( '' === $q || $this->sensitive_query( $q ) ) { return new \WP_Error( 'file26_alert_query_not_allowed', 'A non-sensitive search query is required for server-side alerts.', array( 'status' => 400 ) ); }
		$filters = $this->sanitize_filters( isset( $params['filters'] ) && is_array( $params['filters'] ) ? $params['filters'] : array() );
		foreach ( $filters as $filter_value ) { if ( $this->sensitive_query( (string) $filter_value ) ) { return new \WP_Error( 'file26_alert_filters_not_allowed', 'Sensitive values are not accepted in server-side saved-alert filters.', array( 'status' => 400 ) ); } }
		$cadence = isset( $params['cadence'] ) ? sanitize_key( (string) $params['cadence'] ) : 'daily'; if ( ! in_array( $cadence, array( 'hourly', 'daily', 'weekly' ), true ) ) { $cadence = 'daily'; }
		if ( count( $alerts ) >= 50 && ( ! $id || ! isset( $alerts[ $id ] ) ) ) { return new \WP_Error( 'file26_alert_limit', 'Saved search alert limit reached.', array( 'status' => 409 ) ); }
		$id = $id ?: sanitize_key( wp_generate_uuid4() ); $alerts[ $id ] = array( 'alert_id' => $id, 'query' => $q, 'query_hash' => hash( 'sha256', $q ), 'filters' => $filters, 'cadence' => $cadence, 'enabled' => ! isset( $params['enabled'] ) || (bool) $params['enabled'], 'updated_at' => gmdate( 'c' ) );
		$saved = $this->save_user_meta_cas( $user_id, self::META_ALERTS, $stored, $alerts ); if ( is_wp_error( $saved ) ) { return $saved; } do_action( 'sabri_file26_saved_search_alert_changed', $user_id, $id, $alerts[ $id ] );
		return array( 'saved' => true, 'alert' => $alerts[ $id ], 'delivery_owner' => 'File 19', 'file26_sends_notifications' => false );
	}

	public function search_history( \WP_REST_Request $request ) {
		$user_id = get_current_user_id(); $method = strtoupper( $request->get_method() ); $opted_in = (bool) get_user_meta( $user_id, self::META_HISTORY_OPT_IN, true ); $stored = get_user_meta( $user_id, self::META_HISTORY, true ); $history = is_array( $stored ) ? $stored : array();
		if ( 'GET' === $method ) { return array( 'policy' => 'local_first', 'server_sync_opt_in' => $opted_in, 'server_history' => $opted_in ? array_values( $history ) : array(), 'default_network_sync' => false ); }
		if ( 'DELETE' === $method ) { delete_user_meta( $user_id, self::META_HISTORY ); if ( ! empty( $this->params( $request )['disable_sync'] ) ) { delete_user_meta( $user_id, self::META_HISTORY_OPT_IN ); } return array( 'cleared' => true, 'server_sync_opt_in' => (bool) get_user_meta( $user_id, self::META_HISTORY_OPT_IN, true ) ); }
		$params = $this->params( $request ); if ( empty( $params['sync_opt_in'] ) ) { return new \WP_Error( 'file26_history_explicit_opt_in_required', 'Server history sync requires explicit opt-in.', array( 'status' => 400 ) ); }
		$q = $this->query( $params ); $sensitive = '' !== $q && $this->sensitive_query( $q );
		if ( '' !== $q && ! $sensitive ) { $query_hash = hash( 'sha256', $q ); $deduped = array(); foreach ( $history as $old ) { if ( is_array( $old ) && ! empty( $old['query_hash'] ) && $query_hash !== $old['query_hash'] ) { $deduped[] = $old; } } $deduped[] = array( 'query' => $q, 'query_hash' => $query_hash, 'searched_at' => gmdate( 'c' ) ); $history = array_slice( $deduped, -50 ); $saved = $this->save_user_meta_cas( $user_id, self::META_HISTORY, $stored, $history ); if ( is_wp_error( $saved ) ) { return $saved; } }
		update_user_meta( $user_id, self::META_HISTORY_OPT_IN, 1 );
		return array( 'saved' => '' !== $q && ! $sensitive, 'sensitive_query_not_synced' => $sensitive, 'server_sync_opt_in' => true, 'default_network_sync' => false );
	}
}
