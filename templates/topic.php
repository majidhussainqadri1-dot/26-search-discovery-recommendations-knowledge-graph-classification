<?php defined( 'ABSPATH' ) || exit; ?>
<section class="sabri-f26" aria-labelledby="sabri-f26-topic-title">
	<header class="sabri-f26__header">
		<span class="sabri-f26-badge"><span class="dashicons dashicons-networking" aria-hidden="true"></span><?php esc_html_e( 'Knowledge topic', 'sabri-file26' ); ?></span>
		<h1 id="sabri-f26-topic-title" class="sabri-f26__title"><?php echo esc_html( $term['preferred_label'] ); ?></h1>
		<?php if ( ! empty( $term['definition'] ) ) : ?><p class="sabri-f26__lead"><?php echo esc_html( $term['definition'] ); ?></p><?php endif; ?>
	</header>
	<?php if ( is_wp_error( $data ) ) : ?>
		<div class="sabri-f26-state sabri-f26-state--error" role="alert"><?php echo esc_html( $data->get_error_message() ); ?></div>
	<?php elseif ( empty( $data['results'] ) ) : ?>
		<div class="sabri-f26-state" role="status"><?php esc_html_e( 'No public canonical relations are available for this topic yet.', 'sabri-file26' ); ?></div>
	<?php else : ?>
		<div class="sabri-f26__results"><?php foreach ( $data['results'] as $result ) { include __DIR__ . '/_card.php'; } ?></div>
	<?php endif; ?>
</section>
