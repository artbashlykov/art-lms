<?php
/**
 * Payment gateway registry.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Payment_Gateway_Registry
 */
class Art_LMS_Payment_Gateway_Registry {

	/**
	 * Registered gateways.
	 *
	 * @var array<string, Art_LMS_Payment_Gateway>
	 */
	private static $gateways = array();

	/**
	 * Whether gateways were bootstrapped.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Register built-in gateways.
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		self::register( new Art_LMS_Gateway_Test() );
		self::register( new Art_LMS_Gateway_Yoomoney() );
		self::register( new Art_LMS_Gateway_Yookassa() );
		self::register( new Art_LMS_Gateway_Prodamus() );
		self::register( new Art_LMS_Gateway_Plisio() );

		/**
		 * Register additional payment gateways.
		 */
		do_action( 'art_lms_register_payment_gateways' );
	}

	/**
	 * Register a gateway instance.
	 *
	 * @param Art_LMS_Payment_Gateway $gateway Gateway instance.
	 */
	public static function register( Art_LMS_Payment_Gateway $gateway ) {
		self::$gateways[ $gateway->get_id() ] = $gateway;
	}

	/**
	 * Get all registered gateways.
	 *
	 * @return array<string, Art_LMS_Payment_Gateway>
	 */
	public static function all() {
		self::boot();

		return self::$gateways;
	}

	/**
	 * Get gateway metadata for admin UI.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_available_meta() {
		$gateways = array();

		foreach ( self::all() as $gateway_id => $gateway ) {
			$gateways[ $gateway_id ] = $gateway->get_meta();
		}

		return $gateways;
	}

	/**
	 * Get gateway by ID.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return Art_LMS_Payment_Gateway|null
	 */
	public static function get( $gateway_id ) {
		self::boot();

		$gateway_id = sanitize_key( (string) $gateway_id );

		return self::$gateways[ $gateway_id ] ?? null;
	}

	/**
	 * Get active site-wide gateway.
	 *
	 * @return Art_LMS_Payment_Gateway|null
	 */
	public static function get_active() {
		$default = Art_LMS_Settings::get_default_checkout_gateway();

		if ( $default ) {
			$gateway = self::get( $default );

			if ( $gateway ) {
				return $gateway;
			}
		}

		foreach ( Art_LMS_Settings::get_ordered_gateway_ids() as $gateway_id ) {
			if ( ! Art_LMS_Settings::is_checkout_gateway_available( $gateway_id ) ) {
				continue;
			}

			$gateway = self::get( $gateway_id );

			if ( $gateway ) {
				return $gateway;
			}
		}

		return self::get( 'yoomoney' ) ?: self::get( 'test' );
	}

	/**
	 * Resolve gateway for an order.
	 *
	 * @param object $order Order object.
	 * @return Art_LMS_Payment_Gateway|null
	 */
	public static function get_for_order( $order ) {
		$gateway_id = Art_LMS_Orders::get_payment_gateway_slug( $order );

		if ( $gateway_id ) {
			$gateway = self::get( $gateway_id );

			if ( $gateway ) {
				return $gateway;
			}
		}

		return self::get_active();
	}

	/**
	 * Register webhook routes for all gateways.
	 */
	public static function register_webhook_routes() {
		foreach ( self::all() as $gateway ) {
			$gateway->register_webhook_routes();
		}
	}
}
