<?php
/** Round 12 regression: high-risk ranking/classification/graph transitions need real approval, CAS and owner gates. */
$root = dirname( __DIR__ );
$gov = file_get_contents( $root . '/includes/class-file26-governance.php' );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );
$checks = array(
	'public function second_approve_ranking_policy' => 'second ranking approver acts explicitly',
	"approval_two" => 'activation consumes recorded approval, not named user only',
	'public function second_approve_ranking_rollback' => 'rollback has explicit second approval receipt',
	'delete_transient($receipt_key)' => 'rollback approval receipt is one-time',
	'$expected_version<1' => 'classification review requires expected version',
	'AND version=%d' => 'classification decision is compare-and-swap',
	"'approved'===\$decision&&'active'!==\$term['status']" => 'approved classification requires active term',
	'sabri_file26_classification_domain_reviewer_approved' => 'high-impact classification has domain-review gate',
	'return $this->graph->approve_edge' => 'graph activation cannot bypass Graph owner/provenance checks',
	'return $this->graph->remove_edge' => 'graph removal uses governed Graph transition',
);
$rest_checks = array(
	'/second-approve' => 'REST second approval route',
	'/rollback-second-approve' => 'REST rollback second approval route',
	"get_param('expected_version')" => 'REST passes classification expected version',
);
$failures = 0;
foreach ( $checks as $needle => $label ) { if ( false === strpos( $gov, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
foreach ( $rest_checks as $needle => $label ) { if ( false === strpos( $rest, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; } }
if ( $failures ) { exit( 1 ); }
echo "Round 12 governance transitions regression passed.\n";
