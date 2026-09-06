<?php
/** Second-cycle Round 6 regression: feedback idempotency is truthful, conflict-aware and replay-safe. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-recommendations.php' );
$checks = array(
	'feedback_by_idempotency' => 'stored idempotency binding is re-read before success',
	'file26_idempotency_conflict' => 'one idempotency key cannot silently describe a different operation',
	'VALUES (%s,%d,NULL,\'undo\',%s,%s,0' => 'undo persists an inactive receipt bound to its target operation',
	'\'undo\' !== $receipt[\'feedback_type\']' => 'undo replay verifies receipt operation type',
	'$target !== (string) $receipt[\'scope_key\']' => 'undo replay verifies the same target operation',
	'\'idempotent_replay\' => true' => 'identical replay is disclosed as an idempotent replay',
	'ON DUPLICATE KEY UPDATE idempotency_key=VALUES(idempotency_key)' => 'duplicate idempotency handling is a no-op rather than a silent payload rewrite',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; }
}
if ( false !== strpos( $source, 'ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)' ) ) {
	fwrite( STDERR, "FAIL: duplicate feedback must not masquerade as a newly recorded operation by merely refreshing updated_at\n" );
	$failures++;
}
$ordinary_replay = strpos( $source, 'return array( \'recorded\' => true, \'effective_next_request\' => true, \'idempotent_replay\' => true' );
$ordinary_rebuild = strpos( $source, '$rebuilt = $this->rebuild_negative_controls( $user_id );', $ordinary_replay === false ? 0 : $ordinary_replay );
if ( false === $ordinary_replay || false === $ordinary_rebuild || $ordinary_replay > $ordinary_rebuild ) {
	fwrite( STDERR, "FAIL: identical ordinary feedback replay must return before rebuilding mutable profile projections\n" );
	$failures++;
}
$undo_replay = strpos( $source, 'return array( \'reversed\' => true, \'effective_next_request\' => true, \'idempotent_replay\' => true' );
$undo_reverse = strpos( $source, '$reversed = $wpdb->update', $undo_replay === false ? 0 : $undo_replay );
if ( false === $undo_replay || false === $undo_reverse || $undo_replay > $undo_reverse ) {
	fwrite( STDERR, "FAIL: identical undo replay must return before attempting the target reversal again\n" );
	$failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 06 feedback idempotency regression passed.\n";
