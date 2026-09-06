<?php
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'ARRAY_A', 'ARRAY_A' );
	$GLOBALS['f26_test_logged_in'] = false;
	$GLOBALS['f26_test_filter_values'] = array();
	$GLOBALS['wpdb'] = new class {
		public $row = null;
		public $last_error = '';
		public function prepare( $query ) { return $query; }
		public function get_row() { return $this->row; }
	};
	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private $code;
			private $message;
			private $data;
			public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
			public function get_error_code() { return $this->code; }
			public function get_error_message() { return $this->message; }
			public function get_error_data() { return $this->data; }
		}
	}
	function is_wp_error( $thing ) { return $thing instanceof \WP_Error; }
	function __( $text ) { return $text; }
	function apply_filters( $tag, $value ) { return array_key_exists( $tag, $GLOBALS['f26_test_filter_values'] ) ? $GLOBALS['f26_test_filter_values'][ $tag ] : $value; }
	function is_user_logged_in() { return (bool) $GLOBALS['f26_test_logged_in']; }
	function get_current_user_id() { return $GLOBALS['f26_test_logged_in'] ? 10 : 0; }
	function wp_get_current_user() { return (object) array( 'roles' => array( 'subscriber' ) ); }
	function current_user_can() { return false; }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
}

namespace Sabri\File26 {
	final class DB {
		public static function settings() { return array( 'synonyms' => array(), 'transliteration_aliases' => array(), 'unsafe_auto_synonyms' => array() ); }
		public static function setting( $key, $default = null ) { return $default; }
		public static function table( $name ) { return 'wp_f26_' . $name; }
	}
}
