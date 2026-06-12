<?php
/**
 * Plugin settings API.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Settings
 */
class Art_LMS_Settings {

	const OPTION_GENERAL  = 'art_lms_settings_general';
	const OPTION_LOGIN    = 'art_lms_settings_login';
	const OPTION_PAYMENT  = 'art_lms_settings_payment';
	const OPTION_CHECKOUT = 'art_lms_settings_checkout';
	const OPTION_EMAIL    = 'art_lms_settings_email';
	const OPTION_MIGRATED              = 'art_lms_settings_migrated';
	const OPTION_DELETE_DATA_ON_UNINSTALL = 'art_lms_delete_data_on_uninstall';

	/**
	 * In-request cache.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'bootstrap_settings' ), 5 );
	}

	/**
	 * Ensure settings exist on first plugin run.
	 */
	public static function bootstrap_settings() {
		if ( get_option( self::OPTION_MIGRATED ) ) {
			return;
		}

		self::ensure_defaults();
		update_option( self::OPTION_MIGRATED, ART_LMS_VERSION, false );
	}

	/**
	 * Ensure all settings exist with defaults.
	 */
	public static function ensure_defaults() {
		if ( false === get_option( self::OPTION_GENERAL, false ) ) {
			self::save_general( self::get_default_general() );
		}

		if ( false === get_option( self::OPTION_PAYMENT, false ) ) {
			self::save_payment( self::get_default_payment() );
		}

		if ( false === get_option( self::OPTION_LOGIN, false ) ) {
			self::save_login( self::get_default_login() );
		}

		if ( false === get_option( self::OPTION_CHECKOUT, false ) ) {
			self::save_checkout( self::get_default_checkout() );
		}

		if ( false === get_option( self::OPTION_EMAIL, false ) ) {
			self::save_emails( self::get_default_emails() );
		}
	}

	/**
	 * Get login page settings.
	 *
	 * @return array
	 */
	public static function get_login() {
		return self::get_option( self::OPTION_LOGIN, self::get_default_login() );
	}

	/**
	 * Whether custom login page is enabled.
	 *
	 * @return bool
	 */
	public static function is_custom_login_enabled() {
		return 'yes' === ( self::get_login()['enabled'] ?? 'no' );
	}

	/**
	 * Get configured custom login slug.
	 *
	 * @return string
	 */
	public static function get_login_slug() {
		$login = self::get_login();
		$slug  = sanitize_title( (string) ( $login['slug'] ?? '' ) );

		if ( '' === $slug ) {
			$slug = self::get_default_login()['slug'];
		}

		return $slug;
	}

	/**
	 * Sanitize custom login slug.
	 *
	 * @param string $slug Raw slug.
	 * @return string
	 */
	public static function sanitize_login_slug( $slug ) {
		$slug = sanitize_title( (string) $slug );

		if ( '' === $slug ) {
			$slug = self::get_default_login()['slug'];
		}

		if ( $slug === self::get_checkout_slug() ) {
			$slug .= '-login';
		}

		return $slug;
	}

	/**
	 * Get login page URL (custom or default WordPress login).
	 *
	 * @param string $redirect     Redirect target after login.
	 * @param bool   $force_reauth Whether to force reauthentication.
	 * @return string
	 */
	public static function get_login_page_url( $redirect = '', $force_reauth = false ) {
		return wp_login_url( $redirect, $force_reauth );
	}

	/**
	 * Get login page design settings.
	 *
	 * @return array<string, string>
	 */
	public static function get_login_design() {
		$login    = self::get_login();
		$defaults = self::get_default_login()['design'];

		return wp_parse_args( $login['design'] ?? array(), $defaults );
	}

	/**
	 * Default color values for login design controls.
	 *
	 * @return array<string, string>
	 */
	public static function get_login_design_color_defaults() {
		$defaults = self::get_default_login()['design'];

		return array(
			'page_background_color'    => $defaults['page_background_color'],
			'form_background_color'    => $defaults['form_background_color'],
			'form_border_color'      => $defaults['form_border_color'],
			'field_border_color'     => $defaults['field_border_color'],
			'field_focus_border_color' => $defaults['field_focus_border_color'],
		);
	}

	/**
	 * Default dimension values for login design controls.
	 *
	 * @return array<string, int>
	 */
	public static function get_login_design_dimension_defaults() {
		$defaults = self::get_default_login()['design'];

		return array(
			'form_max_width'        => (int) $defaults['form_max_width'],
			'form_padding'          => (int) $defaults['form_padding'],
			'form_border_radius'    => (int) $defaults['form_border_radius'],
			'field_label_font_size' => (int) $defaults['field_label_font_size'],
			'field_input_font_size' => (int) $defaults['field_input_font_size'],
		);
	}

	/**
	 * Build inline CSS for login design tokens.
	 *
	 * @return string
	 */
	public static function get_login_design_css() {
		if ( ! self::is_custom_login_enabled() ) {
			return '';
		}

		$design = self::get_login_design();
		$button = self::get_login_button();

		return sprintf(
			':root{--art-lms-login-page-bg:%1$s;--art-lms-login-form-bg:%2$s;--art-lms-login-form-border:%3$s;--art-lms-login-form-width:%4$dpx;--art-lms-login-form-padding:%5$dpx;--art-lms-login-form-radius:%6$dpx;--art-lms-login-field-label-font-size:%7$dpx;--art-lms-login-field-input-font-size:%8$dpx;--art-lms-login-field-border:%9$s;--art-lms-login-field-focus-border:%10$s;--art-lms-login-button-bg:%11$s;--art-lms-login-button-color:%12$s;--art-lms-login-button-font-size:%13$dpx;--art-lms-login-button-radius:%14$dpx;--art-lms-login-button-padding-y:%15$dpx;--art-lms-login-button-padding-x:%16$dpx;}',
			esc_attr( $design['page_background_color'] ),
			esc_attr( $design['form_background_color'] ),
			esc_attr( $design['form_border_color'] ),
			(int) $design['form_max_width'],
			(int) $design['form_padding'],
			(int) $design['form_border_radius'],
			(int) $design['field_label_font_size'],
			(int) $design['field_input_font_size'],
			esc_attr( $design['field_border_color'] ),
			esc_attr( $design['field_focus_border_color'] ),
			esc_attr( $button['background_color'] ),
			esc_attr( $button['text_color'] ),
			(int) $button['font_size'],
			(int) $button['border_radius'],
			(int) $button['custom_padding_y'],
			(int) $button['custom_padding_x']
		);
	}

	/**
	 * Get login button settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_login_button() {
		$login    = self::get_login();
		$defaults = self::get_default_login()['button'];

		return wp_parse_args( $login['button'] ?? array(), $defaults );
	}

	/**
	 * Default color values for login button controls.
	 *
	 * @return array<string, string>
	 */
	public static function get_login_button_color_defaults() {
		$defaults = self::get_default_login()['button'];

		return array(
			'background_color' => $defaults['background_color'],
			'text_color'       => $defaults['text_color'],
		);
	}

	/**
	 * Default dimension values for login button controls.
	 *
	 * @return array<string, int>
	 */
	public static function get_login_button_dimension_defaults() {
		$defaults = self::get_default_login()['button'];

		return array(
			'font_size'         => (int) $defaults['font_size'],
			'border_radius'     => (int) $defaults['border_radius'],
			'custom_padding_y'  => (int) $defaults['custom_padding_y'],
			'custom_padding_x'  => (int) $defaults['custom_padding_x'],
		);
	}

	/**
	 * Button size options for login form.
	 *
	 * @return array<string, string>
	 */
	public static function get_login_button_size_options() {
		return array(
			'small'  => __( 'Маленькая', 'art-lms' ),
			'medium' => __( 'Средняя', 'art-lms' ),
			'large'  => __( 'Большая', 'art-lms' ),
			'custom' => __( 'Произвольный', 'art-lms' ),
		);
	}

	/**
	 * Button alignment options for login form.
	 *
	 * @return array<string, string>
	 */
	public static function get_login_button_align_options() {
		return array(
			'left'   => __( 'Слева', 'art-lms' ),
			'center' => __( 'По центру', 'art-lms' ),
			'right'  => __( 'Справа', 'art-lms' ),
			'full'   => __( 'На всю ширину', 'art-lms' ),
		);
	}

	/**
	 * CSS classes for login form button wrapper.
	 *
	 * @return string
	 */
	public static function get_login_button_wrapper_class() {
		$button = self::get_login_button();
		$sizes  = array_keys( self::get_login_button_size_options() );
		$aligns = array_keys( self::get_login_button_align_options() );
		$size   = $button['size'] ?? 'medium';
		$align  = $button['align'] ?? 'full';

		if ( ! in_array( $size, $sizes, true ) ) {
			$size = 'medium';
		}

		if ( ! in_array( $align, $aligns, true ) ) {
			$align = 'full';
		}

		return 'art-lms-login--button-size-' . $size . ' art-lms-login--button-align-' . $align;
	}

	/**
	 * Sanitize login button settings.
	 *
	 * @param array $input Raw button input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_login_button( $input ) {
		$defaults = self::get_default_login()['button'];
		$sizes    = array_keys( self::get_login_button_size_options() );
		$aligns   = array_keys( self::get_login_button_align_options() );

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$size  = isset( $input['size'] ) ? sanitize_key( $input['size'] ) : $defaults['size'];
		$align = isset( $input['align'] ) ? sanitize_key( $input['align'] ) : $defaults['align'];

		if ( ! in_array( $size, $sizes, true ) ) {
			$size = $defaults['size'];
		}

		if ( ! in_array( $align, $aligns, true ) ) {
			$align = $defaults['align'];
		}

		$background = isset( $input['background_color'] ) ? sanitize_hex_color( $input['background_color'] ) : '';
		$text_color = isset( $input['text_color'] ) ? sanitize_hex_color( $input['text_color'] ) : '';

		return array(
			'text'              => self::sanitize_login_form_text(
				$input['text'] ?? $defaults['text'],
				$defaults['text'],
				50
			),
			'font_size'         => self::sanitize_checkout_design_dimension(
				$input['font_size'] ?? $defaults['font_size'],
				$defaults['font_size'],
				10,
				48
			),
			'size'              => $size,
			'align'             => $align,
			'background_color'  => $background ? $background : $defaults['background_color'],
			'text_color'        => $text_color ? $text_color : $defaults['text_color'],
			'border_radius'     => self::sanitize_checkout_design_dimension(
				$input['border_radius'] ?? $defaults['border_radius'],
				$defaults['border_radius'],
				0,
				48
			),
			'custom_padding_y'  => self::sanitize_checkout_design_dimension(
				$input['custom_padding_y'] ?? $defaults['custom_padding_y'],
				$defaults['custom_padding_y'],
				4,
				40
			),
			'custom_padding_x'  => self::sanitize_checkout_design_dimension(
				$input['custom_padding_x'] ?? $defaults['custom_padding_x'],
				$defaults['custom_padding_x'],
				8,
				80
			),
		);
	}

	/**
	 * Sanitize login page design settings.
	 *
	 * @param array $input Raw design input.
	 * @return array<string, string>
	 */
	public static function sanitize_login_design( $input ) {
		$defaults = self::get_default_login()['design'];

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$page_background = isset( $input['page_background_color'] ) ? sanitize_hex_color( $input['page_background_color'] ) : '';
		$form_background = isset( $input['form_background_color'] ) ? sanitize_hex_color( $input['form_background_color'] ) : '';
		$form_border     = isset( $input['form_border_color'] ) ? sanitize_hex_color( $input['form_border_color'] ) : '';
		$field_border    = isset( $input['field_border_color'] ) ? sanitize_hex_color( $input['field_border_color'] ) : '';
		$field_focus     = isset( $input['field_focus_border_color'] ) ? sanitize_hex_color( $input['field_focus_border_color'] ) : '';

		return array(
			'page_background_color'    => $page_background ? $page_background : $defaults['page_background_color'],
			'form_background_color'    => $form_background ? $form_background : $defaults['form_background_color'],
			'form_border_color'        => $form_border ? $form_border : $defaults['form_border_color'],
			'form_max_width'           => self::sanitize_checkout_design_dimension(
				$input['form_max_width'] ?? $defaults['form_max_width'],
				$defaults['form_max_width'],
				280,
				720
			),
			'form_padding'             => self::sanitize_checkout_design_dimension(
				$input['form_padding'] ?? $defaults['form_padding'],
				$defaults['form_padding'],
				0,
				80
			),
			'form_border_radius'       => self::sanitize_checkout_design_dimension(
				$input['form_border_radius'] ?? $defaults['form_border_radius'],
				$defaults['form_border_radius'],
				0,
				48
			),
			'field_label_font_size'    => self::sanitize_checkout_design_dimension(
				$input['field_label_font_size'] ?? $defaults['field_label_font_size'],
				$defaults['field_label_font_size'],
				10,
				32
			),
			'field_input_font_size'    => self::sanitize_checkout_design_dimension(
				$input['field_input_font_size'] ?? $defaults['field_input_font_size'],
				$defaults['field_input_font_size'],
				10,
				32
			),
			'field_border_color'       => $field_border ? $field_border : $defaults['field_border_color'],
			'field_focus_border_color' => $field_focus ? $field_focus : $defaults['field_focus_border_color'],
		);
	}

