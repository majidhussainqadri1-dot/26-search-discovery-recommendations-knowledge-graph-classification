<?php
/** Second-cycle Round 14 regression: autocomplete is IME-safe, listbox-semantic, and suggestion reads fail closed. */
$root = dirname( __DIR__ );
$js = file_get_contents( $root . '/assets/js/file26.js' );
$css = file_get_contents( $root . '/assets/css/file26.css' );
$search = file_get_contents( $root . '/includes/class-file26-search.php' );
$rest = file_get_contents( $root . '/includes/class-file26-rest.php' );

$checks = array(
	array( $js, "addEventListener('compositionstart'", 'IME composition start is tracked' ),
	array( $js, "addEventListener('compositionend'", 'IME composition end resumes suggestion work' ),
	array( $js, 'event.isComposing || composing || event.keyCode === 229', 'keyboard navigation is disabled while an IME composition is active' ),
	array( $js, 'if (event.isComposing || composing)', 'input events cannot schedule suggestions mid-composition' ),
	array( $js, "option.setAttribute('role', 'option')", 'suggestions remain listbox options' ),
	array( $js, "option.textContent = String((item && item.label) || '')", 'suggestion labels are rendered as text rather than HTML' ),
	array( $js, "option.setAttribute('data-url', target)", 'option activation stores only a validated navigation target' ),
	array( $js, 'safeSameOriginUrl', 'client activation has a same-origin URL guard' ),
	array( $js, 'window.location.assign(target)', 'Enter/click activates the option without a nested focusable anchor' ),
	array( $css, '.sabri-f26__suggestions [role="option"]', 'listbox options retain visible pointer/selection styling' ),
	array( $search, 'file26_suggest_read_failed', 'suggestion DB read failure has an explicit fail-closed error' ),
	array( $search, 'search_suggest_read_failed', 'suggestion DB read failure is audited' ),
	array( $search, 'if ( ! is_array( $rows ) )', 'suggestion result shape is verified before iteration' ),
	array( $rest, '$suggestions = $this->search->suggest', 'REST captures the suggestion backend result' ),
	array( $rest, 'if ( is_wp_error( $suggestions ) ) { return $suggestions; }', 'REST propagates suggestion backend failure instead of returning empty success' ),
);

$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}

if ( false !== strpos( $js, 'list.innerHTML = suggestions.map' ) ) {
	fwrite( STDERR, "FAIL: suggestion list must not be built with HTML-string mapping\n" );
	$failures++;
}
if ( preg_match( '/<a\s+href=/i', $js ) ) {
	fwrite( STDERR, "FAIL: active-descendant listbox options must not contain nested anchor markup\n" );
	$failures++;
}
if ( false !== strpos( $search, 'foreach ( (array) $rows as $row )' ) ) {
	fwrite( STDERR, "FAIL: suggestion DB failure must not be erased by array-casting the result\n" );
	$failures++;
}

if ( $failures ) { exit( 1 ); }
echo "Second-cycle Round 14 autocomplete/IME regression passed.\n";
