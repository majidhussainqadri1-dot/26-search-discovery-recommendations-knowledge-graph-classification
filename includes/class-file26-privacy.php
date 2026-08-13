<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Privacy {
	public function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-file26'] = array(
			'exporter_friendly_name' => __( 'Sabri Search, Recommendations and Ranking Appeals', 'sabri-file26' ),
			'callback' => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-file26'] = array(
			'eraser_friendly_name' => __( 'Sabri Search, Recommendations and Ranking Appeals', 'sabri-file26' ),
			'callback' => array( $this, 'erase' ),
		);
		return $erasers;
	}

	public function export( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) {
			return array( 'data' => array(), 'done' => true );
		}
		$profile = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . DB::table( 'profiles' ) . ' WHERE user_id=%d', $user->ID ),
			ARRAY_A
		);
		$feedback = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT item_key,feedback_type,scope_key,active,created_at,updated_at FROM ' . DB::table( 'feedback' ) . ' WHERE user_id=%d ORDER BY id ASC LIMIT 1000',
				$user->ID
			),
			ARRAY_A
		);
		$appeals = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT appeal_uuid,doctor_key,reason_text,evidence_json,status,decision_reason,policy_version,rank_snapshot,submitted_at,updated_at,decided_at FROM ' . Doctor_Appeals::table() . ' WHERE appellant_user_id=%d ORDER BY id ASC LIMIT 1000',
				$user->ID
			),
			ARRAY_A
		);
		$data = array();
		if ( $profile ) {
			$data[] = array(
				'group_id' => 'sabri-file26-controls',
				'group_label' => __( 'Search and Recommendation Controls', 'sabri-file26' ),
				'item_id' => 'profile-' . $user->ID,
				'data' => array(
					array( 'name' => __( 'Consent', 'sabri-file26' ), 'value' => $profile['consent'] ? 'yes' : 'no' ),
					array( 'name' => __( 'Opted out', 'sabri-file26' ), 'value' => $profile['opted_out'] ? 'yes' : 'no' ),
					array( 'name' => __( 'Interests', 'sabri-file26' ), 'value' => $profile['interests_json'] ),
					array( 'name' => __( 'Negative controls', 'sabri-file26' ), 'value' => $profile['negatives_json'] ),
				),
			);
		}
		foreach ( $feedback as $row ) {
			$data[] = array(
				'group_id' => 'sabri-file26-feedback',
				'group_label' => __( 'Recommendation Feedback', 'sabri-file26' ),
				'item_id' => 'feedback-' . hash( 'sha256', wp_json_encode( $row ) ),
				'data' => array(
					array( 'name' => __( 'Item key', 'sabri-file26' ), 'value' => $row['item_key'] ),
					array( 'name' => __( 'Type', 'sabri-file26' ), 'value' => $row['feedback_type'] ),
					array( 'name' => __( 'Scope', 'sabri-file26' ), 'value' => $row['scope_key'] ),
					array( 'name' => __( 'Active', 'sabri-file26' ), 'value' => $row['active'] ? 'yes' : 'no' ),
					array( 'name' => __( 'Created', 'sabri-file26' ), 'value' => $row['created_at'] ),
				),
			);
		}
		foreach ( $appeals as $row ) {
			$data[] = array(
				'group_id' => 'sabri-file26-ranking-appeals',
				'group_label' => __( 'Doctor Ranking Appeals', 'sabri-file26' ),
				'item_id' => 'ranking-appeal-' . $row['appeal_uuid'],
				'data' => array(
					array( 'name' => __( 'Appeal ID', 'sabri-file26' ), 'value' => $row['appeal_uuid'] ),
					array( 'name' => __( 'Doctor reference', 'sabri-file26' ), 'value' => $row['doctor_key'] ),
					array( 'name' => __( 'Reason', 'sabri-file26' ), 'value' => $row['reason_text'] ),
					array( 'name' => __( 'Evidence', 'sabri-file26' ), 'value' => $row['evidence_json'] ),
					array( 'name' => __( 'Status', 'sabri-file26' ), 'value' => $row['status'] ),
					array( 'name' => __( 'Decision reason', 'sabri-file26' ), 'value' => $row['decision_reason'] ),
					array( 'name' => __( 'Policy version', 'sabri-file26' ), 'value' => $row['policy_version'] ),
					array( 'name' => __( 'Rank snapshot', 'sabri-file26' ), 'value' => $row['rank_snapshot'] ),
					array( 'name' => __( 'Submitted', 'sabri-file26' ), 'value' => $row['submitted_at'] ),
					array( 'name' => __( 'Decided', 'sabri-file26' ), 'value' => $row['decided_at'] ),
				),
			);
		}
		return array( 'data' => $data, 'done' => true );
	}

	public function erase( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) {
			return array(
				'items_removed' => false,
				'items_retained' => false,
				'messages' => array(),
				'done' => true,
			);
		}
		$removed_feedback = $wpdb->delete( DB::table( 'feedback' ), array( 'user_id' => $user->ID ), array( '%d' ) );
		$removed_profile = $wpdb->delete( DB::table( 'profiles' ), array( 'user_id' => $user->ID ), array( '%d' ) );
		$appeal_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Doctor_Appeals::table() . ' WHERE appellant_user_id=%d', $user->ID ) );
		$retained = false;
		if ( $appeal_count ) {
			$redacted = '[redacted after verified data-erasure request]';
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . Doctor_Appeals::table() . " SET appellant_user_id=0,reason_text=%s,evidence_json='[]',decision_reason=CASE WHEN decision_reason IS NULL THEN NULL ELSE %s END,status=CASE WHEN status IN ('submitted','under_review','changes_requested') THEN 'withdrawn' ELSE status END,version=version+1,updated_at=%s WHERE appellant_user_id=%d",
					$redacted,
					$redacted,
					DB::now(),
					$user->ID
				)
			);
			$retained = true;
		}
		$messages = array( __( 'File 26 recommendation controls and feedback were erased.', 'sabri-file26' ) );
		if ( $retained ) {
			$messages[] = __( 'Ranking appeal text and identity were redacted; a minimal policy, status and fairness record was retained for audit integrity.', 'sabri-file26' );
		}
		return array(
			'items_removed' => (bool) ( $removed_feedback || $removed_profile || $appeal_count ),
			'items_retained' => $retained,
			'messages' => $messages,
			'done' => true,
		);
	}
}
