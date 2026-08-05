<?php defined( 'ABSPATH' ) || exit; ?>
<section class="sabri-f26" aria-labelledby="sabri-f26-search-title">
	<header class="sabri-f26__header">
		<h1 id="sabri-f26-search-title" class="sabri-f26__title"><?php esc_html_e( 'Search the Sabri Platform', 'sabri-file26' ); ?></h1>
		<p class="sabri-f26__lead"><?php esc_html_e( 'Search eligible knowledge, doctors, clinics, learning, media, research and marketplace destinations. Canonical owners remain the source of truth.', 'sabri-file26' ); ?></p>
	</header>
	<form class="sabri-f26__search-form" method="get" action="<?php echo esc_url( home_url( '/search/' ) ); ?>" role="search">
		<div class="sabri-f26__search-field">
			<label class="screen-reader-text" for="sabri-f26-q"><?php esc_html_e( 'Search', 'sabri-file26' ); ?></label>
			<input id="sabri-f26-q" class="sabri-f26__input" type="search" name="q" value="<?php echo esc_attr( $query ); ?>" maxlength="200" autocomplete="off" data-f26-suggest>
		</div>
		<button class="sabri-f26__button" type="submit"><span class="dashicons dashicons-search" aria-hidden="true"></span><span><?php esc_html_e( 'Search', 'sabri-file26' ); ?></span></button>
	</form>
	<?php if ( is_wp_error( $data ) ) : ?>
		<div class="sabri-f26-state sabri-f26-state--error" role="alert"><strong><?php esc_html_e( 'Search unavailable:', 'sabri-file26' ); ?></strong> <?php echo esc_html( $data->get_error_message() ); ?></div>
	<?php elseif ( empty( $data['results'] ) ) : ?>
		<div class="sabri-f26-state" role="status"><?php esc_html_e( 'No eligible results were found. Try a different spelling, language, or category.', 'sabri-file26' ); ?></div>
	<?php else : ?>
		<div class="sabri-f26__status-row" aria-live="polite"><span><?php echo esc_html( sprintf( _n( '%d eligible result', '%d eligible results', count( $data['results'] ), 'sabri-file26' ), count( $data['results'] ) ) ); ?></span><span><?php echo esc_html( $data['policy_version'] ); ?></span></div>
		<div class="sabri-f26__results">
			<?php foreach ( $data['results'] as $result ) { include __DIR__ . '/_card.php'; } ?>
		</div>
		<?php if ( ! empty( $data['next_cursor'] ) ) : ?>
			<div class="sabri-f26__pager"><a class="sabri-f26__action" href="<?php echo esc_url( add_query_arg( array( 'q' => $query, 'cursor' => $data['next_cursor'] ), home_url( '/search/' ) ) ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><span><?php esc_html_e( 'More results', 'sabri-file26' ); ?></span></a></div>
		<?php endif; ?>
	<?php endif; ?>
</section>
