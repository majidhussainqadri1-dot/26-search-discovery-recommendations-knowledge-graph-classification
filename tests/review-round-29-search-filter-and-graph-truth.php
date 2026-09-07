<?php
/** Round 29 regression: worldwide filters, cross-language discovery, taxonomy topic truth and bounded graph signals. */
$root = dirname( __DIR__ );
$code = file_get_contents( $root . '/includes/class-file26-search.php' );
$compact = static function ( $source ) { return preg_replace( '/\s+/', '', (string) $source ); };
$c = $compact( $code );
$failures = 0;
$fail = static function ( $m ) use ( &$failures ) { fwrite( STDERR, 'FAIL: ' . $m . "\n" ); $failures++; };
$checks = array(
	'private$graph_signal_bounded=false;' => 'bounded graph-ranking state is explicit',
	'$this->graph_signal_bounded=false;' => 'graph bounded state resets per request',
	"if(\$locale&&empty(\$filters['language']))" => 'explicit content-language filter supersedes general request-locale gating',
	"if(!empty(\$filters['country'])){\$clean['country']=substr(sanitize_text_field(\$filters['country']),0,64);}" => 'country filter preserves Unicode/text semantics',
	"if(!empty(\$filters['location'])){\$clean['location']=substr(sanitize_text_field(\$filters['location']),0,191);}" => 'location filter preserves Unicode/text semantics',
	'privatefunctionfold_filter_text($value)' => 'text filter comparison has a bounded case/whitespace fold',
	"\$this->fold_filter_text(\$row['country'])!==\$this->fold_filter_text(\$filters['country'])" => 'country eligibility uses text folding',
	"\$this->fold_filter_text(\$row['location'])!==\$this->fold_filter_text(\$filters['location'])" => 'location eligibility uses text folding',
	"fc.statusIN('approved','corrected')" => 'approved/corrected classification topics participate in retrieval truth',
	'array(5001)' => 'graph scan requests a sentinel row beyond the bounded 5000-edge window',
	'count((array)$edges)>5000' => 'graph bounded state is detected',
	'$edges=array_slice($edges,0,5000);' => 'graph signal remains bounded after sentinel detection',
	"'health'=>'graph_signal_limit'" => 'bounded graph ranking is disclosed as partial state',
);
foreach ( $checks as $needle => $label ) {
	if ( false === strpos( $c, $compact( $needle ) ) ) { $fail( 'Missing search safeguard: ' . $label ); }
}
$forbidden = array(
	"foreach(array('entity_type','country','location','availability','connector','domain','topic','sort','language')as\$key)" => 'country/location must not return to sanitize_key-based generic filter normalization',
	"if(!empty(\$filters['topic'])&&!in_array((string)\$filters['topic'],(array)\$row['topic_ids_array'],true))" => 'common eligibility must not discard classification-only topic matches',
	"if(\$locale&&!in_array(\$row['locale'],array(\$locale,substr(\$locale,0,2),'und'),true))" => 'general locale gate must not override an explicit language filter',
);
foreach ( $forbidden as $needle => $label ) {
	if ( false !== strpos( $c, $compact( $needle ) ) ) { $fail( 'Forbidden stale behavior remains: ' . $label ); }
}
if ( substr_count( $c, $compact( "fc.status IN ('approved','corrected')" ) ) < 2 ) {
	$fail( 'Both lexical and semantic topic retrieval must honor approved/corrected classifications.' );
}
if ( $failures ) { exit( 1 ); }
echo "PASS: round 29 search filter, language, topic and graph partial-state truth\n";
