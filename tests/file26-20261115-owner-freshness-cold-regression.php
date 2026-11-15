<?php
/**
 * FR006: test native read-only owner proof of a missing serving document.
 * No WordPress state, source database, plugin installer or network required.
 */
namespace Sabri\File26 {
	final class Connectors {
		public $manifest = array();
		public function get( $slug ) { return $this->manifest; }
	}
}
namespace {
	define( 'ABSPATH', '/' );
	function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
	function sanitize_text_field( $value ) { return trim( (string) $value ); }
	function wp_strip_all_tags( $value, $remove_breaks = false ) { return strip_tags( (string) $value ); }
	class WP_Error {
		public $code;
		public function __construct( $code, $message = '' ) { $this->code = $code; }
		public function get_error_code() { return $this->code; }
	}
	function is_wp_error( $value ) { return $value instanceof \WP_Error; }
	function assert_cold( $yes, $what ) {
		if ( ! $yes ) { fwrite( STDERR, 'FAIL: ' . $what . "\n" ); exit( 1 ); }
	}
	require dirname( __DIR__ ) . '/includes/class-file26-owner-freshness.php';
	$incoming = array(
		'connector_slug' => 'file21-publication',
		'domain_name' => 'posts',
		'object_id' => 'posted-501',
		'object_version' => 3,
		'source_event_sequence' => 6,
		'entity_type' => 'article',
		'state' => 'published',
		'visibility' => 'public',
		'locale' => 'en-US',
		'title' => 'A Current Article',
		'excerpt' => 'Safe public summary',
		'canonical_url' => 'https://example.test/articles/501',
	);
	$current = $incoming;
	unset( $current['domain_name'] );
	$current['domain'] = 'posts';
	$owner = new \Sabri\File26\Connectors();
	$owner->manifest = array(
		'status' => 'active',
		'fetch_object' => static function () use ( &$current ) { return $current; },
	);
	$verify = static function () use ( $owner, $incoming ) {
		return \Sabri\File26\Owner_Freshness::attest_cold_document( $owner, $incoming );
	};
	assert_cold( true === $verify(), 'same-current canonical owner accepted' );
	$current['object_version'] = 2;
	assert_cold( is_wp_error( $verify() ) && 'file26_cold_owner_version_conflict' === $verify()->get_error_code(), 'old version refused' );
	$current['object_version'] = 4;
	assert_cold( is_wp_error( $verify() ), 'future owner version cannot admit old replay' );
	$current['object_version'] = 3;
	$current['title'] = 'New Display Name';
	assert_cold( 'file26_cold_owner_projection_conflict' === $verify()->get_error_code(), 'unchanged version but revised title cannot reindex stale content' );
	$current['title'] = $incoming['title'];
	$current['state'] = 'deleted';
	assert_cold( 'file26_cold_owner_projection_conflict' === $verify()->get_error_code(), 'owner withdrawal cannot resurrect a document' );
	$current['state'] = 'published';
	$current['visibility'] = 'restricted';
	assert_cold( is_wp_error( $verify() ), 'rights revocation cannot resurrect document' );
	$current['visibility'] = 'public';
	$current['source_event_sequence'] = 7;
	assert_cold( 'file26_cold_owner_event_conflict' === $verify()->get_error_code(), 'owner event sequence cannot lag' );
	$current['source_event_sequence'] = 6;
	$current['object_version'] = '999999999999999999999999999999999999';
	assert_cold( 'file26_cold_owner_version_conflict' === $verify()->get_error_code(), 'overflowed owner versions cannot cast to PHP_INT_MAX' );
	$current['object_version'] = 3;
	$current['canonical_url'] = 'https://example.test/changed-destination';
	assert_cold( 'file26_cold_owner_projection_conflict' === $verify()->get_error_code(), 'old target URL cannot be revived' );
	$current['canonical_url'] = $incoming['canonical_url'];
	$current['excerpt'] = 'Different public summary';
	assert_cold( 'file26_cold_owner_projection_conflict' === $verify()->get_error_code(), 'same-version changed summary cannot revive old content' );
	$current['excerpt'] = $incoming['excerpt'];
	$owner->manifest['fetch_object'] = static function () { return null; };
	assert_cold( 'file26_cold_owner_attestation_denied' === $verify()->get_error_code(), 'owner deletion null refuses stale replay' );
	$owner->manifest['fetch_object'] = static function () { throw new \RuntimeException( 'owner outage' ); };
	assert_cold( 'file26_cold_owner_fetch_failed' === $verify()->get_error_code(), 'owner outage is typed denial, not authorized' );
	unset( $owner->manifest['fetch_object'] );
	assert_cold( 'file26_cold_owner_attestation_unavailable' === $verify()->get_error_code(), 'old connector without owner fetch cannot ingest cold replay' );
	$owner->manifest['status'] = 'retired';
	assert_cold( 'file26_cold_owner_attestation_unavailable' === $verify()->get_error_code(), 'retired connector cannot claim fresh owner proof' );
	echo "PASS: FR006 canonical current cold-owner proof prevents expired-tombstone replay without permanent deleted identifiers.\n";
}
