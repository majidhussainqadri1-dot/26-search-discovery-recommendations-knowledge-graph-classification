<?php
/** Round 107 taxonomy/classification integrity regression guards. */
$root = dirname( __DIR__ );
$taxonomy = file_get_contents( $root . '/includes/class-file26-taxonomy.php' );
$checks = 0;
function f26_r107_assert( $condition, $message ) { global $checks; $checks++; if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
f26_r107_assert( false !== strpos( $taxonomy, 'file26_transaction_state_read_failed' ) && false !== strpos( $taxonomy, "SELECT @@in_transaction" ) && false !== strpos( $taxonomy, "null === \$in_transaction && '' !== (string) \$wpdb->last_error" ), 'R107: taxonomy creation fails closed when transaction ownership cannot be read' );
f26_r107_assert( false !== strpos( $taxonomy, 'file26_classification_target_read_failed' ) && false !== strpos( $taxonomy, "\$wpdb->last_error=''" ), 'R107: classification target database read failure is distinct from an orphan target' );
f26_r107_assert( false !== strpos( $taxonomy, 'file26_domain_review_required' ) && false !== strpos( $taxonomy, 'sabri_file26_classification_domain_reviewer_approved' ), 'R107: direct high-impact classification approval requires domain-review authorization' );
f26_r107_assert( false !== strpos( $taxonomy, "ORDER BY language ASC LIMIT 2" ) && false !== strpos( $taxonomy, '1===count($rows)?$rows[0]:null' ), 'R107: ambiguous multilingual slug lookup fails closed instead of choosing an arbitrary language term' );
echo "PASS: $checks Round 107 regression assertions\n";
