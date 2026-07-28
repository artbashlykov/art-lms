<?php
/**
 * Custom front-end password reset pages (request + set new password).
 *
 * Enabled automatically when the custom login page is enabled.
 * Visual design is inherited from login settings.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public password URLs use shareable GET parameters.

/**
 * Class Art_LMS_Custom_Password
 */
class Art_LMS_Custom_Password {

	const QUERY_VAR = 'art_lms_password';

	/**
	 * Whether the custom password template is being rendered.
	 *
	 * @var bool
	 */
	private static $is_serving_template = false;

	/**
	 * Form / flow errors for the current request.
	 *
	 * @var WP_Error|null
	 */
	private static $errors = null;

	/**
	 * Register early hooks (wp-login redirects, URL filters).
	 */
	public static function boot() {
		add_filter( 'lostpassword_url', array( __CLASS__, 'filter_lostpassword_url' ), 10, 2 );
		add_filter( 'retrieve_password_message', array( __CLASS__, 'filter_retrieve_password_message_url' ), 5, 4 );
		add_action( 'login_init', array( __CLASS__, 'maybe_redirect_wp_login_password' ), 0 );

		if ( Art_LMS_Custom_Login::is_wp_login_request() ) {
			add_action( 'plugins_loaded', array( __CLASS__, 'maybe_redirect_wp_login_password' ), 19 );
		}
	}

