<?php
namespace Sabri\File26;
defined( 'ABSPATH' ) || exit;

final class Admin {
	private $health; private $connectors; private $indexer; private $taxonomy; private $security;
	public function __construct( Health $health, Connectors $connectors, Indexer $indexer, Taxonomy $taxonomy, Security $security ) { $this->health = $health; $this->connectors = $connectors; $this->indexer = $indexer; $this->taxonomy = $taxonomy; $this->security = $security; }
	public function register() { add_action( 'admin_menu', array( $this, 'menu' ) ); add_action( 'admin_post_sabri_file26_save_settings', array( $this, 'save_settings' ) ); add_action( 'admin_post_sabri_file26_reindex', array( $this, 'reindex' ) ); add_action( 'admin_post_sabri_file26_reconcile', array( $this, 'reconcile' ) ); }
	public function menu() { add_menu_page( __( 'Search & Discovery', 'sabri-file26' ), __( 'Search & Discovery', 'sabri-file26' ), 'manage_sabri_search', 'sabri-file26', array( $this, 'page' ), 'dashicons-search', 58 ); }

	public function page() {
		if ( ! $this->security->can_manage() ) { wp_die( esc_html__( 'You are not allowed to manage File 26.', 'sabri-file26' ) ); }
		$health = $this->health->snapshot(); $settings = DB::settings(); $connectors = $this->connectors->all(); ?>
		<div class="wrap sabri-f26-admin">
		<h1><span class="dashicons dashicons-search" aria-hidden="true"></span> <?php esc_html_e( 'File 26 — Search, Discovery and Knowledge Graph', 'sabri-file26' ); ?></h1>
		<p><?php esc_html_e( 'This control plane never replaces canonical content owners. Unknown or incompatible dependencies fail closed.', 'sabri-file26' ); ?></p>
		<div class="notice notice-<?php echo 'healthy' === $health['status'] ? 'success' : 'warning'; ?> inline"><p><strong><?php esc_html_e( 'Health:', 'sabri-file26' ); ?></strong> <?php echo esc_html( ucfirst( $health['status'] ) ); ?></p></div>
		<h2><?php esc_html_e( 'Truthful completion status', 'sabri-file26' ); ?></h2><table class="widefat striped"><tbody>
		<tr><th><?php esc_html_e( 'Specified', 'sabri-file26' ); ?></th><td><?php esc_html_e( 'Complete', 'sabri-file26' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Coded', 'sabri-file26' ); ?></th><td><?php esc_html_e( 'Repository candidate', 'sabri-file26' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Staging accepted', 'sabri-file26' ); ?></th><td><?php esc_html_e( 'Pending external Hostinger acceptance', 'sabri-file26' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Live / Operational', 'sabri-file26' ); ?></th><td><?php esc_html_e( 'Not claimed', 'sabri-file26' ); ?></td></tr></tbody></table>
		<h2><?php esc_html_e( 'Connectors', 'sabri-file26' ); ?></h2><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Connector', 'sabri-file26' ); ?></th><th><?php esc_html_e( 'Owner', 'sabri-file26' ); ?></th><th><?php esc_html_e( 'Contract', 'sabri-file26' ); ?></th><th><?php esc_html_e( 'Status', 'sabri-file26' ); ?></th></tr></thead><tbody>
		<?php if ( ! $connectors ) : ?><tr><td colspan="4"><?php esc_html_e( 'No approved owner connector is registered. Public search will honestly return no indexed results.', 'sabri-file26' ); ?></td></tr>
		<?php else : foreach ( $connectors as $connector ) : ?><tr><td><?php echo esc_html( $connector['slug'] ); ?></td><td><?php echo esc_html( $connector['owner_file'] ); ?></td><td><?php echo esc_html( $connector['contract_version'] ); ?></td><td><?php echo esc_html( $connector['status'] ); ?></td></tr><?php endforeach; endif; ?></tbody></table>
		<h2><?php esc_html_e( 'Settings', 'sabri-file26' ); ?></h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="sabri_file26_save_settings"><?php wp_nonce_field( 'sabri_file26_save_settings' ); ?><table class="form-table" role="presentation">
		<tr><th scope="row"><?php esc_html_e( 'Approved runtime activation', 'sabri-file26' ); ?></th><td><label><input type="checkbox" name="activated" value="1" <?php checked( ! empty( $settings['activated'] ) ); ?>> <?php esc_html_e( 'Enable only after owner connectors, privacy, security, migration and staging gates are approved', 'sabri-file26' ); ?></label></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Public search', 'sabri-file26' ); ?></th><td><label><input type="checkbox" name="public_search_enabled" value="1" <?php checked( ! empty( $settings['public_search_enabled'] ) ); ?>> <?php esc_html_e( 'Enable the public query contract', 'sabri-file26' ); ?></label></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Personalization', 'sabri-file26' ); ?></th><td><label><input type="checkbox" name="personalization_enabled" value="1" <?php checked( ! empty( $settings['personalization_enabled'] ) ); ?>> <?php esc_html_e( 'Enable only for users with explicit consent', 'sabri-file26' ); ?></label></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Privacy-minimized metrics', 'sabri-file26' ); ?></th><td><label><input type="checkbox" name="telemetry_enabled" value="1" <?php checked( ! empty( $settings['telemetry_enabled'] ) ); ?>> <?php esc_html_e( 'Store aggregate query classes; raw sensitive text remains disabled', 'sabri-file26' ); ?></label></td></tr>
		<tr><th scope="row"><label for="f26-results"><?php esc_html_e( 'Results per page', 'sabri-file26' ); ?></label></th><td><input id="f26-results" type="number" min="1" max="30" name="results_per_page" value="<?php echo esc_attr( $settings['results_per_page'] ); ?>"></td></tr></table><?php submit_button( __( 'Save settings', 'sabri-file26' ) ); ?></form>
		<h2><?php esc_html_e( 'Operations', 'sabri-file26' ); ?></h2>
		<form class="f26-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="sabri_file26_reindex"><?php wp_nonce_field( 'sabri_file26_reindex' ); ?><label for="f26-connector"><?php esc_html_e( 'Connector', 'sabri-file26' ); ?></label><select id="f26-connector" name="connector" required><option value=""><?php esc_html_e( 'Select', 'sabri-file26' ); ?></option><?php foreach ( $connectors as $connector ) : ?><option value="<?php echo esc_attr( $connector['slug'] ); ?>"><?php echo esc_html( $connector['slug'] ); ?></option><?php endforeach; ?></select><?php submit_button( __( 'Start bounded shadow reindex', 'sabri-file26' ), 'secondary', 'submit', false ); ?></form>
		<form class="f26-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="sabri_file26_reconcile"><?php wp_nonce_field( 'sabri_file26_reconcile' ); ?><?php submit_button( __( 'Run deletion and graph reconciliation', 'sabri-file26' ), 'secondary', 'submit', false ); ?></form></div><?php
	}

