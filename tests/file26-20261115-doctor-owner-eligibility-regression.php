<?php
/**
 * Source-only regression: direct doctor ranking must not bypass current File07
 * ownership and current eligibility after revocation or retirement.
 */
namespace Sabri\File26 {
	final class DB { public static function table( $name ) { return 'wp_f26_' . $name; } }
	final class Security {
		public $audienceAvailable = true;
		public function audience() { return $this->audienceAvailable ? array( 'authenticated' => false, 'valid' => false ) : null; }
	}
	final class Connectors {
		public $mode = 'allow';
		public $calls = 0;
		public function get( $slug ) {
			if ( 'missing' === $this->mode ) { return null; }
			$owner = 'wrong_owner' === $this->mode ? 'File 03' : 'File 07';
			$manifest = array( 'status' => 'active', 'owner_file' => $owner );
			if ( 'no_callback' !== $this->mode ) { $manifest['can_view'] = array( $this, 'can_view' ); }
			return $manifest;
		}
		public function can_view( $slug, array $document, array $audience ) {
			$this->calls++;
			if ( 'outage' === $this->mode ) { throw new \RuntimeException( 'private owner DB unavailable' ); }
			if ( 'deny' === $this->mode ) { return false; }
			if ( 'string_true' === $this->mode ) { return 'true'; }
			if ( 'second_denied' === $this->mode ) { return 2 !== (int) $document['object_version']; }
			return isset( $document['payload']['verified_doctor'] ) && true === $document['payload']['verified_doctor'];
		}
	}
}
namespace {
	define( 'ABSPATH', '/' );
	define( 'ARRAY_A', 'ARRAY_A' );
	class WP_Error {
		private $code;
		public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; }
		public function get_error_code() { return $this->code; }
	}
	function is_wp_error( $x ) { return $x instanceof \WP_Error; }
	$wpdb = new class {
		public $last_error = '';
		public $rows = array();
		public $failRead = false;
		public $lastQuery = '';
		public function get_results( $sql, $mode ) {
			$this->lastQuery = (string) $sql;
			if ( $this->failRead ) { $this->last_error = 'private owner SQL error'; return array(); }
			return $this->rows;
		}
	};
	$GLOBALS['wpdb'] = $wpdb;
	require dirname( __DIR__ ) . '/includes/class-file26-doctor-ranking.php';

	function must( $yes, $message ) {
		if ( ! $yes ) { fwrite( STDERR, 'FAIL: ' . $message . "\n" ); exit( 1 ); }
	}
	$security = new \Sabri\File26\Security();
	$connectors = new \Sabri\File26\Connectors();
	$ranking = new \Sabri\File26\Doctor_Ranking( $security, $connectors );
	$reader = new \ReflectionMethod( $ranking, 'eligible_rows' );
	$reader->setAccessible( true );

	$document = array(
		'canonical_key' => str_repeat( 'a', 64 ),
		'connector_slug' => 'file07-doctor-directory',
		'domain_name' => 'doctors',
		'object_id' => 'public-doctor-001',
		'object_version' => 1,
		'entity_type' => 'doctor_directory_projection',
		'state' => 'published',
		'visibility' => 'public',
		'title' => 'Approved example doctor',
		'canonical_url' => 'https://example.test/doctors/1',
		'country' => 'PK',
		'location' => 'Gujrat',
		'locale' => 'en_US',
		'topic_ids' => '[]',
		'payload' => '{"verified_doctor":true}',
	);
	$wpdb->rows = array( $document );
	$allowed = $reader->invoke( $ranking );
	must( is_array( $allowed ) && 1 === count( $allowed ) && 1 === $connectors->calls, 'current native File07 owner approves one public record' );
	must( false !== strpos( $wpdb->lastQuery, 'd.object_version' ) && false !== strpos( $wpdb->lastQuery, "c.owner_file='File 07'" ), 'owner version and File07 source must be included in SQL projection' );

	$connectors->mode = 'deny';
	$denied = $reader->invoke( $ranking );
	must( is_wp_error( $denied ) && 'file26_doctor_owner_not_current' === $denied->get_error_code(), 'stale/revoked owner doctor must never be served' );
	foreach ( array( 'missing', 'no_callback', 'wrong_owner' ) as $mode ) {
		$connectors->mode = $mode;
		$unavailable = $reader->invoke( $ranking );
		must( is_wp_error( $unavailable ) && 'file26_doctor_owner_unavailable' === $unavailable->get_error_code(), 'untrusted owner manifest must fail closed: ' . $mode );
	}
	$connectors->mode = 'string_true';
	$wrongType = $reader->invoke( $ranking );
	must( is_wp_error( $wrongType ) && 'file26_doctor_owner_not_current' === $wrongType->get_error_code(), 'string-shaped owner permission must not count as true' );

	$connectors->mode = 'allow';
	$legacyPureHelper = new \Sabri\File26\Doctor_Ranking( $security );
	$noRegistry = $reader->invoke( $legacyPureHelper );
	must( is_wp_error( $noRegistry ) && 'file26_doctor_owner_unavailable' === $noRegistry->get_error_code(), 'legacy one-argument constructor cannot serve an unverified ranking' );

	$connectors->mode = 'outage';
	$outage = $reader->invoke( $ranking );
	must( is_wp_error( $outage ) && 'file26_doctor_owner_read_failed' === $outage->get_error_code(), 'owner DB/callback outage must fail closed' );

	$connectors->mode = 'allow';
	$wpdb->rows = array( array_merge( $document, array( 'payload' => '{broken-json' ) ) );
	$malformed = $reader->invoke( $ranking );
	must( is_wp_error( $malformed ) && 'file26_doctor_owner_projection_invalid' === $malformed->get_error_code(), 'malformed indexed doctor projection must fail closed' );

	$wpdb->rows = array( $document, array_merge( $document, array( 'object_version' => 2, 'canonical_key' => str_repeat( 'b', 64 ) ) ) );
	$connectors->mode = 'second_denied';
	$partial = $reader->invoke( $ranking );
	must( is_wp_error( $partial ) && 'file26_doctor_owner_not_current' === $partial->get_error_code(), 'one revoked doctor may not produce a falsely complete Top10' );

	$wpdb->rows = array( $document );
	$connectors->mode = 'allow';
	$security->audienceAvailable = false;
	$badAudience = $reader->invoke( $ranking );
	must( is_wp_error( $badAudience ) && 'file26_doctor_owner_audience_unknown' === $badAudience->get_error_code(), 'missing audience context fails closed' );
	$security->audienceAvailable = true;

	$wpdb->failRead = true;
	$dbFailure = $reader->invoke( $ranking );
	must( is_wp_error( $dbFailure ) && 'file26_doctor_ranking_read_failed' === $dbFailure->get_error_code(), 'empty-on-SQL-failure must not impersonate zero eligible doctors' );
	$wpdb->failRead = false;
	$wpdb->rows = array();
	$empty = $reader->invoke( $ranking );
	must( is_array( $empty ) && 0 === count( $empty ), 'genuinely empty owner candidate set remains a truthful empty list' );
	echo "PASS: File26 direct doctor ranking checks current native File07 owner for every candidate.\n";
}
