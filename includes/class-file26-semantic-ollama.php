<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/**
 * Optional genuine local Ollama embedding reranker.
 *
 * No external network, no model downloads, no personal/member content and
 * no credentials. Disabled unless explicitly configured in wp-config.php:
 *
 * define('SABRI_FILE26_SEMANTIC_OLLAMA_ENABLED', true);
 * define('SABRI_FILE26_SEMANTIC_OLLAMA_MODEL', 'bge-m3');
 *
 * Ollama must be independently running on 127.0.0.1:11434 and the model
 * installed. Hosting/staging, model rights and relevance acceptance remain
 * outside this source-code contract.
 */
final class Semantic_Ollama {
	const ENDPOINT = 'http://127.0.0.1:11434/api/embed';

	public static function register() {
		add_filter( 'sabri_file26_semantic_reranker', array( __CLASS__, 'rerank' ), 30, 4 );
	}

	private static function enabled() {
		if ( ! defined( 'SABRI_FILE26_SEMANTIC_OLLAMA_ENABLED' ) ||
			true !== SABRI_FILE26_SEMANTIC_OLLAMA_ENABLED ||
			! defined( 'SABRI_FILE26_SEMANTIC_OLLAMA_MODEL' ) ) {
			return false;
		}
		$model = SABRI_FILE26_SEMANTIC_OLLAMA_MODEL;
		return is_string( $model ) && in_array( $model, array( 'bge-m3', 'embeddinggemma', 'qwen3-embedding:0.6b' ), true ) &&
			function_exists( 'wp_remote_post' );
	}

	private static function bounded_text( $text, $length ) {
		$text = wp_strip_all_tags( (string) $text, true );
		return function_exists( 'mb_substr' )
			? mb_substr( $text, 0, (int) $length, 'UTF-8' )
			: substr( $text, 0, (int) $length );
	}

	private static function has_sensitive_context( $text ) {
		$text = strtolower( (string) $text );
		foreach ( array(
			'patient', 'passport', 'cnic', 'phone number', 'medical record',
			'prescribe', 'what potency', 'dose', 'diagnose',
			'مریض', 'شناختی کارڈ', 'فون نمبر', 'نسخہ', 'پوٹینسی', 'تشخیص',
		) as $word ) {
			if ( false !== strpos( $text, $word ) ) { return true; }
		}
		return false;
	}

	/**
	 * Only reorders the pre-authorized exact candidate set; no vector result
	 * can create a File26 document, bypass owner ACL or write to live indexes.
	 */
	public static function rerank( $existing, $query, $candidates, $policy = array() ) {
		if ( ! self::enabled() || is_user_logged_in() || ! is_array( $candidates ) ||
			count( $candidates ) < 1 || count( $candidates ) > 30 ) {
			return is_array( $existing ) ? $existing : array();
		}
		$q = self::bounded_text( $query, 240 );
		if ( '' === trim( $q ) || self::has_sensitive_context( $q ) ||
			! is_array( $policy ) || empty( $policy['safety_gates_immutable'] ) ||
			empty( $policy['visibility_gates_immutable'] ) ) {
			return is_array( $existing ) ? $existing : array();
		}

		$items = array(); $keys = array();
		foreach ( array_slice( $candidates, 0, 12 ) as $candidate ) {
			if ( ! is_array( $candidate ) ) { continue; }
			$key = isset( $candidate['key'] ) ? (string) $candidate['key'] : '';
			if ( ! preg_match( '/^[a-f0-9]{64}$/D', $key ) || isset( $keys[ $key ] ) ) { continue; }
			$title = self::bounded_text( $candidate['title'] ?? '', 200 );
			$excerpt = self::bounded_text( $candidate['excerpt'] ?? '', 500 );
			$summary = trim( $title . ' ' . $excerpt );
			if ( '' === $summary || self::has_sensitive_context( $summary ) ) { continue; }
			$items[] = $summary;
			$keys[ $key ] = count( $items );
		}
		if ( ! $items ) { return is_array( $existing ) ? $existing : array(); }

		$body = wp_json_encode( array(
			'model' => SABRI_FILE26_SEMANTIC_OLLAMA_MODEL,
			'input' => array_merge( array( $q ), $items ),
			'truncate' => true,
		) );
		if ( ! is_string( $body ) || strlen( $body ) > 13000 ) { return array(); }
		$response = wp_remote_post( self::ENDPOINT, array(
			'method' => 'POST',
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body' => $body,
			'timeout' => 8,
			'redirection' => 0,
			'blocking' => true,
			'limit_response_size' => 250000,
		) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { return array(); }
		$raw = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $raw ) > 250000 ) { return array(); }
		$parsed = json_decode( $raw, true );
		$vectors = is_array( $parsed ) ? ( $parsed['embeddings'] ?? null ) : null;
		if ( ! is_array( $vectors ) || count( $vectors ) !== count( $items ) + 1 ) { return array(); }
		$dimensions = 0; $norms = array();
		foreach ( $vectors as $vector ) {
			if ( ! is_array( $vector ) ) { return array(); }
			if ( 0 === $dimensions ) {
				$dimensions = count( $vector );
				if ( $dimensions < 128 || $dimensions > 2048 ) { return array(); }
			} elseif ( count( $vector ) !== $dimensions ) { return array(); }
			$sum = 0.0;
			foreach ( $vector as $coordinate ) {
				if ( ! is_int( $coordinate ) && ! is_float( $coordinate ) ) { return array(); }
				$value = (float) $coordinate;
				if ( ! is_finite( $value ) || abs( $value ) > 1000000 ) { return array(); }
				$sum += $value * $value;
			}
			if ( ! is_finite( $sum ) || $sum <= 0 ) { return array(); }
			$norms[] = sqrt( $sum );
		}
		$assigned = array();
		$j = 1;
		foreach ( $keys as $key => $position ) {
			$vector = $vectors[ $j ];
			$dot = 0.0;
			for ( $i = 0; $i < $dimensions; $i++ ) {
				$dot += (float) $vectors[0][$i] * (float) $vector[$i];
			}
			$score = $dot / ( $norms[0] * $norms[$j] );
			if ( ! is_finite( $score ) ) { return array(); }
			$assigned[ $key ] = max( -1.0, min( 1.0, $score ) );
			$j++;
		}
		return $assigned;
	}
}