	/**
	 * Get login form content settings.
	 *
	 * @return array<string, string>
	 */
	public static function get_login_form() {
		$login    = self::get_login();
		$defaults = self::get_default_login()['form'];

		return wp_parse_args( $login['form'] ?? array(), $defaults );
	}

	/**
	 * Default text values for login form controls.
	 *
	 * @return array<string, string>
	 */
	public static function get_login_form_text_defaults() {
		$defaults = self::get_default_login()['form'];

		return array(
			'title_text'          => $defaults['title_text'],
			'subtitle_text'       => $defaults['subtitle_text'],
			'username_label'      => $defaults['username_label'],
			'password_label'      => $defaults['password_label'],
			'remember_label'      => $defaults['remember_label'],
			'lost_password_text' => $defaults['lost_password_text'],
		);
	}

	/**
	 * Sanitize login form content settings.
	 *
	 * @param array $input Raw form input.
	 * @return array<string, string>
	 */
	public static function sanitize_login_form( $input ) {
		$defaults = self::get_default_login()['form'];

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		return array(
			'title_enabled'         => ! empty( $input['title_enabled'] ) ? 'yes' : 'no',
			'title_text'            => self::sanitize_login_form_text(
				$input['title_text'] ?? $defaults['title_text'],
				$defaults['title_text'],
				100
			),
			'subtitle_enabled'      => ! empty( $input['subtitle_enabled'] ) ? 'yes' : 'no',
			'subtitle_text'         => self::sanitize_login_form_text(
				$input['subtitle_text'] ?? $defaults['subtitle_text'],
				$defaults['subtitle_text'],
				200
			),
			'username_label'        => self::sanitize_login_form_text(
				$input['username_label'] ?? $defaults['username_label'],
				$defaults['username_label'],
				80
			),
			'password_label'        => self::sanitize_login_form_text(
				$input['password_label'] ?? $defaults['password_label'],
				$defaults['password_label'],
				80
			),
			'remember_enabled'      => ! empty( $input['remember_enabled'] ) ? 'yes' : 'no',
			'remember_label'        => self::sanitize_login_form_text(
				$input['remember_label'] ?? $defaults['remember_label'],
				$defaults['remember_label'],
				80
			),
			'lost_password_enabled' => ! empty( $input['lost_password_enabled'] ) ? 'yes' : 'no',
			'lost_password_text'    => self::sanitize_login_form_text(
				$input['lost_password_text'] ?? $defaults['lost_password_text'],
				$defaults['lost_password_text'],
				80
			),
		);
	}

	/**
	 * Sanitize a single login form text field.
	 *
	 * @param mixed  $value   Raw value.
	 * @param string $default Default value.
	 * @param int    $max     Maximum length.
	 * @return string
	 */
	private static function sanitize_login_form_text( $value, $default, $max ) {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );
		$value = trim( $value );

		if ( '' === $value ) {
			return $default;
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}

