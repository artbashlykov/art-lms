<?php
/**
 * Public checkout route and rendering.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public LMS URLs use shareable GET parameters (checkout, account, payment status).

/**
 * Class Art_LMS_Checkout
 */
class Art_LMS_Checkout {

	const QUERY_VAR = 'art_lms_checkout';

	const QUERY_DESIGN_PREVIEW = 'art_lms_checkout_preview';

	const QUERY_PAY = 'art_lms_pay';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 10 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 99 );
		add_action( 'update_option_' . Art_LMS_Settings::OPTION_CHECKOUT, array( __CLASS__, 'on_checkout_settings_updated' ), 10, 2 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_checkout_request' ), 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'strip_foreign_styles' ), 9999 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_to_payment' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_checkout' ), 0 );
		add_filter( 'template_include', array( __CLASS__, 'filter_template' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Redirect pending order to the configured payment gateway.
	 */
	public static function maybe_redirect_to_payment() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( empty( $_GET[ self::QUERY_PAY ] ) ) {
			return;
		}

		$order_key = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_PAY ] ) );
		$order     = Art_LMS_Orders::get_by_key( $order_key );

		if ( ! $order || Art_LMS_Orders::STATUS_PENDING !== $order->status ) {
			wp_die( esc_html__( 'Заказ не найден или уже оплачен.', 'art-lms' ), '', array( 'response' => 404 ) );
		}

		$gateway = Art_LMS_Payment_Gateway_Registry::get_for_order( $order );

		if ( ! $gateway ) {
			wp_die( esc_html__( 'Платёжный шлюз не найден.', 'art-lms' ), '', array( 'response' => 500 ) );
		}

		if ( $gateway->should_skip_external_payment( $order ) ) {
			wp_safe_redirect( Art_LMS_Checkout::get_order_success_url( $order ) );
			exit;
		}

		$gateway->render_payment_redirect( $order );
		exit;
	}

	/**
	 * Register checkout REST routes.
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'art-lms/v1',
			'/checkout/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_submit_checkout' ),
				'permission_callback' => array( __CLASS__, 'rest_submit_checkout_permissions' ),
			)
		);
	}

	/**
	 * Validate checkout submit request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public static function rest_submit_checkout_permissions( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Недействительный запрос.', 'art-lms' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Create order from checkout form and return payment redirect URL.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_submit_checkout( WP_REST_Request $request ) {
		$params    = $request->get_json_params();
		$params    = is_array( $params ) ? $params : array();
		$button_id = absint( $params['button_id'] ?? 0 );
		unset( $params['button_id'] );

		$rate_limit = Art_LMS_Checkout_Rate_Limit::check( $params );

		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$result = Art_LMS_Orders::create_from_checkout( $button_id, $params );

		if ( is_wp_error( $result ) ) {
			if ( 'verification_pending' === $result->get_error_code() ) {
				return new WP_REST_Response(
					array(
						'verification_pending' => true,
						'message'              => $result->get_error_message(),
					),
					200
				);
			}

			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		$order = Art_LMS_Orders::get( (int) $result );

		if ( ! $order ) {
			return new WP_Error(
				'create_failed',
				Art_LMS_Settings::format_checkout_form_message( 'create_order_failed' ),
				array( 'status' => 500 )
			);
		}

		Art_LMS_Checkout_Rate_Limit::record( $params );

		return new WP_REST_Response(
			array(
				'redirect' => self::get_order_payment_redirect_url( $order ),
			),
			200
		);
	}

	/**
	 * Build success page URL for an order.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	public static function get_order_success_url( $order ) {
		$general     = Art_LMS_Settings::get_general();
		$success_url = ! empty( $general['success_page_id'] )
			? get_permalink( (int) $general['success_page_id'] )
			: home_url( '/' );

		if ( ! $success_url ) {
			$success_url = home_url( '/' );
		}

		return add_query_arg(
			array(
				'art_lms_order' => $order->order_key,
			),
			$success_url
		);
	}

	/**
	 * Resolve redirect URL after checkout submit.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	public static function get_order_payment_redirect_url( $order ) {
		$gateway = Art_LMS_Payment_Gateway_Registry::get_for_order( $order );

		if ( ! $gateway ) {
			return self::get_order_success_url( $order );
		}

		if ( $gateway->should_skip_external_payment( $order ) ) {
			return self::get_order_success_url( $order );
		}

		return $gateway->get_checkout_redirect_url( $order );
	}

	/**
	 * Register checkout rewrite rule for configured slug.
	 */
	public static function register_rewrite() {
		$slug = Art_LMS_Settings::get_checkout_slug();

		if ( ! $slug ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( $slug, '/' ) . '/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Flush rewrite rules when checkout settings change (e.g. slug).
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 */
	public static function on_checkout_settings_updated( $old_value, $value ) {
		unset( $old_value );

		if ( ! is_array( $value ) ) {
			return;
		}

		$new_slug = Art_LMS_Settings::sanitize_checkout_slug( (string) ( $value['slug'] ?? '' ) );
		$stored   = (string) get_option( 'art_lms_checkout_rewrite_slug', '' );

		if ( $stored === $new_slug ) {
			return;
		}

		self::register_rewrite();
		flush_rewrite_rules( false );
		update_option( 'art_lms_checkout_rewrite_slug', $new_slug, false );
		update_option( 'art_lms_checkout_rewrite_version', ART_LMS_VERSION, false );
	}

	/**
	 * Flush rewrite rules when checkout slug or plugin version changes.
	 */
	public static function maybe_flush_rewrites() {
		$slug         = Art_LMS_Settings::get_checkout_slug();
		$stored_slug  = get_option( 'art_lms_checkout_rewrite_slug', '' );
		$stored_version = get_option( 'art_lms_checkout_rewrite_version', '' );

		if ( $stored_slug === $slug && $stored_version === ART_LMS_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'art_lms_checkout_rewrite_slug', $slug, false );
		update_option( 'art_lms_checkout_rewrite_version', ART_LMS_VERSION, false );
	}

	/**
	 * Register public query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Mark checkout requests even when rewrite rules are stale.
	 *
	 * @param WP $wp Current WordPress environment instance.
	 */
	public static function parse_checkout_request( $wp ) {
		if ( ! self::matches_checkout_path( self::get_request_path_from_wp( $wp ) ) ) {
			return;
		}

		$wp->query_vars[ self::QUERY_VAR ] = 1;
		unset( $wp->query_vars['pagename'], $wp->query_vars['page'], $wp->query_vars['name'] );
	}

	/**
	 * Serve checkout before theme templates can return 404.
	 */
	public static function maybe_serve_checkout() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! self::is_checkout_request() ) {
			return;
		}

		status_header( 200 );
		Art_LMS_Cache_Control::prevent_page_cache();

		self::load_template();
		exit;
	}

	/**
	 * Load checkout page shell based on design settings.
	 */
	public static function load_template() {
		$design   = Art_LMS_Settings::get_checkout_design();
		$template = 'standalone' === $design['template'] ? 'checkout-standalone.php' : 'checkout.php';
		$path     = ART_LMS_PLUGIN_DIR . 'public/views/' . $template;

		if ( ! file_exists( $path ) ) {
			$path = ART_LMS_PLUGIN_DIR . 'public/views/checkout.php';
		}

		include $path;
	}

	/**
	 * Load plugin checkout template.
	 *
	 * @param string $template Current template.
	 * @return string
	 */
	public static function filter_template( $template ) {
		if ( ! self::is_checkout_request() ) {
			return $template;
		}

		$design   = Art_LMS_Settings::get_checkout_design();
		$filename = 'standalone' === $design['template'] ? 'checkout-standalone.php' : 'checkout.php';
		$checkout_template = ART_LMS_PLUGIN_DIR . 'public/views/' . $filename;

		if ( ! file_exists( $checkout_template ) ) {
			$checkout_template = ART_LMS_PLUGIN_DIR . 'public/views/checkout.php';
		}

		return file_exists( $checkout_template ) ? $checkout_template : $template;
	}

	/**
	 * Whether current request is the checkout page.
	 *
	 * @return bool
	 */
	public static function is_checkout_request() {
		if ( get_query_var( self::QUERY_VAR ) ) {
			return true;
		}

		return self::matches_checkout_path( self::get_request_relative_path() );
	}

	/**
	 * Compare request path with configured checkout slug.
	 *
	 * @param string $path Relative request path.
	 * @return bool
	 */
	public static function matches_checkout_path( $path ) {
		$slug = Art_LMS_Settings::get_checkout_slug();

		if ( ! $slug ) {
			return false;
		}

		return $slug === trim( (string) $path, '/' );
	}

	/**
	 * Get request path relative to site home.
	 *
	 * @return string
	 */
	public static function get_request_relative_path() {
		global $wp;

		if ( isset( $wp ) && is_object( $wp ) ) {
			return self::get_request_path_from_wp( $wp );
		}

		return self::normalize_relative_path( self::get_raw_request_path() );
	}

	/**
	 * Get request path from WP environment.
	 *
	 * @param WP $wp Current WordPress environment instance.
	 * @return string
	 */
	public static function get_request_path_from_wp( $wp ) {
		if ( isset( $wp->request ) ) {
			return trim( (string) $wp->request, '/' );
		}

		return self::normalize_relative_path( self::get_raw_request_path() );
	}

	/**
	 * Read current request path from server vars.
	 *
	 * @return string
	 */
	private static function get_raw_request_path() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		return trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
	}

	/**
	 * Strip subdirectory home path from request path.
	 *
	 * @param string $path Raw request path.
	 * @return string
	 */
	private static function normalize_relative_path( $path ) {
		$path      = trim( (string) $path, '/' );
		$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

		if ( $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = trim( substr( $path, strlen( $home_path ) ), '/' );
		}

		return $path;
	}

	/**
	 * Enqueue checkout-only frontend assets.
	 */
	public static function enqueue_assets() {
		if ( ! self::is_checkout_request() ) {
			return;
		}

		wp_enqueue_style( 'art-lms-public' );
		wp_add_inline_style( 'art-lms-public', Art_LMS_Settings::get_checkout_design_css() );

		if ( self::is_design_preview_request() ) {
			return;
		}

		wp_enqueue_script( 'art-lms-checkout' );
		wp_localize_script(
			'art-lms-checkout',
			'artLmsCheckout',
			array(
				'config'   => Art_LMS_Settings::get_checkout_frontend_form_config(),
				'buttonId' => absint( $_GET[ Art_LMS_Payment_Buttons::CHECKOUT_QUERY_ARG ] ?? 0 ),
				'restUrl'  => esc_url_raw( rest_url( 'art-lms/v1/checkout/submit' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'strings'  => array(
					'submitting'            => __( 'Создаём заказ…', 'art-lms' ),
					'submitFailed'          => Art_LMS_Settings::format_checkout_form_message( 'create_order_failed' ),
					'verificationPending'   => Art_LMS_Settings::format_checkout_form_message( 'email_verification_sent' ),
				),
			)
		);
	}

	/**
	 * Remove theme and third-party styles from the checkout page.
	 */
	public static function strip_foreign_styles() {
		if ( ! self::is_checkout_request() ) {
			return;
		}

		$allowed = apply_filters(
			'art_lms_checkout_style_handles',
			array(
				'art-lms-public',
				'admin-bar',
				'dashicons',
			)
		);

		global $wp_styles;

		if ( ! ( $wp_styles instanceof WP_Styles ) ) {
			return;
		}

		foreach ( array_keys( $wp_styles->registered ) as $handle ) {
			if ( in_array( $handle, $allowed, true ) ) {
				continue;
			}

			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
	}

	/**
	 * Render checkout page content.
	 */
	public static function render_content() {
		include ART_LMS_PLUGIN_DIR . 'public/views/checkout-content.php';
	}

	/**
	 * Whether the current request is an admin-only checkout design preview.
	 *
	 * @return bool
	 */
	public static function is_design_preview_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( empty( $_GET[ self::QUERY_DESIGN_PREVIEW ] ) ) {
			return false;
		}

		return '1' === sanitize_text_field( wp_unslash( $_GET[ self::QUERY_DESIGN_PREVIEW ] ) );
	}

	/**
	 * Get frontend checkout URL for saved design preview.
	 *
	 * @return string
	 */
	public static function get_design_preview_url() {
		$url = Art_LMS_Settings::get_checkout_url();

		if ( ! $url ) {
			return '';
		}

		return add_query_arg( self::QUERY_DESIGN_PREVIEW, '1', $url );
	}
}
