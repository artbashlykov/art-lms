<?php
/**
 * Plugin deactivation.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Deactivator
 */
class Art_LMS_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-access.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-orders.php';
		Art_LMS_Access::unschedule_cron();
		Art_LMS_Orders::unschedule_trash_cron();

		flush_rewrite_rules();
	}
}
