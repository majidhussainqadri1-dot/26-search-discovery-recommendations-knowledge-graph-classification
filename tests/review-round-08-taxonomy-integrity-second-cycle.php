<?php
/** Second-cycle Round 8 regression: taxonomy creation/merge/classification remain atomic and review-governed. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-taxonomy.php' );
$checks = array(
	'public function create( array $input )' => 'public term creation remains available',
	'START TRANSACTION' => 'standalone term creation participates in an atomic transaction',
	'private function create_in_transaction' => 'split can create target terms inside its existing transaction without nested commits',
	'$this->create_in_transaction( $target )' => 'split uses the transaction-safe term creation helper',
	'private function transfer_aliases' => 'merge reparents source aliases instead of silently ignoring collisions',
	'SELECT id,alias_normalized,language' => 'alias migration reads concrete source alias identities',
	'term_uuid<>%s' => 'alias transfer detects ownership collisions outside the source term',
	'Classification source read failed.' => 'classification source read failure blocks destructive merge cleanup',
	'sabri_file26_classification_domain_reviewer_approved' => 'high-impact direct approval requires the independent domain-review contract',
	'file26_domain_review_required' => 'missing independent high-impact review fails closed',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; }
}
if ( false !== strpos( $source, 'INSERT IGNORE INTO ' ) ) {
	fwrite( STDERR, "FAIL: taxonomy alias ownership must not rely on INSERT IGNORE\n" );
	$failures++;
}
$call = strpos( $source, '$this->transfer_aliases( $source[\'term_uuid\'], $target[\'term_uuid\'] )' );
$cleanup = strpos( $source, 'Source classification cleanup failed.' );
$commit = strpos( $source, 'Taxonomy merge commit failed.' );
if ( false === $call || false === $cleanup || false === $commit || $call < $cleanup || $call > $commit ) {
	fwrite( STDERR, "FAIL: alias transfer must be part of the merge transaction after classification migration and before commit\n" );
	$failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 08 taxonomy integrity regression passed.\n";
