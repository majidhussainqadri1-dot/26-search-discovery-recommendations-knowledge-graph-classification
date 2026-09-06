<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/** Enforces durable saved-query erasure and bounded central-plan retention. */
final class Privacy_Truth {
	const META_SAVED_QUERIES = 'sabri_file26_saved_queries_v1';
	const OPTION_CONTENT_GAPS = 'sabri_file26_explicit_content_gaps_v1';
	const OPTION_CURSOR = 'sabri_file26_privacy_retention_cursor';

	public static function boot() {
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'override_saved_query_eraser' ), 100 );
		add_action( DB::CRON_RETENTION, array( __CLASS__, 'retention' ), 60 );
	}

	public static function override_saved_query_eraser( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();
		$erasers['sabri-file26-saved-queries'] = array(
			'eraser_friendly_name' => __( 'File 26 saved search queries', 'sabri-file26' ),
			'callback' => array( __CLASS__, 'erase_saved_queries' ),
		);
		return $erasers;
	}

	public static function erase_saved_queries( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user || (int) $page > 1 ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$before = get_user_meta( $user->ID, self::META_SAVED_QUERIES, true );
		$had = is_array( $before ) ? ! empty( $before ) : (bool) $before;
		if ( $had ) {
			delete_user_meta( $user->ID, self::META_SAVED_QUERIES );
		}
		$after = get_user_meta( $user->ID, self::META_SAVED_QUERIES, true );
		if ( ! empty( $after ) ) {
			return array(
				'items_removed' => false,
				'items_retained' => true,
				'messages' => array( __( 'Saved-query erasure could not be verified; no success is reported.', 'sabri-file26' ) ),
				'done' => false,
			);
		}
		return array( 'items_removed' => $had, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}

	public static function retention() {
		$saved = self::retain_saved_queries_batch();
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$gaps = self::retain_content_gaps();
		if ( is_wp_error( $gaps ) ) {
			return $gaps;
		}
		return array( 'saved_queries' => $saved, 'content_gaps' => $gaps );
	}

	private static function retain_saved_queries_batch() {
		global $wpdb;
		$cursor = max( 0, (int) get_option( self::OPTION_CURSOR, 0 ) );
		$wpdb->last_error = '';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key=%s AND user_id>%d ORDER BY user_id ASC LIMIT 500",
				self::META_SAVED_QUERIES,
				$cursor
			),
			ARRAY_A
		);
		if ( null === $rows && ! empty( $wpdb->last_error ) ) {
			return new \WP_Error( 'file26_saved_query_retention_read_failed', 'Saved-query retention could not read its user-meta batch.' );
		}
		$rows = is_array( $rows ) ? $rows : array();
		$now = time();
		$pruned = 0;
		$last = $cursor;
		foreach ( $rows as $row ) {
			$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
			if ( $user_id < 1 ) { continue; }
			$last = max( $last, $user_id );
			$value = get_user_meta( $user_id, self::META_SAVED_QUERIES, true );
			$value = is_array( $value ) ? $value : array();
			$clean = $value;
			foreach ( $clean as $id => $record ) {
				$expires = is_array( $record ) && ! empty( $record['expires_at'] ) ? strtotime( $record['expires_at'] . ' UTC' ) : false;
				if ( false === $expires || $expires < $now ) {
					unset( $clean[ $id ] );
					$pruned++;
				}
			}
			if ( $clean !== $value ) {
				update_user_meta( $user_id, self::META_SAVED_QUERIES, $clean );
				$verified = get_user_meta( $user_id, self::META_SAVED_QUERIES, true );
				$verified = is_array( $verified ) ? $verified : array();
				if ( $verified !== $clean ) {
					return new \WP_Error( 'file26_saved_query_retention_write_failed', 'Expired saved queries could not be durably removed.' );
				}
			}
		}
		$next = count( $rows ) < 500 ? 0 : $last;
		update_option( self::OPTION_CURSOR, $next, false );
		return array( 'users_scanned' => count( $rows ), 'records_pruned' => $pruned, 'next_cursor' => $next );
	}

	private static function retain_content_gaps() {
		$registry = get_option( self::OPTION_CONTENT_GAPS, array() );
		$registry = is_array( $registry ) ? $registry : array();
		$clean = $registry;
		$now = time();
		foreach ( $clean as $key => $record ) {
			$expires = is_array( $record ) && ! empty( $record['expires_at'] ) ? strtotime( $record['expires_at'] . ' UTC' ) : false;
			if ( false === $expires || $expires < $now ) {
				unset( $clean[ $key ] );
			}
		}
		if ( $clean !== $registry ) {
			update_option( self::OPTION_CONTENT_GAPS, $clean, false );
			$verified = get_option( self::OPTION_CONTENT_GAPS, array() );
			$verified = is_array( $verified ) ? $verified : array();
			if ( $verified !== $clean ) {
				return new \WP_Error( 'file26_content_gap_retention_write_failed', 'Expired content-gap records could not be durably removed.' );
			}
		}
		return array( 'records_pruned' => max( 0, count( $registry ) - count( $clean ) ) );
	}
}
