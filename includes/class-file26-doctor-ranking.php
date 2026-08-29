<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

/** Explainable global verified-doctor ranking projection. */
final class Doctor_Ranking {
	private $security;
	public function __construct( Security $security ) { $this->security = $security; }

	public function recompute( $reason = 'scheduled_monthly' ) {
		global $wpdb;
		$scheduled = 'scheduled_monthly' === $reason;
		if ( $scheduled ) {
			if ( ! function_exists( 'wp_doing_cron' ) || ! wp_doing_cron() ) { return new \WP_Error( 'file26_cron_context_required', 'Scheduled ranking recompute requires the WordPress cron context.', array( 'status' => 403 ) ); }
		} elseif ( ! $this->security->can_approve_ranking() ) { return new \WP_Error( 'file26_forbidden', 'Ranking approval capability is required.', array( 'status' => 403 ) ); }
		$lock_name = 'file26:doctor-ranking';
		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
		if ( '1' !== (string) $locked ) { return new \WP_Error( 'file26_ranking_busy', 'Doctor ranking recompute is already running.', array( 'status' => 409 ) ); }
		try {
			$policy = $this->policy();
			$rows = $this->eligible_rows();
			if ( is_wp_error( $rows ) ) { return $rows; }
			$scored = array();
			foreach ( $rows as $row ) {
				$payload = json_decode( $row['payload'], true );
				if ( ! is_array( $payload ) || empty( $payload['verified_doctor'] ) ) { continue; }
				$scored[] = array( 'key' => $row['canonical_key'], 'payload' => $payload, 'score' => $this->score( $payload, $policy['weights'] ) );
			}
			$this->sort_scored( $scored );
			$table = DB::table( 'documents' );
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return new \WP_Error( 'file26_doctor_ranking_transaction_unavailable', 'Doctor ranking transaction could not be started safely.', array( 'status' => 500 ) ); }
			try {
				// First remove stale ranking metadata from the entire currently visible File 07 doctor projection cohort.
				foreach ( $rows as $row ) {
					$payload = json_decode( $row['payload'], true );
					if ( ! is_array( $payload ) ) { $payload = array(); }
					$had_rank = array_key_exists( 'global_doctor_rank', $payload ) || array_key_exists( 'doctor_rank_score', $payload ) || array_key_exists( 'doctor_rank_policy_version', $payload );
					if ( ! $had_rank ) { continue; }
					unset( $payload['global_doctor_rank'], $payload['doctor_rank_score'], $payload['doctor_rank_policy_version'] );
					$cleared = $wpdb->update( $table, array( 'payload' => wp_json_encode( $payload ), 'updated_at' => DB::now() ), array( 'canonical_key' => $row['canonical_key'] ), array( '%s', '%s' ), array( '%s' ) );
					if ( false === $cleared ) { throw new \RuntimeException( 'Stale doctor rank cleanup failed.' ); }
				}
				$rank = 0;
				foreach ( $scored as $item ) {
					$rank++; $payload = $item['payload']; $payload['global_doctor_rank'] = $rank; $payload['doctor_rank_score'] = round( $item['score'], 6 ); $payload['doctor_rank_policy_version'] = $policy['version'];
					$updated = $wpdb->update( $table, array( 'payload' => wp_json_encode( $payload ), 'updated_at' => DB::now() ), array( 'canonical_key' => $item['key'] ), array( '%s', '%s' ), array( '%s' ) );
					if ( false === $updated ) { throw new \RuntimeException( 'Doctor rank projection write failed.' ); }
				}
				$settings_result = DB::update_settings( array( 'doctor_ranking_last_run' => DB::now(), 'doctor_ranking_policy_version' => $policy['version'] ) );
				if ( false === $settings_result || is_wp_error( $settings_result ) ) { throw new \RuntimeException( 'Doctor ranking settings write failed.' ); }
				if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'Doctor ranking transaction commit failed.' ); }
			} catch ( \Throwable $e ) {
				$wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'file26_doctor_ranking_write_failed', 'Doctor ranking recompute failed atomically.', array( 'status' => 500 ) );
			}
			$this->security->audit( 'doctor_ranking_recomputed', array( 'object_type' => 'ranking_policy', 'object_key' => $policy['version'], 'reason' => sanitize_text_field( $reason ), 'metadata' => array( 'eligible_doctors' => count( $scored ), 'safe_fallback' => ! empty( $policy['safe_fallback'] ) ) ) );
			do_action( 'sabri_file26_event', 'DoctorRankingRecomputed', array( 'policy_version' => $policy['version'], 'eligible_doctors' => count( $scored ) ) );
			return array( 'policy_version' => $policy['version'], 'eligible_doctors' => count( $scored ), 'safe_fallback' => ! empty( $policy['safe_fallback'] ) );
		} finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); }
	}

	public function directory( array $request = array() ) {
		$context = isset( $request['context'] ) ? sanitize_key( $request['context'] ) : 'global'; $value = isset( $request['value'] ) ? substr( sanitize_text_field( $request['value'] ), 0, 191 ) : ''; $tier = isset( $request['tier'] ) ? sanitize_key( $request['tier'] ) : 'all_verified';
		$allowed_contexts = array( 'global', 'country', 'city', 'language', 'specialization', 'educator', 'researcher' ); $allowed_tiers = array( 'top_10', 'top_100', 'top_1000', 'all_verified' );
		if ( ! in_array( $context, $allowed_contexts, true ) || ! in_array( $tier, $allowed_tiers, true ) ) { return new \WP_Error( 'file26_invalid_doctor_ranking_view', 'Invalid doctor-ranking context or tier.', array( 'status' => 400 ) ); }
		if ( in_array( $context, array( 'country', 'city', 'language', 'specialization' ), true ) && '' === $value ) { return new \WP_Error( 'file26_ranking_context_value_required', 'This contextual ranking requires a value.', array( 'status' => 400 ) ); }
		$limit = isset( $request['limit'] ) ? max( 1, min( 100, (int) $request['limit'] ) ) : 20; $policy = $this->policy();
		$cursor_context = hash( 'sha256', wp_json_encode( array( 'context' => $context, 'value' => $value, 'tier' => $tier, 'limit' => $limit, 'policy' => $policy['version'] ) ) ); $offset = 0;
		if ( ! empty( $request['cursor'] ) ) { $cursor = $this->security->verify_cursor( $request['cursor'] ); if ( ! $cursor || empty( $cursor['h'] ) || empty( $cursor['p'] ) || $cursor['p'] !== $policy['version'] || ! hash_equals( $cursor_context, (string) $cursor['h'] ) ) { return new \WP_Error( 'file26_invalid_cursor', 'The doctor-ranking cursor is invalid or expired.', array( 'status' => 400 ) ); } $offset = max( 0, min( 100000, (int) $cursor['o'] ) ); }
		$rows = $this->eligible_rows();
		if ( is_wp_error( $rows ) ) { return $rows; }
		$scored = array();
		foreach ( $rows as $row ) { $payload = json_decode( $row['payload'], true ); if ( ! is_array( $payload ) || empty( $payload['verified_doctor'] ) || ! $this->matches_context( $row, $payload, $context, $value ) ) { continue; } $global_rank = isset( $payload['global_doctor_rank'] ) ? max( 0, (int) $payload['global_doctor_rank'] ) : 0; if ( ! $this->matches_tier( $global_rank, $tier ) ) { continue; } $scored[] = array( 'key' => $row['canonical_key'], 'title' => $row['title'], 'url' => $row['canonical_url'], 'country' => $row['country'], 'location' => $row['location'], 'locale' => $row['locale'], 'payload' => $payload, 'score' => $this->score( $payload, $policy['weights'] ), 'global_rank' => $global_rank ); }
		$this->sort_scored( $scored ); $context_rank = 0;
		foreach ( $scored as &$item ) { $context_rank++; $item['context_rank'] = $context_rank; $item['global_tier'] = $this->tier_for_rank( $item['global_rank'] ); $item['explanation'] = $this->explain( $item['payload'], $policy ); unset( $item['payload'] ); } unset( $item );
		$total = count( $scored ); $page = array_slice( $scored, $offset, $limit ); $has_more = $total > $offset + $limit;
		return array( 'contract_version' => SABRI_FILE26_CONTRACT_VERSION, 'policy_version' => $policy['version'], 'policy_safe_fallback' => ! empty( $policy['safe_fallback'] ), 'context' => $context, 'context_value' => $value, 'tier' => $tier, 'total_eligible' => $total, 'results' => $page, 'next_cursor' => $has_more ? $this->security->sign_cursor( array( 'o' => $offset + $limit, 'p' => $policy['version'], 'h' => $cursor_context ) ) : null, 'global_tiers_preserved' => true );
	}

	public function score( array $payload, array $weights = array() ) { $weights = $weights ? $weights : $this->policy()['weights']; $score = 0.0; foreach ( $weights as $field => $weight ) { $value = isset( $payload[ $field ] ) ? min( 1.0, max( 0.0, (float) $payload[ $field ] ) ) : 0.0; $score += $value * max( 0.0, (float) $weight ); } return round( $score * 100, 6 ); }

	public function policy() {
		global $wpdb;
		$defaults = array( 'version' => (string) DB::setting( 'doctor_ranking_policy_version', 'doctor-global-1.0' ), 'safe_fallback' => false, 'weights' => array( 'qualification_score' => 0.16, 'experience_score' => 0.12, 'patient_verified_review_score' => 0.17, 'ethical_conduct_score' => 0.15, 'knowledge_contribution_score' => 0.14, 'responsiveness_score' => 0.08, 'profile_completeness_score' => 0.06, 'complaint_appeal_outcome_score' => 0.07, 'manipulation_resistant_engagement_score' => 0.05 ) );
		$row = $wpdb->get_row( "SELECT version,features_json FROM " . DB::table( 'ranking_policies' ) . " WHERE context_name='doctor_global' AND audience='public' AND status='active' ORDER BY effective_at DESC,id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $row ) { return $defaults; }
		$features = json_decode( $row['features_json'], true ); $features = is_array( $features ) ? $features : array(); $weights = isset( $features['weights'] ) && is_array( $features['weights'] ) ? $features['weights'] : $features; $candidate = $defaults['weights'];
		foreach ( $candidate as $field => $fallback ) { if ( isset( $weights[ $field ] ) && is_numeric( $weights[ $field ] ) ) { $candidate[ $field ] = min( 1.0, max( 0.0, (float) $weights[ $field ] ) ); } }
		$total = array_sum( $candidate ); if ( $total <= 0 ) { $defaults['safe_fallback'] = true; $defaults['version'] = 'safe-fallback-' . sanitize_key( $row['version'] ); return $defaults; }
		foreach ( $candidate as &$weight ) { $weight = $weight / $total; } unset( $weight ); $defaults['weights'] = $candidate; $defaults['version'] = sanitize_text_field( $row['version'] ); return $defaults;
	}

	private function eligible_rows() {
		global $wpdb;
		$documents = DB::table( 'documents' );
		$connectors = DB::table( 'connectors' );
		$rows = $wpdb->get_results(
			"SELECT d.canonical_key,d.title,d.canonical_url,d.country,d.location,d.locale,d.topic_ids,d.payload FROM $documents d INNER JOIN $connectors c ON c.slug=d.connector_slug AND c.status='active' AND c.owner_file='File 07' WHERE d.entity_type='doctor_directory_projection' AND d.state IN ('published','active','corrected') AND d.visibility='public' ORDER BY d.canonical_key",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( null === $rows ) { return new \WP_Error( 'file26_doctor_ranking_read_failed', 'Doctor ranking source projections could not be read safely.', array( 'status' => 500 ) ); }
		return $rows;
	}
	private function matches_context( array $row, array $payload, $context, $value ) { if ( 'global' === $context ) { return true; } $topics = json_decode( isset( $row['topic_ids'] ) ? $row['topic_ids'] : '', true ); $topics = is_array( $topics ) ? array_map( 'sanitize_key', $topics ) : array(); if ( 'educator' === $context ) { return in_array( 'educator', $topics, true ) || in_array( 'teacher', $topics, true ) || ( isset( $payload['knowledge_contribution_score'] ) && (float) $payload['knowledge_contribution_score'] >= 0.6 ); } if ( 'researcher' === $context ) { return in_array( 'researcher', $topics, true ) || in_array( 'research', $topics, true ) || ( isset( $payload['knowledge_contribution_score'] ) && (float) $payload['knowledge_contribution_score'] >= 0.75 ); } $needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $value ), 'UTF-8' ) : strtolower( trim( $value ) ); if ( 'country' === $context ) { $candidate = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $row['country'] ), 'UTF-8' ) : strtolower( trim( (string) $row['country'] ) ); return $candidate === $needle; } if ( 'city' === $context ) { $candidate = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $row['location'] ), 'UTF-8' ) : strtolower( trim( (string) $row['location'] ) ); return $candidate === $needle; } if ( 'language' === $context ) { $language = isset( $payload['language'] ) ? (string) $payload['language'] : (string) $row['locale']; $candidate = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $language ), 'UTF-8' ) : strtolower( trim( $language ) ); return $candidate === $needle || 0 === strpos( $candidate, $needle . '-' ); } if ( 'specialization' === $context ) { return in_array( sanitize_key( $value ), $topics, true ); } return false; }
	private function matches_tier( $rank, $tier ) { if ( $rank <= 0 ) { return 'all_verified' === $tier; } if ( 'top_10' === $tier ) { return $rank <= 10; } if ( 'top_100' === $tier ) { return $rank <= 100; } if ( 'top_1000' === $tier ) { return $rank <= 1000; } return true; }
	private function tier_for_rank( $rank ) { if ( $rank > 0 && $rank <= 10 ) { return array( 'key' => 'top_10', 'label' => __( 'Top 10 Verified Doctors', 'sabri-file26' ) ); } if ( $rank > 0 && $rank <= 100 ) { return array( 'key' => 'top_100', 'label' => __( 'Top 100 Verified Doctors', 'sabri-file26' ) ); } if ( $rank > 0 && $rank <= 1000 ) { return array( 'key' => 'top_1000', 'label' => __( 'Top 1000 Verified Doctors', 'sabri-file26' ) ); } return array( 'key' => 'all_verified', 'label' => __( 'All Verified Doctors', 'sabri-file26' ) ); }
	private function explain( array $payload, array $policy ) { $reasons = array(); $labels = array( 'qualification_score' => __( 'Verified qualifications', 'sabri-file26' ), 'experience_score' => __( 'Professional experience', 'sabri-file26' ), 'patient_verified_review_score' => __( 'Patient-verified reviews', 'sabri-file26' ), 'ethical_conduct_score' => __( 'Ethical-conduct record', 'sabri-file26' ), 'knowledge_contribution_score' => __( 'Knowledge contribution', 'sabri-file26' ), 'responsiveness_score' => __( 'Responsiveness', 'sabri-file26' ), 'profile_completeness_score' => __( 'Profile completeness', 'sabri-file26' ), 'complaint_appeal_outcome_score' => __( 'Complaint and appeal outcomes', 'sabri-file26' ), 'manipulation_resistant_engagement_score' => __( 'Manipulation-resistant engagement', 'sabri-file26' ) ); $components = array(); foreach ( $policy['weights'] as $field => $weight ) { $value = isset( $payload[ $field ] ) ? min( 1.0, max( 0.0, (float) $payload[ $field ] ) ) : 0.0; $components[ $field ] = $value * $weight; } arsort( $components ); foreach ( array_slice( $components, 0, 4, true ) as $field => $component ) { if ( $component > 0 && isset( $labels[ $field ] ) ) { $reasons[] = $labels[ $field ]; } } $reasons[] = __( 'Donation, payment, paid promotion, follower count and Founder favoritism are excluded', 'sabri-file26' ); return $reasons; }
	private function sort_scored( array &$scored ) { usort( $scored, static function ( $a, $b ) { if ( (float) $a['score'] === (float) $b['score'] ) { return strcmp( $a['key'], $b['key'] ); } return (float) $a['score'] > (float) $b['score'] ? -1 : 1; } ); }
}
