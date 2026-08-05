<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

final class Normalizer {
	private $arabic_map = array(
		'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی',
		'ك' => 'ک', 'ۀ' => 'ہ', 'ة' => 'ہ',
		'ؤ' => 'و', 'إ' => 'ا', 'أ' => 'ا', 'ٱ' => 'ا',
		'ـ' => '',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
	);

	public function normalize( $text ) {
		$text = is_scalar( $text ) ? (string) $text : '';
		if ( class_exists( '\Normalizer' ) ) {
			$normalized = \Normalizer::normalize( $text, \Normalizer::FORM_C );
			if ( is_string( $normalized ) ) {
				$text = $normalized;
			}
		}
		$text = strtr( $text, $this->arabic_map );
		$text = preg_replace( '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = preg_replace( '/[^\p{L}\p{N}\s\-]+/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', trim( $text ) );
		return $text;
	}

	public function tokens( $text ) {
		$normalized = $this->normalize( $text );
		if ( '' === $normalized ) {
			return array();
		}
		$tokens = preg_split( '/\s+/u', $normalized );
		$stopwords = array( 'کی', 'کے', 'کا', 'کو', 'میں', 'اور', 'ہے', 'ہیں', 'the', 'of', 'and', 'in', 'to' );
		$tokens = array_values( array_unique( array_filter( $tokens, static function ( $token ) use ( $stopwords ) {
			$long_enough = function_exists( 'mb_strlen' ) ? mb_strlen( $token, 'UTF-8' ) >= 2 : strlen( $token ) >= 2;
			return $long_enough && ! in_array( $token, $stopwords, true );
		} ) ) );
		return array_slice( $tokens, 0, 16 );
	}

	public function expansions( $query ) {
		$normalized = $this->normalize( $query );
		$settings = DB::settings();
		$synonyms = isset( $settings['synonyms'] ) && is_array( $settings['synonyms'] ) ? $settings['synonyms'] : array();
		$translit = isset( $settings['transliteration_aliases'] ) && is_array( $settings['transliteration_aliases'] ) ? $settings['transliteration_aliases'] : array();
		$unsafe = isset( $settings['unsafe_auto_synonyms'] ) && is_array( $settings['unsafe_auto_synonyms'] ) ? $settings['unsafe_auto_synonyms'] : array();
		$expanded = array( $normalized );
		foreach ( array( $synonyms, $translit ) as $map ) {
			foreach ( $map as $source => $targets ) {
				if ( $this->normalize( $source ) !== $normalized ) {
					continue;
				}
				foreach ( (array) $targets as $target ) {
					$target = $this->normalize( $target );
					if ( $target && ! in_array( $target, $unsafe, true ) ) {
						$expanded[] = $target;
					}
				}
			}
		}
		$expanded = apply_filters( 'sabri_file26_query_expansions', $expanded, $query, $normalized );
		return array_slice( array_values( array_unique( array_filter( $expanded ) ) ), 0, 8 );
	}

	public function prefix_is_safe( $prefix ) {
		$prefix = $this->normalize( $prefix );
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $prefix, 'UTF-8' ) < 2 : strlen( $prefix ) < 2 ) {
			return false;
		}
		return ! preg_match( '/(?:\d{7,}|@|password|otp|cnic|شناختی|فون)/iu', $prefix );
	}
}
