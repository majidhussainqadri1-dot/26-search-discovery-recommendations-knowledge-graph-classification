<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/**
 * Explainable global verified-doctor ranking projection.
 * File 09/profile owners provide verified eligibility and normalized factors;
 * File 26 orchestrates a manipulation-resistant rank without changing identity truth.
 */
final class Doctor_Ranking {
	private $security;

	public function __construct( Security $security ) { $this->security = $security; }

	public function recompute( $reason = 'scheduled_monthly' ) {
		global $wpdb;
		if ( 'scheduled_monthly' !== $reason && ! $this->security->can_approve_ranking() ) {
			return new \WP_Error( 'file26_forbidden', 'Ranking approval capability is required.', array( 'status' => 403 ) );
		}
		$table = DB::table( 'documents' );
		$rows = $wpdb->get_results( "SELECT canonical_key,payload FROM $table WHERE entity_type='doctor' AND state IN ('published','active','corrected') AND visibility='public' ORDER BY canonical_key", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$scored = array();
		foreach ( $rows as $row ) {
			$payload = json_decode( $row['payload'], true );
			if ( ! is_array( $payload ) || empty( $payload['verified_doctor'] ) ) { continue; }
			$scored[] = array( 'key' => $row['canonical_key'], 'payload' => $payload, 'score' => $this->score( $payload ) );
		}
		usort( $scored, static function ( $a, $b ) {
			if ( $a['score'] === $b['score'] ) { return strcmp( $a['key'], $b['key'] ); }
			return $a['score'] > $b['score'] ? -1 : 1;
		} );
		$policy = 'doctor-global-1.0';
		$rank = 0;
		foreach ( $scored as $item ) {
			$rank++;
			$payload = $item['payload'];
			$payload['global_doctor_rank'] = $rank;
			$payload['doctor_rank_score'] = round( $item['score'], 6 );
			$payload['doctor_rank_policy_version'] = $policy;
			$wpdb->update( $table, array( 'payload' => wp_json_encode( $payload ), 'updated_at' => DB::now() ), array( 'canonical_key' => $item['key'] ), array( '%s', '%s' ), array( '%s' ) );
		}
		DB::update_settings( array( 'doctor_ranking_last_run' => DB::now(), 'doctor_ranking_policy_version' => $policy ) );
		$this->security->audit( 'doctor_ranking_recomputed', array( 'object_type' => 'ranking_policy', 'object_key' => $policy, 'reason' => sanitize_text_field( $reason ), 'metadata' => array( 'eligible_doctors' => count( $scored ) ) ) );
		do_action( 'sabri_file26_event', 'DoctorRankingRecomputed', array( 'policy_version' => $policy, 'eligible_doctors' => count( $scored ) ) );
		return array( 'policy_version' => $policy, 'eligible_doctors' => count( $scored ) );
	}

	public function score( array $payload ) {
		$weights = array(
			'qualification_score' => 0.16,
			'experience_score' => 0.12,
			'patient_verified_review_score' => 0.17,
			'ethical_conduct_score' => 0.15,
			'knowledge_contribution_score' => 0.14,
			'responsiveness_score' => 0.08,
			'profile_completeness_score' => 0.06,
			'complaint_appeal_outcome_score' => 0.07,
			'manipulation_resistant_engagement_score' => 0.05,
		);
		$score = 0.0;
		foreach ( $weights as $field => $weight ) {
			$value = isset( $payload[ $field ] ) ? min( 1.0, max( 0.0, (float) $payload[ $field ] ) ) : 0.0;
			$score += $value * $weight;
		}
		// Donation, payment, promotion and Founder preference are deliberately absent.
		return $score * 100;
	}
}
