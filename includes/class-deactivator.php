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
		Art_LMS_Access::unschedule_cron();

		flush_rewrite_rules();
	}
}
