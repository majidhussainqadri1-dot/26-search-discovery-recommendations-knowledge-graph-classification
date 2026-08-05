<?php
defined( 'ABSPATH' ) || exit;
$controls = ! is_wp_error( $data ) && ! empty( $data['controls'] ) && is_array( $data['controls'] ) ? $data['controls'] : array();
$interests = isset( $controls['interests'] ) && is_array( $controls['interests'] ) ? implode( ', ', $controls['interests'] ) : '';
?>
<section class="sabri-f26" aria-labelledby="sabri-f26-discover-title">
	<header class="sabri-f26__header">
		<h1 id="sabri-f26-discover-title" class="sabri-f26__title"><?php esc_html_e( 'Discover', 'sabri-file26' ); ?></h1>
		<p class="sabri-f26__lead"><?php esc_html_e( 'Diverse, source-conscious recommendations with clear controls. Personalization is used only after explicit consent.', 'sabri-file26' ); ?></p>
	</header>
	<div class="sabri-f26__live" role="status" aria-live="polite" data-f26-live></div>

	<?php if ( ! empty( $controls['logged_in'] ) ) : ?>
		<section class="sabri-f26__preference-panel" aria-labelledby="sabri-f26-preference-title">
			<h2 id="sabri-f26-preference-title"><?php esc_html_e( 'Recommendation controls', 'sabri-file26' ); ?></h2>
			<p><?php esc_html_e( 'Your interests are used only after explicit consent. You can change, reset, or opt out at any time.', 'sabri-file26' ); ?></p>
			<div class="sabri-f26__controls">
				<?php if ( ! empty( $controls['personalization_available'] ) && empty( $controls['consent'] ) ) : ?>
					<button class="sabri-f26__button" type="button" data-f26-personalization="consent-on"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><span><?php esc_html_e( 'Enable personalization', 'sabri-file26' ); ?></span></button>
				<?php elseif ( ! empty( $controls['consent'] ) ) : ?>
					<button class="sabri-f26__action" type="button" data-f26-personalization="consent-off"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span><?php esc_html_e( 'Withdraw consent', 'sabri-file26' ); ?></span></button>
				<?php endif; ?>
				<?php if ( ! empty( $controls['can_reset'] ) ) : ?><button class="sabri-f26__action" type="button" data-f26-personalization="reset"><span class="dashicons dashicons-update" aria-hidden="true"></span><span><?php esc_html_e( 'Reset recommendations', 'sabri-file26' ); ?></span></button><?php endif; ?>
				<?php if ( ! empty( $controls['can_opt_out'] ) ) : ?><button class="sabri-f26__action" type="button" data-f26-personalization="opt-out"><span class="dashicons dashicons-privacy" aria-hidden="true"></span><span><?php esc_html_e( 'Opt out', 'sabri-file26' ); ?></span></button><?php endif; ?>
			</div>
			<?php if ( ! empty( $controls['personalization_available'] ) && ! empty( $controls['consent'] ) ) : ?>
				<form class="sabri-f26__interest-form" data-f26-interests>
					<label for="sabri-f26-interests"><span><?php esc_html_e( 'Selected topic IDs, separated by commas', 'sabri-file26' ); ?></span><input id="sabri-f26-interests" class="sabri-f26__input" name="interests" value="<?php echo esc_attr( $interests ); ?>" maxlength="1000"></label>
					<button class="sabri-f26__button" type="submit"><span class="dashicons dashicons-saved" aria-hidden="true"></span><span><?php esc_html_e( 'Save interests', 'sabri-file26' ); ?></span></button>
				</form>
			<?php endif; ?>
		</section>
	<?php else : ?>
		<div class="sabri-f26-state" role="note"><?php esc_html_e( 'Guest discovery uses only non-personal signals unless a request supplies temporary session topics. File 26 does not create a hidden guest profile.', 'sabri-file26' ); ?></div>
	<?php endif; ?>

	<?php if ( is_wp_error( $data ) ) : ?>
		<div class="sabri-f26-state sabri-f26-state--error" role="alert"><?php echo esc_html( $data->get_error_message() ); ?></div>
	<?php else : ?>
		<?php if ( ! empty( $data['partial'] ) ) : ?><div class="sabri-f26-state sabri-f26-state--warning" role="status"><?php esc_html_e( 'Discovery is currently partial because one or more approved source domains are degraded or bounded.', 'sabri-file26' ); ?></div><?php endif; ?>
		<?php if ( empty( $data['results'] ) ) : ?>
			<div class="sabri-f26-state" role="status"><?php esc_html_e( 'Discovery is not yet available because approved source connectors or activation gates are incomplete.', 'sabri-file26' ); ?></div>
		<?php else : ?>
			<div class="sabri-f26__status-row">
				<span><?php echo ! empty( $data['personalized'] ) ? esc_html__( 'Personalized with explicit consent', 'sabri-file26' ) : ( ! empty( $data['session_contextual'] ) ? esc_html__( 'Temporary session-context discovery', 'sabri-file26' ) : esc_html__( 'Privacy-safe general discovery', 'sabri-file26' ) ); ?></span>
				<span><?php echo esc_html( sprintf( __( 'Ranking policy: %s', 'sabri-file26' ), $data['policy_version'] ) ); ?></span>
			</div>
			<div class="sabri-f26__grid">
				<?php $show_feedback = true; foreach ( $data['results'] as $result ) { include __DIR__ . '/_card.php'; } ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</section>
