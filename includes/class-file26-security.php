<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Security {
	private $supported_membership_contracts = array( '1.0', '1.1', '1.1.2' );

	public function trace_id() {
		try { return bin2hex( random_bytes( 16 ) ); }
		catch ( \Exception $e ) { return substr( hash( 'sha256', uniqid( 'f26', true ) . wp_rand() ), 0, 32 ); }
	}

	public function audience() {
		$authenticated = is_user_logged_in();
		$current_user_id = get_current_user_id();
		$default = array(
			'contract_version' => null, 'user_id' => $current_user_id, 'authenticated' => $authenticated,
			'suspended' => false, 'is_minor' => false, 'guardian_verified' => false,
			'entitlements' => array(), 'consents' => array(),
			'roles' => $authenticated ? wp_get_current_user()->roles : array(), 'valid' => ! $authenticated,
		);
		$claims = apply_filters( 'sabri_file26_membership_assertions', $default, $current_user_id );
		if ( ! is_array( $claims ) ) { $claims = $default; $claims['valid'] = false; }
		$versions = apply_filters( 'sabri_file26_supported_membership_contracts', $this->supported_membership_contracts );
		$versions = is_array( $versions ) ? array_values( array_unique( array_map( 'strval', $versions ) ) ) : $this->supported_membership_contracts;
		$version = isset( $claims['contract_version'] ) ? (string) $claims['contract_version'] : '';
		$not_expired = true;
		if ( isset( $claims['expires_at'] ) && '' !== (string) $claims['expires_at'] ) {
			$expires = is_numeric( $claims['expires_at'] ) ? (int) $claims['expires_at'] : strtotime( (string) $claims['expires_at'] );
			$not_expired = $expires && $expires >= time();
		}
		if ( $authenticated ) {
			$claims['valid'] = $version && in_array( $version, $versions, true ) && empty( $claims['suspended'] ) && $not_expired;
		} else { $claims['valid'] = true; }
		$audience = array_merge( $default, $claims );
		// External assertions can enrich a request but cannot change its authenticated subject or WordPress roles.
		$audience['user_id'] = $current_user_id;
		$audience['authenticated'] = $authenticated;
		$audience['roles'] = $authenticated ? wp_get_current_user()->roles : array();
		$audience['suspended'] = ! empty( $audience['suspended'] );
		$audience['is_minor'] = ! empty( $audience['is_minor'] );
		$audience['guardian_verified'] = ! empty( $audience['guardian_verified'] );
		$audience['entitlements'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', is_array( $audience['entitlements'] ) ? $audience['entitlements'] : array() ) ) ) );
		$audience['consents'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', is_array( $audience['consents'] ) ? $audience['consents'] : array() ) ) ) );
		return $audience;
	}

	public function can_view_visibility( $visibility, array $audience, array $payload = array() ) {
		$visibility = (string) $visibility;
		if ( 'public' === $visibility ) { return true; }
		if ( empty( $audience['authenticated'] ) || empty( $audience['valid'] ) ) { return false; }
		if ( 'members' === $visibility ) { return true; }
		if ( 'entitled' === $visibility ) {
			$required = isset( $payload['required_entitlement'] ) ? sanitize_key( (string) $payload['required_entitlement'] ) : '';
			return $required && in_array( $required, (array) $audience['entitlements'], true );
		}
		if ( 'minor_guarded' === $visibility ) { return empty( $audience['is_minor'] ) || ! empty( $audience['guardian_verified'] ); }
		return false;
	}

	/** Public authenticated-subject gate shared by route permission callbacks. */
	public function valid_authenticated_member() { return $this->current_membership_valid(); }

	/** Configuration authority only; it is not an operational super-capability. */
	public function can_manage() { return $this->current_membership_valid() && current_user_can( 'manage_sabri_search' ); }
	public function can_operate() { return $this->current_membership_valid() && current_user_can( 'operate_sabri_search' ); }
	public function can_curate() { return $this->current_membership_valid() && current_user_can( 'curate_sabri_taxonomy' ); }
	public function can_approve_ranking() { return $this->current_membership_valid() && current_user_can( 'approve_sabri_ranking' ); }
	public function can_audit() { return $this->current_membership_valid() && current_user_can( 'audit_sabri_search' ); }

	private function current_membership_valid() {
		if ( ! is_user_logged_in() ) { return false; }
		$audience = $this->audience();
		return ! empty( $audience['authenticated'] ) && ! empty( $audience['valid'] ) && empty( $audience['suspended'] ) && (int) $audience['user_id'] === (int) get_current_user_id();
	}

	public function require_step_up( $purpose ) {
		if ( ! $this->current_membership_valid() ) { return false; }
		return (bool) apply_filters( 'sabri_file26_step_up_authorized', false, get_current_user_id(), sanitize_key( (string) $purpose ) );
	}

	public function sanitize_query( $query ) {
		$query = is_scalar( $query ) ? (string) $query : '';
		$query = wp_strip_all_tags( $query, true );
		$query = preg_replace( '/[\x00-\x1F\x7F]/u', ' ', $query );
		$query = preg_replace( '/\s+/u', ' ', trim( $query ) );
		$max = max( 20, min( 500, (int) DB::setting( 'max_query_length', 200 ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $query, 0, $max, 'UTF-8' ) : substr( $query, 0, $max );
	}

	public function contains_sensitive_query( $query ) {
		$query = (string) $query;
		$patterns = array(
			'/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', '/(?:\+?92|0)?3\d{9}/u', '/\b\d{5}-?\d{7}-?\d\b/u',
			'/\b(?:patient|medical\s*record|clinical\s*record|prescription|cnic|passport|otp|password|access\s*token)\b/iu',
			'/(?:مریض|طبی\s*ریکارڈ|نسخہ|شناختی|پاسپورٹ|فون\s*نمبر|خفیہ)/u',
		);
		foreach ( $patterns as $pattern ) { if ( preg_match( $pattern, $query ) ) { return true; } }
		return false;
	}

	public function safe_url( $url ) {
		$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );
		if ( ! $url ) { return ''; }
		$home = wp_parse_url( home_url( '/' ) ); $target = wp_parse_url( $url );
		if ( empty( $target['host'] ) ) { return esc_url_raw( home_url( '/' . ltrim( $url, '/' ) ), array( 'http', 'https' ) ); }
		$home_scheme = isset( $home['scheme'] ) ? strtolower( $home['scheme'] ) : '';
		$target_scheme = isset( $target['scheme'] ) ? strtolower( $target['scheme'] ) : '';
		$home_port = isset( $home['port'] ) ? (int) $home['port'] : ( 'https' === $home_scheme ? 443 : 80 );
		$target_port = isset( $target['port'] ) ? (int) $target['port'] : ( 'https' === $target_scheme ? 443 : 80 );
		if ( empty( $home['host'] ) || strtolower( $target['host'] ) !== strtolower( $home['host'] ) || $target_scheme !== $home_scheme || $target_port !== $home_port || isset( $target['user'] ) || isset( $target['pass'] ) ) { return ''; }
		return $url;
	}

	public function safe_resource_url( $url, $purpose = 'resource' ) {
		$url = trim( (string) $url ); if ( '' === $url ) { return ''; }
		$same_origin = $this->safe_url( $url ); if ( $same_origin ) { return $same_origin; }
		$url = esc_url_raw( $url, array( 'https' ) );
		$parts = $url ? wp_parse_url( $url ) : false;
		if ( ! $parts || empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) { return ''; }
		$host = strtolower( $parts['host'] );
		if ( 'localhost' === $host || substr( $host, -6 ) === '.local' || ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) ) { return ''; }
		return apply_filters( 'sabri_file26_allowed_external_resource_url', false, $url, sanitize_key( $purpose ), $host ) ? $url : '';
	}

	public function sign_cursor( array $payload ) {
		$now = time(); $ttl = max( 60, min( HOUR_IN_SECONDS, (int) apply_filters( 'sabri_file26_cursor_ttl', 900 ) ) );
		$payload['v'] = 1; $payload['iat'] = $now; $payload['exp'] = $now + $ttl;
		$json = wp_json_encode( $payload );
		if ( false === $json || strlen( $json ) > 2048 ) { return ''; }
		$body = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
		$sig = hash_hmac( 'sha256', $body, wp_salt( 'auth' ) );
		return $body . '.' . $sig;
	}

	public function verify_cursor( $cursor ) {
		if ( ! is_string( $cursor ) || strlen( $cursor ) > 4096 || 1 !== substr_count( $cursor, '.' ) ) { return false; }
		list( $body, $sig ) = explode( '.', $cursor, 2 );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $sig ) ) { return false; }
		$expected = hash_hmac( 'sha256', $body, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, $sig ) ) { return false; }
		$encoded = strtr( $body, '-_', '+/' ); $encoded .= str_repeat( '=', ( 4 - strlen( $encoded ) % 4 ) % 4 );
		$decoded = base64_decode( $encoded, true ); if ( false === $decoded || strlen( $decoded ) > 2048 ) { return false; }
		$payload = json_decode( $decoded, true );
		return is_array( $payload ) && isset( $payload['v'], $payload['iat'], $payload['exp'] ) && 1 === (int) $payload['v'] && (int) $payload['iat'] <= time() + 30 && (int) $payload['exp'] >= time() && (int) $payload['exp'] <= (int) $payload['iat'] + HOUR_IN_SECONDS ? $payload : false;
	}

	public function rate_limit( $bucket, $limit, $window_seconds ) {
		global $wpdb;
		$bucket = hash( 'sha256', (string) $bucket ); $limit = max( 1, (int) $limit ); $window_seconds = max( 10, (int) $window_seconds );
		$window = (int) floor( time() / $window_seconds ); $expires = gmdate( 'Y-m-d H:i:s', ( $window + 2 ) * $window_seconds ); $table = DB::table( 'rate_limits' );
		$sql = $wpdb->prepare( "INSERT INTO $table (bucket_key,window_start,count_value,expires_at) VALUES (%s,%d,1,%s) ON DUPLICATE KEY UPDATE count_value = count_value + 1, expires_at = VALUES(expires_at)", $bucket, $window, $expires );
		$written = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $written ) { return false; }
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT count_value FROM $table WHERE bucket_key=%s AND window_start=%d", $bucket, $window ) );
		if ( null === $count || ! empty( $wpdb->last_error ) ) { return false; }
		return (int) $count <= $limit;
	}

	public function client_bucket() {
		$user = get_current_user_id(); if ( $user ) { return 'u:' . $user; }
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		return 'g:' . hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	}

	public function audit( $action, array $context = array() ) {
		global $wpdb;
		$metadata = isset( $context['metadata'] ) && is_array( $context['metadata'] ) ? $this->sanitize_audit_metadata( $context['metadata'] ) : array();
		$trace = isset( $context['trace_id'] ) && preg_match( '/^[a-f0-9]{32}$/', (string) $context['trace_id'] ) ? (string) $context['trace_id'] : $this->trace_id();
		$inserted = $wpdb->insert(
			DB::table( 'audit' ),
			array(
				'action_name' => substr( sanitize_key( $action ), 0, 96 ), 'actor_id' => get_current_user_id() ?: null,
				'object_type' => isset( $context['object_type'] ) ? substr( sanitize_key( $context['object_type'] ), 0, 64 ) : null,
				'object_key' => isset( $context['object_key'] ) ? substr( sanitize_text_field( $context['object_key'] ), 0, 191 ) : null,
				'reason_code' => isset( $context['reason'] ) ? substr( sanitize_key( $context['reason'] ), 0, 96 ) : null,
				'trace_id' => $trace, 'metadata' => wp_json_encode( $metadata ), 'created_at' => DB::now(),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			update_option( 'sabri_file26_last_audit_failure', array( 'at' => DB::now(), 'action' => substr( sanitize_key( $action ), 0, 96 ) ), false );
			return new \WP_Error( 'file26_audit_write_failed', 'Required File 26 audit evidence could not be persisted.' );
		}
		delete_option( 'sabri_file26_last_audit_failure' );
		return true;
	}

	private function sanitize_audit_metadata( array $metadata, $depth = 0 ) {
		if ( $depth > 3 ) { return array(); }
		$clean = array();
		foreach ( array_slice( $metadata, 0, 100, true ) as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( ! $key || preg_match( '/(?:query|password|token|secret|otp|cnic|passport|message|patient|identity|clinical|medical|credential|cookie|authorization)/', $key ) ) { continue; }
			if ( is_array( $value ) ) { $clean[ $key ] = $this->sanitize_audit_metadata( $value, $depth + 1 ); }
			elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) { $clean[ $key ] = $value; }
			elseif ( is_scalar( $value ) ) { $text = sanitize_text_field( (string) $value ); $clean[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 512, 'UTF-8' ) : substr( $text, 0, 512 ); }
		}
		return $clean;
	}
}
