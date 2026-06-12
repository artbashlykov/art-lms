<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Uninstaller
 */
class Art_LMS_Uninstaller {

	const POST_TYPES = array(
		'art_lms_material',
		'art_lms_pay_button',
	);

	const USER_META_KEYS = array(
		'art_lms_phone',
		'art_lms_created_via_plugin',
	);

	/**
	 * Run uninstall cleanup when the admin opted in.
	 */
	public static function run() {
		if ( ! self::is_delete_data_enabled() ) {
			return;
		}

		self::delete_posts();
		self::drop_tables();
		self::delete_user_meta();
		self::remove_customer_role();
		self::delete_plugin_options();
		self::delete_transients();
	}

	/**
	 * Whether the site admin enabled data removal on uninstall.
	 *
	 * @return bool
	 */
	private static function is_delete_data_enabled() {
		return 'yes' === get_option( 'art_lms_delete_data_on_uninstall', 'no' );
	}

	/**
	 * Delete plugin custom post types and their meta.
	 */
	private static function delete_posts() {
		foreach ( self::POST_TYPES as $post_type ) {
			$post_ids = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			foreach ( $post_ids as $post_id ) {
				wp_delete_post( (int) $post_id, true );
			}
		}
	}

	/**
	 * Drop custom plugin tables.
	 */
	private static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'art_lms_orders',
			$wpdb->prefix . 'art_lms_access',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin-owned tables during uninstall.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}
	}

	/**
	 * Delete plugin-specific user meta.
	 */
	private static function delete_user_meta() {
		foreach ( self::USER_META_KEYS as $meta_key ) {
			delete_metadata( 'user', 0, $meta_key, '', true );
		}
	}

	/**
	 * Reassign customers to subscribers and remove the dedicated role.
	 */
	private static function remove_customer_role() {
		$user_ids = get_users(
			array(
				'role'   => 'art_lms_customer',
				'fields' => 'ID',
			)
		);

		foreach ( $user_ids as $user_id ) {
			$user = new WP_User( (int) $user_id );

			if ( $user->exists() ) {
				$user->set_role( 'subscriber' );
			}
		}

		remove_role( 'art_lms_customer' );
	}

	/**
	 * Delete plugin options from the database.
	 */
	private static function delete_plugin_options() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Bulk cleanup during uninstall.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'art_lms_' ) . '%'
			)
		);
	}

	/**
	 * Delete plugin transients.
	 */
	private static function delete_transients() {
		global $wpdb;

		$like_transient = $wpdb->esc_like( '_transient_art_lms_' ) . '%';
		$like_timeout   = $wpdb->esc_like( '_transient_timeout_art_lms_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Bulk cleanup during uninstall.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like_transient,
				$like_timeout
			)
		);
	}
}
