<?php
/**
 * Security helpers.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public LMS URLs use shareable GET parameters (checkout, account, payment status).

/**
 * Class Art_LMS_Security
 */
class Art_LMS_Security {

	/**
	 * Admin-post actions allowed for buyers.
	 *
	 * @var string[]
	 */
	const BUYER_ALLOWED_ADMIN_POST_ACTIONS = array(
		'art_lms_start_payment',
	);

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'block_buyers_from_admin' ), 1 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar_for_buyers' ) );
	}

	/**
	 * Check if current user can manage plugin settings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user is a buyer without backend access.
	 *
	 * @param int $user_id Optional user ID.
	 * @return bool
	 */
	public static function is_buyer_only( $user_id = 0 ) {
		if ( ! is_user_logged_in() && ! $user_id ) {
			return false;
		}

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'edit_posts' ) ) {
			return false;
		}

		$is_buyer_only = false;

		if ( Art_LMS_Roles::user_is_customer( $user_id ) ) {
			$is_buyer_only = true;
		} elseif (
			in_array( 'subscriber', (array) $user->roles, true )
			&& 'yes' === get_user_meta( $user_id, 'art_lms_created_via_plugin', true )
		) {
			$is_buyer_only = true;
		}

		/**
		 * Override buyer-only detection.
		 *
		 * @param bool $is_buyer_only Whether user is treated as buyer.
		 * @param int  $user_id       User ID.
		 */
		return (bool) apply_filters( 'art_lms_is_buyer_only', $is_buyer_only, $user_id );
	}

	/**
	 * Check if current user may access wp-admin.
	 *
	 * @return bool
	 */
	public static function can_access_admin() {
		if ( ! is_user_logged_in() ) {
			return true;
		}

		$can_access = ! self::is_buyer_only();

		/**
		 * Filter admin access for current user.
		 *
		 * @param bool $can_access Can access wp-admin.
		 */
		return (bool) apply_filters( 'art_lms_can_access_admin', $can_access );
	}

	/**
	 * Redirect buyers away from wp-admin.
	 */
	public static function block_buyers_from_admin() {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::can_access_admin() ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( self::is_allowed_buyer_admin_post_action() ) {
			return;
		}

		wp_safe_redirect( self::get_buyer_redirect_url() );
		exit;
	}

	/**
	 * Hide admin bar for buyers on the frontend.
	 *
	 * @param bool $show Whether to show admin bar.
	 * @return bool
	 */
	public static function hide_admin_bar_for_buyers( $show ) {
		if ( self::is_buyer_only() ) {
			return false;
		}

		return $show;
	}

	/**
	 * Get redirect URL for blocked buyers.
	 *
	 * @return string
	 */
	public static function get_buyer_redirect_url() {
		$url = Art_LMS_Settings::get_account_url();

		if ( ! $url ) {
			$url = home_url( '/' );
		}

		/**
		 * Filter redirect URL when buyer tries to access wp-admin.
		 *
		 * @param string $url Redirect URL.
		 */
		return (string) apply_filters( 'art_lms_buyer_admin_redirect_url', $url );
	}

	/**
	 * Allow specific admin-post actions needed for checkout flow.
	 *
	 * @return bool
	 */
	private static function is_allowed_buyer_admin_post_action() {
		global $pagenow;

		if ( 'admin-post.php' !== $pagenow ) {
			return false;
		}

		$action = '';

		if ( isset( $_REQUEST['action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
		}

		return in_array( $action, self::BUYER_ALLOWED_ADMIN_POST_ACTIONS, true );
	}
}
