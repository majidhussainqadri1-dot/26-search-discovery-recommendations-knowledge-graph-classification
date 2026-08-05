<?php
namespace Sabri\File26;

defined( 'ABSPATH' ) || exit;

/**
 * Unicode-, phrase-, spelling- and transliteration-aware query normalizer.
 * The service never stores raw queries and all expansions are bounded.
 */
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

	private $urdu_roman_map = array(
		'کھ' => 'kh', 'غ' => 'gh', 'خ' => 'kh', 'چ' => 'ch', 'ش' => 'sh', 'ژ' => 'zh',
		'ث' => 's', 'ص' => 's', 'س' => 's', 'ض' => 'z', 'ظ' => 'z', 'ذ' => 'z', 'ز' => 'z',
		'ٹ' => 't', 'ت' => 't', 'ط' => 't', 'ڈ' => 'd', 'د' => 'd', 'ڑ' => 'r', 'ر' => 'r',
		'ق' => 'q', 'ک' => 'k', 'گ' => 'g', 'ف' => 'f', 'پ' => 'p', 'ب' => 'b',
		'م' => 'm', 'ن' => 'n', 'ں' => 'n', 'ل' => 'l', 'و' => 'o', 'ؤ' => 'o',
		'ی' => 'i', 'ے' => 'e', 'ہ' => 'h', 'ح' => 'h', 'ع' => 'a', 'ا' => 'a',
		'آ' => 'aa', 'ء' => '', 'ئ' => 'i', 'ج' => 'j',
	);

	private $roman_urdu_map = array(
		'kh' => 'خ', 'gh' => 'غ', 'ch' => 'چ', 'sh' => 'ش', 'zh' => 'ژ', 'ph' => 'ف',
		'aa' => 'ا', 'ee' => 'ی', 'oo' => 'و',
		'a' => 'ا', 'b' => 'ب', 'c' => 'ک', 'd' => 'د', 'e' => 'ے', 'f' => 'ف',
		'g' => 'گ', 'h' => 'ہ', 'i' => 'ی', 'j' => 'ج', 'k' => 'ک', 'l' => 'ل',
		'm' => 'م', 'n' => 'ن', 'o' => 'و', 'p' => 'پ', 'q' => 'ق', 'r' => 'ر',
		's' => 'س', 't' => 'ت', 'u' => 'و', 'v' => 'و', 'w' => 'و', 'x' => 'کس',
		'y' => 'ی', 'z' => 'ز',
	);

	public function normalize( $text ) {
		$text = is_scalar( $text ) ? (string) $text : '';
		if ( class_exists( '\\Normalizer' ) ) {
			$normalized = \Normalizer::normalize( $text, \Normalizer::FORM_C );
			if ( is_string( $normalized ) ) {
				$text = $normalized;
			}
		}
		$text = strtr( $text, $this->arabic_map );
		$text = preg_replace( '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = preg_replace( '/[^\p{L}\p{N}\s\-]+/u', ' ', $text );
		return preg_replace( '/\s+/u', ' ', trim( $text ) );
	}

	public function tokens( $text ) {
		$normalized = $this->normalize( $text );
		if ( '' === $normalized ) {
			return array();
		}
		$tokens = preg_split( '/\s+/u', $normalized );
		$stopwords = array( 'کی', 'کے', 'کا', 'کو', 'میں', 'اور', 'ہے', 'ہیں', 'the', 'of', 'and', 'in', 'to', 'a', 'an' );
		$tokens = array_values( array_unique( array_filter( $tokens, static function ( $token ) use ( $stopwords ) {
			$long_enough = function_exists( 'mb_strlen' ) ? mb_strlen( $token, 'UTF-8' ) >= 2 : strlen( $token ) >= 2;
			return $long_enough && ! in_array( $token, $stopwords, true );
		} ) ) );
		return array_slice( $tokens, 0, 20 );
	}

	/** Return normalized quoted phrases; an unquoted multi-word query is also a soft phrase. */
	public function phrases( $query ) {
		$query = is_scalar( $query ) ? (string) $query : '';
		$phrases = array();
		if ( preg_match_all( '/["“”](.+?)["“”]/u', $query, $matches ) ) {
			foreach ( $matches[1] as $phrase ) {
				$phrase = $this->normalize( $phrase );
				if ( $phrase && count( $this->tokens( $phrase ) ) >= 2 ) {
					$phrases[] = $phrase;
				}
			}
		}
		$normalized = $this->normalize( $query );
		if ( ! $phrases && count( $this->tokens( $normalized ) ) >= 2 ) {
			$phrases[] = $normalized;
		}
		return array_slice( array_values( array_unique( $phrases ) ), 0, 4 );
	}

	public function expansions( $query ) {
		$normalized = $this->normalize( $query );
		$settings = DB::settings();
		$synonyms = isset( $settings['synonyms'] ) && is_array( $settings['synonyms'] ) ? $settings['synonyms'] : array();
		$translit = isset( $settings['transliteration_aliases'] ) && is_array( $settings['transliteration_aliases'] ) ? $settings['transliteration_aliases'] : array();
		$unsafe = isset( $settings['unsafe_auto_synonyms'] ) && is_array( $settings['unsafe_auto_synonyms'] ) ? array_map( array( $this, 'normalize' ), $settings['unsafe_auto_synonyms'] ) : array();
		$expanded = array( $normalized );

		if ( preg_match( '/[\x{0600}-\x{06FF}]/u', $normalized ) ) {
			$expanded[] = $this->romanize( $normalized );
		} elseif ( preg_match( '/^[a-z0-9\s\-]+$/', $normalized ) ) {
			$expanded[] = $this->roman_to_urdu( $normalized );
		}

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
		return array_slice( array_values( array_unique( array_filter( $expanded ) ) ), 0, 12 );
	}

	/**
	 * Bounded SQL retrieval patterns. Prefix patterns provide typo tolerance;
	 * final acceptance is decided by Ranking::token_similarity().
	 */
	public function retrieval_terms( $query ) {
		$terms = array();
		foreach ( $this->expansions( $query ) as $expansion ) {
			foreach ( $this->tokens( $expansion ) as $token ) {
				$terms[] = $token;
				$length = function_exists( 'mb_strlen' ) ? mb_strlen( $token, 'UTF-8' ) : strlen( $token );
				if ( $length >= 5 ) {
					$terms[] = function_exists( 'mb_substr' ) ? mb_substr( $token, 0, 3, 'UTF-8' ) : substr( $token, 0, 3 );
				}
			}
		}
		return array_slice( array_values( array_unique( array_filter( $terms ) ) ), 0, 24 );
	}

	public function token_similarity( $left, $right ) {
		$left = $this->normalize( $left );
		$right = $this->normalize( $right );
		if ( '' === $left || '' === $right ) {
			return 0.0;
		}
		if ( $left === $right ) {
			return 1.0;
		}
		if ( false !== $this->strpos( $left, $right ) || false !== $this->strpos( $right, $left ) ) {
			return 0.88;
		}
		// levenshtein is byte-based; use it only for bounded ASCII transliterations.
		$a = preg_replace( '/[^a-z0-9]/', '', $this->romanize( $left ) );
		$b = preg_replace( '/[^a-z0-9]/', '', $this->romanize( $right ) );
		if ( '' === $a || '' === $b ) {
			return 0.0;
		}
		$max = max( strlen( $a ), strlen( $b ) );
		if ( $max > 64 ) {
			return 0.0;
		}
		return max( 0.0, 1.0 - ( levenshtein( $a, $b ) / $max ) );
	}

	public function prefix_is_safe( $prefix ) {
		$prefix = $this->normalize( $prefix );
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $prefix, 'UTF-8' ) < 2 : strlen( $prefix ) < 2 ) {
			return false;
		}
		return ! preg_match( '/(?:\d{7,}|@|password|otp|cnic|شناختی|فون)/iu', $prefix );
	}

	private function romanize( $text ) {
		$text = strtr( $this->normalize( $text ), $this->urdu_roman_map );
		$text = preg_replace( '/[^a-z0-9\s\-]+/', '', strtolower( $text ) );
		return preg_replace( '/\s+/', ' ', trim( $text ) );
	}

	private function roman_to_urdu( $text ) {
		$text = strtolower( $this->normalize( $text ) );
		$out = '';
		$length = strlen( $text );
		for ( $i = 0; $i < $length; $i++ ) {
			if ( ' ' === $text[ $i ] || '-' === $text[ $i ] ) {
				$out .= $text[ $i ];
				continue;
			}
			$pair = $i + 1 < $length ? substr( $text, $i, 2 ) : '';
			if ( $pair && isset( $this->roman_urdu_map[ $pair ] ) ) {
				$out .= $this->roman_urdu_map[ $pair ];
				$i++;
				continue;
			}
			$out .= isset( $this->roman_urdu_map[ $text[ $i ] ] ) ? $this->roman_urdu_map[ $text[ $i ] ] : '';
		}
		return $this->normalize( $out );
	}

	private function strpos( $haystack, $needle ) {
		if ( function_exists( 'mb_strpos' ) ) {
			return mb_strpos( (string) $haystack, (string) $needle, 0, 'UTF-8' );
		}
		return strpos( (string) $haystack, (string) $needle );
	}
}
