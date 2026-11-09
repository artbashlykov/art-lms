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
	 * View orders list / order details (read-only). Granted to admins and ART MAX BOT operators.
	 */
	const CAP_VIEW_ORDERS = 'art_lms_view_orders';

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
		add_action( 'init', array( __CLASS__, 'ensure_caps' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'block_buyers_from_admin' ), 1 );
		add_filter( 'user_has_cap', array( __CLASS__, 'filter_user_has_cap' ), 10, 4 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar_for_non_admins' ) );
	}

	/**
	 * Site admins always pass art_lms_view_orders checks (menu capability).
	 *
	 * @param array<string, bool> $allcaps Caps the user has.
	 * @param array<int, string>  $caps    Required caps being checked.
	 * @param array<int, mixed>   $args    Arguments to current_user_can.
	 * @param WP_User             $user    User object.
	 * @return array<string, bool>
	 */
	public static function filter_user_has_cap( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args, $user );

		if ( ! empty( $allcaps['manage_options'] ) ) {
			$allcaps[ self::CAP_VIEW_ORDERS ] = true;
		}

		return $allcaps;
	}

	/**
	 * Persist view-orders cap on administrator and Max Bot operator (if present).
	 */
	public static function ensure_caps() {
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::CAP_VIEW_ORDERS ) ) {
			$admin->add_cap( self::CAP_VIEW_ORDERS );
		}

		$operator_slug = class_exists( 'Art_Max_Bot_Capabilities', false )
			? Art_Max_Bot_Capabilities::ROLE_OPERATOR
			: 'art_max_bot_operator';
		$operator      = get_role( $operator_slug );
		if ( $operator && ! $operator->has_cap( self::CAP_VIEW_ORDERS ) ) {
			$operator->add_cap( self::CAP_VIEW_ORDERS );
		}
	}

	/**
	 * Check if current user can manage plugin settings / mutate LMS data.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can view the orders list and order details.
	 *
	 * @return bool
	 */
	public static function can_view_orders() {
		return self::can_manage() || current_user_can( self::CAP_VIEW_ORDERS );
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
	 * Hide WordPress admin bar for everyone except site admins.
	 *
	 * @param bool $show Whether to show admin bar.
	 * @return bool
	 */
	public static function hide_admin_bar_for_non_admins( $show ) {
		if ( ! is_user_logged_in() ) {
			return $show;
		}

		if ( current_user_can( 'manage_options' ) || self::can_view_orders() ) {
			return $show;
		}

		/**
		 * Whether to show the admin bar for a logged-in non-admin.
		 *
		 * Default: hidden. Return true to show it for the current user.
		 *
		 * @param bool $show Whether to show the admin bar.
		 */
		return (bool) apply_filters( 'art_lms_show_admin_bar_for_non_admin', false );
	}

	/**
	 * Backward-compatible alias.
	 *
	 * @param bool $show Whether to show admin bar.
	 * @return bool
	 */
	public static function hide_admin_bar_for_buyers( $show ) {
		return self::hide_admin_bar_for_non_admins( $show );
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
