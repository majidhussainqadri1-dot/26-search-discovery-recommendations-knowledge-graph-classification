<?php
defined( 'ABSPATH' ) || exit;
$filters = isset( $filters ) && is_array( $filters ) ? $filters : array();
$facets = ! is_wp_error( $data ) && ! empty( $data['facets'] ) && is_array( $data['facets'] ) ? $data['facets'] : array();
$get_filter = static function ( $key ) use ( $filters ) { return isset( $filters[ $key ] ) ? (string) $filters[ $key ] : ''; };
?>
<section class="sabri-f26" aria-labelledby="sabri-f26-search-title">
	<header class="sabri-f26__header">
		<h1 id="sabri-f26-search-title" class="sabri-f26__title"><?php esc_html_e( 'Search the Sabri Platform', 'sabri-file26' ); ?></h1>
		<p class="sabri-f26__lead"><?php esc_html_e( 'Search eligible knowledge, doctors, clinics, learning, media, research and marketplace destinations. Canonical owners remain the source of truth.', 'sabri-file26' ); ?></p>
	</header>

	<form class="sabri-f26__search" method="get" action="<?php echo esc_url( home_url( '/search/' ) ); ?>" role="search">
		<div class="sabri-f26__search-form">
			<div class="sabri-f26__search-field">
				<label class="screen-reader-text" for="sabri-f26-q"><?php esc_html_e( 'Search', 'sabri-file26' ); ?></label>
				<input id="sabri-f26-q" class="sabri-f26__input" type="search" name="q" value="<?php echo esc_attr( $query ); ?>" maxlength="200" autocomplete="off" data-f26-suggest aria-autocomplete="list" aria-expanded="false">
			</div>
			<button class="sabri-f26__button" type="submit"><span class="dashicons dashicons-search" aria-hidden="true"></span><span><?php esc_html_e( 'Search', 'sabri-file26' ); ?></span></button>
		</div>

		<details class="sabri-f26__filter-panel"<?php echo $filters ? ' open' : ''; ?>>
			<summary><span class="dashicons dashicons-filter" aria-hidden="true"></span> <?php esc_html_e( 'Filters and sorting', 'sabri-file26' ); ?></summary>
			<div class="sabri-f26__filters">
				<label><span><?php esc_html_e( 'Content type', 'sabri-file26' ); ?></span>
					<select class="sabri-f26__select" name="type"><option value=""><?php esc_html_e( 'All types', 'sabri-file26' ); ?></option>
					<?php foreach ( isset( $facets['entity_type'] ) ? $facets['entity_type'] : array() as $value => $count ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $get_filter( 'entity_type' ), $value ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $value ) ) . ' (' . (int) $count . ')' ); ?></option>
					<?php endforeach; ?></select>
				</label>
				<label><span><?php esc_html_e( 'Language', 'sabri-file26' ); ?></span>
					<select class="sabri-f26__select" name="language"><option value=""><?php esc_html_e( 'All languages', 'sabri-file26' ); ?></option>
					<?php foreach ( isset( $facets['locale'] ) ? $facets['locale'] : array() as $value => $count ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $get_filter( 'language' ), $value ); ?>><?php echo esc_html( $value . ' (' . (int) $count . ')' ); ?></option>
					<?php endforeach; ?></select>
				</label>
				<label><span><?php esc_html_e( 'Country', 'sabri-file26' ); ?></span>
					<select class="sabri-f26__select" name="country"><option value=""><?php esc_html_e( 'All countries', 'sabri-file26' ); ?></option>
					<?php foreach ( isset( $facets['country'] ) ? $facets['country'] : array() as $value => $count ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $get_filter( 'country' ), $value ); ?>><?php echo esc_html( $value . ' (' . (int) $count . ')' ); ?></option>
					<?php endforeach; ?></select>
				</label>
				<label><span><?php esc_html_e( 'Location', 'sabri-file26' ); ?></span><input class="sabri-f26__input" name="location" value="<?php echo esc_attr( $get_filter( 'location' ) ); ?>" maxlength="191"></label>
				<label><span><?php esc_html_e( 'Availability', 'sabri-file26' ); ?></span>
					<select class="sabri-f26__select" name="availability"><option value=""><?php esc_html_e( 'Any availability', 'sabri-file26' ); ?></option>
					<?php foreach ( isset( $facets['availability'] ) ? $facets['availability'] : array() as $value => $count ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $get_filter( 'availability' ), $value ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $value ) ) . ' (' . (int) $count . ')' ); ?></option>
					<?php endforeach; ?></select>
				</label>
				<label><span><?php esc_html_e( 'Topic ID', 'sabri-file26' ); ?></span><input class="sabri-f26__input" name="topic" value="<?php echo esc_attr( $get_filter( 'topic' ) ); ?>" maxlength="191"></label>
				<label><span><?php esc_html_e( 'Author', 'sabri-file26' ); ?></span><input class="sabri-f26__input" name="author" value="<?php echo esc_attr( $get_filter( 'author' ) ); ?>" maxlength="191"></label>
				<label><span><?php esc_html_e( 'Domain', 'sabri-file26' ); ?></span><input class="sabri-f26__input" name="domain" value="<?php echo esc_attr( $get_filter( 'domain' ) ); ?>" maxlength="64"></label>
				<label><span><?php esc_html_e( 'Connector', 'sabri-file26' ); ?></span><input class="sabri-f26__input" name="connector" value="<?php echo esc_attr( $get_filter( 'connector' ) ); ?>" maxlength="191"></label>
				<label><span><?php esc_html_e( 'From date', 'sabri-file26' ); ?></span><input class="sabri-f26__input" type="date" name="date_from" value="<?php echo esc_attr( $get_filter( 'date_from' ) ); ?>"></label>
				<label><span><?php esc_html_e( 'To date', 'sabri-file26' ); ?></span><input class="sabri-f26__input" type="date" name="date_to" value="<?php echo esc_attr( $get_filter( 'date_to' ) ); ?>"></label>
				<label><span><?php esc_html_e( 'Sort', 'sabri-file26' ); ?></span>
					<select class="sabri-f26__select" name="sort">
						<option value=""><?php esc_html_e( 'Relevance', 'sabri-file26' ); ?></option>
						<option value="newest"<?php selected( $get_filter( 'sort' ), 'newest' ); ?>><?php esc_html_e( 'Newest', 'sabri-file26' ); ?></option>
						<option value="oldest"<?php selected( $get_filter( 'sort' ), 'oldest' ); ?>><?php esc_html_e( 'Oldest', 'sabri-file26' ); ?></option>
						<option value="authority"<?php selected( $get_filter( 'sort' ), 'authority' ); ?>><?php esc_html_e( 'Source authority', 'sabri-file26' ); ?></option>
					</select>
				</label>
			</div>
			<div class="sabri-f26__controls">
				<button class="sabri-f26__button" type="submit"><span class="dashicons dashicons-yes" aria-hidden="true"></span><span><?php esc_html_e( 'Apply filters', 'sabri-file26' ); ?></span></button>
				<a class="sabri-f26__action" href="<?php echo esc_url( add_query_arg( 'q', $query, home_url( '/search/' ) ) ); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span><span><?php esc_html_e( 'Clear filters', 'sabri-file26' ); ?></span></a>
			</div>
		</details>
	</form>

	<?php if ( is_wp_error( $data ) ) : ?>
		<div class="sabri-f26-state sabri-f26-state--error" role="alert"><strong><?php esc_html_e( 'Search unavailable:', 'sabri-file26' ); ?></strong> <?php echo esc_html( $data->get_error_message() ); ?></div>
	<?php else : ?>
		<?php if ( ! empty( $data['partial'] ) ) : ?>
			<div class="sabri-f26-state sabri-f26-state--warning" role="status"><strong><?php esc_html_e( 'Partial results:', 'sabri-file26' ); ?></strong> <?php esc_html_e( 'One or more source domains are degraded or the bounded scan limit was reached. No unavailable domain is being represented as complete.', 'sabri-file26' ); ?></div>
		<?php endif; ?>
		<?php if ( empty( $data['results'] ) ) : ?>
			<div class="sabri-f26-state" role="status"><?php esc_html_e( 'No eligible results were found. Try a different spelling, language, category, or fewer filters.', 'sabri-file26' ); ?></div>
		<?php else : ?>
			<div class="sabri-f26__status-row" aria-live="polite">
				<span><?php echo esc_html( sprintf( _n( '%d eligible candidate', '%d eligible candidates', (int) $data['eligible_candidates'], 'sabri-file26' ), (int) $data['eligible_candidates'] ) ); ?></span>
				<span><?php echo esc_html( sprintf( __( 'Ranking policy: %s', 'sabri-file26' ), $data['policy_version'] ) ); ?></span>
			</div>
			<div class="sabri-f26__results">
				<?php foreach ( $data['results'] as $result ) { include __DIR__ . '/_card.php'; } ?>
			</div>
			<?php if ( ! empty( $data['next_cursor'] ) ) :
				$next_args = array_merge( array( 'q' => $query, 'cursor' => $data['next_cursor'] ), array_filter( array(
					'type' => $get_filter( 'entity_type' ), 'country' => $get_filter( 'country' ), 'location' => $get_filter( 'location' ),
					'availability' => $get_filter( 'availability' ), 'connector' => $get_filter( 'connector' ), 'domain' => $get_filter( 'domain' ),
					'topic' => $get_filter( 'topic' ), 'sort' => $get_filter( 'sort' ), 'author' => $get_filter( 'author' ),
					'language' => $get_filter( 'language' ), 'date_from' => $get_filter( 'date_from' ), 'date_to' => $get_filter( 'date_to' ),
				) ) ); ?>
				<div class="sabri-f26__pager"><a class="sabri-f26__action" href="<?php echo esc_url( add_query_arg( $next_args, home_url( '/search/' ) ) ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><span><?php esc_html_e( 'More results', 'sabri-file26' ); ?></span></a></div>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</section>
