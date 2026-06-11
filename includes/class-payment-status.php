<?php
/**
 * Payment success page status display.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public LMS URLs use shareable GET parameters (checkout, account, payment status).

/**
 * Class Art_LMS_Payment_Status
 */
class Art_LMS_Payment_Status {

	const POLL_INTERVAL_MS       = 8000;
	const POLL_TIMEOUT_SECONDS   = 600;
	const LONG_WAIT_SECONDS      = 90;

	/**
	 * Whether order status means payment failed or was cancelled.
	 *
	 * @param string $status Order status.
	 * @return bool
	 */
	public static function is_failure_status( $status ) {
		return in_array(
			(string) $status,
			array(
				Art_LMS_Orders::STATUS_FAILED,
				Art_LMS_Orders::STATUS_CANCELLED,
			),
			true
		);
	}

	/**
	 * Build minimal REST payload for anonymous success-page polling.
	 *
	 * Personal order fields are exposed only via get_initial_order_context()
	 * when the success page is rendered for the buyer.
	 *
	 * @param object $order Order object.
	 * @return array<string, mixed>
	 */
	public static function build_public_poll_payload( $order ) {
		$emails       = Art_LMS_Settings::get_emails();
		$account_url  = self::get_account_page_url();
		$product_id   = absint( $order->product_id ?? 0 );
		$checkout_url = $product_id ? Art_LMS_Settings::get_checkout_url( $product_id ) : '';
		$status       = (string) ( $order->status ?? Art_LMS_Orders::STATUS_PENDING );

		return array(
			'status'                 => $status,
			'paid'                   => Art_LMS_Orders::STATUS_PAID === $status,
			'failed'                 => self::is_failure_status( $status ),
			'cancelled'              => Art_LMS_Orders::STATUS_CANCELLED === $status,
			'account_url'            => $account_url,
			'checkout_url'           => $checkout_url ? esc_url_raw( $checkout_url ) : '',
			'support_email'          => Art_LMS_Settings::get_support_email(),
			'purchase_email_enabled' => 'yes' === ( $emails['purchase']['enabled'] ?? 'yes' ),
		);
	}

	/**
	 * Order display context embedded in the success page for the current buyer.
	 *
	 * @param object $order Order object.
	 * @return array<string, mixed>
	 */
	public static function build_initial_order_context( $order ) {
		$product_id = absint( $order->product_id ?? 0 );

		return array(
			'order_id'     => absint( $order->id ?? 0 ),
			'email'        => sanitize_email( (string) ( $order->email ?? '' ) ),
			'product_name' => $product_id ? Art_LMS_Payment_Buttons::get_product_name( $product_id ) : '',
			'amount'       => Art_LMS_Orders::format_amount( $order->amount ?? 0 ),
		);
	}

	/**
	 * Resolve initial order context for the current success-page request.
	 *
	 * @param string $order_key Optional order key override.
	 * @return array<string, mixed>
	 */
	public static function get_initial_order_context_for_request( $order_key = '' ) {
		if ( '' === $order_key && isset( $_GET['art_lms_order'] ) ) {
			$order_key = sanitize_text_field( wp_unslash( (string) $_GET['art_lms_order'] ) );
		}

		if ( '' === $order_key ) {
			return array();
		}

		$order = Art_LMS_Orders::get_by_key( $order_key );

		if ( ! $order ) {
			return array();
		}

		return self::build_initial_order_context( $order );
	}

	/**
	 * Get account page URL only when a page is configured in settings.
	 *
	 * @return string
	 */
	public static function get_account_page_url() {
		$page_id = Art_LMS_Settings::get_account_page_id();

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? esc_url_raw( $url ) : '';
	}

	/**
	 * Attach frontend config to the registered payment-status script.
	 */
	public static function localize_script() {
		static $localized = false;

		if ( $localized || ! wp_script_is( 'art-lms-payment-status', 'registered' ) ) {
			return;
		}

		$localized = true;

		wp_localize_script(
			'art-lms-payment-status',
			'artLmsPaymentStatus',
			self::get_script_config()
		);
	}

	/**
	 * Config for payment-status.js.
	 *
	 * @return array
	 */
	public static function get_script_config() {
		$account_url    = self::get_account_page_url();
		$initial_order  = self::get_initial_order_context_for_request();
		$config         = array(
			'restUrl'      => esc_url_raw( rest_url( 'art-lms/v1/order-status/' ) ),
			'pollInterval' => self::POLL_INTERVAL_MS,
			'timeoutMs'    => self::POLL_TIMEOUT_SECONDS * 1000,
			'longWaitMs'   => self::LONG_WAIT_SECONDS * 1000,
			'accountUrl'   => $account_url,
			'supportEmail' => Art_LMS_Settings::get_support_email(),
			'strings'      => self::get_script_strings(),
		);

		if ( ! empty( $initial_order ) ) {
			$config['initialOrder'] = $initial_order;
		}

		return $config;
	}

	/**
	 * Localized UI strings for payment status block.
	 *
	 * @return array<string, string>
	 */
	public static function get_script_strings() {
		return Art_LMS_Settings::get_payment_status_frontend_config();
	}
}
