<?php
/** Round 09 regression: sensitive/session-bound REST responses must not be publicly cacheable and taxonomy governance surfaces must be reachable. */
$root = dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-file26-rest.php' );
$checks = array(
	"! $this->security->contains_sensitive_query( $query )" => 'search/suggest HTTP cache excludes sensitive queries',
	"! is_user_logged_in() && empty( $session_topics )" => 'session-context discovery is never public-cacheable',
	"/merge-preview" => 'taxonomy merge preview route exists',
	"'merge_term'" => 'taxonomy merge execution route exists',
	"/split-preview" => 'taxonomy split preview route exists',
	"'active' === $redirect['status']" => 'merged topic redirect target must still be active',
);
$failures = 0;
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "FAIL: $label\n" );
		$failures++;
	}
}
if ( substr_count( $source, "! $this->security->contains_sensitive_query( $query )" ) < 2 ) {
	fwrite( STDERR, "FAIL: both search and suggest must suppress public caching for sensitive queries\n" );
	$failures++;
}
if ( $failures ) { exit( 1 ); }
echo "Round 09 REST cache/governance regression passed.\n";
