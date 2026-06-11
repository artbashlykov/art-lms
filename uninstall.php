<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Art_LMS
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-uninstaller.php';

Art_LMS_Uninstaller::run();
