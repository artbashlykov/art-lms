<?php
/**
 * Plugin activation.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Activator
 */
class Art_LMS_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-roles.php';
		Art_LMS_Roles::register_role();

		self::create_tables();
		self::set_default_options();
		self::register_post_types_for_flush();

		require_once ART_LMS_PLUGIN_DIR . 'includes/class-orders.php';
		Art_LMS_Orders::maybe_upgrade_schema();
		Art_LMS_Orders::maybe_upgrade_indexes();

		flush_rewrite_rules();
	}

	/**
	 * Register CPT before rewrite flush.
	 */
	private static function register_post_types_for_flush() {
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-materials.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-payment-buttons.php';

		Art_LMS_Materials::register_post_type();
		Art_LMS_Payment_Buttons::register_post_type();
		Art_LMS_Payment_Buttons::register_post_status();
	}

	/**
	 * Ensure plugin tables exist (safe to call on every request).
	 */
	public static function ensure_tables() {
		self::create_tables();
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$orders_table    = $wpdb->prefix . 'art_lms_orders';
		$access_table    = $wpdb->prefix . 'art_lms_access';

		$sql_orders = "CREATE TABLE {$orders_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_key varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(190) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			form_data longtext NULL,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(3) NOT NULL DEFAULT 'RUB',
			status varchar(20) NOT NULL DEFAULT 'pending',
			payment_label varchar(64) NOT NULL DEFAULT '',
			gateway_transaction_id varchar(64) NOT NULL DEFAULT '',
			gateway_payment_method varchar(32) NOT NULL DEFAULT '',
			payment_gateway varchar(20) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			paid_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			raw_notification longtext,
			PRIMARY KEY  (id),
			UNIQUE KEY order_key (order_key),
			UNIQUE KEY payment_label (payment_label),
			KEY user_id (user_id),
			KEY product_id (product_id),
			KEY status (status),
			KEY gateway_transaction_id (gateway_transaction_id),
			KEY status_created_at (status, created_at),
			KEY status_paid_at (status, paid_at)
		) {$charset_collate};";

		$sql_access = "CREATE TABLE {$access_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			starts_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY product_id (product_id),
			KEY order_id (order_id),
			KEY status (status),
			KEY user_product_status (user_id, product_id, status),
			KEY order_product_status (order_id, product_id, status)
		) {$charset_collate};";

		dbDelta( $sql_orders );
		dbDelta( $sql_access );

		update_option( 'art_lms_db_version', ART_LMS_VERSION );
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-settings.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-checkout.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-custom-login.php';
		Art_LMS_Settings::ensure_defaults();
		Art_LMS_Checkout::register_rewrite();
		Art_LMS_Custom_Login::register_rewrite();
	}
}
