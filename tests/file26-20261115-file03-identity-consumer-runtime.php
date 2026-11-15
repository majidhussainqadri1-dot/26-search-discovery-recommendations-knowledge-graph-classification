<?php
/**
 * Source-only File03 native outbox -> File26 current-public-owner reindex.
 * Tests idempotent two-stage direct+durable owner delivery and fail-closed
 * behavior; actual WP/MariaDB owner version tests remain independent.
 */
namespace Sabri\File26 {
	final class DB {
		public static function setting( $name, $default = false ) { return $GLOBALS['activated']; }
		public static function now() { return '2026-11-15 13:05:00'; }
	}
	class Connectors {
		public $active = false;
		public $native;
		public function is_active( $slug ) { return $this->active && 'file03-profiles' === $slug; }
		public function get( $slug ) { return $this->native; }
	}
	class Indexer {
		public $docs = array();
		public $deny = false;
		public function upsert( array $record ) {
			if ( $this->deny ) { return new \WP_Error( 'file26_write_denied' ); }
			$this->docs[] = $record;
			return hash( 'sha256', $record['object_id'] );
		}
	}
	class Security {
		public $audits = array();
		public function audit( $name, array $data ) { $this->audits[] = $name; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	class WP_Error {
		private $code;
		public function __construct( $code, $message = '' ) { $this->code = $code; }
		public function get_error_code() { return $this->code; }
	}
	function is_wp_error( $x ) { return $x instanceof \WP_Error; }
	function sanitize_key( $x ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $x ) ); }
	function update_option( $name, $value, $autoload = false ) { $GLOBALS['store'][$name] = $value; return true; }
	function delete_option( $name ) { unset( $GLOBALS['store'][$name] ); return true; }
	function verify_file03_delivery( $condition, $name ) {
		if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $name . "\n" ); exit( 1 ); }
		echo 'PASS: ' . $name . "\n";
	}
	require_once dirname( __DIR__ ) . '/includes/class-file26-file03-identity-consumer.php';
	$uuid = 'f1111111-1111-4111-8111-111111111111';
	$record = array(
		'connector_slug' => 'file03-profiles',
		'domain' => 'profiles', 'object_id' => $uuid,
		'object_version' => 15,
		'entity_type' => 'member_profile',
		'state' => 'active', 'visibility' => 'public',
		'title' => 'New approved current user', 'canonical_url' => 'https://example.test/profile/' . $uuid,
	);
	$registry = new \Sabri\File26\Connectors();
	$indexer = new \Sabri\File26\Indexer();
	$security = new \Sabri\File26\Security();
	$GLOBALS['activated'] = true;
	$GLOBALS['store'] = array();
	$current = $record;
	$registry->native = array(
		'entity_types' => array( 'founder', 'doctor', 'member_profile' ),
		'fetch_object' => static function ( $type, $id ) use ( &$current, $uuid ) {
			return is_array( $current ) && isset( $current['entity_type'] ) &&
				$id === $uuid && $type === $current['entity_type'] ? $current : null;
		},
	);
	$consumer = new \Sabri\File26\File03_Identity_Consumer( $registry, $indexer, $security );
	verify_file03_delivery( false === $consumer->refresh( $uuid, 15 ), 'Inactivated native connector performs no search mutation' );
	$registry->active = true;
	$consumer->on_profile_changed( $uuid, 15 );
	verify_file03_delivery( 1 === count( $indexer->docs ) && (int) $indexer->docs[0]['object_version'] === 15,
		'Post-commit native File03 v15 authorizes one immediate derivative upsert' );
	$event = array( 'changed_fields' => array( 'file00_public_display_name' ), 'version' => 15 );
	$consumer->on_file03_outbox( 'PublicProfileUpdated.v1', $event, array( 'aggregate_id' => $uuid ) );
	verify_file03_delivery( 2 === count( $indexer->docs ), 'Durable re-delivery of same native revision is an allowed idempotent source operation' );
	$consumer->on_file03_outbox( 'PublicProfileUpdated.v1', array(
		'changed_fields' => array( 'bio' ), 'version' => 15,
	), array( 'aggregate_id' => $uuid ) );
	verify_file03_delivery( 2 === count( $indexer->docs ), 'Unrelated owner field changes are not forged File00 name events' );
	$failed = false;
	try {
		$consumer->on_file03_outbox( 'PublicProfileUpdated.v1', array(
			'changed_fields' => array( 'file00_public_display_name' ), 'version' => 16,
		), array( 'aggregate_id' => $uuid ) );
	} catch ( \RuntimeException $e ) {
		$failed = false !== strpos( $e->getMessage(), 'file26_file03_reindex_pending' );
	}
	verify_file03_delivery( $failed && isset( $GLOBALS['store']['sabri_file26_file03_identity_refresh_error'] ),
		'Outbox v16 cannot be acknowledged while current native owner is still on v15' );
	verify_file03_delivery( false === strpos( json_encode( $GLOBALS['store'] ), $uuid ),
		'Persisted operational errors contain only a hashed profile identifier' );
	$current['object_version'] = 16;
	$current['title'] = 'Latest File00 approved public name';
	$consumer->on_file03_outbox( 'PublicProfileUpdated.v1', array(
		'changed_fields' => array( 'file00_public_display_name' ), 'version' => 16,
	), array( 'aggregate_id' => $uuid ) );
	verify_file03_delivery( 3 === count( $indexer->docs ) &&
		'Latest File00 approved public name' === $indexer->docs[2]['title'] &&
		empty( $GLOBALS['store']['sabri_file26_file03_identity_refresh_error'] ),
		'Owner new version replays successfully and clears old transient failure' );
	$indexer->deny = true;
	$failed = false;
	try {
		$consumer->on_file03_outbox( 'PublicProfileUpdated.v1', array(
			'changed_fields' => array( 'file00_public_display_name' ), 'version' => 16,
		), array( 'aggregate_id' => $uuid ) );
	} catch ( \RuntimeException $e ) { $failed = true; }
	verify_file03_delivery( $failed, 'Index transaction error must keep owner outbox in retry/dead-letter control' );
	$indexer->deny = false;
	$registry->active = false;
	$consumer->on_file03_outbox( 'PublicProfileUpdated.v1', array(
		'changed_fields' => array( 'file00_public_display_name' ), 'version' => 16,
	), array( 'aggregate_id' => $uuid ) );
	verify_file03_delivery( 3 === count( $indexer->docs ), 'Inactive owner cannot write through an unapproved File26 connector' );
	$registry->active = true;
	$current = array();
	verify_file03_delivery( 'file26_file03_public_projection_unavailable' ===
		$consumer->refresh( $uuid, 16 )->get_error_code(),
		'Missing current public owner cannot be inferred from cached File26 identity' );
	echo "PASS: File03 -> File26 native immutable revision replay/owner ACL and outbox handoff contract; no Live claims.\n";
}
