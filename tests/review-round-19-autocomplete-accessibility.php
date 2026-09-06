<?php
/** Round 19 regression: autocomplete and repeated shortcode instances remain accessible and isolated. */
$root = dirname( __DIR__ );
$js = file_get_contents( $root . '/assets/js/file26.js' );
$routes = file_get_contents( $root . '/includes/class-file26-routes.php' );
$search = file_get_contents( $root . '/templates/search.php' );
$discover = file_get_contents( $root . '/templates/discover.php' );
$topic = file_get_contents( $root . '/templates/topic.php' );

$checks = array(
	array( $js, 'requestSequence += 1', 'input/blur changes invalidate prior requests' ),
	array( $js, 'sequence !== requestSequence || input.value.trim() !== value || document.activeElement !== input', 'stale or blurred suggestion response cannot render' ),
	array( $js, "event.key === 'ArrowDown'", 'ArrowDown listbox navigation' ),
	array( $js, "event.key === 'ArrowUp'", 'ArrowUp listbox navigation' ),
	array( $js, "event.key === 'Enter'", 'Enter activates current suggestion' ),
	array( $js, 'aria-activedescendant', 'active option is exposed to assistive technology' ),
	array( $js, 'aria-selected', 'listbox selection state is explicit' ),
	array( $js, 'function liveRegion(context)', 'status lookup accepts an initiating component' ),
	array( $js, "context.closest('.sabri-f26')", 'status announcements are scoped to the initiating component' ),
	array( $js, 'const live=liveRegion(button)', 'feedback uses its own component live region' ),
	array( $routes, "\$vars['instance_id'] = 'sabri-f26-' . sanitize_key( \$template ) . '-' . \$render_sequence;", 'every rendered shortcode receives a request-unique instance id' ),
	array( $search, "\$title_id = \$instance_id . '-title';", 'search heading id is instance-scoped' ),
	array( $search, "\$query_id = \$instance_id . '-q';", 'search input id is instance-scoped' ),
	array( $search, 'role="combobox"', 'search autocomplete exposes combobox semantics' ),
	array( $discover, "\$preference_id = \$instance_id . '-preference-title';", 'discover preference heading is instance-scoped' ),
	array( $discover, "\$interests_id = \$instance_id . '-interests';", 'discover interests label/input pair is instance-scoped' ),
	array( $topic, "\$title_id = \$instance_id . '-title';", 'topic heading id is instance-scoped' ),
);

$failures = 0;
foreach ( $checks as $check ) {
	if ( false === strpos( $check[0], $check[1] ) ) {
		fwrite( STDERR, 'FAIL: ' . $check[2] . "\n" );
		$failures++;
	}
}

$forbidden_fixed_ids = array(
	'id="sabri-f26-search-title"',
	'id="sabri-f26-q"',
	'id="sabri-f26-discover-title"',
	'id="sabri-f26-preference-title"',
	'id="sabri-f26-interests"',
	'id="sabri-f26-topic-title"',
);
foreach ( array( $search, $discover, $topic ) as $template_source ) {
	foreach ( $forbidden_fixed_ids as $fixed_id ) {
		if ( false !== strpos( $template_source, $fixed_id ) ) {
			fwrite( STDERR, 'FAIL: fixed duplicate-prone DOM id remains: ' . $fixed_id . "\n" );
			$failures++;
		}
	}
}

if ( $failures ) { exit( 1 ); }
echo "Round 19 frontend accessibility/isolation regression passed.\n";
