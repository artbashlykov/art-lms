<?php
/**
 * Plisio callback signature verification.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Plisio_Callback
 */
class Art_LMS_Plisio_Callback {

	/**
	 * Verify Plisio IPN callback signature (PHP serialize + HMAC-SHA1).
	 *
	 * @param array<string, mixed> $data      Callback payload.
	 * @param string               $secret_key API secret key.
	 * @return bool
	 */
	public static function verify( array $data, $secret_key ) {
		if ( empty( $data['verify_hash'] ) || '' === trim( (string) $secret_key ) ) {
			return false;
		}

		$post        = $data;
		$verify_hash = (string) $post['verify_hash'];
		unset( $post['verify_hash'] );
		ksort( $post );

		if ( isset( $post['expire_utc'] ) ) {
			$post['expire_utc'] = (string) $post['expire_utc'];
		}

		if ( isset( $post['tx_urls'] ) ) {
			$post['tx_urls'] = html_entity_decode( stripslashes( (string) $post['tx_urls'] ) );
		}

		$check = hash_hmac( 'sha1', serialize( $post ), (string) $secret_key );

		return hash_equals( $check, $verify_hash );
	}

	/**
	 * Whether callback status means the invoice is paid.
	 *
	 * @param string $status Plisio status slug.
	 * @return bool
	 */
	public static function is_paid_status( $status ) {
		$status = strtolower( sanitize_text_field( (string) $status ) );

		return in_array( $status, array( 'completed', 'mismatch' ), true );
	}
}
