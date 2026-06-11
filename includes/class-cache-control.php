<?php
/**
 * Prevent full-page caching on dynamic ART LMS front-end views.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Cache_Control
 */
class Art_LMS_Cache_Control {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_prevent_page_cache' ), -1 );
	}

	/**
	 * Send no-cache signals for dynamic plugin pages.
	 */
	public static function maybe_prevent_page_cache() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! self::should_prevent_page_cache() ) {
			return;
		}

		self::prevent_page_cache();
	}

	/**
	 * Whether the current front-end request should bypass page cache.
	 *
	 * @return bool
	 */
	public static function should_prevent_page_cache() {
		if ( Art_LMS_Checkout::is_checkout_request() ) {
			return true;
		}

		if ( Art_LMS_Settings::is_account_page() ) {
			return true;
		}

		$success_page_id = Art_LMS_Settings::get_success_page_id();

		if ( $success_page_id && is_page( $success_page_id ) ) {
			return true;
		}

		if ( is_singular( Art_LMS_Materials::POST_TYPE ) ) {
			return true;
		}

		$query_flags = array(
			'art_lms_order',
			Art_LMS_Checkout::QUERY_PAY,
			Art_LMS_Account::QUERY_SET_PASSWORD,
			Art_LMS_User_Registration::QUERY_VERIFY_CHECKOUT,
		);

		foreach ( $query_flags as $query_flag ) {
			if ( ! empty( $_GET[ $query_flag ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Cache bypass only; no data processing.
				return true;
			}
		}

		/**
		 * Filter whether the current request should bypass page cache.
		 *
		 * @param bool $prevent Whether to prevent page cache.
		 */
		return (bool) apply_filters( 'art_lms_prevent_page_cache', false );
	}

	/**
	 * Define DONOTCACHEPAGE and send no-cache HTTP headers.
	 */
	public static function prevent_page_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core page-cache constant.
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}
}