	public function save_settings() {
		if ( ! $this->security->can_manage() ) { wp_die( esc_html__( 'Forbidden.', 'sabri-file26' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'sabri_file26_save_settings' ); $activate_requested = ! empty( $_POST['activated'] );
		if ( $activate_requested ) {
			$connectors = array_filter( $this->connectors->all(), static function ( $connector ) { return in_array( $connector['status'], array( 'approved', 'active' ), true ); } ); $health = $this->health->snapshot(); $approved = (bool) apply_filters( 'sabri_file26_activation_gate_approved', false, $health, $connectors );
			if ( ! $connectors || ! $approved || ! $this->security->require_step_up( 'runtime_activate' ) ) { wp_die( esc_html__( 'Runtime activation is blocked until an approved owner connector, staging evidence, external gate approval and fresh authorization are present.', 'sabri-file26' ), '', array( 'response' => 403 ) ); }
		}
		DB::update_settings( array( 'activated' => $activate_requested, 'public_search_enabled' => ! empty( $_POST['public_search_enabled'] ), 'personalization_enabled' => ! empty( $_POST['personalization_enabled'] ), 'telemetry_enabled' => ! empty( $_POST['telemetry_enabled'] ), 'results_per_page' => isset( $_POST['results_per_page'] ) ? max( 1, min( 30, (int) $_POST['results_per_page'] ) ) : 20 ) );
		$this->security->audit( 'search_settings_updated', array( 'object_type' => 'settings' ) ); wp_safe_redirect( admin_url( 'admin.php?page=sabri-file26&updated=1' ) ); exit;
	}

	public function reindex() {
		if ( ! $this->security->can_operate() ) { wp_die( esc_html__( 'Forbidden.', 'sabri-file26' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'sabri_file26_reindex' ); $connector = isset( $_POST['connector'] ) ? sanitize_key( wp_unslash( $_POST['connector'] ) ) : '';
		$job = $this->indexer->enqueue_reindex( $connector );
		if ( is_wp_error( $job ) ) { wp_die( esc_html( $job->get_error_message() ), '', array( 'response' => 409 ) ); }
		wp_safe_redirect( admin_url( 'admin.php?page=sabri-file26&reindex=queued&job=' . rawurlencode( $job ) ) ); exit;
	}

	public function reconcile() {
		if ( ! $this->security->can_operate() ) { wp_die( esc_html__( 'Forbidden.', 'sabri-file26' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'sabri_file26_reconcile' ); $result = $this->indexer->reconcile();
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 500 ) ); }
		wp_safe_redirect( admin_url( 'admin.php?page=sabri-file26&reconcile=complete' ) ); exit;
	}
}
