<?php
/**
 * Rate limiting for public checkout submissions.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Checkout_Rate_Limit
 */
class Art_LMS_Checkout_Rate_Limit {

	const EMAIL_COOLDOWN_SECONDS = 40;

	const IP_MAX_ATTEMPTS = 7;

	const IP_WINDOW_SECONDS = 900;

	const TRANSIENT_EMAIL_PREFIX = 'art_lms_checkout_rl_email_';

	const TRANSIENT_IP_PREFIX = 'art_lms_checkout_rl_ip_';

	/**
	 * Enforce checkout submit limits. Records the attempt when allowed.
	 *
	 * @param array<string, mixed> $params Checkout submission payload.
	 * @return true|WP_Error
	 */
	public static function enforce( array $params ) {
		$ip_error = self::check_ip_limit();

		if ( is_wp_error( $ip_error ) ) {
			return $ip_error;
		}

		$email = self::resolve_submission_email( $params );

		if ( is_email( $email ) ) {
			$email_error = self::check_email_limit( $email );

			if ( is_wp_error( $email_error ) ) {
				return $email_error;
			}
		}

		self::record_ip_attempt();
		self::record_email_attempt( $email );

		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function check_ip_limit() {
		$ip = self::get_client_ip();

		if ( '' === $ip ) {
			return true;
		}

		$attempts = self::get_recent_ip_attempts( $ip );

		if ( count( $attempts ) >= self::IP_MAX_ATTEMPTS ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Слишком много попыток оформления заказа. Подождите 15 минут и попробуйте снова.', 'art-lms' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * @param string $email Buyer email.
	 * @return true|WP_Error
	 */
	private static function check_email_limit( $email ) {
		$email = strtolower( sanitize_email( $email ) );

		if ( ! is_email( $email ) ) {
			return true;
		}

		$last_attempt = get_transient( self::TRANSIENT_EMAIL_PREFIX . md5( $email ) );

		if ( false === $last_attempt ) {
			return true;
		}

		$elapsed = time() - (int) $last_attempt;

		if ( $elapsed < self::EMAIL_COOLDOWN_SECONDS ) {
			$wait_seconds = self::EMAIL_COOLDOWN_SECONDS - $elapsed;

			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %d: seconds to wait */
					__( 'Подождите %d сек. перед повторной отправкой формы.', 'art-lms' ),
					max( 1, $wait_seconds )
				),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $params Checkout submission payload.
	 * @return string
	 */
	private static function resolve_submission_email( array $params ) {
		$snapshot = Art_LMS_Order_Form_Data::parse_submission( $params );
		$values   = array();

		foreach ( $snapshot['fields'] as $row ) {
			if ( 'field' !== ( $row['type'] ?? 'field' ) ) {
				continue;
			}

			$values[ $row['key'] ] = $row['value'];
		}

		$email = sanitize_email( $values['email'] ?? '' );

		if ( is_user_logged_in() ) {
			$profile = Art_LMS_User_Registration::get_buyer_details_for_form( wp_get_current_user()->user_email );

			if ( ! is_email( $email ) && ! empty( $profile['email'] ) ) {
				$email = sanitize_email( $profile['email'] );
			}
		}

		return is_email( $email ) ? $email : '';
	}

	/**
	 * @return string
	 */
	private static function get_client_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return $ip;
	}

	/**
	 * @param string $ip Client IP address.
	 * @return int[]
	 */
	private static function get_recent_ip_attempts( $ip ) {
		$attempts = get_transient( self::TRANSIENT_IP_PREFIX . md5( $ip ) );

		if ( ! is_array( $attempts ) ) {
			return array();
		}

		$now     = time();
		$window  = self::IP_WINDOW_SECONDS;
		$recent  = array();

		foreach ( $attempts as $timestamp ) {
			$timestamp = (int) $timestamp;

			if ( ( $now - $timestamp ) < $window ) {
				$recent[] = $timestamp;
			}
		}

		return $recent;
	}

	/**
	 * Record a successful checkout submit attempt for the current IP.
	 */
	private static function record_ip_attempt() {
		$ip = self::get_client_ip();

		if ( '' === $ip ) {
			return;
		}

		$attempts   = self::get_recent_ip_attempts( $ip );
		$attempts[] = time();

		set_transient(
			self::TRANSIENT_IP_PREFIX . md5( $ip ),
			$attempts,
			self::IP_WINDOW_SECONDS
		);
	}

	/**
	 * Record a successful checkout submit attempt for the buyer email.
	 *
	 * @param string $email Buyer email.
	 */
	private static function record_email_attempt( $email ) {
		$email = strtolower( sanitize_email( $email ) );

		if ( ! is_email( $email ) ) {
			return;
		}

		set_transient(
			self::TRANSIENT_EMAIL_PREFIX . md5( $email ),
			time(),
			self::EMAIL_COOLDOWN_SECONDS
		);
	}
}
