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
			'exporter_friendly_name' => __( 'Sabri Search and Recommendation Controls', 'sabri-file26' ),
			'callback' => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-file26'] = array(
			'eraser_friendly_name' => __( 'Sabri Search and Recommendation Controls', 'sabri-file26' ),
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
		return array(
			'items_removed' => (bool) ( $removed_feedback || $removed_profile ),
			'items_retained' => false,
			'messages' => array( __( 'File 26 recommendation controls and feedback were erased.', 'sabri-file26' ) ),
			'done' => true,
		);
	}
}
