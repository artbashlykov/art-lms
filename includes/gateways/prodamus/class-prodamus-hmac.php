<?php
/**
 * Prodamus HMAC signature helper.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Prodamus_Hmac
 */
class Art_LMS_Prodamus_Hmac {

	/**
	 * Create request signature.
	 *
	 * @param array  $data   Payload.
	 * @param string $secret Secret key.
	 * @return string
	 */
	public static function create( array $data, $secret ) {
		return hash_hmac( 'sha256', self::encode( $data ), (string) $secret );
	}

	/**
	 * Verify incoming signature.
	 *
	 * @param array  $data   Payload.
	 * @param string $secret Secret key.
	 * @param string $sign   Received signature.
	 * @return bool
	 */
	public static function verify( array $data, $secret, $sign ) {
		$sign = strtolower( trim( (string) $sign ) );

		if ( '' === $sign ) {
			return false;
		}

		foreach ( self::get_json_encode_flag_sets() as $flags ) {
			$expected = hash_hmac( 'sha256', self::encode( $data, $flags ), (string) $secret );

			if ( hash_equals( $expected, $sign ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Encode payload for signing.
	 *
	 * @param array $data  Payload.
	 * @param int   $flags json_encode flags.
	 * @return string
	 */
	public static function encode( array $data, $flags = JSON_UNESCAPED_UNICODE ) {
		$normalized = self::normalize( $data );

		return wp_json_encode( $normalized, $flags );
	}

	/**
	 * JSON flag combinations used when verifying provider signatures.
	 *
	 * @return int[]
	 */
	private static function get_json_encode_flag_sets() {
		return array(
			JSON_UNESCAPED_UNICODE,
			0,
		);
	}

	/**
	 * Recursively sort and stringify payload values.
	 *
	 * @param array $data Payload.
	 * @return array
	 */
	private static function normalize( array $data ) {
		$normalized = array();

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( self::is_list( $value ) ) {
					$normalized[ $key ] = array_map(
						static function ( $item ) {
							return is_array( $item ) ? self::normalize( $item ) : (string) $item;
						},
						array_values( $value )
					);
				} else {
					$normalized[ $key ] = self::normalize( $value );
				}
			} else {
				$normalized[ $key ] = (string) $value;
			}
		}

		ksort( $normalized, SORT_STRING );

		return $normalized;
	}

	/**
	 * Whether array is a list.
	 *
	 * @param array $value Array value.
	 * @return bool
	 */
	private static function is_list( array $value ) {
		if ( array() === $value ) {
			return true;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
