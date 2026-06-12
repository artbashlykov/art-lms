<?php
/**
 * Custom front-end login page route.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public login URLs use shareable GET parameters.

/**
 * Class Art_LMS_Custom_Login
 */
class Art_LMS_Custom_Login {

	const QUERY_VAR = 'art_lms_login';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 10 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 99 );
		add_action( 'update_option_' . Art_LMS_Settings::OPTION_LOGIN, array( __CLASS__, 'on_login_settings_updated' ), 10, 2 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_login_request' ), 0 );
		add_action( 'login_init', array( __CLASS__, 'maybe_redirect_wp_login' ), 1 );
		add_filter( 'login_url', array( __CLASS__, 'filter_login_url' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_login' ), 0 );
		add_filter( 'template_include', array( __CLASS__, 'filter_template' ) );
	}

	/**
	 * Whether custom login page is active.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return Art_LMS_Settings::is_custom_login_enabled();
	}

	/**
	 * Register rewrite rule for the configured login slug.
	 */
	public static function register_rewrite() {
		$slug = Art_LMS_Settings::get_login_slug();

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
	 * Flush rewrite rules when login settings change.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 */
	public static function on_login_settings_updated( $old_value, $value ) {
		unset( $old_value );

		if ( ! is_array( $value ) ) {
			return;
		}

		$new_slug = Art_LMS_Settings::sanitize_login_slug( (string) ( $value['slug'] ?? '' ) );
		$stored   = (string) get_option( 'art_lms_login_rewrite_slug', '' );

		if ( $stored === $new_slug ) {
			return;
		}

		self::register_rewrite();
		flush_rewrite_rules( false );
		update_option( 'art_lms_login_rewrite_slug', $new_slug, false );
		update_option( 'art_lms_login_rewrite_version', ART_LMS_VERSION, false );
	}

	/**
	 * Flush rewrite rules when login slug or plugin version changes.
	 */
	public static function maybe_flush_rewrites() {
		$slug           = Art_LMS_Settings::get_login_slug();
		$stored_slug    = get_option( 'art_lms_login_rewrite_slug', '' );
		$stored_version = get_option( 'art_lms_login_rewrite_version', '' );

		if ( $stored_slug === $slug && $stored_version === ART_LMS_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'art_lms_login_rewrite_slug', $slug, false );
		update_option( 'art_lms_login_rewrite_version', ART_LMS_VERSION, false );
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
	 * Mark login requests even when rewrite rules are stale.
	 *
	 * @param WP $wp Current WordPress environment instance.
	 */
	public static function parse_login_request( $wp ) {
		if ( ! self::matches_login_path( self::get_request_path_from_wp( $wp ) ) ) {
			return;
		}

		$wp->query_vars[ self::QUERY_VAR ] = 1;
		unset( $wp->query_vars['pagename'], $wp->query_vars['page'], $wp->query_vars['name'] );
	}

	/**
	 * Redirect default wp-login.php GET requests to the custom login page.
	 */
	public static function maybe_redirect_wp_login() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: 'GET';

		if ( 'POST' === $request_method ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';

		$wp_login_only_actions = array(
			'logout',
			'rp',
			'resetpass',
			'lostpassword',
			'postpass',
			'confirmaction',
			'register',
			'confirm_admin_email',
		);

		if ( in_array( $action, $wp_login_only_actions, true ) ) {
			return;
		}

		$url = self::get_url( self::get_sanitized_redirect_to_from_request() );

		if ( ! empty( $_REQUEST['reauth'] ) ) {
			$url = add_query_arg( 'reauth', '1', $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Replace login URL when custom login page is enabled.
	 *
	 * @param string $login_url    Login URL.
	 * @param string $redirect     Redirect target.
	 * @param bool   $force_reauth Whether to force reauthentication.
	 * @return string
	 */
	public static function filter_login_url( $login_url, $redirect, $force_reauth ) {
		if ( ! self::is_enabled() ) {
			return $login_url;
		}

		$url = self::get_url( $redirect );

		if ( $force_reauth ) {
			$url = add_query_arg( 'reauth', '1', $url );
		}

		return $url;
	}

	/**
	 * Serve custom login before theme templates can return 404.
	 */
	public static function maybe_serve_login() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! self::is_login_request() ) {
			return;
		}

		if ( ! self::is_enabled() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		self::maybe_redirect_logged_in_user();

		status_header( 200 );
		Art_LMS_Cache_Control::prevent_page_cache();

		self::load_template();
		exit;
	}

	/**
	 * Redirect authenticated visitors away from the custom login page.
	 */
	public static function maybe_redirect_logged_in_user() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$redirect_to = self::get_sanitized_redirect_to_from_get();
		$destination = $redirect_to ? $redirect_to : home_url( '/' );

		wp_safe_redirect( $destination );
		exit;
	}

	/**
	 * Load login page shell.
	 */
	public static function load_template() {
		self::maybe_redirect_logged_in_user();

		$path = ART_LMS_PLUGIN_DIR . 'public/views/login.php';

		if ( ! file_exists( $path ) ) {
			wp_die( esc_html__( 'Шаблон страницы входа не найден.', 'art-lms' ), '', array( 'response' => 500 ) );
		}

		self::enqueue_assets();
		include $path;
	}

	/**
	 * Load plugin login template.
	 *
	 * @param string $template Current template.
	 * @return string
	 */
	public static function filter_template( $template ) {
		if ( ! self::is_login_request() || ! self::is_enabled() ) {
			return $template;
		}

		$login_template = ART_LMS_PLUGIN_DIR . 'public/views/login.php';

		return file_exists( $login_template ) ? $login_template : $template;
	}

	/**
	 * Whether current request is the custom login page.
	 *
	 * @return bool
	 */
	public static function is_login_request() {
		if ( get_query_var( self::QUERY_VAR ) ) {
			return true;
		}

		return self::matches_login_path( self::get_request_relative_path() );
	}

	/**
	 * Compare request path with configured login slug.
	 *
	 * @param string $path Relative request path.
	 * @return bool
	 */
	public static function matches_login_path( $path ) {
		$slug = Art_LMS_Settings::get_login_slug();

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

		return self::get_request_path_from_wp( $wp );
	}

	/**
	 * Extract relative path from WP environment.
	 *
	 * @param WP|null $wp WordPress environment.
	 * @return string
	 */
	public static function get_request_path_from_wp( $wp ) {
		if ( ! $wp instanceof WP ) {
			return '';
		}

		$path = isset( $wp->request ) ? (string) $wp->request : '';

		if ( '' !== $path ) {
			return trim( $path, '/' );
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		$uri_path    = '' !== $request_uri ? wp_parse_url( $request_uri, PHP_URL_PATH ) : '';

		if ( ! is_string( $uri_path ) || '' === $uri_path ) {
			return '';
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( is_string( $home_path ) && '' !== $home_path && '/' !== $home_path ) {
			$prefix = untrailingslashit( $home_path );

			if ( 0 === strpos( $uri_path, $prefix ) ) {
				$uri_path = substr( $uri_path, strlen( $prefix ) );
			}
		}

		return trim( $uri_path, '/' );
	}

	/**
	 * Build custom login page URL.
	 *
	 * @param string $redirect Redirect target after login.
	 * @return string
	 */
	public static function get_url( $redirect = '' ) {
		$slug = Art_LMS_Settings::get_login_slug();
		$url  = $slug ? home_url( '/' . $slug . '/' ) : '';

		if ( ! $url ) {
			return '';
		}

		$redirect = is_string( $redirect ) ? $redirect : '';

		if ( '' !== $redirect ) {
			$validated = wp_validate_redirect( $redirect, '' );

			if ( $validated ) {
				$url = add_query_arg( 'redirect_to', $validated, $url );
			}
		}

		return (string) apply_filters( 'art_lms_login_url', $url, $redirect, Art_LMS_Settings::get_login() );
	}

	/**
	 * Read and validate redirect_to from the current GET request.
	 *
	 * @return string
	 */
	public static function get_sanitized_redirect_to_from_get() {
		if ( ! isset( $_GET['redirect_to'] ) ) {
			return '';
		}

		return wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), '' );
	}

	/**
	 * Read and validate redirect_to from the current request superglobal.
	 *
	 * @return string
	 */
	public static function get_sanitized_redirect_to_from_request() {
		if ( ! isset( $_REQUEST['redirect_to'] ) ) {
			return '';
		}

		return wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), '' );
	}

	/**
	 * Render login page content.
	 */
	public static function render_content() {
		include ART_LMS_PLUGIN_DIR . 'public/views/login-content.php';
	}

	/**
	 * Enqueue login page assets.
	 */
	public static function enqueue_assets() {
		wp_enqueue_style( 'art-lms-public' );

		$css = Art_LMS_Settings::get_login_design_css();

		if ( $css ) {
			wp_add_inline_style( 'art-lms-public', $css );
		}
	}
}
