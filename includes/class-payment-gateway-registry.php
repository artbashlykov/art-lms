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
	 * Default display order for built-in payment gateways.
	 *
	 * @return string[]
	 */
	public static function get_builtin_order() {
		return array(
			'test',
			'yoomoney',
			'prodamus',
			'yookassa',
			'plisio',
		);
	}

	/**
	 * Sort gateway IDs using the built-in order, appending unknown IDs at the end.
	 *
	 * @param string[] $gateway_ids Gateway IDs.
	 * @return string[]
	 */
	public static function sort_gateway_ids( array $gateway_ids ) {
		$registry_ids = array_keys( self::all() );
		$sorted       = array();

		foreach ( self::get_builtin_order() as $gateway_id ) {
			if ( in_array( $gateway_id, $gateway_ids, true ) ) {
				$sorted[] = $gateway_id;
			}
		}

		foreach ( $gateway_ids as $gateway_id ) {
			$gateway_id = sanitize_key( (string) $gateway_id );

			if ( '' === $gateway_id || in_array( $gateway_id, $sorted, true ) ) {
				continue;
			}

			if ( in_array( $gateway_id, $registry_ids, true ) ) {
				$sorted[] = $gateway_id;
			}
		}

		foreach ( $registry_ids as $gateway_id ) {
			if ( ! in_array( $gateway_id, $sorted, true ) ) {
				$sorted[] = $gateway_id;
			}
		}

		return $sorted;
	}

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
		self::register( new Art_LMS_Gateway_Prodamus() );
		self::register( new Art_LMS_Gateway_Yookassa() );
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

	/**
	 * Partner signup URLs for supported payment gateways.
	 *
	 * @return array<string, string>
	 */
	public static function get_partner_signup_urls() {
		$urls = array(
			'prodamus' => 'https://connect.prodamus.ru/?ref=ARTOUT&c=Rv1',
			'yookassa' => 'https://yookassa.ru/joinups/?source=artbashlykov',
			'plisio'   => 'https://plisio.net/account/signup?ref=313636',
		);

		/**
		 * Filter partner signup URLs shown in gateway admin settings.
		 *
		 * @param array<string, string> $urls Gateway ID to signup URL map.
		 */
		return apply_filters( 'art_lms_payment_gateway_partner_signup_urls', $urls );
	}

	/**
	 * Partner signup URL for a gateway, if available.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return string
	 */
	public static function get_partner_signup_url( $gateway_id ) {
		$gateway_id = sanitize_key( (string) $gateway_id );
		$urls       = self::get_partner_signup_urls();

		$url = $urls[ $gateway_id ] ?? '';

		return is_string( $url ) ? esc_url_raw( $url ) : '';
	}

	/**
	 * Documentation URLs for payment gateway setup guides.
	 *
	 * @return array<string, string>
	 */
	public static function get_documentation_urls() {
		$urls = array(
			'prodamus' => 'https://docs.artbashlykov.ru/art_doc/art-lms/priem-platezhey-platezhnye-shlyuzy/prodamus/',
			'yookassa' => 'https://docs.artbashlykov.ru/art_doc/art-lms/priem-platezhey-platezhnye-shlyuzy/yukassa/',
			'yoomoney' => 'https://docs.artbashlykov.ru/art_doc/art-lms/priem-platezhey-platezhnye-shlyuzy/yumani-dlya-fizlits-i-samozanyatyh/',
			'plisio'   => 'https://docs.artbashlykov.ru/art_doc/art-lms/priem-platezhey-platezhnye-shlyuzy/plisio-kripto-shlyuz/',
		);

		/**
		 * Filter documentation URLs shown in gateway admin settings.
		 *
		 * @param array<string, string> $urls Gateway ID to documentation URL map.
		 */
		return apply_filters( 'art_lms_payment_gateway_documentation_urls', $urls );
	}

	/**
	 * Documentation URL for a gateway setup guide, if available.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return string
	 */
	public static function get_documentation_url( $gateway_id ) {
		$gateway_id = sanitize_key( (string) $gateway_id );
		$urls       = self::get_documentation_urls();

		$url = $urls[ $gateway_id ] ?? '';

		return is_string( $url ) ? esc_url_raw( $url ) : '';
	}
}
