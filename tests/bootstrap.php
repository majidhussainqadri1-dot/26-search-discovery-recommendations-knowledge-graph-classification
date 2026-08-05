<?php
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	$GLOBALS['f26_test_logged_in'] = false;
	$GLOBALS['f26_test_filter_values'] = array();
	function __( $text ) { return $text; }
	function apply_filters( $tag, $value ) { return array_key_exists( $tag, $GLOBALS['f26_test_filter_values'] ) ? $GLOBALS['f26_test_filter_values'][ $tag ] : $value; }
	function is_user_logged_in() { return (bool) $GLOBALS['f26_test_logged_in']; }
	function get_current_user_id() { return $GLOBALS['f26_test_logged_in'] ? 10 : 0; }
	function wp_get_current_user() { return (object) array( 'roles' => array( 'subscriber' ) ); }
	function current_user_can() { return false; }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
}

namespace Sabri\File26 {
	final class DB {
		public static function settings() { return array( 'synonyms' => array(), 'transliteration_aliases' => array(), 'unsafe_auto_synonyms' => array() ); }
		public static function setting( $key, $default = null ) { return $default; }
	}
}
