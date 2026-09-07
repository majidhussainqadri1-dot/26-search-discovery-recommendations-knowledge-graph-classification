<?php
/** Round 26 regression: taxonomy creation/merge/split remain atomic and alias-safe. */
$root = dirname( __DIR__ );
$code = file_get_contents( $root . '/includes/class-file26-taxonomy.php' );
$compact = preg_replace( '/\s+/', '', $code );
$failures = 0;
$fail = static function ( $m ) use ( &$failures ) { fwrite( STDERR, 'FAIL: ' . $m . "\n" ); $failures++; };
foreach ( array(
	'Taxonomytermandaliasescouldnotbecreatedatomically.' => 'term + alias creation is atomic',
	'privatefunctioninsert_term_record' => 'non-transactional inner term insert helper exists for split reuse',
	'Parenttermmustexistandremaincurrentbeforeapproval.' => 'parent currentness is checked before approval',
	'file26_merge_preview_failed' => 'merge preview read failures are explicit',
	'Sourcealiascleanupfailed.' => 'merge alias cleanup failure aborts commit',
	'file26_split_preview_failed' => 'split preview read failures are explicit',
	'file26_classification_target_read_failed' => 'classification target read failure is explicit',
	"SELECTterm_uuidFROM'.DB::table('term_aliases').'" => 'alias collision state is read explicitly',
) as $needle => $label ) {
	if ( false === strpos( $compact, $needle ) ) { $fail( 'Missing taxonomy safeguard: ' . $label ); }
}
if ( false !== strpos( $compact, "INSERTIGNOREINTO'.DB::table('term_aliases')" ) ) {
	$fail( 'Alias collisions must not be silently ignored.' );
}
if ( false !== strpos( $compact, '$result=$this->create($target);' ) ) {
	$fail( 'Split must not start nested transactions through public create().' );
}
if ( $failures ) { exit( 1 ); }
echo "PASS: round 26 taxonomy atomicity and alias collision safety\n";
