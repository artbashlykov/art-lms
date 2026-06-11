<?php
/**
 * Cyrillic to Latin transliteration helpers.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Transliteration
 */
class Art_LMS_Transliteration {

	/**
	 * Ordered Cyrillic to Latin replacement pairs.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function get_replacement_pairs() {
		return array(
			array( 'Щ', 'sch' ),
			array( 'щ', 'sch' ),
			array( 'Ш', 'sh' ),
			array( 'ш', 'sh' ),
			array( 'Ч', 'ch' ),
			array( 'ч', 'ch' ),
			array( 'Ж', 'zh' ),
			array( 'ж', 'zh' ),
			array( 'Ю', 'yu' ),
			array( 'ю', 'yu' ),
			array( 'Я', 'ya' ),
			array( 'я', 'ya' ),
			array( 'Ё', 'yo' ),
			array( 'ё', 'yo' ),
			array( 'Ц', 'ts' ),
			array( 'ц', 'ts' ),
			array( 'Х', 'h' ),
			array( 'х', 'h' ),
			array( 'Ъ', '' ),
			array( 'ъ', '' ),
			array( 'Ь', '' ),
			array( 'ь', '' ),
			array( 'Э', 'e' ),
			array( 'э', 'e' ),
			array( 'А', 'a' ),
			array( 'а', 'a' ),
			array( 'Б', 'b' ),
			array( 'б', 'b' ),
			array( 'В', 'v' ),
			array( 'в', 'v' ),
			array( 'Г', 'g' ),
			array( 'г', 'g' ),
			array( 'Д', 'd' ),
			array( 'д', 'd' ),
			array( 'Е', 'e' ),
			array( 'е', 'e' ),
			array( 'З', 'z' ),
			array( 'з', 'z' ),
			array( 'И', 'i' ),
			array( 'и', 'i' ),
			array( 'Й', 'y' ),
			array( 'й', 'y' ),
			array( 'К', 'k' ),
			array( 'к', 'k' ),
			array( 'Л', 'l' ),
			array( 'л', 'l' ),
			array( 'М', 'm' ),
			array( 'м', 'm' ),
			array( 'Н', 'n' ),
			array( 'н', 'n' ),
			array( 'О', 'o' ),
			array( 'о', 'o' ),
			array( 'П', 'p' ),
			array( 'п', 'p' ),
			array( 'Р', 'r' ),
			array( 'р', 'r' ),
			array( 'С', 's' ),
			array( 'с', 's' ),
			array( 'Т', 't' ),
			array( 'т', 't' ),
			array( 'У', 'u' ),
			array( 'у', 'u' ),
			array( 'Ф', 'f' ),
			array( 'ф', 'f' ),
			array( 'Ы', 'y' ),
			array( 'ы', 'y' ),
		);
	}

	/**
	 * Transliterate Russian/Cyrillic text to Latin letters.
	 *
	 * @param string $text Source text.
	 * @return string
	 */
	public static function ru_to_latin( $text ) {
		$text = (string) $text;

		if ( '' === $text ) {
			return '';
		}

		foreach ( self::get_replacement_pairs() as $pair ) {
			$text = str_replace( $pair[0], $pair[1], $text );
		}

		return $text;
	}

	/**
	 * Whether text contains Cyrillic characters.
	 *
	 * @param string $text Source text.
	 * @return bool
	 */
	public static function contains_cyrillic( $text ) {
		return (bool) preg_match( '/[\x{0400}-\x{04FF}]/u', (string) $text );
	}

	/**
	 * Whether slug is a WordPress-style UTF-8 hex encoding (d0-bc-d0-b8-...).
	 *
	 * @param string $slug Post slug.
	 * @return bool
	 */
	public static function is_hex_utf8_slug( $slug ) {
		return '' !== self::decode_hex_utf8_slug( (string) $slug );
	}

	/**
	 * Whether slug is a readable Latin slug.
	 *
	 * @param string $slug Post slug.
	 * @return bool
	 */
	public static function is_valid_latin_slug( $slug ) {
		$slug = (string) $slug;

		if ( '' === $slug ) {
			return false;
		}

		return (bool) preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug );
	}

	/**
	 * Whether slug should be regenerated from title/source text.
	 *
	 * @param string $slug Post slug.
	 * @return bool
	 */
	public static function needs_latin_slug_fix( $slug ) {
		$slug = (string) $slug;

		if ( '' === $slug ) {
			return true;
		}

		if ( self::is_valid_latin_slug( $slug ) ) {
			return false;
		}

		return self::contains_cyrillic( $slug ) || self::is_hex_utf8_slug( $slug );
	}

	/**
	 * Decode WordPress hex-encoded UTF-8 slug back to text.
	 *
	 * @param string $slug Post slug.
	 * @return string
	 */
	public static function decode_hex_utf8_slug( $slug ) {
		$slug = strtolower( trim( (string) $slug, " \t\n\r\0\x0B-" ) );

		if ( '' === $slug ) {
			return '';
		}

		if ( ! preg_match( '/^(?:[0-9a-f]{2})(?:-[0-9a-f]{2})+$/', $slug ) ) {
			return '';
		}

		$hex = str_replace( '-', '', $slug );

		if ( strlen( $hex ) % 2 !== 0 || ! ctype_xdigit( $hex ) ) {
			return '';
		}

		if ( ! function_exists( 'hex2bin' ) ) {
			return '';
		}

		$binary = hex2bin( $hex );

		if ( false === $binary || '' === $binary ) {
			return '';
		}

		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $binary, 'UTF-8' ) ) {
			return '';
		}

		if ( ! self::contains_cyrillic( $binary ) ) {
			return '';
		}

		return $binary;
	}

	/**
	 * Normalize slug source before transliteration.
	 *
	 * @param string $text Source text or slug.
	 * @return string
	 */
	public static function normalize_slug_source( $text ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return '';
		}

		$decoded = self::decode_hex_utf8_slug( $text );

		if ( '' !== $decoded ) {
			return $decoded;
		}

		if ( false !== strpos( $text, '%' ) ) {
			$urldecoded = rawurldecode( $text );

			if ( self::contains_cyrillic( $urldecoded ) ) {
				return $urldecoded;
			}
		}

		return $text;
	}

	/**
	 * Build a URL slug with mandatory Cyrillic transliteration.
	 *
	 * @param string $text Source text.
	 * @return string
	 */
	public static function to_slug( $text ) {
		$text = self::normalize_slug_source( $text );

		if ( '' === $text ) {
			return '';
		}

		$latin = self::ru_to_latin( $text );
		$latin = strtolower( $latin );
		$latin = preg_replace( '/[^a-z0-9]+/', '-', $latin );

		return trim( (string) $latin, '-' );
	}
}
