<?php
/**
 * Plugin Name:       ART LMS
 * Description:       Простая LMS с автовыдачей цифровых продуктов, приемом платежей для физлиц, ИП и самозанятых.
 * Version:           2.17.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Арт Башлыков
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       art-lms
 * Domain Path:       /languages
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

define( 'ART_LMS_VERSION', '2.17.2' );
define( 'ART_LMS_ADMIN_MENU_SLUG', 'art-lms' );
define( 'ART_LMS_AUTHOR_URL', 'https://forge.artbashlykov.ru' );
define( 'ART_LMS_PLUGIN_FILE', __FILE__ );
define( 'ART_LMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ART_LMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ART_LMS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

add_filter( 'puc_view_details_link-' . ART_LMS_ADMIN_MENU_SLUG, '__return_empty_string' );

require_once ART_LMS_PLUGIN_DIR . 'includes/class-activator.php';
require_once ART_LMS_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once ART_LMS_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( ART_LMS_PLUGIN_FILE, array( 'Art_LMS_Activator', 'activate' ) );
register_deactivation_hook( ART_LMS_PLUGIN_FILE, array( 'Art_LMS_Deactivator', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return Art_LMS_Plugin
 */
function art_lms() {
	return Art_LMS_Plugin::instance();
}

art_lms()->run();
