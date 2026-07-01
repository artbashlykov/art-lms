<?php
/**
 * Customer role for ART LMS buyers.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Roles
 */
class Art_LMS_Roles {

	const ROLE_CUSTOMER = 'art_lms_customer';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_role' ), 0 );
	}

	/**
	 * Create customer role if missing.
	 */
	public static function ensure_role() {
		self::register_role();
	}

	/**
	 * Register the buyer role with subscriber-like capabilities.
	 */
	public static function register_role() {
		if ( get_role( self::ROLE_CUSTOMER ) ) {
			return;
		}

		$capabilities = array(
			'read' => true,
		);

		/**
		 * Filter capabilities for the ART LMS customer role.
		 *
		 * @param array<string, bool> $capabilities Role capabilities.
		 */
		$capabilities = apply_filters( 'art_lms_customer_role_capabilities', $capabilities );

		add_role(
			self::ROLE_CUSTOMER,
			__( 'Покупатель ART LMS', 'art-lms' ),
			$capabilities
		);
	}

	/**
	 * Whether the user has the ART LMS customer role.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_is_customer( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		return in_array( self::ROLE_CUSTOMER, (array) $user->roles, true );
	}
}
