<?php
/**
 * Admin payment buttons list redirect.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Admin_Payment_Buttons
 */
class Art_LMS_Admin_Payment_Buttons {

	const PAGE_LIST = 'art-lms-payment-buttons';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'load-' . ART_LMS_ADMIN_MENU_SLUG . '_page_' . self::PAGE_LIST, array( __CLASS__, 'load_list_page' ) );
	}

	/**
	 * Redirect to native payment buttons list.
	 */
	public static function load_list_page() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-lms' ) );
		}

		self::ensure_post_type_registered();

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Art_LMS_Payment_Buttons::POST_TYPE ) );
		exit;
	}

	/**
	 * Menu callback fallback.
	 */
	public static function render_list_page() {
		self::load_list_page();
	}

	/**
	 * Ensure CPT is registered.
	 */
	public static function ensure_post_type_registered() {
		if ( post_type_exists( Art_LMS_Payment_Buttons::POST_TYPE ) ) {
			return;
		}

		Art_LMS_Payment_Buttons::register_post_type();
		Art_LMS_Payment_Buttons::register_post_status();
		Art_LMS_Payment_Buttons::register_meta();
	}
}
