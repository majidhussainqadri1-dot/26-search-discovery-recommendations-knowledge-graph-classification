<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * File 26 is non-destructive by default.
 *
 * Search indexes, taxonomy history, audit evidence and deletion tombstones are
 * retained so an accidental uninstall cannot resurrect restricted content or
 * destroy rollback evidence. A future owner-approved purge must be implemented
 * as a separate, authenticated, backed-up and audited operation.
 */
wp_clear_scheduled_hook( 'sabri_file26_process_queue' );
wp_clear_scheduled_hook( 'sabri_file26_reconcile' );
wp_clear_scheduled_hook( 'sabri_file26_retention' );
wp_clear_scheduled_hook( 'sabri_file26_doctor_ranking' );
