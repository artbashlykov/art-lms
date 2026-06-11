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

	const MIGRATION_OPTION = 'art_lms_customer_role_migrated';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_role' ), 0 );
	}

	/**
	 * Create customer role and migrate legacy plugin buyers.
	 */
	public static function ensure_role() {
		self::register_role();
		self::maybe_migrate_legacy_customers();
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
	 * Move plugin-created subscribers to the dedicated customer role.
	 */
	public static function maybe_migrate_legacy_customers() {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		if ( ! get_role( self::ROLE_CUSTOMER ) ) {
			return;
		}

		$subscriber_ids = get_users(
			array(
				'fields' => 'ID',
				'role'   => 'subscriber',
			)
		);

		foreach ( $subscriber_ids as $user_id ) {
			$user_id = (int) $user_id;

			if ( 'yes' !== get_user_meta( $user_id, 'art_lms_created_via_plugin', true ) ) {
				continue;
			}

			$user = new WP_User( $user_id );

			if ( ! $user->exists() ) {
				continue;
			}

			$user->set_role( self::ROLE_CUSTOMER );
		}

		update_option( self::MIGRATION_OPTION, ART_LMS_VERSION );
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
