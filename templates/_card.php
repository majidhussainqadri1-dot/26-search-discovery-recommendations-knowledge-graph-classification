<?php
/** @var array $result */
defined( 'ABSPATH' ) || exit;
$custom = apply_filters( 'sabri_file25_render_search_result', '', $result, 'file26' );
if ( is_string( $custom ) && '' !== trim( $custom ) ) {
	echo $custom; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- File 25 renderer contract.
	return;
}
?>
<article class="sabri-f26-card" data-object-key="<?php echo esc_attr( $result['key'] ); ?>">
	<span class="sabri-f26-card__type"><span class="dashicons dashicons-search" aria-hidden="true"></span><?php echo esc_html( ucwords( str_replace( '_', ' ', $result['entity_type'] ) ) ); ?></span>
	<?php if ( ! empty( $result['payload']['ai_generated'] ) ) : ?>
		<span class="sabri-f26-badge"><span class="dashicons dashicons-superhero" aria-hidden="true"></span><?php echo esc_html( ! empty( $result['payload']['ai_provider_label'] ) ? $result['payload']['ai_provider_label'] : __( 'AI-generated', 'sabri-file26' ) ); ?></span>
	<?php endif; ?>
	<?php if ( ! empty( $result['doctor_tier'] ) ) : ?>
		<span class="sabri-f26-badge"><span class="dashicons dashicons-awards" aria-hidden="true"></span><?php echo esc_html( $result['doctor_tier']['label'] ); ?></span>
	<?php endif; ?>
	<h2 class="sabri-f26-card__title"><a href="<?php echo esc_url( $result['url'] ); ?>"><?php echo esc_html( $result['title'] ); ?></a></h2>
	<?php if ( ! empty( $result['excerpt'] ) ) : ?><p class="sabri-f26-card__excerpt"><?php echo esc_html( $result['excerpt'] ); ?></p><?php endif; ?>
	<div class="sabri-f26__meta">
		<?php if ( ! empty( $result['country'] ) ) : ?><span><?php echo esc_html( $result['country'] ); ?></span><?php endif; ?>
		<?php if ( ! empty( $result['state'] ) && 'published' !== $result['state'] ) : ?><span><?php echo esc_html( ucfirst( $result['state'] ) ); ?></span><?php endif; ?>
	</div>
	<?php if ( ! empty( $result['explanation_codes'] ) ) : ?>
		<p class="sabri-f26__why"><strong><?php esc_html_e( 'Why this result:', 'sabri-file26' ); ?></strong> <?php echo esc_html( implode( ', ', array_map( static function ( $code ) { return str_replace( '_', ' ', $code ); }, $result['explanation_codes'] ) ) ); ?></p>
	<?php endif; ?>
	<div class="sabri-f26-card__actions">
		<?php foreach ( $result['actions'] as $key => $action ) : ?>
			<a class="sabri-f26__action" href="<?php echo esc_url( $action['url'] ); ?>"<?php echo 'download' === $key ? ' download' : ''; ?>>
				<span class="dashicons dashicons-<?php echo esc_attr( 'download' === $key ? 'download' : 'external' ); ?>" aria-hidden="true"></span>
				<span><?php echo esc_html( $action['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php if ( ! empty( $show_feedback ) && is_user_logged_in() ) : ?>
		<div class="sabri-f26__controls">
			<button class="sabri-f26__action" type="button" data-f26-feedback="not_interested" data-object-key="<?php echo esc_attr( $result['key'] ); ?>"><span class="dashicons dashicons-hidden" aria-hidden="true"></span><span><?php esc_html_e( 'Not interested', 'sabri-file26' ); ?></span></button>
		</div>
	<?php endif; ?>
</article>