	/**
	 * Register rewrite and front-end route hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 11 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 100 );
		add_action( 'update_option_' . Art_LMS_Settings::OPTION_PASSWORD, array( __CLASS__, 'on_password_settings_updated' ), 10, 2 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'parse_request', array( __CLASS__, 'parse_password_request' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_password' ), 0 );
	}

	/**
	 * Whether the password pages are active (tied to custom login).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return Art_LMS_Settings::is_custom_login_enabled();
	}

	/**
	 * Register rewrite rule for the configured password slug.
	 */
	public static function register_rewrite() {
		$slug = Art_LMS_Settings::get_password_slug();

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
	 * Flush rewrite rules when password settings change.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 */
	public static function on_password_settings_updated( $old_value, $value ) {
		unset( $old_value );

		if ( ! is_array( $value ) ) {
			return;
		}

		$new_slug = Art_LMS_Settings::sanitize_password_slug( (string) ( $value['slug'] ?? '' ) );
		$stored   = (string) get_option( 'art_lms_password_rewrite_slug', '' );

		if ( $stored === $new_slug ) {
			return;
		}

		self::register_rewrite();
		flush_rewrite_rules( false );
		update_option( 'art_lms_password_rewrite_slug', $new_slug, false );
		update_option( 'art_lms_password_rewrite_version', ART_LMS_VERSION, false );
	}

	/**
	 * Flush rewrite rules when password slug or plugin version changes.
	 */
	public static function maybe_flush_rewrites() {
		$slug           = Art_LMS_Settings::get_password_slug();
		$stored_slug    = get_option( 'art_lms_password_rewrite_slug', '' );
		$stored_version = get_option( 'art_lms_password_rewrite_version', '' );

		if ( $stored_slug === $slug && $stored_version === ART_LMS_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'art_lms_password_rewrite_slug', $slug, false );
		update_option( 'art_lms_password_rewrite_version', ART_LMS_VERSION, false );
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
	 * Mark password requests even when rewrite rules are stale.
	 *
	 * @param WP $wp Current WordPress environment instance.
	 */
	public static function parse_password_request( $wp ) {
		if ( ! self::matches_password_path( Art_LMS_Custom_Login::get_request_path_from_wp( $wp ) ) ) {
			return;
		}

		$wp->query_vars[ self::QUERY_VAR ] = 1;
		unset( $wp->query_vars['pagename'], $wp->query_vars['page'], $wp->query_vars['name'] );
	}

	/**
	 * Redirect password-related wp-login.php GET requests to the custom page.
	 */
	public static function maybe_redirect_wp_login_password() {
		if ( ! self::is_enabled() || ! Art_LMS_Custom_Login::is_wp_login_request() ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: 'GET';

		if ( 'POST' === $request_method ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';

		if ( ! in_array( $action, array( 'lostpassword', 'retrievepassword', 'rp', 'resetpass' ), true ) ) {
			// checkemail=confirm is shown without action on some WP versions.
			if ( empty( $_GET['checkemail'] ) ) {
				return;
			}
		}

		$url = self::get_url_from_wp_login_request();

		if ( '' === $url ) {
			return;
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Map current wp-login password request to the custom password URL.
	 *
	 * @return string
	 */
	private static function get_url_from_wp_login_request() {
		$args = array();

		if ( ! empty( $_GET['checkemail'] ) ) {
			$args['checkemail'] = sanitize_key( wp_unslash( $_GET['checkemail'] ) );
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
			$args['action'] = 'rp';

			if ( ! empty( $_GET['key'] ) ) {
				$args['key'] = sanitize_text_field( wp_unslash( $_GET['key'] ) );
			}

			if ( ! empty( $_GET['login'] ) ) {
				$args['login'] = sanitize_text_field( wp_unslash( $_GET['login'] ) );
			}
		}

		$redirect_to = Art_LMS_Custom_Login::get_sanitized_redirect_to_from_request();

		return self::get_url( $args, $redirect_to );
	}

	/**
	 * Replace lost-password URL when custom pages are enabled.
	 *
	 * @param string $url        Default lostpassword URL.
	 * @param string $redirect   Redirect target after reset flow.
	 * @return string
	 */
	public static function filter_lostpassword_url( $url, $redirect ) {
		if ( ! self::is_enabled() ) {
			return $url;
		}

		return self::get_lostpassword_url( $redirect );
	}

	/**
	 * Replace wp-login reset links in the default password-reset email body.
	 *
	 * @param string  $message    Email message.
	 * @param string  $key        Reset key.
	 * @param string  $user_login User login.
	 * @param WP_User $user_data  User object.
	 * @return string
	 */
	public static function filter_retrieve_password_message_url( $message, $key, $user_login, $user_data ) {
		unset( $user_data );

		if ( ! self::is_enabled() ) {
			return $message;
		}

		$custom_url = self::get_reset_url( (string) $key, (string) $user_login );

		if ( '' === $custom_url ) {
			return $message;
		}

		$replaced = preg_replace(
			'#https?://[^\s<>"\']*wp-login\.php\?action=rp[^\s<>"\']*#',
			$custom_url,
			(string) $message
		);

		return is_string( $replaced ) ? $replaced : $message;
	}

	/**
	 * Build lost-password (request) URL.
	 *
	 * @param string $redirect Redirect target.
	 * @return string
	 */
	public static function get_lostpassword_url( $redirect = '' ) {
		return self::get_url( array(), $redirect );
	}

	/**
	 * Build set-new-password URL (rp).
	 *
	 * @param string $key      Reset key.
	 * @param string $login    User login.
	 * @param string $redirect Redirect after success.
	 * @return string
	 */
	public static function get_reset_url( $key = '', $login = '', $redirect = '' ) {
		$args = array( 'action' => 'rp' );

		if ( '' !== (string) $key ) {
			$args['key'] = (string) $key;
		}

		if ( '' !== (string) $login ) {
			$args['login'] = (string) $login;
		}

		return self::get_url( $args, $redirect );
	}

	/**
	 * Build custom password page URL.
	 *
	 * @param array  $args     Extra query args.
	 * @param string $redirect Optional redirect_to.
	 * @return string
	 */
	public static function get_url( array $args = array(), $redirect = '' ) {
		$slug = Art_LMS_Settings::get_password_slug();
		$url  = $slug ? home_url( '/' . $slug . '/' ) : '';

		if ( ! $url ) {
			return '';
		}

		if ( array() !== $args ) {
			$url = add_query_arg( $args, $url );
		}

		$redirect = is_string( $redirect ) ? $redirect : '';

		if ( '' !== $redirect ) {
			$validated = wp_validate_redirect( $redirect, '' );

			if ( $validated ) {
				$url = add_query_arg( 'redirect_to', $validated, $url );
			}
		}

		return (string) apply_filters( 'art_lms_password_url', $url, $args, $redirect );
	}

	/**
	 * Serve custom password page.
	 */
	public static function maybe_serve_password() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! self::is_password_request() ) {
			return;
		}

		if ( ! self::is_enabled() ) {
			wp_safe_redirect( wp_lostpassword_url() );
			exit;
		}

		// Logged-in “Сменить пароль” from account — before guest-only redirect.
		Art_LMS_Account::maybe_handle_change_password_link();

		self::maybe_redirect_logged_in_user();
		self::maybe_handle_post();

		status_header( 200 );
		Art_LMS_Cache_Control::prevent_page_cache();

		self::$is_serving_template = true;
		self::load_template();
		exit;
	}

	/**
	 * Redirect authenticated visitors away from request / confirmation screens.
	 *
	 * The set-new-password screen (email link) stays available even when logged in.
	 */
	public static function maybe_redirect_logged_in_user() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( 'reset' === self::get_current_mode() ) {
			return;
		}

		$redirect_to = Art_LMS_Custom_Login::get_sanitized_redirect_to_from_get();
		$user        = wp_get_current_user();
		$destination = Art_LMS_Custom_Login::get_post_login_redirect_url( $user, $redirect_to );

		wp_safe_redirect( $destination );
		exit;
	}

	/**
	 * Handle lostpassword / resetpass POST on the custom page.
	 */
	private static function maybe_handle_post() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
			return;
		}

		$mode = self::get_current_mode();

		if ( 'reset' === $mode ) {
			self::handle_reset_post();
			return;
		}

		self::handle_lostpassword_post();
	}

	/**
	 * Process “forgot password” form.
	 */
	private static function handle_lostpassword_post() {
		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';

		if ( '' === $user_login ) {
			self::$errors = new WP_Error(
				'empty_username',
				__( 'Введите email.', 'art-lms' )
			);
			return;
		}

		$result = retrieve_password( $user_login );

		if ( is_wp_error( $result ) ) {
			self::$errors = $result;
			return;
		}

		$redirect_to = Art_LMS_Custom_Login::get_sanitized_redirect_to_from_request();
		wp_safe_redirect( self::get_url( array( 'checkemail' => 'confirm' ), $redirect_to ) );
		exit;
	}

	/**
	 * Process “set new password” form.
	 */
	private static function handle_reset_post() {
		$rp_key   = isset( $_POST['rp_key'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_key'] ) ) : '';
		$rp_login = isset( $_POST['rp_login'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_login'] ) ) : '';
		$pass1    = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password must stay raw.
		$pass2    = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password must stay raw.

		check_admin_referer( 'resetpass' );

		$user = check_password_reset_key( $rp_key, $rp_login );

		if ( is_wp_error( $user ) ) {
			self::$errors = new WP_Error(
				'invalid_key',
				Art_LMS_Settings::get_password_messages()['invalid_key']
			);
			return;
		}

		$errors = new WP_Error();

		if ( '' === $pass1 || '' === $pass2 ) {
			$errors->add( 'password_reset_empty', __( 'Введите новый пароль дважды.', 'art-lms' ) );
		} elseif ( $pass1 !== $pass2 ) {
			$errors->add( 'password_reset_mismatch', __( 'Пароли не совпадают.', 'art-lms' ) );
		}

		/**
		 * Fires before the password is reset on the custom page (same hook as wp-login).
		 *
		 * @param WP_Error $errors Errors object.
		 * @param WP_User  $user   User object.
		 */
		do_action( 'validate_password_reset', $errors, $user );

		if ( $errors->has_errors() ) {
			self::$errors = $errors;
			return;
		}

		reset_password( $user, $pass1 );

		$cookie_name = 'wp-resetpass-' . COOKIEHASH;
		setcookie( $cookie_name, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		$redirect_to = Art_LMS_Custom_Login::get_sanitized_redirect_to_from_request();
		wp_safe_redirect( self::get_url( array( 'password' => 'changed' ), $redirect_to ) );
		exit;
	}

	/**
	 * Current screen mode: lost|reset|checkemail|changed.
	 *
	 * @return string
	 */
	public static function get_current_mode() {
		if ( ! empty( $_GET['password'] ) && 'changed' === sanitize_key( wp_unslash( $_GET['password'] ) ) ) {
			return 'changed';
		}

		if ( ! empty( $_GET['checkemail'] ) ) {
			return 'checkemail';
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
			return 'reset';
		}

		if ( self::get_rp_key_and_login() ) {
			return 'reset';
		}

		return 'lost';
	}

	/**
	 * Resolve reset key/login from request or cookie.
	 *
	 * @return array{key: string, login: string}|null
	 */
	public static function get_rp_key_and_login() {
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

		if ( '' !== $key && '' !== $login ) {
			return array(
				'key'   => $key,
				'login' => $login,
			);
		}

		$cookie_name = 'wp-resetpass-' . COOKIEHASH;

		if ( empty( $_COOKIE[ $cookie_name ] ) || ! is_string( $_COOKIE[ $cookie_name ] ) ) {
			return null;
		}

		list( $rp_login, $rp_key ) = explode( ':', wp_unslash( $_COOKIE[ $cookie_name ] ), 2 );

		$rp_login = sanitize_text_field( $rp_login );
		$rp_key   = sanitize_text_field( $rp_key );

		if ( '' === $rp_login || '' === $rp_key ) {
			return null;
		}

		return array(
			'key'   => $rp_key,
			'login' => $rp_login,
		);
	}

	/**
	 * Errors for the current page render.
	 *
	 * @return WP_Error|null
	 */
	public static function get_errors() {
		return self::$errors;
	}

	/**
	 * Load password page shell.
	 */
	public static function load_template() {
		$path = ART_LMS_PLUGIN_DIR . 'public/views/password.php';

		if ( ! file_exists( $path ) ) {
			wp_die( esc_html__( 'Шаблон страницы пароля не найден.', 'art-lms' ), '', array( 'response' => 500 ) );
		}

		include $path;
	}

	/**
	 * Render password page content.
	 */
	public static function render_content() {
		include ART_LMS_PLUGIN_DIR . 'public/views/password-content.php';
	}

	/**
	 * Print shared login design styles for the password page.
	 */
	public static function print_template_styles() {
		if ( ! self::is_enabled() ) {
			return;
		}

		Art_LMS_Custom_Login::print_template_styles();
	}

	/**
	 * Whether current request is the custom password page.
	 *
	 * @return bool
	 */
	public static function is_password_request() {
		if ( get_query_var( self::QUERY_VAR ) ) {
			return true;
		}

		return self::matches_password_path( Art_LMS_Custom_Login::get_request_relative_path() );
	}

	/**
	 * Compare request path with configured password slug.
	 *
	 * @param string $path Relative request path.
	 * @return bool
	 */
	public static function matches_password_path( $path ) {
		$slug = Art_LMS_Settings::get_password_slug();

		if ( ! $slug ) {
			return false;
		}

		return $slug === trim( (string) $path, '/' );
	}

	/**
	 * Whether the custom password template is being rendered.
	 *
	 * @return bool
	 */
	public static function is_serving_template() {
		return self::$is_serving_template;
	}
}