		return substr( $value, 0, $max );
	}

	/**
	 * Get general settings.
	 *
	 * @return array
	 */
	public static function get_general() {
		return self::get_option( self::OPTION_GENERAL, self::get_default_general() );
	}

	/**
	 * Whether checkout should create a WordPress user before payment.
	 *
	 * @return bool
	 */
	public static function should_create_user_before_payment() {
		return 'yes' === ( self::get_general()['create_user_before_payment'] ?? 'yes' );
	}

	/**
	 * Whether checkout should require email verification for new users.
	 *
	 * @return bool
	 */
	public static function should_require_email_verification() {
		if ( ! self::should_create_user_before_payment() ) {
			return false;
		}

		return 'email' === self::get_user_registration_verification();
	}

	/**
	 * Get user registration verification mode.
	 *
	 * @return string none|email
	 */
	public static function get_user_registration_verification() {
		$mode = sanitize_key( (string) ( self::get_general()['user_registration_verification'] ?? 'none' ) );

		return in_array( $mode, array( 'none', 'email' ), true ) ? $mode : 'none';
	}

	/**
	 * Whether a newly registered buyer should be logged in automatically.
	 *
	 * @return bool
	 */
	public static function should_auto_login_after_register() {
		return 'yes' === ( self::get_general()['auto_login_after_register'] ?? 'yes' );
	}

	/**
	 * Assign account page in general and account settings.
	 *
	 * @param int $page_id Page ID.
	 */
	public static function assign_account_page( $page_id ) {
		$page_id = absint( $page_id );
		$general = self::get_general();

		self::update_option(
			self::OPTION_GENERAL,
			array_merge(
				$general,
				array(
					'account_page_id' => $page_id,
				)
			)
		);
	}

	/**
	 * Get configured account page ID.
	 *
	 * @return int
	 */
	public static function get_account_page_id() {
		return absint( self::get_general()['account_page_id'] ?? 0 );
	}

	/**
	 * Whether plugin data should be removed on uninstall.
	 *
	 * @return bool
	 */
	public static function delete_data_on_uninstall_enabled() {
		return 'yes' === get_option( self::OPTION_DELETE_DATA_ON_UNINSTALL, 'no' );
	}

	/**
	 * Persist the uninstall data removal preference.
	 *
	 * @param bool $enabled Whether to delete data on uninstall.
	 */
	public static function set_delete_data_on_uninstall( $enabled ) {
		update_option( self::OPTION_DELETE_DATA_ON_UNINSTALL, $enabled ? 'yes' : 'no', false );
	}

	/**
	 * Get support email for payment issues.
	 *
	 * @return string
	 */
	public static function get_support_email() {
		$email = sanitize_email( (string) ( self::get_general()['support_email'] ?? '' ) );

		if ( is_email( $email ) ) {
			return $email;
		}

		$admin_email = sanitize_email( (string) get_option( 'admin_email' ) );

		return is_email( $admin_email ) ? $admin_email : '';
	}

	/**
	 * Assign success page in general settings.
	 *
	 * @param int $page_id Page ID.
	 */
	public static function assign_success_page( $page_id ) {
		$page_id = absint( $page_id );
		$general = self::get_general();

		self::update_option(
			self::OPTION_GENERAL,
			array_merge(
				$general,
				array(
					'success_page_id' => $page_id,
				)
			)
		);
	}

	/**
	 * Get configured success page ID.
	 *
	 * @return int
	 */
	public static function get_success_page_id() {
		return absint( self::get_general()['success_page_id'] ?? 0 );
	}

	/**
	 * Get payment settings.
	 *
	 * @return array
	 */
	public static function get_payment() {
		return self::get_option( self::OPTION_PAYMENT, self::get_default_payment() );
	}

	/**
	 * Get checkout settings.
	 *
	 * @return array
	 */
	public static function get_checkout() {
		return self::get_option( self::OPTION_CHECKOUT, self::get_default_checkout() );
	}

	/**
	 * Checkout form heading shown above the order form.
	 *
	 * @return string
	 */
	public static function get_checkout_form_title() {
		$checkout = self::get_checkout();
		$title    = trim( (string) ( $checkout['form_title'] ?? '' ) );

		if ( '' === $title ) {
			return self::get_default_checkout()['form_title'];
		}

		return $title;
	}

	/**
	 * Get email settings.
	 *
	 * @return array
	 */
	public static function get_emails() {
		return self::get_option( self::OPTION_EMAIL, self::get_default_emails() );
	}

	/**
	 * Get configured email sender identity.
	 *
	 * @return array{email_from: string, email_from_name: string}
	 */
	public static function get_email_sender() {
		$emails = self::get_emails();

		$email_from      = sanitize_email( $emails['email_from'] ?? '' );
		$email_from_name = sanitize_text_field( $emails['email_from_name'] ?? '' );

		if ( ! is_email( $email_from ) ) {
			$email_from = get_option( 'admin_email' );
		}

		if ( '' === $email_from_name ) {
			$email_from_name = get_bloginfo( 'name' );
		}

		return array(
			'email_from'      => $email_from,
			'email_from_name' => $email_from_name,
		);
	}

	/**
	 * Get legacy active gateway slug (alias for default checkout gateway).
	 *
	 * @return string
	 */
	public static function get_active_gateway() {
		$default = self::get_default_checkout_gateway();

		if ( '' !== $default ) {
			return $default;
		}

		foreach ( self::get_ordered_gateway_ids() as $gateway_id ) {
			if ( self::is_checkout_gateway_available( $gateway_id ) ) {
				return $gateway_id;
			}
		}

		return 'yoomoney';
	}

	/**
	 * Get ordered gateway IDs for checkout and admin lists.
	 *
	 * @return array<int, string>
	 */
	public static function get_ordered_gateway_ids() {
		$payment      = self::get_payment();
		$registry_ids = array_keys( Art_LMS_Payment_Gateway_Registry::all() );
		$order        = is_array( $payment['gateway_order'] ?? null ) ? $payment['gateway_order'] : array();
		$normalized   = array();

		foreach ( $order as $gateway_id ) {
			$gateway_id = sanitize_key( (string) $gateway_id );

			if ( in_array( $gateway_id, $registry_ids, true ) && ! in_array( $gateway_id, $normalized, true ) ) {
				$normalized[] = $gateway_id;
			}
		}

		foreach ( $registry_ids as $gateway_id ) {
			if ( ! in_array( $gateway_id, $normalized, true ) ) {
				$normalized[] = $gateway_id;
			}
		}

		return $normalized;
	}

	/**
	 * Get default checkout gateway if enabled.
	 *
	 * @return string Empty string when not configured or disabled.
	 */
	public static function get_default_checkout_gateway() {
		$payment = self::get_payment();
		$default = sanitize_key( (string) ( $payment['default_gateway'] ?? $payment['active_gateway'] ?? '' ) );

		if ( '' === $default || ! self::is_checkout_gateway_available( $default ) ) {
			return '';
		}

		return $default;
	}

	/**
	 * Whether a gateway is enabled for checkout.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return bool
	 */
	public static function is_checkout_gateway_available( $gateway_id ) {
		$gateway_id = sanitize_key( (string) $gateway_id );

		if ( ! Art_LMS_Payment_Gateway_Registry::get( $gateway_id ) ) {
			return false;
		}

		$settings = self::get_gateway( $gateway_id );

		return ( $settings['enabled'] ?? 'no' ) === 'yes';
	}

	/**
	 * Get external display name for a gateway.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return string
	 */
	public static function get_gateway_display_name( $gateway_id ) {
		$gateway = Art_LMS_Payment_Gateway_Registry::get( $gateway_id );

		if ( ! $gateway ) {
			return '';
		}

		$settings = self::get_gateway( $gateway_id );
		$display  = trim( (string) ( $settings['display_name'] ?? '' ) );

		if ( '' !== $display ) {
			return $display;
		}

		$meta = $gateway->get_meta();

		return (string) ( $meta['title'] ?? $gateway_id );
	}

	/**
	 * Get enabled gateways for checkout dropdown.
	 *
	 * @return array<int, array{id: string, label: string}>
	 */
	public static function get_checkout_payment_methods() {
		$methods = array();

		foreach ( self::get_ordered_gateway_ids() as $gateway_id ) {
			if ( ! self::is_checkout_gateway_available( $gateway_id ) ) {
				continue;
			}

			$methods[] = array(
				'id'    => $gateway_id,
				'label' => self::get_gateway_display_name( $gateway_id ),
			);
		}

		return $methods;
	}

	/**
	 * Get gateway settings by ID.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return array
	 */
	public static function get_gateway( $gateway_id = '' ) {
		$payment    = self::get_payment();
		$gateway_id = $gateway_id ?: self::get_active_gateway();

		return $payment['gateways'][ $gateway_id ] ?? array();
	}

	/**
	 * Get registered payment gateways metadata.
	 *
	 * @return array
	 */
	public static function get_available_gateways() {
		return Art_LMS_Payment_Gateway_Registry::get_available_meta();
	}

	/**
	 * Get account page URL based on settings.
	 *
	 * @return string
	 */
	public static function get_account_url() {
		$page_id = self::get_account_page_id();

		if ( $page_id ) {
			$url = get_permalink( $page_id );

			if ( $url ) {
				return (string) apply_filters( 'art_lms_account_url', $url, array() );
			}
		}

		return (string) apply_filters( 'art_lms_account_url', home_url( '/' ), array() );
	}

	/**
	 * Get shared checkout page URL.
	 *
	 * @param int $button_id Payment button ID.
	 * @return string
	 */
	public static function get_checkout_url( $button_id = 0 ) {
		$slug     = self::get_checkout_slug();
		$base_url = $slug ? home_url( '/' . $slug . '/' ) : '';

		if ( ! $base_url ) {
			return '';
		}

		if ( $button_id ) {
			$base_url = add_query_arg(
				Art_LMS_Payment_Buttons::CHECKOUT_QUERY_ARG,
				absint( $button_id ),
				$base_url
			);
		}

		return (string) apply_filters( 'art_lms_checkout_url', $base_url, $button_id, self::get_checkout() );
	}

	/**
	 * Get configured checkout slug.
	 *
	 * @return string
	 */
	public static function get_checkout_slug() {
		$checkout = self::get_checkout();
		$slug     = sanitize_title( (string) ( $checkout['slug'] ?? '' ) );

		if ( '' === $slug ) {
			$slug = self::get_default_checkout()['slug'];
		}

		return $slug;
	}

	/**
	 * Sanitize checkout URL slug.
	 *
	 * @param string $slug Raw slug.
	 * @return string
	 */
	public static function sanitize_checkout_slug( $slug ) {
		$slug = sanitize_title( (string) $slug );

		if ( '' === $slug ) {
			$slug = self::get_default_checkout()['slug'];
		}

		return $slug;
	}

	/**
	 * Checkout field catalog for admin and frontend.
	 *
	 * @return array
	 */
	public static function get_checkout_field_catalog() {
		return array(
			'full_name' => __( 'ФИО', 'art-lms' ),
			'email'     => __( 'Почта', 'art-lms' ),
			'phone'     => __( 'Телефон', 'art-lms' ),
		);
	}

	/**
	 * Built-in checkout field keys.
	 *
	 * @return string[]
	 */
	public static function get_checkout_builtin_field_keys() {
		return array_keys( self::get_checkout_field_catalog() );
	}

	/**
	 * Whether field key belongs to built-in checkout fields.
	 *
	 * @param string $field_key Field key.
	 * @return bool
	 */
	public static function is_checkout_builtin_field( $field_key ) {
		return in_array( $field_key, self::get_checkout_builtin_field_keys(), true );
	}

	/**
	 * Get enabled checkout fields in display order.
	 *
	 * @return array[]
	 */
	public static function get_checkout_form_fields() {
		$checkout = self::get_checkout();
		$fields   = array();

		foreach ( self::get_checkout_builtin_field_keys() as $key ) {
			$field = $checkout['fields'][ $key ] ?? array();

			if ( ( $field['enabled'] ?? 'no' ) !== 'yes' ) {
				continue;
			}

			$fields[] = array(
				'key'      => $key,
				'type'     => 'builtin',
				'label'    => $field['label'] ?? '',
				'required' => ( $field['required'] ?? 'no' ) === 'yes',
				'input'    => 'email' === $key ? 'email' : 'text',
			);
		}

		foreach ( $checkout['custom_fields'] ?? array() as $field ) {
			if ( ( $field['enabled'] ?? 'no' ) !== 'yes' ) {
				continue;
			}

			if ( empty( $field['label'] ) || empty( $field['id'] ) ) {
				continue;
			}

			$fields[] = array(
				'key'      => $field['id'],
				'type'     => 'custom',
				'label'    => $field['label'],
				'required' => ( $field['required'] ?? 'no' ) === 'yes',
				'input'    => 'text',
			);
		}

		return $fields;
	}

	/**
	 * Checkout consent item keys.
	 *
	 * @return array<string, string> Key => admin label.
	 */
	public static function get_checkout_consent_catalog() {
		return array(
			'privacy' => __( 'Политика конфиденциальности', 'art-lms' ),
		);
	}

	/**
	 * Get enabled checkout consents for the frontend form.
	 *
	 * @return array{title: string, items: array[]}
	 */
	public static function get_checkout_consents() {
		$consents = self::get_checkout()['consents'] ?? self::get_default_checkout()['consents'];
		$items    = array();

		foreach ( self::get_checkout_consent_catalog() as $key => $admin_label ) {
			$item = $consents[ $key ] ?? array();

			if ( ( $item['enabled'] ?? 'no' ) !== 'yes' ) {
				continue;
			}

			$page_id = absint( $item['page_id'] ?? 0 );
			$url     = $page_id ? get_permalink( $page_id ) : '';

			$items[] = array(
				'key'        => $key,
				'admin_label'=> $admin_label,
				'text'       => self::normalize_checkout_consent_text( (string) ( $item['text'] ?? '' ) ),
				'link_text'  => $item['link_text'] ?? '',
				'page_id'    => $page_id,
				'url'        => $url ? esc_url_raw( $url ) : '',
				'required'   => ( $item['required'] ?? 'no' ) === 'yes',
				'post_key'   => self::get_checkout_consent_post_key( $key ),
			);
		}

		foreach ( self::get_checkout()['custom_consents'] ?? array() as $item ) {
			if ( ( $item['enabled'] ?? 'no' ) !== 'yes' ) {
				continue;
			}

			$key = (string) ( $item['id'] ?? '' );

			if ( '' === $key ) {
				continue;
			}

			$text      = self::normalize_checkout_consent_text( (string) ( $item['text'] ?? '' ) );
			$link_text = (string) ( $item['link_text'] ?? '' );

			if ( '' === $text && '' === $link_text ) {
				continue;
			}

			$page_id = absint( $item['page_id'] ?? 0 );
			$url     = $page_id ? get_permalink( $page_id ) : '';

			$items[] = array(
				'key'         => $key,
				'admin_label' => (string) ( $item['label'] ?? '' ),
				'text'        => $text,
				'link_text'   => $link_text,
				'page_id'     => $page_id,
				'url'         => $url ? esc_url_raw( $url ) : '',
				'required'    => ( $item['required'] ?? 'no' ) === 'yes',
				'post_key'    => self::get_checkout_consent_post_key( $key ),
			);
		}

		return array(
			'title' => $consents['title'] ?? self::get_default_checkout()['consents']['title'],
			'items' => $items,
		);
	}

	/**
	 * Map consent key to POST field name.
	 *
	 * @param string $consent_key Consent key.
	 * @return string
	 */
	public static function get_checkout_consent_post_key( $consent_key ) {
		return 'consent_' . sanitize_key( $consent_key );
	}

	/**
	 * Build consent checkbox label HTML with optional link.
	 *
	 * @param array $item Consent item from get_checkout_consents().
	 * @return string
	 */
	public static function format_checkout_consent_label( $item ) {
		$text      = self::normalize_checkout_consent_text( (string) ( $item['text'] ?? '' ) );
		$link_text = (string) ( $item['link_text'] ?? '' );
		$url       = (string) ( $item['url'] ?? '' );

		if ( '' === $text && '' === $link_text ) {
			return '';
		}

		$html = esc_html( $text );

		if ( '' !== $link_text ) {
			if ( '' !== $html ) {
				$html .= ' ';
			}

			if ( $url ) {
				$html .= sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $url ),
					esc_html( $link_text )
				);
			} else {
				$html .= esc_html( $link_text );
			}
		}

		return wp_kses(
			$html,
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		);
	}

	/**
	 * Normalize consent text.
	 *
	 * @param string $text Raw consent text.
	 * @return string
	 */
	public static function normalize_checkout_consent_text( $text ) {
		return trim( (string) $text );
	}

	/**
	 * Sanitize checkout consent settings.
	 *
	 * @param array $input Raw consents input.
	 * @return array
	 */
	public static function sanitize_checkout_consents( $input ) {
		$defaults = self::get_default_checkout()['consents'];
		$consents = array(
			'title' => '',
		);

		foreach ( array_keys( self::get_checkout_consent_catalog() ) as $key ) {
			$row = is_array( $input ) && isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array();
			$def = $defaults[ $key ];

			$consents[ $key ] = array(
				'enabled'   => ! empty( $row['enabled'] ) ? 'yes' : 'no',
				'required'  => ! empty( $row['required'] ) ? 'yes' : 'no',
				'text'      => self::normalize_checkout_consent_text(
					isset( $row['text'] ) ? sanitize_text_field( $row['text'] ) : $def['text']
				),
				'link_text' => isset( $row['link_text'] ) ? sanitize_text_field( $row['link_text'] ) : $def['link_text'],
				'page_id'   => isset( $row['page_id'] ) ? absint( $row['page_id'] ) : 0,
			);
		}

		return $consents;
	}

	/**
	 * Sanitize custom checkout consents.
	 *
	 * @param array $input Raw custom consents input.
	 * @return array
	 */
	public static function sanitize_checkout_custom_consents( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$consents    = array();
		$used_ids    = array();
		$reserved    = array_merge( array( 'title' ), array_keys( self::get_checkout_consent_catalog() ) );

		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$text      = self::normalize_checkout_consent_text(
				isset( $row['text'] ) ? sanitize_text_field( $row['text'] ) : ''
			);
			$link_text = isset( $row['link_text'] ) ? sanitize_text_field( $row['link_text'] ) : '';

			if ( '' === $text && '' === $link_text ) {
				continue;
			}

			$id = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';

			if ( ! preg_match( '/^custom_[a-z0-9_]+$/', $id ) ) {
				$id = 'custom_' . wp_generate_password( 8, false, false );
				$id = sanitize_key( strtolower( $id ) );
			}

			if ( in_array( $id, $reserved, true ) || isset( $used_ids[ $id ] ) ) {
				$id = 'custom_' . wp_generate_password( 8, false, false );
				$id = sanitize_key( strtolower( $id ) );
			}

			$used_ids[ $id ] = true;

			$consents[] = array(
				'id'        => $id,
				'label'     => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
				'enabled'   => ! empty( $row['enabled'] ) ? 'yes' : 'no',
				'required'  => ! empty( $row['required'] ) ? 'yes' : 'no',
				'text'      => $text,
				'link_text' => $link_text,
				'page_id'   => isset( $row['page_id'] ) ? absint( $row['page_id'] ) : 0,
			);
		}

		return $consents;
	}

	/**
	 * Map checkout field key to POST key.
	 *
	 * @param string $field_key Field key.
	 * @return string
	 */
	public static function get_checkout_field_post_key( $field_key ) {
		$map = array(
			'full_name' => 'name',
			'email'     => 'email',
			'phone'     => 'phone',
		);

		if ( isset( $map[ $field_key ] ) ) {
			return $map[ $field_key ];
		}

		if ( 0 === strpos( $field_key, 'custom_' ) ) {
			return $field_key;
		}

		return 'custom_' . sanitize_key( $field_key );
	}

	/**
	 * Check whether the current request is the configured checkout page.
	 *
	 * @return bool
	 */
	public static function is_checkout_page() {
		return Art_LMS_Checkout::is_checkout_request();
	}

	/**
	 * Check whether the current request is the configured account page.
	 *
	 * @return bool
	 */
	public static function is_account_page() {
		$page_id = self::get_account_page_id();

		return $page_id && is_page( $page_id );
	}

	/**
	 * Save general settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function save_general( $input ) {
		$data = self::sanitize_general( $input );
		self::update_option( self::OPTION_GENERAL, $data );

		return $data;
	}

	/**
	 * Save login page settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function save_login( $input ) {
		$data = self::sanitize_login( $input );
		self::update_option( self::OPTION_LOGIN, $data );

		return $data;
	}

	/**
	 * Sanitize login page settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_login( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$slug = isset( $input['slug'] )
			? self::sanitize_login_slug( wp_unslash( $input['slug'] ) )
			: self::get_login_slug();

		return array(
			'enabled' => ! empty( $input['enabled'] ) ? 'yes' : 'no',
			'slug'    => $slug,
			'form'    => isset( $input['form'] )
				? self::sanitize_login_form( $input['form'] )
				: self::get_login_form(),
			'button'  => isset( $input['button'] )
				? self::sanitize_login_button( $input['button'] )
				: self::get_login_button(),
			'design'  => isset( $input['design'] )
				? self::sanitize_login_design( $input['design'] )
				: self::get_login_design(),
		);
	}

	/**
	 * Save payment settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function save_payment( $input ) {
		$data = self::sanitize_payment( $input );
		self::update_option( self::OPTION_PAYMENT, $data );

		return $data;
	}

	/**
	 * Save checkout settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function save_checkout( $input ) {
		$data = self::sanitize_checkout( $input );
		self::update_option( self::OPTION_CHECKOUT, $data );

		return $data;
	}

	/**
	 * Save email settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function save_emails( $input ) {
		$data = self::sanitize_emails( $input );
		self::update_option( self::OPTION_EMAIL, $data );

		return $data;
	}

	/**
	 * Default purchase email subject.
	 *
	 * @return string
	 */
	public static function get_default_purchase_email_subject() {
		return __( 'Доступ к материалам — {товар}', 'art-lms' );
	}

	/**
	 * Default purchase email body.
	 *
	 * @return string
	 */
	public static function get_default_purchase_email_body() {
		return implode(
			"\n",
			array(
				__( 'Здравствуйте, {имя}!', 'art-lms' ),
				'',
				__( 'Ваш заказ №{номер_заказа} на сумму {сумма} успешно оплачен.', 'art-lms' ),
				'',
				__( 'Доступ к материалам:', 'art-lms' ),
				'{материалы}',
				'',
				__( 'ДАННЫЕ ДЛЯ ВХОДА В АККАУНТ:', 'art-lms' ),
				__( 'Личный кабинет: {войти}', 'art-lms' ),
				__( 'Логин: {логин}', 'art-lms' ),
				__( 'Пароль: {установить_пароль}', 'art-lms' ),
				'',
				__( 'Если вы уже задавали пароль раньше, войдите как обычно или воспользуйтесь «Забыли пароль?» на странице входа.', 'art-lms' ),
				'',
				__( 'С уважением,', 'art-lms' ),
				'{сайт}',
			)
		);
	}

	/**
	 * Default checkout email verification subject.
	 *
	 * @return string
	 */
	public static function get_default_email_verification_subject() {
		return __( 'Подтвердите email для продолжения оплаты — {сайт}', 'art-lms' );
	}

	/**
	 * Default checkout email verification body.
	 *
	 * @return string
	 */
	public static function get_default_email_verification_body() {
		return implode(
			"\n",
			array(
				__( 'Здравствуйте, {имя}!', 'art-lms' ),
				'',
				__( 'Чтобы продолжить оплату «{товар}», подтвердите ваш email по ссылке ниже:', 'art-lms' ),
				'{ссылка}',
				'',
				__( 'Если вы не оформляли заказ на нашем сайте, просто проигнорируйте это письмо.', 'art-lms' ),
				'',
				__( 'С уважением,', 'art-lms' ),
				'{сайт}',
			)
		);
	}

	/**
	 * Email verification placeholder catalog for admin UI.
	 *
	 * @return array<string, string>
	 */
	public static function get_email_verification_placeholder_catalog() {
		return array(
			'{имя}'   => __( 'Имя покупателя (или email, если имени нет)', 'art-lms' ),
			'{email}' => __( 'Email покупателя', 'art-lms' ),
			'{товар}' => __( 'Название платёжной кнопки', 'art-lms' ),
			'{ссылка}' => __( 'Ссылка подтверждения email', 'art-lms' ),
			'{сайт}'  => __( 'Название сайта', 'art-lms' ),
		);
	}

	/**
	 * Purchase email placeholder catalog for admin UI.
	 *
	 * @return array<string, string>
	 */
	public static function get_purchase_email_placeholder_catalog() {
		return array(
			'{имя}'          => __( 'Имя покупателя (или email, если имени нет)', 'art-lms' ),
			'{email}'        => __( 'Email покупателя', 'art-lms' ),
			'{номер_заказа}' => __( 'Номер заказа', 'art-lms' ),
			'{сумма}'        => __( 'Сумма заказа', 'art-lms' ),
			'{товар}'        => __( 'Название платёжной кнопки', 'art-lms' ),
			'{кабинет}'           => __( 'URL личного кабинета (текстом)', 'art-lms' ),
			'{войти}'             => __( 'Ссылка «Войти» на личный кабинет', 'art-lms' ),
			'{логин}'             => __( 'Email покупателя для входа', 'art-lms' ),
			'{установить_пароль}' => __( 'Ссылка «Нажмите здесь, чтобы установить пароль»', 'art-lms' ),
			'{материалы}'         => __( 'Список купленных материалов (названия со ссылками)', 'art-lms' ),
			'{сайт}'         => __( 'Название сайта', 'art-lms' ),
			'{заказ}'        => __( 'Ссылка на заказ в админке', 'art-lms' ),
		);
	}

	/**
	 * Admin payment email placeholder catalog.
	 *
	 * @return array<string, string>
	 */
	public static function get_admin_payment_email_placeholder_catalog() {
		return array_merge(
			self::get_purchase_email_placeholder_catalog(),
			array(
				'{платежный_шлюз}' => __( 'Платёжный шлюз, через который прошла оплата', 'art-lms' ),
				'{all-fields}'     => __( 'Все поля формы и согласия заказа (списком)', 'art-lms' ),
			)
		);
	}

	/**
	 * Default admin payment notification subject.
	 *
	 * @return string
	 */
	public static function get_default_admin_payment_email_subject() {
		return __( 'Новая оплата — заказ №{номер_заказа}', 'art-lms' );
	}

	/**
	 * Default admin payment notification body.
	 *
	 * @return string
	 */
	public static function get_default_admin_payment_email_body() {
		return implode(
			"\n",
			array(
				__( 'Получена новая оплата.', 'art-lms' ),
				'',
				__( 'Заказ: №{номер_заказа}', 'art-lms' ),
				__( 'Покупатель: {имя}', 'art-lms' ),
				__( 'Email: {email}', 'art-lms' ),
				__( 'Товар: {товар}', 'art-lms' ),
				__( 'Сумма: {сумма}', 'art-lms' ),
				__( 'Способ оплаты: {платежный_шлюз}', 'art-lms' ),
				'',
				__( 'Данные формы:', 'art-lms' ),
				'{all-fields}',
				'',
				__( 'Материалы:', 'art-lms' ),
				'{материалы}',
				'',
				__( 'Открыть заказ: {заказ}', 'art-lms' ),
			)
		);
	}

	/**
	 * Sanitize a single email template block.
	 *
	 * @param array  $input         Raw block input.
	 * @param array  $current       Current saved block.
	 * @param array  $default_block Default block values.
	 * @param string $recipient     Optional recipient email for admin notifications.
	 * @return array
	 */
	private static function sanitize_email_template_block( $input, $current, $default_block, $recipient = '' ) {
		$block = array(
			'enabled' => ! empty( $input['enabled'] ) ? 'yes' : 'no',
			'subject' => isset( $input['subject'] )
				? sanitize_text_field( $input['subject'] )
				: $current['subject'],
			'body'    => isset( $input['body'] )
				? sanitize_textarea_field( $input['body'] )
				: $current['body'],
		);

		if ( '' === trim( $block['subject'] ) ) {
			$block['subject'] = $default_block['subject'];
		}

		if ( '' === trim( $block['body'] ) ) {
			$block['body'] = $default_block['body'];
		}

		if ( '' !== $recipient ) {
			$block['recipient'] = is_email( $recipient ) ? $recipient : $default_block['recipient'];
		}

		return $block;
	}

	/**
	 * Sanitize email settings.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function sanitize_emails( $input ) {
		$current  = self::get_emails();
		$defaults = self::get_default_emails();
		$from     = isset( $input['email_from'] ) ? sanitize_email( $input['email_from'] ) : $current['email_from'];
		$name     = isset( $input['email_from_name'] ) ? sanitize_text_field( $input['email_from_name'] ) : $current['email_from_name'];

		if ( ! is_email( $from ) ) {
			$from = $defaults['email_from'];
		}

		if ( '' === trim( $name ) ) {
			$name = $defaults['email_from_name'];
		}

		return array(
			'email_from'      => $from,
			'email_from_name' => $name,
			'purchase'        => self::sanitize_email_template_block(
				is_array( $input['purchase'] ?? null ) ? $input['purchase'] : array(),
				$current['purchase'],
				$defaults['purchase']
			),
			'admin_payment'   => self::sanitize_email_template_block(
				is_array( $input['admin_payment'] ?? null ) ? $input['admin_payment'] : array(),
				$current['admin_payment'],
				$defaults['admin_payment'],
				isset( $input['admin_payment']['recipient'] ) ? sanitize_email( wp_unslash( $input['admin_payment']['recipient'] ) ) : ( $current['admin_payment']['recipient'] ?? $defaults['admin_payment']['recipient'] )
			),
			'email_verification' => self::sanitize_email_template_block(
				is_array( $input['email_verification'] ?? null ) ? $input['email_verification'] : array(),
				$current['email_verification'],
				$defaults['email_verification']
			),
		);
	}

	/**
	 * Sanitize general settings.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function sanitize_general( $input ) {
		$account_page_id = isset( $input['account_page_id'] ) ? absint( $input['account_page_id'] ) : 0;
		$create_user     = ! empty( $input['create_user_before_payment'] );

		$data = array(
			'account_page_id'                => $account_page_id,
			'success_page_id'                => isset( $input['success_page_id'] ) ? absint( $input['success_page_id'] ) : 0,
			'support_email'                  => isset( $input['support_email'] ) ? sanitize_email( wp_unslash( $input['support_email'] ) ) : '',
			'currency'                       => 'RUB',
			'create_user_before_payment'     => $create_user ? 'yes' : 'no',
			'user_registration_verification' => $create_user ? self::sanitize_user_registration_verification( $input ) : 'none',
			'auto_login_after_register'      => ! empty( $input['auto_login_after_register'] ) ? 'yes' : 'no',
		);

		self::set_delete_data_on_uninstall( ! empty( $input['delete_data_on_uninstall'] ) );

		return $data;
	}

	/**
	 * Sanitize user registration verification mode.
	 *
	 * @param array $input Raw settings input.
	 * @return string
	 */
	private static function sanitize_user_registration_verification( $input ) {
		$mode = sanitize_key( (string) ( $input['user_registration_verification'] ?? 'none' ) );

		return in_array( $mode, array( 'none', 'email' ), true ) ? $mode : 'none';
	}

	/**
	 * Sanitize payment settings.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function sanitize_payment( $input ) {
		$current      = self::get_payment();
		$registry_ids = array_keys( Art_LMS_Payment_Gateway_Registry::all() );
		$order        = array();

		if ( isset( $input['gateway_order'] ) && is_array( $input['gateway_order'] ) ) {
			foreach ( $input['gateway_order'] as $gateway_id ) {
				$gateway_id = sanitize_key( (string) $gateway_id );

				if ( in_array( $gateway_id, $registry_ids, true ) && ! in_array( $gateway_id, $order, true ) ) {
					$order[] = $gateway_id;
				}
			}
		} else {
			$order = self::get_ordered_gateway_ids();
		}

		foreach ( $registry_ids as $gateway_id ) {
			if ( ! in_array( $gateway_id, $order, true ) ) {
				$order[] = $gateway_id;
			}
		}

		$default = isset( $input['default_gateway'] )
			? sanitize_key( (string) $input['default_gateway'] )
			: sanitize_key( (string) ( $current['default_gateway'] ?? $current['active_gateway'] ?? '' ) );

		if ( '' !== $default && ! in_array( $default, $registry_ids, true ) ) {
			$default = '';
		}

		$gateway_settings = array();

		foreach ( Art_LMS_Payment_Gateway_Registry::all() as $gateway_id => $gateway ) {
			$existing = $current['gateways'][ $gateway_id ] ?? $gateway->get_default_settings();
			$raw      = isset( $input['gateways'][ $gateway_id ] ) && is_array( $input['gateways'][ $gateway_id ] )
				? $input['gateways'][ $gateway_id ]
				: array();

			$gateway_settings[ $gateway_id ] = $gateway->sanitize_settings( $raw, $existing );
		}

		return array(
			'default_gateway' => $default,
			'gateway_order'   => $order,
			'active_gateway'  => $default,
			'gateways'        => $gateway_settings,
		);
	}

	/**
	 * Get checkout design settings.
	 *
	 * @return array
	 */
	public static function get_checkout_design() {
		$checkout = self::get_checkout();
		$defaults = self::get_default_checkout()['design'];
		$raw      = is_array( $checkout['design'] ?? null ) ? $checkout['design'] : array();

		if ( isset( $raw['background_color'] ) && ! isset( $raw['page_background_color'] ) ) {
			$raw['page_background_color'] = $raw['background_color'];
			$raw['form_background_color'] = $raw['background_color'];
		}

		$design = wp_parse_args( $raw, $defaults );
		unset( $design['background_color'] );

		return $design;
	}

	/**
	 * Default color values for checkout design controls.
	 *
	 * @return array<string, string>
	 */
	public static function get_checkout_design_color_defaults() {
		$defaults = self::get_default_checkout()['design'];

		return array(
			'page_background_color' => $defaults['page_background_color'],
			'form_background_color' => $defaults['form_background_color'],
			'button_color'          => $defaults['button_color'],
			'button_text_color'     => $defaults['button_text_color'],
		);
	}

	/**
	 * Default dimension values for checkout design controls.
	 *
	 * @return array<string, int>
	 */
	public static function get_checkout_design_dimension_defaults() {
		$defaults = self::get_default_checkout()['design'];

		return array(
			'form_max_width'     => (int) $defaults['form_max_width'],
			'form_padding'       => (int) $defaults['form_padding'],
			'form_border_radius' => (int) $defaults['form_border_radius'],
		);
	}

	/**
	 * Default text sizing values for checkout design controls.
	 *
	 * @return array<string, int>
	 */
	public static function get_checkout_design_text_defaults() {
		$defaults = self::get_default_checkout()['design'];

		return array(
			'title_font_size'         => (int) $defaults['title_font_size'],
			'product_name_font_size'  => (int) $defaults['product_name_font_size'],
			'compare_price_font_size' => (int) $defaults['compare_price_font_size'],
			'price_font_size'         => (int) $defaults['price_font_size'],
			'field_label_font_size'   => (int) $defaults['field_label_font_size'],
			'field_input_font_size'   => (int) $defaults['field_input_font_size'],
			'consent_checkbox_size'   => (int) $defaults['consent_checkbox_size'],
			'consent_font_size'       => (int) $defaults['consent_font_size'],
		);
	}

	/**
	 * Text sizing field definitions for checkout design admin UI.
	 *
	 * @return array<string, array{label: string, min: int, max: int}>
	 */
	public static function get_checkout_design_text_fields() {
		return array(
			'title_font_size'         => array(
				'label' => __( 'Размер шрифта «Оформление заказа»', 'art-lms' ),
				'min'   => 10,
				'max'   => 48,
			),
			'product_name_font_size'  => array(
				'label' => __( 'Размер шрифта «Название товара»', 'art-lms' ),
				'min'   => 10,
				'max'   => 48,
			),
			'compare_price_font_size' => array(
				'label' => __( 'Размер шрифта «зачёркнутая цена»', 'art-lms' ),
				'min'   => 10,
				'max'   => 48,
			),
			'price_font_size'         => array(
				'label' => __( 'Размер шрифта «реальная цена»', 'art-lms' ),
				'min'   => 10,
				'max'   => 48,
			),
			'field_label_font_size'   => array(
				'label' => __( 'Размер шрифта «лейбла полей»', 'art-lms' ),
				'min'   => 10,
				'max'   => 48,
			),
			'field_input_font_size'   => array(
				'label' => __( 'Размер шрифта «полей формы»', 'art-lms' ),
				'min'   => 10,
				'max'   => 48,
			),
			'consent_checkbox_size'   => array(
				'label' => __( 'Размер чек-бокса', 'art-lms' ),
				'min'   => 12,
				'max'   => 32,
			),
			'consent_font_size'       => array(
				'label' => __( 'Размер шрифта «Согласий»', 'art-lms' ),
				'min'   => 10,
				'max'   => 48,
			),
		);
	}

	/**
	 * Sanitize checkout design dimension in pixels.
	 *
	 * @param mixed  $value    Raw value.
	 * @param int    $default  Default value.
	 * @param int    $min      Minimum value.
	 * @param int    $max      Maximum value.
	 * @return int
	 */
	public static function sanitize_checkout_design_dimension( $value, $default, $min, $max ) {
		$value = absint( $value );

		if ( $value < $min || $value > $max ) {
			return (int) $default;
		}

		return $value;
	}

	/**
	 * Template options for checkout page shell.
	 *
	 * @return array<string, string>
	 */
	public static function get_checkout_design_template_options() {
		return array(
			'with_theme'  => __( 'С шапкой и подвалом сайта', 'art-lms' ),
			'standalone'  => __( 'Без шапки и подвала', 'art-lms' ),
		);
	}

	/**
	 * Button size options for checkout form.
	 *
	 * @return array<string, string>
	 */
	public static function get_checkout_design_button_size_options() {
		return array(
			'small'  => __( 'Маленькая', 'art-lms' ),
			'medium' => __( 'Средняя', 'art-lms' ),
			'large'  => __( 'Большая', 'art-lms' ),
		);
	}

	/**
	 * Button alignment options for checkout form.
	 *
	 * @return array<string, string>
	 */
	public static function get_checkout_design_button_align_options() {
		return array(
			'left'   => __( 'Слева', 'art-lms' ),
			'center' => __( 'По центру', 'art-lms' ),
			'right'  => __( 'Справа', 'art-lms' ),
			'full'   => __( 'На всю ширину', 'art-lms' ),
		);
	}

	/**
	 * Build inline CSS for checkout design tokens.
	 *
	 * @return string
	 */
	public static function get_checkout_design_css() {
		$design = self::get_checkout_design();

		return sprintf(
			':root{--art-lms-checkout-page-bg:%1$s;--art-lms-checkout-form-bg:%2$s;--art-lms-button-bg:%3$s;--art-lms-button-color:%4$s;--art-lms-checkout-form-width:%5$dpx;--art-lms-checkout-form-padding:%6$dpx;--art-lms-checkout-form-radius:%7$dpx;--art-lms-checkout-title-font-size:%8$dpx;--art-lms-checkout-product-name-font-size:%9$dpx;--art-lms-checkout-compare-price-font-size:%10$dpx;--art-lms-checkout-price-font-size:%11$dpx;--art-lms-checkout-field-label-font-size:%12$dpx;--art-lms-checkout-field-input-font-size:%13$dpx;--art-lms-checkout-consent-checkbox-size:%14$dpx;--art-lms-checkout-consent-font-size:%15$dpx;}',
			esc_attr( $design['page_background_color'] ),
			esc_attr( $design['form_background_color'] ),
			esc_attr( $design['button_color'] ),
			esc_attr( $design['button_text_color'] ),
			(int) $design['form_max_width'],
			(int) $design['form_padding'],
			(int) $design['form_border_radius'],
			(int) $design['title_font_size'],
			(int) $design['product_name_font_size'],
			(int) $design['compare_price_font_size'],
			(int) $design['price_font_size'],
			(int) $design['field_label_font_size'],
			(int) $design['field_input_font_size'],
			(int) $design['consent_checkbox_size'],
			(int) $design['consent_font_size']
		);
	}

	/**
	 * Saved checkout design state for admin preview scripts.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_checkout_admin_preview_design_state() {
		$design = self::get_checkout_design();

		return array(
			'template'            => $design['template'],
			'pageBackgroundColor' => $design['page_background_color'],
			'formBackgroundColor' => $design['form_background_color'],
			'buttonColor'         => $design['button_color'],
			'buttonTextColor'     => $design['button_text_color'],
			'buttonSize'          => $design['button_size'],
			'buttonAlign'         => $design['button_align'],
			'buttonText'          => self::get_checkout_button_text(),
			'formMaxWidth'        => (int) $design['form_max_width'],
			'formPadding'         => (int) $design['form_padding'],
			'formBorderRadius'    => (int) $design['form_border_radius'],
			'titleFontSize'       => (int) $design['title_font_size'],
			'productNameFontSize' => (int) $design['product_name_font_size'],
			'comparePriceFontSize' => (int) $design['compare_price_font_size'],
			'priceFontSize'       => (int) $design['price_font_size'],
			'fieldLabelFontSize'  => (int) $design['field_label_font_size'],
			'fieldInputFontSize'  => (int) $design['field_input_font_size'],
			'consentCheckboxSize' => (int) $design['consent_checkbox_size'],
			'consentFontSize'     => (int) $design['consent_font_size'],
		);
	}

	/**
	 * CSS class for checkout pay button size.
	 *
	 * @return string
	 */
	public static function get_checkout_button_size_class() {
		$design = self::get_checkout_design();
		$sizes  = array_keys( self::get_checkout_design_button_size_options() );
		$size   = $design['button_size'] ?? 'medium';

		if ( ! in_array( $size, $sizes, true ) ) {
			$size = 'medium';
		}

		return 'art-lms-button--size-' . $size;
	}

	/**
	 * CSS class for checkout pay button container alignment.
	 *
	 * @return string
	 */
	public static function get_checkout_button_actions_class() {
		$design = self::get_checkout_design();
		$aligns = array_keys( self::get_checkout_design_button_align_options() );
		$align  = $design['button_align'] ?? 'center';

		if ( ! in_array( $align, $aligns, true ) ) {
			$align = 'center';
		}

		return 'art-lms-checkout-form__actions art-lms-checkout-form__actions--align-' . $align;
	}

	/**
	 * Label for checkout pay button.
	 *
	 * @return string
	 */
	public static function get_checkout_button_text() {
		$design = self::get_checkout_design();
		$text   = trim( (string) ( $design['button_text'] ?? '' ) );

		if ( '' === $text ) {
			return self::get_default_checkout()['design']['button_text'];
		}

		return $text;
	}

	/**
	 * Default payment confirmation page messages.
	 *
	 * @return array<string, string>
	 */
	public static function get_default_payment_status_messages() {
		return array(
			'paid_title'                 => __( 'Оплата получена. Доступ к материалам уже открыт.', 'art-lms' ),
			'paid_description'           => self::join_payment_status_paragraphs(
				array(
					__( 'Мы отправили письмо с подробностями на {email}. В нём — ссылка в личный кабинет и инструкция по входу.', 'art-lms' ),
					__( 'Доступ к материалам уже открыт. Вы можете перейти в личный кабинет по кнопке ниже.', 'art-lms' ),
					__( 'Если письма нет — проверьте папки «Спам» и «Промоакции». Обычно оно приходит в течение 1–2 минут.', 'art-lms' ),
					__( 'В письме есть ссылка для установки пароля. Если вы уже входили раньше — используйте свой пароль.', 'art-lms' ),
					__( 'Заказ №{order} · {product} · {amount}', 'art-lms' ),
				)
			),
			'paid_show_account_button'     => 'yes',
			'account_button_label'         => __( 'Перейти в личный кабинет', 'art-lms' ),
			'pending_title'                => __( 'Подтверждаем оплату…', 'art-lms' ),
			'pending_description'          => self::join_payment_status_paragraphs(
				array(
					__( 'Платёж принят платёжной системой. Сейчас мы ждём финальное подтверждение — это нормально и обычно занимает от нескольких секунд до 2 минут.', 'art-lms' ),
					__( 'Не закрывайте эту страницу — статус обновится автоматически, как только оплата подтвердится.', 'art-lms' ),
					__( 'После подтверждения на почту {email} придёт письмо с доступом в личный кабинет.', 'art-lms' ),
					__( 'Подтверждение занимает чуть дольше обычного. Пожалуйста, подождите ещё немного.', 'art-lms' ),
				)
			),
			'failed_title'                 => __( 'Оплата не завершена', 'art-lms' ),
			'failed_description'           => self::join_payment_status_paragraphs(
				array(
					__( 'Платёж был отменён или не прошёл. Средства с карты не списаны (или будут автоматически разблокированы банком).', 'art-lms' ),
					__( 'Если вы уверены, что оплата прошла, но видите это сообщение — напишите нам: {support}', 'art-lms' ),
				)
			),
			'timeout_title'                => __( 'Подтверждение задерживается', 'art-lms' ),
			'timeout_description'          => self::join_payment_status_paragraphs(
				array(
					__( 'Мы пока не получили подтверждение от платёжной системы. Иногда это занимает до 30 минут.', 'art-lms' ),
					__( 'Не оплачивайте повторно, пока не убедитесь, что первый платёж не прошёл (проверьте SMS или выписку банка).', 'art-lms' ),
					__( 'Напишите нам на {support} с указанием email покупки — мы проверим заказ вручную.', 'art-lms' ),
				)
			),
			'not_found_title'              => __( 'Заказ не найден', 'art-lms' ),
			'not_found_description'        => __( 'Не удалось найти заказ по ссылке. Если вы только что оплатили заказ, подождите минуту и обновите страницу.', 'art-lms' ),
			'missing_order_title'          => __( 'Не удалось проверить статус оплаты', 'art-lms' ),
			'missing_order_description'    => self::join_payment_status_paragraphs(
				array(
					__( 'Откройте эту страницу по ссылке после оплаты. Если оплата уже прошла, проверьте личный кабинет или почту.', 'art-lms' ),
					__( 'Если оплата прошла, доступ уже может быть открыт в личном кабинете.', 'art-lms' ),
				)
			),
		);
	}

	/**
	 * Placeholder hint for payment status descriptions.
	 *
	 * @return string
	 */
	public static function get_payment_status_placeholder_hint() {
		return __( 'Плейсхолдеры: {email} — почта покупателя, {support} — email поддержки, {order} — номер заказа, {product} — товар, {amount} — сумма. Разделяйте абзацы пустой строкой.', 'art-lms' );
	}

	/**
	 * Join description paragraphs for storage.
	 *
	 * @param array<int, string> $paragraphs Paragraphs.
	 * @return string
	 */
	public static function join_payment_status_paragraphs( array $paragraphs ) {
		$paragraphs = array_filter(
			array_map(
				static function ( $paragraph ) {
					return trim( (string) $paragraph );
				},
				$paragraphs
			)
		);

		return implode( "\n\n", $paragraphs );
	}

	/**
	 * Migrate legacy payment status message keys.
	 *
	 * @param array $stored Stored messages.
	 * @return array
	 */
	private static function migrate_payment_status_messages( array $stored ) {
		if ( isset( $stored['paid_title'] ) || empty( $stored ) ) {
			return $stored;
		}

		if ( ! isset( $stored['paidTitle'] ) ) {
			return $stored;
		}

		return array(
			'paid_title'              => (string) ( $stored['paidTitle'] ?? '' ),
			'paid_description'        => self::join_payment_status_paragraphs(
				array(
					$stored['paidEmailSent'] ?? '',
					$stored['paidEmailDisabled'] ?? '',
					$stored['paidSpamHint'] ?? '',
					$stored['paidPasswordHint'] ?? '',
					isset( $stored['paidOrderMeta'] )
						? str_replace(
							array( '%1$s', '%2$s', '%3$s' ),
							array( '{order}', '{product}', '{amount}' ),
							(string) $stored['paidOrderMeta']
						)
						: '',
				)
			),
			'paid_show_account_button'  => 'yes',
			'account_button_label'      => (string) ( $stored['accountButton'] ?? '' ),
			'pending_title'             => (string) ( $stored['pendingTitle'] ?? '' ),
			'pending_description'       => self::join_payment_status_paragraphs(
				array(
					$stored['pendingIntro'] ?? '',
					$stored['pendingWait'] ?? '',
					str_replace( '%s', '{email}', (string) ( $stored['pendingEmailNotice'] ?? $stored['pendingEmailNoticeNoMail'] ?? '' ) ),
					$stored['pendingLongWait'] ?? '',
				)
			),
			'failed_title'              => (string) ( $stored['failedTitle'] ?? '' ),
			'failed_description'        => self::join_payment_status_paragraphs(
				array(
					$stored['failedCancelled'] ?? $stored['failedGeneric'] ?? '',
					str_replace( '%s', '{support}', (string) ( $stored['failedSupport'] ?? '' ) ),
				)
			),
			'timeout_title'               => (string) ( $stored['timeoutTitle'] ?? '' ),
			'timeout_description'         => self::join_payment_status_paragraphs(
				array(
					$stored['timeoutBody'] ?? '',
					$stored['timeoutWarning'] ?? '',
					str_replace( '%s', '{support}', (string) ( $stored['timeoutSupport'] ?? '' ) ),
				)
			),
			'not_found_title'             => (string) ( $stored['notFoundTitle'] ?? '' ),
			'not_found_description'       => (string) ( $stored['notFoundBody'] ?? '' ),
			'missing_order_title'         => (string) ( $stored['missingOrderTitle'] ?? '' ),
			'missing_order_description'   => self::join_payment_status_paragraphs(
				array(
					$stored['missingOrderBody'] ?? '',
					$stored['missingOrderAccount'] ?? '',
				)
			),
		);
	}

	/**
	 * Get payment confirmation page messages.
	 *
	 * @return array<string, string>
	 */
	public static function get_payment_status_messages() {
		$messages = self::get_checkout()['payment_status'] ?? array();

		if ( ! is_array( $messages ) ) {
			$messages = array();
		}

		$messages = self::migrate_payment_status_messages( $messages );

		return wp_parse_args( $messages, self::get_default_payment_status_messages() );
	}

	/**
	 * Payment status config for frontend script.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_payment_status_frontend_config() {
		$messages = self::get_payment_status_messages();

		return array(
			'paidTitle'               => $messages['paid_title'],
			'paidDescription'         => $messages['paid_description'],
			'paidShowAccountButton'   => 'yes' === ( $messages['paid_show_account_button'] ?? 'yes' ),
			'pendingTitle'            => $messages['pending_title'],
			'pendingDescription'      => $messages['pending_description'],
			'failedTitle'             => $messages['failed_title'],
			'failedDescription'       => $messages['failed_description'],
			'timeoutTitle'            => $messages['timeout_title'],
			'timeoutDescription'      => $messages['timeout_description'],
			'notFoundTitle'           => $messages['not_found_title'],
			'notFoundDescription'     => $messages['not_found_description'],
			'missingOrderTitle'       => $messages['missing_order_title'],
			'missingOrderDescription' => $messages['missing_order_description'],
			'accountButton'           => $messages['account_button_label'],
			'retryButton'             => __( 'Попробовать оплатить снова', 'art-lms' ),
		);
	}

	/**
	 * Sanitize payment confirmation page messages.
	 *
	 * @param array $input Raw messages input.
	 * @return array<string, string>
	 */
	public static function sanitize_payment_status_messages( $input ) {
		$defaults = self::get_default_payment_status_messages();
		$messages = array();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		foreach ( array_keys( $defaults ) as $key ) {
			if ( 'paid_show_account_button' === $key ) {
				$messages[ $key ] = ! empty( $input[ $key ] ) ? 'yes' : 'no';
				continue;
			}

			if ( false !== strpos( $key, '_title' ) || false !== strpos( $key, '_label' ) ) {
				$value = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : '';
			} else {
				$value = isset( $input[ $key ] ) ? sanitize_textarea_field( wp_unslash( $input[ $key ] ) ) : '';
			}

			$value = trim( $value );

			$messages[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		return $messages;
	}

	/**
	 * Default checkout form error messages.
	 *
	 * @return array<string, string>
	 */
	public static function get_default_checkout_form_messages() {
		return array(
			'required_field'     => __( 'Заполните поле «{поле}».', 'art-lms' ),
			'invalid_email'      => __( 'Укажите корректный email.', 'art-lms' ),
			'consent_required'   => __( 'Отметьте согласие «{согласие}».', 'art-lms' ),
			'network_error'      => __( 'Сервер не ответил. Проверьте соединение и попробуйте ещё раз.', 'art-lms' ),
			'create_order_failed' => __( 'Не удалось создать заказ. Попробуйте позже.', 'art-lms' ),
			'button_disabled'     => __( 'Эта платёжная кнопка сейчас недоступна.', 'art-lms' ),
			'email_verification_sent' => __( 'Мы отправили письмо с подтверждением на ваш email. Перейдите по ссылке из письма, чтобы продолжить оплату.', 'art-lms' ),
			'payment_failed'          => __( 'Не удалось перейти к оплате. Попробуйте ещё раз.', 'art-lms' ),
			'payment_method_required' => __( 'Выберите способ оплаты.', 'art-lms' ),
			'generic_error'           => __( 'Что-то пошло не так. Попробуйте ещё раз.', 'art-lms' ),
		);
	}

	/**
	 * Checkout form error message catalog for admin UI.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_checkout_form_message_catalog() {
		return array(
			'required_field'      => array(
				'label'       => __( 'Не заполнено обязательное поле', 'art-lms' ),
				'description' => __( '{поле} — подпись поля', 'art-lms' ),
			),
			'invalid_email'       => array(
				'label'       => __( 'Некорректный email', 'art-lms' ),
				'description' => '',
			),
			'consent_required'    => array(
				'label'       => __( 'Не отмечено обязательное согласие', 'art-lms' ),
				'description' => __( '{согласие} — название согласия', 'art-lms' ),
			),
			'network_error'         => array(
				'label'       => __( 'Сервер не ответил', 'art-lms' ),
				'description' => '',
			),
			'create_order_failed'   => array(
				'label'       => __( 'Не удалось создать заказ', 'art-lms' ),
				'description' => '',
			),
			'button_disabled'       => array(
				'label'       => __( 'Платёжная кнопка отключена', 'art-lms' ),
				'description' => '',
			),
			'email_verification_sent' => array(
				'label'       => __( 'Письмо подтверждения отправлено', 'art-lms' ),
				'description' => '',
			),
			'payment_failed'          => array(
				'label'       => __( 'Ошибка перехода к оплате', 'art-lms' ),
				'description' => '',
			),
			'payment_method_required' => array(
				'label'       => __( 'Не выбран способ оплаты', 'art-lms' ),
				'description' => '',
			),
			'generic_error'           => array(
				'label'       => __( 'Неизвестная ошибка', 'art-lms' ),
				'description' => '',
			),
		);
	}

	/**
	 * Get checkout form error messages.
	 *
	 * @return array<string, string>
	 */
	public static function get_checkout_form_messages() {
		$messages = self::get_checkout()['messages'] ?? array();

		return wp_parse_args( is_array( $messages ) ? $messages : array(), self::get_default_checkout_form_messages() );
	}

	/**
	 * Format a checkout form error message with replacements.
	 *
	 * @param string               $key          Message key.
	 * @param array<string, string> $replacements Token replacements.
	 * @return string
	 */
	public static function format_checkout_form_message( $key, $replacements = array() ) {
		$messages = self::get_checkout_form_messages();
		$text     = $messages[ $key ] ?? self::get_default_checkout_form_messages()[ $key ] ?? self::get_default_checkout_form_messages()['generic_error'];

		$tokens = array(
			'{поле}'     => (string) ( $replacements['field'] ?? $replacements['поле'] ?? '' ),
			'{согласие}' => (string) ( $replacements['consent'] ?? $replacements['согласие'] ?? '' ),
		);

		return str_replace( array_keys( $tokens ), array_values( $tokens ), $text );
	}

	/**
	 * Build frontend checkout form config for validation scripts.
	 *
	 * @return array{fields: array, consents: array, messages: array}
	 */
	public static function get_checkout_frontend_form_config() {
		$fields = array();

		foreach ( self::get_checkout_form_fields() as $field ) {
			$fields[] = array(
				'key'      => $field['key'],
				'label'    => $field['label'],
				'required' => ! empty( $field['required'] ),
				'input'    => $field['input'],
				'name'     => self::get_checkout_field_post_key( $field['key'] ),
			);
		}

		$consents = array();

		foreach ( self::get_checkout_consents()['items'] as $consent ) {
			$label = trim( (string) ( $consent['admin_label'] ?? '' ) );

			if ( '' === $label ) {
				$label = wp_strip_all_tags( self::format_checkout_consent_label( $consent ) );
			}

			$consents[] = array(
				'key'      => $consent['key'],
				'label'    => $label,
				'required' => ! empty( $consent['required'] ),
				'name'     => $consent['post_key'],
			);
		}

		$payment_methods = self::get_checkout_payment_methods();

		return array(
			'fields'                 => $fields,
			'consents'               => $consents,
			'messages'               => self::get_checkout_form_messages(),
			'paymentMethods'         => $payment_methods,
			'defaultGateway'         => self::get_default_checkout_gateway(),
			'requirePayment'         => count( $payment_methods ) > 0,
		);
	}

	/**
	 * Sanitize checkout form error messages.
	 *
	 * @param array $input Raw messages input.
	 * @return array<string, string>
	 */
	public static function sanitize_checkout_messages( $input ) {
		$defaults = self::get_default_checkout_form_messages();
		$messages = array();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		foreach ( array_keys( self::get_checkout_form_message_catalog() ) as $key ) {
			$value = isset( $input[ $key ] ) ? sanitize_textarea_field( wp_unslash( $input[ $key ] ) ) : '';
			$value = trim( $value );

			$messages[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		return $messages;
	}

	/**
	 * Sanitize checkout design settings.
	 *
	 * @param array $input Raw design input.
	 * @return array
	 */
	public static function sanitize_checkout_design( $input ) {
		$defaults  = self::get_default_checkout()['design'];
		$templates = array_keys( self::get_checkout_design_template_options() );
		$sizes     = array_keys( self::get_checkout_design_button_size_options() );
		$aligns    = array_keys( self::get_checkout_design_button_align_options() );
		$template  = isset( $input['template'] ) ? sanitize_key( $input['template'] ) : $defaults['template'];
		$size      = isset( $input['button_size'] ) ? sanitize_key( $input['button_size'] ) : $defaults['button_size'];
		$align     = isset( $input['button_align'] ) ? sanitize_key( $input['button_align'] ) : $defaults['button_align'];

		if ( ! in_array( $template, $templates, true ) ) {
			$template = $defaults['template'];
		}

		if ( ! in_array( $size, $sizes, true ) ) {
			$size = $defaults['button_size'];
		}

		if ( ! in_array( $align, $aligns, true ) ) {
			$align = $defaults['button_align'];
		}

		$page_background = isset( $input['page_background_color'] ) ? sanitize_hex_color( $input['page_background_color'] ) : '';
		$form_background = isset( $input['form_background_color'] ) ? sanitize_hex_color( $input['form_background_color'] ) : '';
		$button          = isset( $input['button_color'] ) ? sanitize_hex_color( $input['button_color'] ) : '';
		$button_text_color = isset( $input['button_text_color'] ) ? sanitize_hex_color( $input['button_text_color'] ) : '';

		if ( ! $page_background && isset( $input['background_color'] ) ) {
			$page_background = sanitize_hex_color( $input['background_color'] );
		}

		if ( ! $form_background && isset( $input['background_color'] ) ) {
			$form_background = sanitize_hex_color( $input['background_color'] );
		}

		$button_text = isset( $input['button_text'] ) ? sanitize_text_field( wp_unslash( $input['button_text'] ) ) : $defaults['button_text'];
		$button_text = trim( $button_text );

		if ( '' === $button_text ) {
			$button_text = $defaults['button_text'];
		}

		if ( function_exists( 'mb_substr' ) ) {
			$button_text = mb_substr( $button_text, 0, 50 );
		} else {
			$button_text = substr( $button_text, 0, 50 );
		}

		$text_defaults = self::get_checkout_design_text_defaults();
		$text_fields   = self::get_checkout_design_text_fields();
		$text_sizes    = array();

		foreach ( $text_fields as $text_key => $text_field ) {
			$text_sizes[ $text_key ] = self::sanitize_checkout_design_dimension(
				$input[ $text_key ] ?? $defaults[ $text_key ] ?? $text_defaults[ $text_key ],
				$text_defaults[ $text_key ],
				(int) $text_field['min'],
				(int) $text_field['max']
			);
		}

		return array(
			'template'              => $template,
			'page_background_color' => $page_background ? $page_background : $defaults['page_background_color'],
			'form_background_color' => $form_background ? $form_background : $defaults['form_background_color'],
			'button_color'          => $button ? $button : $defaults['button_color'],
			'button_text_color'     => $button_text_color ? $button_text_color : $defaults['button_text_color'],
			'button_size'           => $size,
			'button_align'          => $align,
			'button_text'           => $button_text,
			'form_max_width'        => self::sanitize_checkout_design_dimension(
				$input['form_max_width'] ?? $defaults['form_max_width'],
				$defaults['form_max_width'],
				320,
				1200
			),
			'form_padding'          => self::sanitize_checkout_design_dimension(
				$input['form_padding'] ?? $defaults['form_padding'],
				$defaults['form_padding'],
				0,
				80
			),
			'form_border_radius'    => self::sanitize_checkout_design_dimension(
				$input['form_border_radius'] ?? $defaults['form_border_radius'],
				$defaults['form_border_radius'],
				0,
				64
			),
			'title_font_size'         => $text_sizes['title_font_size'],
			'product_name_font_size'  => $text_sizes['product_name_font_size'],
			'compare_price_font_size' => $text_sizes['compare_price_font_size'],
			'price_font_size'         => $text_sizes['price_font_size'],
			'field_label_font_size'   => $text_sizes['field_label_font_size'],
			'field_input_font_size'   => $text_sizes['field_input_font_size'],
			'consent_checkbox_size'   => $text_sizes['consent_checkbox_size'],
			'consent_font_size'       => $text_sizes['consent_font_size'],
		);
	}

	/**
	 * Sanitize checkout settings.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function sanitize_checkout( $input ) {
		$current = self::get_checkout();

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$slug = isset( $current['slug'] ) ? self::sanitize_checkout_slug( $current['slug'] ) : self::get_default_checkout()['slug'];

		if ( isset( $input['slug'] ) ) {
			$slug = self::sanitize_checkout_slug( wp_unslash( $input['slug'] ) );
		}

		$default_title = self::get_default_checkout()['form_title'];
		$form_title    = isset( $input['form_title'] ) ? sanitize_text_field( wp_unslash( $input['form_title'] ) ) : ( $current['form_title'] ?? $default_title );
		$form_title    = trim( $form_title );

		if ( '' === $form_title ) {
			$form_title = $default_title;
		}

		if ( function_exists( 'mb_substr' ) ) {
			$form_title = mb_substr( $form_title, 0, 100 );
		} else {
			$form_title = substr( $form_title, 0, 100 );
		}

		return array(
			'slug'          => $slug,
			'form_title'    => $form_title,
			'fields'        => isset( $input['fields'] )
				? self::sanitize_checkout_fields( $input['fields'] )
				: $current['fields'],
			'custom_fields' => isset( $input['custom_fields'] )
				? self::sanitize_checkout_custom_fields( $input['custom_fields'] )
				: ( $current['custom_fields'] ?? array() ),
			'consents'      => isset( $input['consents'] )
				? self::sanitize_checkout_consents( $input['consents'] )
				: $current['consents'],
			'custom_consents' => isset( $input['custom_consents'] )
				? self::sanitize_checkout_custom_consents( $input['custom_consents'] )
				: ( $current['custom_consents'] ?? array() ),
			'design'        => isset( $input['design'] )
				? self::sanitize_checkout_design( $input['design'] )
				: self::get_checkout_design(),
			'messages'       => isset( $input['messages'] )
				? self::sanitize_checkout_messages( $input['messages'] )
				: self::get_checkout_form_messages(),
			'payment_status' => isset( $input['payment_status'] )
				? self::sanitize_payment_status_messages( $input['payment_status'] )
				: self::get_payment_status_messages(),
		);
	}

	/**
	 * Sanitize checkout field settings.
	 *
	 * @param array $input Raw fields input.
	 * @return array
	 */
	public static function sanitize_checkout_fields( $input ) {
		$defaults = self::get_default_checkout()['fields'];
		$fields   = array();

		foreach ( $defaults as $key => $default ) {
			$row = is_array( $input ) ? ( $input[ $key ] ?? array() ) : array();

			$fields[ $key ] = array(
				'enabled'  => ! empty( $row['enabled'] ) || 'email' === $key ? 'yes' : 'no',
				'required' => ! empty( $row['required'] ) || 'email' === $key ? 'yes' : 'no',
				'label'    => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : $default['label'],
			);
		}

		$fields['email']['enabled']  = 'yes';
		$fields['email']['required'] = 'yes';

		return $fields;
	}

	/**
	 * Sanitize custom checkout fields.
	 *
	 * @param array $input Raw custom fields input.
	 * @return array
	 */
	public static function sanitize_checkout_custom_fields( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$fields         = array();
		$reserved_keys  = self::get_checkout_builtin_field_keys();
		$used_ids       = array();

		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';

			if ( '' === $label ) {
				continue;
			}

			$id = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';

			if ( ! preg_match( '/^custom_[a-z0-9_]+$/', $id ) ) {
				$id = 'custom_' . sanitize_key( $label );
			}

			if ( in_array( $id, $reserved_keys, true ) || isset( $used_ids[ $id ] ) ) {
				$id = 'custom_' . wp_generate_password( 8, false, false );
				$id = sanitize_key( strtolower( $id ) );
			}

			$used_ids[ $id ] = true;

			$fields[] = array(
				'id'       => $id,
				'label'    => $label,
				'enabled'  => ! empty( $row['enabled'] ) ? 'yes' : 'no',
				'required' => ! empty( $row['required'] ) ? 'yes' : 'no',
			);
		}

		return $fields;
	}

	/**
	 * Default general settings.
	 *
	 * @return array
	 */
	public static function get_default_general() {
		return array(
			'account_page_id'                => 0,
			'success_page_id'                => 0,
			'support_email'                  => '',
			'currency'                       => 'RUB',
			'create_user_before_payment'     => 'yes',
			'user_registration_verification' => 'none',
			'auto_login_after_register'      => 'yes',
		);
	}

	/**
	 * Default login page settings.
	 *
	 * @return array
	 */
	public static function get_default_login() {
		return array(
			'enabled' => 'no',
			'slug'    => 'artlogin',
			'form'    => array(
				'title_enabled'         => 'yes',
				'title_text'            => __( 'Вход', 'art-lms' ),
				'subtitle_enabled'      => 'no',
				'subtitle_text'         => __( 'Войдите, чтобы открыть материалы', 'art-lms' ),
				'username_label'        => __( 'Email', 'art-lms' ),
				'password_label'        => __( 'Пароль', 'art-lms' ),
				'remember_enabled'      => 'yes',
				'remember_label'        => __( 'Запомнить меня', 'art-lms' ),
				'lost_password_enabled' => 'yes',
				'lost_password_text'    => __( 'Забыли пароль?', 'art-lms' ),
			),
			'button'  => array(
				'text'             => __( 'Войти', 'art-lms' ),
				'font_size'        => 16,
				'size'             => 'medium',
				'align'            => 'full',
				'background_color' => '#2271b1',
				'text_color'       => '#ffffff',
				'border_radius'    => 4,
				'custom_padding_y' => 10,
				'custom_padding_x' => 16,
			),
			'design'  => array(
				'page_background_color'    => '#f1f5f9',
				'form_background_color'    => '#ffffff',
				'form_border_color'      => '#c3c4c7',
				'form_max_width'           => 360,
				'form_padding'             => 24,
				'form_border_radius'       => 8,
				'field_label_font_size'    => 14,
				'field_input_font_size'    => 16,
				'field_border_color'       => '#c3c4c7',
				'field_focus_border_color' => '#2271b1',
			),
		);
	}

	/**
	 * Default payment settings.
	 *
	 * @return array
	 */
	public static function get_default_payment() {
		$gateways = array();

		foreach ( Art_LMS_Payment_Gateway_Registry::all() as $gateway_id => $gateway ) {
			$gateways[ $gateway_id ] = $gateway->get_default_settings();
		}

		return array(
			'default_gateway' => '',
			'gateway_order'   => array_keys( $gateways ),
			'active_gateway'  => '',
			'gateways'        => $gateways,
		);
	}

	/**
	 * Default checkout settings.
	 *
	 * @return array
	 */
	public static function get_default_checkout() {
		return array(
			'slug'       => 'artout',
			'form_title' => __( 'Оформление заказа', 'art-lms' ),
			'fields'     => array(
				'full_name' => array(
					'enabled'  => 'yes',
					'required' => 'yes',
					'label'    => __( 'ФИО', 'art-lms' ),
				),
				'email'     => array(
					'enabled'  => 'yes',
					'required' => 'yes',
					'label'    => __( 'Почта', 'art-lms' ),
				),
				'phone'     => array(
					'enabled'  => 'yes',
					'required' => 'no',
					'label'    => __( 'Телефон', 'art-lms' ),
				),
			),
			'custom_fields' => array(),
			'custom_consents' => array(),
			'consents'      => array(
				'title'   => '',
				'privacy' => array(
					'enabled'   => 'yes',
					'required'  => 'yes',
					'text'      => __( 'Я согласен с', 'art-lms' ),
					'link_text' => __( 'политикой конфиденциальности', 'art-lms' ),
					'page_id'   => 0,
				),
			),
			'design'        => array(
				'template'              => 'standalone',
				'page_background_color' => '#f1f5f9',
				'form_background_color' => '#ffffff',
				'button_color'          => '#2563eb',
				'button_text_color'     => '#ffffff',
				'button_size'           => 'medium',
				'button_align'          => 'center',
				'button_text'           => __( 'Оплатить', 'art-lms' ),
				'form_max_width'        => 450,
				'form_padding'          => 20,
				'form_border_radius'    => 8,
				'title_font_size'         => 24,
				'product_name_font_size'  => 16,
				'compare_price_font_size' => 16,
				'price_font_size'         => 16,
				'field_label_font_size'   => 16,
				'field_input_font_size'   => 16,
				'consent_checkbox_size'   => 16,
				'consent_font_size'       => 16,
			),
			'messages'       => self::get_default_checkout_form_messages(),
			'payment_status' => self::get_default_payment_status_messages(),
		);
	}

	/**
	 * Default email settings.
	 *
	 * @return array
	 */
	public static function get_default_emails() {
		return array(
			'email_from'      => get_option( 'admin_email' ),
			'email_from_name' => get_bloginfo( 'name' ),
			'purchase'        => array(
				'enabled' => 'yes',
				'subject' => self::get_default_purchase_email_subject(),
				'body'    => self::get_default_purchase_email_body(),
			),
			'admin_payment'   => array(
				'enabled'   => 'yes',
				'recipient' => get_option( 'admin_email' ),
				'subject'   => self::get_default_admin_payment_email_subject(),
				'body'      => self::get_default_admin_payment_email_body(),
			),
			'email_verification' => array(
				'enabled' => 'yes',
				'subject' => self::get_default_email_verification_subject(),
				'body'    => self::get_default_email_verification_body(),
			),
		);
	}

	/**
	 * Get option with cache.
	 *
	 * @param string $option  Option name.
	 * @param array  $default Default value.
	 * @return array
	 */
	private static function get_option( $option, $default ) {
		if ( isset( self::$cache[ $option ] ) ) {
			return self::$cache[ $option ];
		}

		$value = get_option( $option, array() );
		$value = wp_parse_args( is_array( $value ) ? $value : array(), $default );

		if ( self::OPTION_PAYMENT === $option ) {
			if ( ! isset( $value['default_gateway'] ) ) {
				$value['default_gateway'] = $value['active_gateway'] ?? '';
			}

			if ( empty( $value['gateway_order'] ) || ! is_array( $value['gateway_order'] ) ) {
				$value['gateway_order'] = $default['gateway_order'];
			}

			$value['active_gateway'] = $value['default_gateway'] ?? '';

			$value['gateways'] = wp_parse_args( $value['gateways'] ?? array(), $default['gateways'] );

			foreach ( $default['gateways'] as $gateway_id => $gateway_defaults ) {
				$value['gateways'][ $gateway_id ] = wp_parse_args(
					$value['gateways'][ $gateway_id ] ?? array(),
					$gateway_defaults
				);
			}
		}

		if ( self::OPTION_EMAIL === $option ) {
			$value['purchase'] = wp_parse_args( $value['purchase'] ?? array(), $default['purchase'] );
			$value['admin_payment'] = wp_parse_args( $value['admin_payment'] ?? array(), $default['admin_payment'] );
			$value['email_verification'] = wp_parse_args( $value['email_verification'] ?? array(), $default['email_verification'] );
		}

		if ( self::OPTION_LOGIN === $option ) {
			$value['enabled'] = ( $value['enabled'] ?? 'no' ) === 'yes' ? 'yes' : 'no';
			$value['slug']    = self::sanitize_login_slug( (string) ( $value['slug'] ?? $default['slug'] ) );
			$value['form']    = self::sanitize_login_form(
				wp_parse_args( $value['form'] ?? array(), $default['form'] )
			);
			$value['button']  = self::sanitize_login_button(
				wp_parse_args( $value['button'] ?? array(), $default['button'] )
			);
			$value['design']  = self::sanitize_login_design(
				wp_parse_args( $value['design'] ?? array(), $default['design'] )
			);
		}

		if ( self::OPTION_CHECKOUT === $option ) {
			$value['slug'] = self::sanitize_checkout_slug( (string) ( $value['slug'] ?? $default['slug'] ) );
			$value['fields'] = wp_parse_args( $value['fields'] ?? array(), $default['fields'] );

			foreach ( $default['fields'] as $key => $field_default ) {
				$value['fields'][ $key ] = wp_parse_args( $value['fields'][ $key ] ?? array(), $field_default );
			}

			if ( ! is_array( $value['custom_fields'] ?? null ) ) {
				$value['custom_fields'] = array();
			}

			if ( ! is_array( $value['custom_consents'] ?? null ) ) {
				$value['custom_consents'] = array();
			}

			$value['consents'] = wp_parse_args( $value['consents'] ?? array(), $default['consents'] );

			foreach ( self::get_checkout_consent_catalog() as $consent_key => $unused ) {
				$value['consents'][ $consent_key ] = wp_parse_args(
					$value['consents'][ $consent_key ] ?? array(),
					$default['consents'][ $consent_key ]
				);
			}

			$value['design'] = wp_parse_args( $value['design'] ?? array(), $default['design'] );
			$value['messages'] = wp_parse_args( $value['messages'] ?? array(), $default['messages'] );
			$value['payment_status'] = wp_parse_args( $value['payment_status'] ?? array(), $default['payment_status'] );
		}

		self::$cache[ $option ] = $value;

		return $value;
	}

	/**
	 * Update option without autoload and refresh cache.
	 *
	 * @param string $option Option name.
	 * @param array  $value  Value.
	 */
	private static function update_option( $option, $value ) {
		update_option( $option, $value, false );
		self::$cache[ $option ] = $value;
	}
}
