<?php
/** Regression: recommendation hide/undo must return keyboard focus to a visible focusable result control. */
$root = dirname( __DIR__ );
$js = file_get_contents( $root . '/assets/js/file26.js' );
$card = file_get_contents( $root . '/templates/_card.php' );
$checks = array(
	"undo.focus()" => 'focus moves to Undo after a result is hidden',
	"card.hidden=false" => 'undo restores the card before focus restoration',
	"card.querySelector('.sabri-f26-card__title a, a[href], button:not([disabled]), summary')" => 'restored focus targets an actually focusable descendant',
	"restoredFocus.focus" => 'focus is explicitly returned to the restored result',
	'<h2 class="sabri-f26-card__title"><a href=' => 'every fallback result card has a focusable title link',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	$haystack = false !== strpos( $needle, '<h2' ) ? $card : $js;
	if ( false === strpos( $haystack, $needle ) ) { fwrite( STDERR, "FAIL: $label\n" ); $failures++; }
}
if ( false !== strpos( $js, 'card.focus&&card.focus()' ) ) {
	fwrite( STDERR, "FAIL: generic non-focusable article focus fallback remains\n" ); $failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Accessibility focus restoration regression passed.\n";