<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

/** Native appeal workflow for File 26 doctor-ranking projections. */
final class Doctor_Appeals {
	const OPTION_SCHEMA = 'sabri_file26_doctor_appeals_schema';
	const SCHEMA_VERSION = '1.0.0';
	private $security;
	public function __construct( Security $security ) { $this->security = $security; }
	public static function table() { global $wpdb; return $wpdb->prefix . 'f26_ranking_appeals'; }

	public static function install_schema() {
		global $wpdb;
		$table = self::table();
		$table_present = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( self::SCHEMA_VERSION === get_option( self::OPTION_SCHEMA ) && $table_present ) { return; }
		require_once ABSPATH . 'wp-admin/includes/upgrade.php'; $charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE $table (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			appeal_uuid char(36) NOT NULL,
			doctor_key char(64) NOT NULL,
			appellant_user_id bigint unsigned NOT NULL,
			reason_text text NOT NULL,
			evidence_json longtext NULL,
			status varchar(32) NOT NULL DEFAULT 'submitted',
			reviewer_id bigint unsigned NULL,
			decision_reason text NULL,
			policy_version varchar(64) NOT NULL,
			rank_snapshot bigint unsigned NULL,
			version bigint unsigned NOT NULL DEFAULT 1,
			submitted_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			decided_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY appeal_uuid (appeal_uuid),
			KEY doctor_status (doctor_key,status),
			KEY appellant_status (appellant_user_id,status),
			KEY submitted_at (submitted_at)
		) $charset;" );
		update_option( self::OPTION_SCHEMA, self::SCHEMA_VERSION, false );
	}

