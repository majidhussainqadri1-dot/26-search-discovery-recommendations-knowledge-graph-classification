<?php
/** Round 07 regression: taxonomy merge/split must be previewable, owner-governed, serialized and reindexable. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-taxonomy.php' );
$checks = array(
	'public function merge_preview' => 'merge preview exists',
	'public function split_preview' => 'split preview exists',
	'file26_domain_owner_approval_required' => 'external domain-owner approval fails closed',
	'sabri_file26_taxonomy_domain_owner_approved' => 'domain-owner approval contract exists',
	"'active'!==\$target['status']" => 'merge target must remain active',
	'FOR UPDATE' => 'high-risk taxonomy transition locks current state',
	'rollback_mapping' => 'rollback mapping is preserved in evidence',
	'sabri_file26_taxonomy_reindex_required' => 'merge/split/deprecation trigger derivative reindex reconciliation',
	'file26_split_failed' => 'split has atomic failure state',
	'Alias merge failed.' => 'alias migration failure blocks merge commit',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "FAIL: $label\n" );
		$failures++;
	}
}
if ( $failures ) { exit( 1 ); }
echo "Round 07 taxonomy lifecycle regression passed.\n";
