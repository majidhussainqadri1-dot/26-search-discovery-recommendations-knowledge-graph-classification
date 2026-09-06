<?php
/** Second-cycle Round 7 regression: blocked safety classes never surface and first-page concentration caps are hard. */
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-file26-normalizer.php';
require dirname( __DIR__ ) . '/includes/class-file26-ranking.php';

use Sabri\File26\Normalizer;
use Sabri\File26\Ranking;

$n = new Normalizer();
$r = new Ranking( $n );
$base = array(
	'connector_slug' => 'source-a',
	'author_key' => 'author-a',
	'normalized_title' => 'جگر سوزش',
	'normalized_body' => 'تعلیمی مواد',
	'authority_score' => 0.9,
	'quality_score' => 0.9,
	'popularity_score' => 10,
	'freshness_at' => gmdate( 'Y-m-d H:i:s' ),
	'entity_type' => 'article',
	'safety_class' => 'general',
	'payload' => array(),
);
$docs = array();
for ( $i = 0; $i < 5; $i++ ) {
	$doc = $base;
	$doc['canonical_key'] = hash( 'sha256', 'safe-' . $i );
	$doc['author_key'] = 'author-' . $i;
	$doc['connector_slug'] = 'source-' . $i;
	$docs[] = $doc;
}
$blocked = $base;
$blocked['canonical_key'] = hash( 'sha256', 'blocked' );
$blocked['safety_class'] = 'blocked';
$blocked['authority_score'] = 1;
$blocked['quality_score'] = 1;
$restricted = $base;
$restricted['canonical_key'] = hash( 'sha256', 'restricted' );
$restricted['safety_class'] = 'restricted';
$docs[] = $blocked;
$docs[] = $restricted;
$ranked = $r->sort_and_diversify( $docs, 'جگر', 20 );
$keys = array_column( $ranked, 'canonical_key' );
if ( in_array( $blocked['canonical_key'], $keys, true ) || in_array( $restricted['canonical_key'], $keys, true ) ) {
	fwrite( STDERR, "FAIL: blocked/restricted safety classes surfaced in ranked output\n" );
	exit( 1 );
}

$concentrated = array();
for ( $i = 0; $i < 12; $i++ ) {
	$doc = $base;
	$doc['canonical_key'] = hash( 'sha256', 'same-author-' . $i );
	$doc['author_key'] = 'same-author';
	$doc['connector_slug'] = 'same-connector';
	$concentrated[] = $doc;
}
$limited = $r->sort_and_diversify( $concentrated, 'جگر', 20 );
if ( count( $limited ) > 3 ) {
	fwrite( STDERR, "FAIL: first-page author concentration cap was softened by deferred overflow\n" );
	exit( 1 );
}
foreach ( $limited as $row ) {
	if ( 'same-author' !== $row['author_key'] || 'same-connector' !== $row['connector_slug'] ) {
		fwrite( STDERR, "FAIL: concentration fixture changed unexpectedly\n" );
		exit( 1 );
	}
}

echo "Second-cycle Round 07 ranking safety/diversity regression passed.\n";
