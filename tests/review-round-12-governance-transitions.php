<?php
/** Round 12 regression: high-risk ranking/classification/graph/taxonomy transitions need real approval, CAS, owner gates and checked transaction boundaries. */
$root = dirname( __DIR__ );
$gov = file_get_contents( $root . '/includes/class-file26-governance.php' );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$taxonomy = file_get_contents( $root . '/includes/class-file26-taxonomy.php' );
$checks = array(
	'public function second_approve_ranking_policy' => 'second ranking approver acts explicitly',
	'$second = $row ? (int) $row[\'approval_two\'] : 0' => 'activation consumes recorded approval, not named user only',
	'public function second_approve_ranking_rollback' => 'rollback has explicit second approval receipt',
	'delete_transient( $receipt_key )' => 'rollback approval receipt is one-time',
	'$expected_version < 1' => 'classification review requires expected version',
	'AND version=%d' => 'classification decision is compare-and-swap',
	'\'active\' !== $term[\'status\']' => 'approved classification requires active term',
	'sabri_file26_classification_domain_reviewer_approved' => 'high-impact classification has domain-review gate',
	'return $this->graph->approve_edge' => 'graph activation cannot bypass Graph owner/provenance checks',
	'return $this->graph->remove_edge' => 'graph removal uses governed Graph transition',
	'false === $wpdb->query( \'START TRANSACTION\' )' => 'ranking governance verifies transaction start',
	'if ( false === $demoted )' => 'activation detects previous-policy demotion DB failure',
	'false === $wpdb->query( \'COMMIT\' )' => 'ranking governance verifies transaction commit',
);
$rest_checks = array(
	'/second-approve' => 'REST second approval route',
	'/rollback-second-approve' => 'REST rollback second approval route',
	'(int) $request->get_param( \'expected_version\' )' => 'REST passes classification expected version',
);
$taxonomy_checks = array(
	'file26_merge_failed' => 'taxonomy merge has fail-closed error surface',
	'file26_split_failed' => 'taxonomy split has fail-closed error surface',
	'if ( false === $wpdb->query( \'START TRANSACTION\' ) )' => 'taxonomy high-risk mutations verify transaction start',
	'if ( false === $wpdb->query( \'COMMIT\' ) )' => 'taxonomy high-risk mutations verify commit',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $gov, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
foreach ( $rest_checks as $needle => $label ) { if ( false === strpos( $rest, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
foreach ( $taxonomy_checks as $needle => $label ) { if ( false === strpos( $taxonomy, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
if ( substr_count( $gov, 'false === $wpdb->query( \'START TRANSACTION\' )' ) < 2 || substr_count( $gov, 'false === $wpdb->query( \'COMMIT\' )' ) < 2 ) {
	fwrite( STDERR, "FAIL: both ranking activation and rollback must verify transaction boundaries\n" ); $failures++;
}
if ( substr_count( $taxonomy, 'false === $wpdb->query( \'START TRANSACTION\' )' ) < 2 || substr_count( $taxonomy, 'false === $wpdb->query( \'COMMIT\' )' ) < 2 ) {
	fwrite( STDERR, "FAIL: both taxonomy merge and split must verify transaction boundaries\n" ); $failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Round 12 governance transitions regression passed.\n";
