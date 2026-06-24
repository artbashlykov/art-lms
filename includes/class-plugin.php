<?php
/**
 * Main plugin bootstrap.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Plugin
 */
class Art_LMS_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Art_LMS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether admin modules were initialized.
	 *
	 * @var bool
	 */
	private static $admin_initialized = false;

	/**
	 * @return Art_LMS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
	}

	/**
	 * Load required class files.
	 */
	private function load_dependencies() {
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-transliteration.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-materials.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-protected-media.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-payment-buttons.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-orders.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-order-form-data.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-access.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-account.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-roles.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-security.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-settings.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-pages.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-statistics.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-checkout-rate-limit.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-checkout.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-custom-login.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-cache-control.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/abstract-payment-gateway.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/gateways/class-gateway-test.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/gateways/class-gateway-yoomoney.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/gateways/class-gateway-yookassa.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/gateways/class-gateway-prodamus.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/gateways/class-gateway-plisio.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-payment-gateway-registry.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-payment-status.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-notifications.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-user-registration.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-email.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-shortcodes.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-blocks.php';
		require_once ART_LMS_PLUGIN_DIR . 'includes/class-updater.php';
		require_once ART_LMS_PLUGIN_DIR . 'admin/class-admin-statistics.php';
		require_once ART_LMS_PLUGIN_DIR . 'admin/class-admin-settings.php';
		require_once ART_LMS_PLUGIN_DIR . 'admin/class-admin-menu.php';
		require_once ART_LMS_PLUGIN_DIR . 'admin/class-admin-orders.php';
		require_once ART_LMS_PLUGIN_DIR . 'admin/class-admin-materials.php';
		require_once ART_LMS_PLUGIN_DIR . 'admin/class-admin-payment-buttons.php';
		require_once ART_LMS_PLUGIN_DIR . 'admin/class-admin-payment-button-editor.php';
		require_once ART_LMS_PLUGIN_DIR . 'public/class-public.php';

		Art_LMS_Payment_Gateway_Registry::boot();
	}

	/**
	 * Register hooks and initialize modules.
	 */
	public function run() {
		add_action( 'init', array( 'Art_LMS_Materials', 'register_post_type' ), 0 );
		add_action( 'init', array( 'Art_LMS_Payment_Buttons', 'register_post_type' ), 0 );
		add_action( 'init', array( 'Art_LMS_Payment_Buttons', 'register_meta' ), 0 );
		add_action( 'init', array( $this, 'init' ) );

		if ( is_admin() ) {
			$this->init_admin();
		}
	}

	/**
	 * Initialize plugin modules.
	 */
	public function init() {
		Art_LMS_Settings::init();
		Art_LMS_Pages::init();
		Art_LMS_Statistics::init();
		Art_LMS_Checkout::init();
		Art_LMS_Custom_Login::init();
		Art_LMS_Cache_Control::init();
		Art_LMS_Materials::init();
		Art_LMS_Protected_Media::init();
		Art_LMS_Payment_Buttons::init();
		Art_LMS_Orders::init();
		Art_LMS_Access::init();
		Art_LMS_Account::init();
		Art_LMS_Notifications::init();
		Art_LMS_User_Registration::init();
		Art_LMS_Email::init();
		Art_LMS_Roles::init();
		Art_LMS_Security::init();
		Art_LMS_Shortcodes::init();
		Art_LMS_Blocks::init();
		Art_LMS_Public::init();
	}

	/**
	 * Initialize admin modules (registers admin_menu and related hooks).
	 */
	public function init_admin() {
		if ( self::$admin_initialized ) {
			return;
		}

		self::$admin_initialized = true;

		Art_LMS_Updater::init();
		Art_LMS_Admin_Statistics::init();
		Art_LMS_Admin_Settings::init();
		Art_LMS_Admin_Menu::init();
		Art_LMS_Admin_Orders::init();
		Art_LMS_Admin_Materials::init();
		Art_LMS_Admin_Payment_Buttons::init();
		Art_LMS_Admin_Payment_Button_Editor::init();
	}
}
