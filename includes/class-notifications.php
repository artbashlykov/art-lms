<?php
/**
 * Payment notifications and order status polling.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Notifications
 */
class Art_LMS_Notifications {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_gateway_routes' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_order_status_route' ) );
	}

	/**
	 * Register payment gateway webhook routes.
	 */
	public static function register_gateway_routes() {
		Art_LMS_Payment_Gateway_Registry::register_webhook_routes();
	}

	/**
	 * Register order status check route for success page polling.
	 */
	public static function register_order_status_route() {
		register_rest_route(
			'art-lms/v1',
			'/order-status/(?P<order_key>[a-zA-Z0-9_]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_order_status' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return order status for polling on success page.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_order_status( WP_REST_Request $request ) {
		$order_key = sanitize_text_field( $request->get_param( 'order_key' ) );
		$order     = Art_LMS_Orders::get_by_key( $order_key );

		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Заказ не найден.', 'art-lms' ), array( 'status' => 404 ) );
		}

		$order = self::maybe_sync_gateway_payment_status( $order );

		return new WP_REST_Response(
			Art_LMS_Payment_Status::build_public_poll_payload( $order ),
			200
		);
	}

	/**
	 * Ask the order gateway to sync payment status (fallback when webhook is delayed).
	 *
	 * @param object $order Order object.
	 * @return object
	 */
	public static function maybe_sync_gateway_payment_status( $order ) {
		if ( ! is_object( $order ) || Art_LMS_Orders::STATUS_PENDING !== ( $order->status ?? '' ) ) {
			return $order;
		}

		$gateway = Art_LMS_Payment_Gateway_Registry::get_for_order( $order );

		if ( ! $gateway || ! method_exists( $gateway, 'maybe_sync_order_payment' ) ) {
			return $order;
		}

		$gateway->maybe_sync_order_payment( $order );

		$fresh = Art_LMS_Orders::get( (int) ( $order->id ?? 0 ) );

		return $fresh ? $fresh : $order;
	}
}