	public function submit( $doctor_key, $reason, array $evidence = array() ) {
		global $wpdb;
		$user_id = get_current_user_id(); $audience = $this->security->audience();
		if ( ! $user_id || empty( $audience['valid'] ) || ! empty( $audience['suspended'] ) ) { return new \WP_Error( 'file26_auth_required', 'A valid authenticated membership is required.', array( 'status' => 401 ) ); }
		if ( ! $this->security->rate_limit( 'doctor-appeal|u:' . $user_id, 5, DAY_IN_SECONDS ) ) { return new \WP_Error( 'file26_appeal_rate_limited', 'Too many ranking appeals were submitted.', array( 'status' => 429 ) ); }
		$doctor_key = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $doctor_key ) );
		$reason = trim( wp_strip_all_tags( (string) $reason, true ) );
		$reason_length = function_exists( 'mb_strlen' ) ? mb_strlen( $reason, 'UTF-8' ) : strlen( $reason );
		if ( 64 !== strlen( $doctor_key ) || $reason_length < 20 || $reason_length > 4000 ) { return new \WP_Error( 'file26_invalid_appeal', 'A valid doctor reference and a reason between 20 and 4000 characters are required.', array( 'status' => 400 ) ); }
		$document = $wpdb->get_row( $wpdb->prepare( 'SELECT canonical_key,author_key,payload FROM ' . DB::table( 'documents' ) . " WHERE canonical_key=%s AND entity_type='doctor' AND state IN ('published','active','corrected') AND visibility='public' LIMIT 1", $doctor_key ), ARRAY_A );
		if ( ! $document ) { return new \WP_Error( 'file26_doctor_not_found', 'The eligible doctor ranking record was not found.', array( 'status' => 404 ) ); }
		$payload = json_decode( $document['payload'], true ); $payload = is_array( $payload ) ? $payload : array();
		if ( empty( $payload['verified_doctor'] ) ) { return new \WP_Error( 'file26_doctor_not_eligible', 'Only a verified-doctor ranking record may be appealed.', array( 'status' => 409 ) ); }
		$owner_aliases = array( (string) $user_id, 'u:' . $user_id, 'user:' . $user_id, 'wp:' . $user_id );
		$owns_profile = in_array( (string) $document['author_key'], $owner_aliases, true );
		$allowed = apply_filters( 'sabri_file26_can_appeal_doctor_ranking', $owns_profile, $user_id, $doctor_key, array( 'author_key' => $document['author_key'], 'policy_version' => isset( $payload['doctor_rank_policy_version'] ) ? $payload['doctor_rank_policy_version'] : '' ) );
		if ( ! $allowed ) { return new \WP_Error( 'file26_appeal_forbidden', 'Only the affected verified doctor or an authorized representative may appeal.', array( 'status' => 403 ) ); }
		$clean_evidence = array();
		foreach ( array_slice( $evidence, 0, 20 ) as $item ) { $item = is_scalar( $item ) ? trim( sanitize_text_field( (string) $item ) ) : ''; if ( '' !== $item ) { $clean_evidence[] = function_exists( 'mb_substr' ) ? mb_substr( $item, 0, 500, 'UTF-8' ) : substr( $item, 0, 500 ); } }
		$lock_name = 'file26:appeal:' . substr( $doctor_key, 0, 48 );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) ) ) { return new \WP_Error( 'file26_appeal_busy', 'This ranking appeal is being updated; retry safely.', array( 'status' => 409 ) ); }
		try {
			$open = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::table() . " WHERE doctor_key=%s AND status IN ('submitted','under_review','changes_requested') LIMIT 1", $doctor_key ) );
			if ( $open ) { return new \WP_Error( 'file26_appeal_already_open', 'An open appeal already exists for this doctor ranking.', array( 'status' => 409 ) ); }
			$uuid = DB::uuid();
			$policy = isset( $payload['doctor_rank_policy_version'] ) ? sanitize_text_field( $payload['doctor_rank_policy_version'] ) : (string) DB::setting( 'doctor_ranking_policy_version', 'doctor-global-1.0' );
			$ok = $wpdb->insert( self::table(), array( 'appeal_uuid' => $uuid, 'doctor_key' => $doctor_key, 'appellant_user_id' => $user_id, 'reason_text' => $reason, 'evidence_json' => wp_json_encode( $clean_evidence ), 'status' => 'submitted', 'policy_version' => $policy, 'rank_snapshot' => isset( $payload['global_doctor_rank'] ) ? max( 0, (int) $payload['global_doctor_rank'] ) : null, 'version' => 1, 'submitted_at' => DB::now(), 'updated_at' => DB::now() ) );
			if ( ! $ok ) { return new \WP_Error( 'file26_appeal_create_failed', 'The ranking appeal could not be created.', array( 'status' => 500 ) ); }
			$this->security->audit( 'doctor_ranking_appeal_submitted', array( 'object_type' => 'ranking_appeal', 'object_key' => $uuid, 'metadata' => array( 'doctor_key' => $doctor_key, 'policy_version' => $policy ) ) );
			do_action( 'sabri_file26_event', 'DoctorRankingAppealSubmitted', array( 'appeal_uuid' => $uuid, 'doctor_key' => $doctor_key ) );
			return array( 'appeal_uuid' => $uuid, 'status' => 'submitted', 'version' => 1 );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	public function review( $appeal_uuid, $decision, $reason, $expected_version ) {
		global $wpdb;
		if ( ! $this->security->can_approve_ranking() || ! $this->security->require_step_up( 'doctor_ranking_appeal_review' ) ) { return new \WP_Error( 'file26_forbidden', 'Ranking-approver capability and fresh authorization are required.', array( 'status' => 403 ) ); }
		$appeal_uuid = sanitize_text_field( $appeal_uuid ); $decision = sanitize_key( $decision ); $reason = trim( wp_strip_all_tags( (string) $reason, true ) ); $reason_length = function_exists( 'mb_strlen' ) ? mb_strlen( $reason, 'UTF-8' ) : strlen( $reason );
		$allowed = array( 'under_review', 'changes_requested', 'upheld', 'corrected', 'rejected' );
		if ( ! in_array( $decision, $allowed, true ) || $reason_length < 10 || $reason_length > 4000 || (int) $expected_version < 1 ) { return new \WP_Error( 'file26_invalid_appeal_decision', 'A valid decision, reason and expected version are required.', array( 'status' => 400 ) ); }
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE appeal_uuid=%s', $appeal_uuid ), ARRAY_A );
		if ( ! $row || in_array( $row['status'], array( 'upheld', 'corrected', 'rejected', 'withdrawn' ), true ) ) { return new \WP_Error( 'file26_appeal_not_reviewable', 'The appeal is missing or already final.', array( 'status' => 409 ) ); }
		if ( (int) $row['appellant_user_id'] === get_current_user_id() ) { return new \WP_Error( 'file26_appeal_conflict', 'An appellant cannot review the same appeal.', array( 'status' => 403 ) ); }
		$version = (int) $expected_version;
		if ( $version !== (int) $row['version'] ) { return new \WP_Error( 'file26_appeal_conflict', 'The appeal changed concurrently. Reload and retry.', array( 'status' => 409 ) ); }
		$final = in_array( $decision, array( 'upheld', 'corrected', 'rejected' ), true );
		$updated = $wpdb->update( self::table(), array( 'status' => $decision, 'reviewer_id' => get_current_user_id(), 'decision_reason' => $reason, 'version' => $version + 1, 'updated_at' => DB::now(), 'decided_at' => $final ? DB::now() : null ), array( 'appeal_uuid' => $appeal_uuid, 'version' => $version ), array( '%s', '%d', '%s', '%d', '%s', '%s' ), array( '%s', '%d' ) );
		if ( 1 !== $updated ) { return new \WP_Error( 'file26_appeal_conflict', 'The appeal changed concurrently. Reload and retry.', array( 'status' => 409 ) ); }
		$this->security->audit( 'doctor_ranking_appeal_' . $decision, array( 'object_type' => 'ranking_appeal', 'object_key' => $appeal_uuid, 'reason' => $reason, 'metadata' => array( 'doctor_key' => $row['doctor_key'], 'version' => $version + 1 ) ) );
		if ( 'corrected' === $decision ) { do_action( 'sabri_file26_doctor_ranking_recompute_requested', $row['doctor_key'], $appeal_uuid ); }
		do_action( 'sabri_file26_event', 'DoctorRankingAppealReviewed', array( 'appeal_uuid' => $appeal_uuid, 'decision' => $decision ) );
		return array( 'appeal_uuid' => $appeal_uuid, 'status' => $decision, 'version' => $version + 1 );
	}

	public function own() {
		global $wpdb; $user_id = get_current_user_id(); $audience = $this->security->audience();
		if ( ! $user_id || empty( $audience['valid'] ) || ! empty( $audience['suspended'] ) ) { return new \WP_Error( 'file26_auth_required', 'A valid authenticated membership is required.', array( 'status' => 401 ) ); }
		return $wpdb->get_results( $wpdb->prepare( 'SELECT appeal_uuid,doctor_key,status,policy_version,rank_snapshot,version,submitted_at,updated_at,decided_at,decision_reason FROM ' . self::table() . ' WHERE appellant_user_id=%d ORDER BY id DESC LIMIT 100', $user_id ), ARRAY_A );
	}
}
