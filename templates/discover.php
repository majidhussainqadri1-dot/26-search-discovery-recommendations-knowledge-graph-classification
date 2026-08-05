<?php defined( 'ABSPATH' ) || exit; ?>
<section class="sabri-f26" aria-labelledby="sabri-f26-discover-title">
	<header class="sabri-f26__header">
		<h1 id="sabri-f26-discover-title" class="sabri-f26__title"><?php esc_html_e( 'Discover', 'sabri-file26' ); ?></h1>
		<p class="sabri-f26__lead"><?php esc_html_e( 'Diverse, source-conscious recommendations with clear controls. Personalization is used only after explicit consent.', 'sabri-file26' ); ?></p>
	</header>
	<?php if ( is_wp_error( $data ) ) : ?>
		<div class="sabri-f26-state sabri-f26-state--error" role="alert"><?php echo esc_html( $data->get_error_message() ); ?></div>
	<?php elseif ( empty( $data['results'] ) ) : ?>
		<div class="sabri-f26-state" role="status"><?php esc_html_e( 'Discovery is not yet available because approved source connectors or activation gates are incomplete.', 'sabri-file26' ); ?></div>
	<?php else : ?>
		<div class="sabri-f26__status-row"><span><?php echo ! empty( $data['personalized'] ) ? esc_html__( 'Personalized with consent', 'sabri-file26' ) : esc_html__( 'Privacy-safe general discovery', 'sabri-file26' ); ?></span></div>
		<div class="sabri-f26__grid">
			<?php $show_feedback = true; foreach ( $data['results'] as $result ) { include __DIR__ . '/_card.php'; } ?>
		</div>
	<?php endif; ?>
</section>
