<?php
/** @var array $result */
defined( 'ABSPATH' ) || exit;
$custom = apply_filters( 'sabri_file25_render_search_result', '', $result, 'file26' );
if ( is_string( $custom ) && '' !== trim( $custom ) ) {
	echo $custom; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- File 25 renderer contract.
	return;
}
$why = ! empty( $result['why_this'] ) && is_array( $result['why_this'] ) ? $result['why_this'] : array_map( static function ( $code ) { return ucwords( str_replace( '_', ' ', $code ) ); }, (array) $result['explanation_codes'] );
$first_topic = ! empty( $result['topics'] ) ? (string) reset( $result['topics'] ) : '';
?>
<article class="sabri-f26-card" data-object-key="<?php echo esc_attr( $result['key'] ); ?>">
	<div class="sabri-f26-card__badges">
		<span class="sabri-f26-card__type"><span class="dashicons dashicons-search" aria-hidden="true"></span><?php echo esc_html( ucwords( str_replace( '_', ' ', $result['entity_type'] ) ) ); ?></span>
		<?php if ( ! empty( $result['payload']['ai_generated'] ) ) : ?>
			<span class="sabri-f26-badge"><span class="dashicons dashicons-superhero" aria-hidden="true"></span><?php echo esc_html( ! empty( $result['payload']['ai_provider_label'] ) ? $result['payload']['ai_provider_label'] : __( 'AI-generated', 'sabri-file26' ) ); ?></span>
		<?php endif; ?>
		<?php if ( ! empty( $result['doctor_tier'] ) ) : ?>
			<span class="sabri-f26-badge"><span class="dashicons dashicons-awards" aria-hidden="true"></span><?php echo esc_html( $result['doctor_tier']['label'] ); ?></span>
		<?php endif; ?>
	</div>
	<h2 class="sabri-f26-card__title"><a href="<?php echo esc_url( $result['url'] ); ?>"><?php echo esc_html( $result['title'] ); ?></a></h2>
	<?php if ( ! empty( $result['excerpt'] ) ) : ?><p class="sabri-f26-card__excerpt"><?php echo esc_html( $result['excerpt'] ); ?></p><?php endif; ?>
	<div class="sabri-f26__meta">
		<?php if ( ! empty( $result['country'] ) ) : ?><span><?php echo esc_html( $result['country'] ); ?></span><?php endif; ?>
		<?php if ( ! empty( $result['location'] ) ) : ?><span><?php echo esc_html( $result['location'] ); ?></span><?php endif; ?>
		<?php if ( ! empty( $result['state'] ) && 'published' !== $result['state'] ) : ?><span><?php echo esc_html( ucfirst( $result['state'] ) ); ?></span><?php endif; ?>
		<?php if ( ! empty( $result['payload']['graph_relation_count'] ) ) : ?><span><?php echo esc_html( sprintf( _n( '%d approved relation', '%d approved relations', (int) $result['payload']['graph_relation_count'], 'sabri-file26' ), (int) $result['payload']['graph_relation_count'] ) ); ?></span><?php endif; ?>
	</div>
	<?php if ( $why ) : ?>
		<div class="sabri-f26__why"><strong><?php esc_html_e( 'Why this result', 'sabri-file26' ); ?></strong><ul><?php foreach ( $why as $reason ) : ?><li><?php echo esc_html( $reason ); ?></li><?php endforeach; ?></ul></div>
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
		<details class="sabri-f26__feedback-menu">
			<summary><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> <?php esc_html_e( 'Recommendation controls', 'sabri-file26' ); ?></summary>
			<div class="sabri-f26__controls">
				<button class="sabri-f26__action" type="button" data-f26-feedback="helpful" data-object-key="<?php echo esc_attr( $result['key'] ); ?>"><span class="dashicons dashicons-thumbs-up" aria-hidden="true"></span><span><?php esc_html_e( 'Helpful', 'sabri-file26' ); ?></span></button>
				<button class="sabri-f26__action" type="button" data-f26-feedback="not_interested" data-object-key="<?php echo esc_attr( $result['key'] ); ?>"><span class="dashicons dashicons-hidden" aria-hidden="true"></span><span><?php esc_html_e( 'Not interested', 'sabri-file26' ); ?></span></button>
				<button class="sabri-f26__action" type="button" data-f26-feedback="hide_item" data-object-key="<?php echo esc_attr( $result['key'] ); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span><span><?php esc_html_e( 'Hide this item', 'sabri-file26' ); ?></span></button>
				<?php if ( ! empty( $result['author_key'] ) ) : ?><button class="sabri-f26__action" type="button" data-f26-feedback="hide_author" data-object-key="<?php echo esc_attr( $result['key'] ); ?>" data-scope-key="<?php echo esc_attr( $result['author_key'] ); ?>"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span><?php esc_html_e( 'Hide this author', 'sabri-file26' ); ?></span></button><?php endif; ?>
				<?php if ( $first_topic ) : ?><button class="sabri-f26__action" type="button" data-f26-feedback="hide_topic" data-object-key="<?php echo esc_attr( $result['key'] ); ?>" data-scope-key="<?php echo esc_attr( $first_topic ); ?>"><span class="dashicons dashicons-tag" aria-hidden="true"></span><span><?php esc_html_e( 'Hide this topic', 'sabri-file26' ); ?></span></button><?php endif; ?>
			</div>
		</details>
	<?php endif; ?>
</article>
