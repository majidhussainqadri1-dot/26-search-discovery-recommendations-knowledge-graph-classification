<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

trait Future_Infra_Trait {
	public function boot() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 35 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 25 );
		add_filter( 'rest_post_dispatch', array( $this, 'secure_future_response' ), 45, 3 );
		add_filter( 'sabri_file26_future_capabilities', array( $this, 'expose_capabilities' ) );
		add_filter( 'sabri_file24_module_manifest', array( $this, 'assurance_manifest' ), 30 );
		add_filter( 'sabri_file25_search_provider', array( $this, 'visual_provider' ), 30 );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ), 30 );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ), 30 );
	}

	public function enqueue_assets() {
		wp_enqueue_script( 'sabri-file26-future', SABRI_FILE26_URL . 'assets/js/file26-future.js', array(), SABRI_FILE26_VERSION, true );
	}

	public function assurance_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		if ( ! isset( $manifests['file26'] ) || ! is_array( $manifests['file26'] ) ) {
			$manifests['file26'] = array();
		}
		$controls = isset( $manifests['file26']['native_controls'] ) && is_array( $manifests['file26']['native_controls'] ) ? $manifests['file26']['native_controls'] : array();
		$controls[] = 'future-search-knowledge-intelligence-superset-24';
		$controls[] = 'local-first-search-history';
		$controls[] = 'private-vault-public-index-isolation';
		$controls[] = 'historical-no-current-substitution';
		$controls[] = 'external-evidence-separated-lane';
		$manifests['file26']['native_controls'] = array_values( array_unique( $controls ) );
		$manifests['file26']['future_contract'] = self::CONTRACT;
		$manifests['file26']['future_capability_count'] = 24;
		return $manifests;
	}

	public function visual_provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		if ( isset( $providers['file26'] ) && is_array( $providers['file26'] ) ) {
			$providers['file26']['future_contract'] = self::CONTRACT;
			$providers['file26']['future_capabilities'] = $this->capability_manifest();
			$providers['file26']['result_schema'] = 'sabri.file26.result.v1.3';
		}
		return $providers;
	}

	public function register_exporter( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();
		$exporters['sabri-file26-future'] = array(
			'exporter_friendly_name' => __( 'File 26 Future Search Intelligence', 'sabri-file26' ),
			'callback' => array( $this, 'privacy_export' ),
		);
		return $exporters;
	}

	public function register_eraser( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();
		$erasers['sabri-file26-future'] = array(
			'eraser_friendly_name' => __( 'File 26 Future Search Intelligence', 'sabri-file26' ),
			'callback' => array( $this, 'privacy_erase' ),
		);
		return $erasers;
	}

	public function privacy_export( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		$data = array();
		foreach ( array( self::META_TRAILS => 'Research trails', self::META_ALERTS => 'Saved search alerts', self::META_HISTORY => 'Opt-in server search history', self::META_DISCOVERY => 'Discovery controls' ) as $meta_key => $label ) {
			$value = get_user_meta( $user->ID, $meta_key, true );
			if ( ! empty( $value ) ) {
				$data[] = array( 'group_id' => 'sabri-file26-future', 'group_label' => __( 'File 26 Future Search Intelligence', 'sabri-file26' ), 'item_id' => $meta_key, 'data' => array( array( 'name' => $label, 'value' => wp_json_encode( $value ) ) ) );
			}
		}
		return array( 'data' => $data, 'done' => true );
	}

	public function privacy_erase( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$removed = false;
		foreach ( array( self::META_TRAILS, self::META_ALERTS, self::META_HISTORY, self::META_HISTORY_OPT_IN, self::META_DISCOVERY ) as $meta_key ) {
			$removed = delete_user_meta( $user->ID, $meta_key ) || $removed;
		}
		return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}

	public function expose_capabilities( $capabilities ) {
		$capabilities = is_array( $capabilities ) ? $capabilities : array();
		$capabilities['future_search_knowledge_intelligence_24'] = array(
			'contract' => self::CONTRACT,
			'count' => 24,
			'capabilities' => $this->capability_manifest(),
			'canonical_owner_boundary' => 'File 26 orchestrates derivative search/discovery only; native owners retain source truth and write authority.',
		);
		return $capabilities;
	}

}
