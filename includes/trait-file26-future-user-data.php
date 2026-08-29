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
		$enabled = true; if ( array_key_exists( 'enabled', $params ) ) { $parsed_enabled = $this->future_strict_bool( $params['enabled'] ); if ( null === $parsed_enabled ) { return new \WP_Error( 'file26_alert_enabled_invalid', 'Saved-alert enabled must be an explicit boolean value.', array( 'status' => 400 ) ); } $enabled = $parsed_enabled; }
		if ( count( $alerts ) >= 50 && ( ! $id || ! isset( $alerts[ $id ] ) ) ) { return new \WP_Error( 'file26_alert_limit', 'Saved search alert limit reached.', array( 'status' => 409 ) ); }
		$id = $id ?: sanitize_key( wp_generate_uuid4() ); $alerts[ $id ] = array( 'alert_id' => $id, 'query' => $q, 'query_hash' => hash_hmac( 'sha256', $q, wp_salt( 'nonce' ) ), 'filters' => $filters, 'cadence' => $cadence, 'enabled' => $enabled, 'updated_at' => gmdate( 'c' ) );
		$saved = $this->save_user_meta_cas( $user_id, self::META_ALERTS, $stored, $alerts ); if ( is_wp_error( $saved ) ) { return $saved; } do_action( 'sabri_file26_saved_search_alert_changed', $user_id, $id, $alerts[ $id ] );
		return array( 'saved' => true, 'alert' => $alerts[ $id ], 'delivery_owner' => 'File 19', 'file26_sends_notifications' => false );
	}

	public function search_history( \WP_REST_Request $request ) {
		$user_id = get_current_user_id(); $method = strtoupper( $request->get_method() ); $opted_in = true === $this->future_strict_bool( get_user_meta( $user_id, self::META_HISTORY_OPT_IN, true ) ); $stored = get_user_meta( $user_id, self::META_HISTORY, true ); $history = is_array( $stored ) ? $stored : array();
		if ( 'GET' === $method ) { return array( 'policy' => 'local_first', 'server_sync_opt_in' => $opted_in, 'server_history' => $opted_in ? array_values( $history ) : array(), 'default_network_sync' => false ); }
		$params = $this->params( $request ); $lock = $this->acquire_future_history_lock( $user_id ); if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			// Refresh both values after taking the per-user mutation lock.
			$stored = get_user_meta( $user_id, self::META_HISTORY, true ); $history = is_array( $stored ) ? $stored : array();
			if ( 'DELETE' === $method ) {
				$disable_sync = false; if ( array_key_exists( 'disable_sync', $params ) ) { $parsed_disable = $this->future_strict_bool( $params['disable_sync'] ); if ( null === $parsed_disable ) { return new \WP_Error( 'file26_history_disable_sync_invalid', 'disable_sync must be an explicit boolean value.', array( 'status' => 400 ) ); } $disable_sync = $parsed_disable; }
				if ( $disable_sync ) {
					$had_opt_in = metadata_exists( 'user', $user_id, self::META_HISTORY_OPT_IN );
					if ( $had_opt_in ) { delete_user_meta( $user_id, self::META_HISTORY_OPT_IN ); if ( metadata_exists( 'user', $user_id, self::META_HISTORY_OPT_IN ) ) { return new \WP_Error( 'file26_history_opt_in_clear_failed', 'Server history opt-in could not be cleared.', array( 'status' => 500 ) ); } }
				}
				$had_history = metadata_exists( 'user', $user_id, self::META_HISTORY );
				if ( $had_history ) { delete_user_meta( $user_id, self::META_HISTORY ); if ( metadata_exists( 'user', $user_id, self::META_HISTORY ) ) { return new \WP_Error( 'file26_history_clear_failed', 'Server search history could not be cleared.', array( 'status' => 500 ) ); } }
				return array( 'cleared' => true, 'server_sync_opt_in' => true === $this->future_strict_bool( get_user_meta( $user_id, self::META_HISTORY_OPT_IN, true ) ) );
			}

			if ( true !== $this->future_strict_bool( isset( $params['sync_opt_in'] ) ? $params['sync_opt_in'] : null ) ) { return new \WP_Error( 'file26_history_explicit_opt_in_required', 'Server history sync requires explicit boolean opt-in.', array( 'status' => 400 ) ); }
			$opt_in_written = update_user_meta( $user_id, self::META_HISTORY_OPT_IN, 1 );
			if ( false === $opt_in_written && 1 !== (int) get_user_meta( $user_id, self::META_HISTORY_OPT_IN, true ) ) { return new \WP_Error( 'file26_history_opt_in_write_failed', 'Server history opt-in could not be stored.', array( 'status' => 500 ) ); }

			$q = $this->query( $params ); $sensitive = '' !== $q && $this->sensitive_query( $q );
			if ( '' !== $q && ! $sensitive ) {
				$query_hash = hash_hmac( 'sha256', $q, wp_salt( 'nonce' ) ); $deduped = array();
				foreach ( $history as $old ) { if ( is_array( $old ) && ! empty( $old['query_hash'] ) && $query_hash !== $old['query_hash'] ) { $deduped[] = $old; } }
				$deduped[] = array( 'query' => $q, 'query_hash' => $query_hash, 'searched_at' => gmdate( 'c' ) ); $history = array_slice( $deduped, -50 );
				$saved = $this->save_user_meta_cas( $user_id, self::META_HISTORY, $stored, $history ); if ( is_wp_error( $saved ) ) { return $saved; }
			}
			return array( 'saved' => '' !== $q && ! $sensitive, 'sensitive_query_not_synced' => $sensitive, 'server_sync_opt_in' => true, 'default_network_sync' => false );
		} finally { $this->release_future_history_lock( $lock ); }
	}

	private function future_strict_bool( $value ) {
		if ( is_bool( $value ) ) { return $value; }
		if ( is_int( $value ) || is_float( $value ) ) { if ( 1 === (int) $value ) { return true; } if ( 0 === (int) $value ) { return false; } return null; }
		if ( is_string( $value ) ) { $value = strtolower( trim( $value ) ); if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) { return true; } if ( in_array( $value, array( '0', 'false', 'no', 'off', '' ), true ) ) { return false; } }
		return null;
	}

	private function acquire_future_history_lock( $user_id ) {
		global $wpdb; $lock = 'file26:future-history:' . substr( hash( 'sha256', (string) (int) $user_id ), 0, 36 );
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ? $lock : new \WP_Error( 'file26_history_busy', 'Server search history is being updated; reload and retry.', array( 'status' => 409 ) );
	}

	private function release_future_history_lock( $lock ) { global $wpdb; if ( is_string( $lock ) && '' !== $lock ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); } }
}
