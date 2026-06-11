<?php
/**
 * Customer account helpers.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public LMS URLs use shareable GET parameters (checkout, account, payment status).

/**
 * Class Art_LMS_Account
 */
class Art_LMS_Account {

	const QUERY_SET_PASSWORD     = 'art_lms_set_password';
	const SET_PASSWORD_PREFIX    = 'art_lms_set_password_';
	const SET_PASSWORD_TOKEN_TTL = DAY_IN_SECONDS;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_set_password_link' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_account_page_guest' ), 5 );
		add_action( 'login_form_lostpassword', array( __CLASS__, 'prefill_lostpassword_email_field' ) );
	}

	/**
	 * Get materials available to the user for account listing.
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_materials_for_user( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array();
		}

		$access_rows = Art_LMS_Access::get_user_access_list( $user_id );
		$by_material = array();

		foreach ( $access_rows as $row ) {
			$material_id = (int) $row->product_id;

			if ( ! $material_id ) {
				continue;
			}

			if ( ! isset( $by_material[ $material_id ] ) || self::is_access_preferred( $row, $by_material[ $material_id ] ) ) {
				$by_material[ $material_id ] = $row;
			}
		}

		$materials = array();

		foreach ( $by_material as $material_id => $row ) {
			$item = self::build_material_item( $material_id, $row );

			if ( $item ) {
				$materials[] = $item;
			}
		}

		usort(
			$materials,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);

		/**
		 * Filter materials shown in customer account.
		 *
		 * @param array $materials Material items.
		 * @param int   $user_id   User ID.
		 */
		return (array) apply_filters( 'art_lms_account_materials', $materials, $user_id );
	}

	/**
	 * Build account URL for footer links.
	 *
	 * @return string
	 */
	public static function get_page_url() {
		$url = Art_LMS_Settings::get_account_url();

		if ( $url ) {
			return $url;
		}

		$permalink = get_permalink();

		return $permalink ? (string) $permalink : home_url( '/' );
	}

	/**
	 * Build a stable URL to set a password (token in email; fresh reset key on click).
	 *
	 * @param int|WP_User $user        User ID or object.
	 * @param string      $redirect_to Redirect target after password is set.
	 * @param int         $order_id    Optional order ID for token binding.
	 * @return string
	 */
	public static function get_set_password_url( $user, $redirect_to = '', $order_id = 0 ) {
		if ( is_numeric( $user ) ) {
			$user = get_userdata( (int) $user );
		}

		if ( ! $user instanceof WP_User || ! $user->ID ) {
			return wp_lostpassword_url( $redirect_to );
		}

		$redirect_to = self::sanitize_redirect_to( $redirect_to );

		$token = self::create_set_password_token( (int) $user->ID, $redirect_to, $order_id );

		if ( '' === $token ) {
			return wp_lostpassword_url( $redirect_to );
		}

		return add_query_arg(
			self::QUERY_SET_PASSWORD,
			$token,
			home_url( '/' )
		);
	}

	/**
	 * Store a short-lived token for the set-password redirect flow.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $redirect_to Redirect target after password is set.
	 * @param int    $order_id    Optional order ID.
	 * @return string Empty string on failure.
	 */
	public static function create_set_password_token( $user_id, $redirect_to = '', $order_id = 0 ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return '';
		}

		$token = wp_generate_password( 32, false, false );

		$stored = set_transient(
			self::SET_PASSWORD_PREFIX . $token,
			array(
				'user_id'     => $user_id,
				'redirect_to' => self::sanitize_redirect_to( $redirect_to ),
				'order_id'    => absint( $order_id ),
			),
			(int) apply_filters( 'art_lms_set_password_token_ttl', self::SET_PASSWORD_TOKEN_TTL )
		);

		return $stored ? $token : '';
	}

	/**
	 * Handle set-password links from purchase emails.
	 */
	public static function maybe_handle_set_password_link() {
		if ( empty( $_GET[ self::QUERY_SET_PASSWORD ] ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_SET_PASSWORD ] ) );

		if ( '' === $token ) {
			return;
		}

		$payload = get_transient( self::SET_PASSWORD_PREFIX . $token );

		if ( ! is_array( $payload ) ) {
			wp_die(
				esc_html__( 'Ссылка для установки пароля устарела. Запросите новую на странице восстановления пароля.', 'art-lms' ),
				esc_html__( 'Установка пароля', 'art-lms' ),
				array( 'response' => 410 )
			);
		}

		$user = get_userdata( absint( $payload['user_id'] ?? 0 ) );

		if ( ! $user ) {
			delete_transient( self::SET_PASSWORD_PREFIX . $token );

			wp_die(
				esc_html__( 'Аккаунт не найден. Обратитесь в поддержку.', 'art-lms' ),
				esc_html__( 'Установка пароля', 'art-lms' ),
				array( 'response' => 404 )
			);
		}

		$redirect_to = self::sanitize_redirect_to( (string) ( $payload['redirect_to'] ?? '' ) );

		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			wp_die(
				esc_html__( 'Не удалось подготовить установку пароля. Попробуйте восстановление пароля на сайте.', 'art-lms' ),
				esc_html__( 'Установка пароля', 'art-lms' ),
				array( 'response' => 500 )
			);
		}

		self::prime_password_reset_cookie( $user->user_login, $key );

		$login_url = add_query_arg( 'action', 'rp', network_site_url( 'wp-login.php', 'login' ) );

		wp_safe_redirect( add_query_arg( 'redirect_to', $redirect_to, $login_url ) );
		exit;
	}

	/**
	 * Allow redirects only to the same site; fall back to the account page.
	 *
	 * @param string $redirect_to Requested redirect URL.
	 * @param string $fallback    Optional fallback URL.
	 * @return string
	 */
	private static function sanitize_redirect_to( $redirect_to, $fallback = '' ) {
		if ( '' === $fallback ) {
			$fallback = self::get_page_url();
		}

		$redirect_to = trim( (string) $redirect_to );

		if ( '' === $redirect_to ) {
			return $fallback;
		}

		$validated = wp_validate_redirect( $redirect_to, $fallback );

		return $validated ? $validated : $fallback;
	}

	/**
	 * Prime the cookie WordPress expects before showing the reset-password form.
	 *
	 * @param string $login User login.
	 * @param string $key   Password reset key.
	 */
	private static function prime_password_reset_cookie( $login, $key ) {
		$login_url = network_site_url( 'wp-login.php', 'login' );
		$rp_path   = wp_parse_url( $login_url, PHP_URL_PATH );

		if ( ! is_string( $rp_path ) || '' === $rp_path ) {
			$rp_path = '/wp-login.php';
		}

		$cookie_value = sprintf( '%s:%s', $login, $key );

		setcookie(
			'wp-resetpass-' . COOKIEHASH,
			$cookie_value,
			0,
			$rp_path,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
	}

	/**
	 * Build lost-password URL with the current user's email prefilled.
	 *
	 * @param string   $redirect_to Redirect target after password reset flow.
	 * @param WP_User|null $user    Optional user object.
	 * @return string
	 */
	public static function get_reset_password_url( $redirect_to = '', $user = null ) {
		$redirect_to = self::sanitize_redirect_to( $redirect_to );

		$url = wp_lostpassword_url( $redirect_to );

		if ( ! $user instanceof WP_User ) {
			$user = wp_get_current_user();
		}

		if ( $user && $user->ID && is_email( $user->user_email ) ) {
			$url = add_query_arg( 'user_login', $user->user_email, $url );
		}

		return $url;
	}

	/**
	 * Prefill email/username on the WordPress lost-password form.
	 */
	public static function prefill_lostpassword_email_field() {
		if ( empty( $_GET['user_login'] ) ) {
			return;
		}

		$user_login = sanitize_text_field( wp_unslash( $_GET['user_login'] ) );

		if ( '' === $user_login ) {
			return;
		}

		printf(
			'<script>document.addEventListener("DOMContentLoaded",function(){var field=document.getElementById("user_login");if(field&&!field.value){field.value=%s;}});</script>',
			wp_json_encode( $user_login )
		);
	}

	/**
	 * Format access expiration label for account cards.
	 *
	 * @param string|null $expires_at Expiration datetime.
	 * @return string
	 */
	public static function format_access_expires_label( $expires_at ) {
		if ( empty( $expires_at ) ) {
			return __( 'Доступ: бессрочный', 'art-lms' );
		}

		$timestamp = strtotime( (string) $expires_at );

		if ( ! $timestamp ) {
			return __( 'Доступ: бессрочный', 'art-lms' );
		}

		return sprintf(
			/* translators: %s: expiration date */
			__( 'Доступ: до %s', 'art-lms' ),
			wp_date( 'j F Y', $timestamp )
		);
	}

	/**
	 * Whether access expires within the next week.
	 *
	 * @param string|null $expires_at Expiration datetime.
	 * @param int         $days       Days threshold.
	 * @return bool
	 */
	public static function is_expiring_soon( $expires_at, $days = 7 ) {
		if ( empty( $expires_at ) ) {
			return false;
		}

		$timestamp = strtotime( (string) $expires_at );

		if ( ! $timestamp ) {
			return false;
		}

		$days      = max( 1, absint( $days ) );
		$days_left = ( $timestamp - current_time( 'timestamp' ) ) / DAY_IN_SECONDS;

		return $days_left >= 0 && $days_left <= $days;
	}

	/**
	 * Decide whether a new access row should replace the current one.
	 *
	 * @param object $new     Candidate access row.
	 * @param object $current Current access row.
	 * @return bool
	 */
	private static function is_access_preferred( $new, $current ) {
		if ( empty( $current->expires_at ) ) {
			return false;
		}

		if ( empty( $new->expires_at ) ) {
			return true;
		}

		return strtotime( (string) $new->expires_at ) > strtotime( (string) $current->expires_at );
	}

	/**
	 * Build one material card payload.
	 *
	 * @param int    $material_id Material ID.
	 * @param object $access_row  Access row.
	 * @return array<string, mixed>|null
	 */
	private static function build_material_item( $material_id, $access_row ) {
		$post = get_post( $material_id );

		if ( ! $post || Art_LMS_Materials::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		$url = Art_LMS_Materials::get_url( $material_id );

		if ( ! $url ) {
			return null;
		}

		$expires_at    = $access_row->expires_at ?? null;
		$access_label  = self::format_access_expires_label( $expires_at );
		$expiring_soon = self::is_expiring_soon( $expires_at );

		return array(
			'material_id'   => $material_id,
			'title'         => get_the_title( $material_id ),
			'url'           => $url,
			'expires_at'    => $expires_at,
			'access_label'  => $access_label,
			'expiring_soon' => $expiring_soon,
		);
	}

	/**
	 * Default block/shortcode display settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_block_defaults() {
		return array(
			'materials_title'         => __( 'Ваши материалы', 'art-lms' ),
			'empty_message'           => __( 'Пока нет доступных материалов. После оплаты они появятся здесь автоматически.', 'art-lms' ),
			'open_button_text'       => __( 'Открыть', 'art-lms' ),
			'logout_link_text'       => __( 'Выйти', 'art-lms' ),
			'reset_password_link_text' => __( 'Сменить пароль', 'art-lms' ),
			'hide_materials_title'    => false,
			'hide_access_label'      => false,
			'hide_open_button'       => false,
			'hide_logout_link'       => false,
			'hide_reset_password'    => false,
			'container_width_mode'   => 'theme',
			'container_custom_width' => 640,
			'hide_border'            => false,
			'border_color'           => '',
			'border_radius'          => 0,
			'materials_title_font_size' => 0,
			'button_font_size'       => 0,
			'button_text_color'      => '',
			'button_background_color' => '',
			'button_border_radius'   => 0,
		);
	}

	/**
	 * Normalize account block render args.
	 *
	 * @param array $args Block args.
	 * @return array<string, mixed>
	 */
	public static function normalize_block_args( $args = array() ) {
		$defaults = self::get_block_defaults();
		$args     = wp_parse_args( $args, $defaults );

		$width_modes = array( 'theme', 'full', 'custom' );
		$width_mode  = sanitize_key( (string) $args['container_width_mode'] );

		if ( ! in_array( $width_mode, $width_modes, true ) ) {
			$width_mode = $defaults['container_width_mode'];
		}

		$text_fields = array(
			'materials_title',
			'empty_message',
			'open_button_text',
			'logout_link_text',
			'reset_password_link_text',
		);

		$normalized = array(
			'hide_materials_title'    => (bool) $args['hide_materials_title'],
			'hide_access_label'       => (bool) $args['hide_access_label'],
			'hide_open_button'        => (bool) $args['hide_open_button'],
			'hide_logout_link'        => (bool) $args['hide_logout_link'],
			'hide_reset_password'     => (bool) $args['hide_reset_password'],
			'container_width_mode'    => $width_mode,
			'container_custom_width'  => max( 240, min( 1600, absint( $args['container_custom_width'] ) ) ),
			'hide_border'             => (bool) $args['hide_border'],
			'border_color'            => sanitize_hex_color( (string) $args['border_color'] ) ?: '',
			'border_radius'           => max( 0, min( 32, absint( $args['border_radius'] ) ) ),
			'materials_title_font_size' => max( 0, min( 32, absint( $args['materials_title_font_size'] ) ) ),
			'button_font_size'        => max( 0, min( 32, absint( $args['button_font_size'] ) ) ),
			'button_text_color'       => sanitize_hex_color( (string) $args['button_text_color'] ) ?: '',
			'button_background_color' => sanitize_hex_color( (string) $args['button_background_color'] ) ?: '',
			'button_border_radius'    => max( 0, min( 32, absint( $args['button_border_radius'] ) ) ),
		);

		foreach ( $text_fields as $field ) {
			$value = sanitize_text_field( (string) $args[ $field ] );

			if ( '' === $value ) {
				$value = $defaults[ $field ];
			}

			$normalized[ $field ] = $value;
		}

		return $normalized;
	}

	/**
	 * Build inline CSS for account action buttons.
	 *
	 * @param array $settings Normalized settings.
	 * @return string
	 */
	public static function get_button_inline_style( array $settings ) {
		return Art_LMS_Payment_Buttons::build_button_inline_style(
			array(
				'button_font_size'        => $settings['button_font_size'] ?? 0,
				'button_text_color'       => $settings['button_text_color'] ?? '',
				'button_background_color' => $settings['button_background_color'] ?? '',
				'button_border_radius'    => $settings['button_border_radius'] ?? 0,
			)
		);
	}

	/**
	 * Build wrapper attributes for account markup.
	 *
	 * @param array $settings Normalized settings.
	 * @return string
	 */
	public static function get_wrapper_attributes( array $settings ) {
		$classes = array( 'art-lms-account' );
		$styles  = array();

		$width_mode = $settings['container_width_mode'] ?? 'theme';

		if ( 'full' === $width_mode ) {
			$classes[] = 'art-lms-account--full-width';
		} elseif ( 'custom' === $width_mode ) {
			$width_px  = max( 240, min( 1600, (int) ( $settings['container_custom_width'] ?? 640 ) ) );
			$classes[] = 'art-lms-account--width-custom';
			$styles[]  = '--art-lms-account-max-width:' . $width_px . 'px';
			$styles[]  = 'max-width:' . $width_px . 'px';
			$styles[]  = 'margin-left:auto';
			$styles[]  = 'margin-right:auto';
		} else {
			$classes[] = 'art-lms-account--width-theme';
		}

		if ( ! empty( $settings['hide_border'] ) ) {
			$classes[] = 'art-lms-account--no-border';
		} else {
			if ( ! empty( $settings['border_color'] ) ) {
				$styles[] = '--art-lms-account-border-color:' . $settings['border_color'];
			}

			if ( ! empty( $settings['border_radius'] ) ) {
				$styles[] = '--art-lms-account-border-radius:' . (int) $settings['border_radius'] . 'px';
			}
		}

		if ( ! empty( $settings['materials_title_font_size'] ) ) {
			$classes[] = 'art-lms-account--custom-materials-title-size';
			$styles[]  = '--art-lms-account-materials-title-font-size:' . (int) $settings['materials_title_font_size'] . 'px';
		}

		$attrs = 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';

		if ( $styles ) {
			$attrs .= ' style="' . esc_attr( implode( ';', $styles ) ) . '"';
		}

		return $attrs;
	}

	/**
	 * Map Gutenberg block attributes to render args.
	 *
	 * @param array $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	public static function block_attributes_to_args( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();

		$width_mode = sanitize_key( (string) ( $attributes['containerWidthMode'] ?? 'theme' ) );

		if ( ! in_array( $width_mode, array( 'theme', 'full', 'custom' ), true ) ) {
			$width_mode = 'theme';
		}

		$custom_width = absint( $attributes['containerCustomWidth'] ?? 640 );

		if ( $custom_width <= 0 ) {
			$custom_width = 640;
		}

		return array(
			'materials_title'          => $attributes['materialsTitle'] ?? '',
			'empty_message'            => $attributes['emptyMessage'] ?? '',
			'open_button_text'         => $attributes['openButtonText'] ?? '',
			'logout_link_text'         => $attributes['logoutLinkText'] ?? '',
			'reset_password_link_text' => $attributes['resetPasswordLinkText'] ?? '',
			'hide_materials_title'     => ! empty( $attributes['hideMaterialsTitle'] ),
			'hide_access_label'        => ! empty( $attributes['hideAccessLabel'] ),
			'hide_open_button'         => ! empty( $attributes['hideOpenButton'] ),
			'hide_logout_link'         => ! empty( $attributes['hideLogoutLink'] ),
			'hide_reset_password'      => ! empty( $attributes['hideResetPassword'] ),
			'container_width_mode'     => $width_mode,
			'container_custom_width'   => $custom_width,
			'hide_border'              => ! empty( $attributes['hideBorder'] ),
			'border_color'             => $attributes['borderColor'] ?? '',
			'border_radius'            => absint( $attributes['borderRadius'] ?? 0 ),
			'materials_title_font_size' => absint( $attributes['materialsTitleFontSize'] ?? 0 ),
			'button_font_size'         => absint( $attributes['buttonFontSize'] ?? 0 ),
			'button_text_color'        => $attributes['buttonTextColor'] ?? '',
			'button_background_color'  => $attributes['buttonBackgroundColor'] ?? '',
			'button_border_radius'     => absint( $attributes['buttonBorderRadius'] ?? 0 ),
		);
	}

	/**
	 * Redirect guests away from the account page before it renders.
	 */
	public static function maybe_redirect_account_page_guest() {
		if ( ! Art_LMS_Settings::is_account_page() ) {
			return;
		}

		self::redirect_guest_to_login( self::get_login_redirect_url() );
	}

	/**
	 * Build redirect URL for wp_login_url().
	 *
	 * @return string
	 */
	public static function get_login_redirect_url() {
		if ( Art_LMS_Settings::is_account_page() ) {
			$url = Art_LMS_Settings::get_account_url();

			if ( ! $url ) {
				$url = self::get_page_url();
			}

			$args = array();

			$return_url = Art_LMS_Materials::get_requested_return_url();

			if ( $return_url ) {
				$args[ Art_LMS_Materials::QUERY_RETURN ] = $return_url;
			}

			if ( Art_LMS_Materials::is_access_denied_request() ) {
				$args[ Art_LMS_Materials::QUERY_ACCESS_DENIED ] = '1';
			}

			return ! empty( $args ) ? add_query_arg( $args, $url ) : $url;
		}

		return self::get_page_url();
	}

	/**
	 * Redirect guests to the WordPress login page.
	 *
	 * @param string $redirect_to URL to open after login.
	 */
	public static function redirect_guest_to_login( $redirect_to = '' ) {
		if ( is_user_logged_in() ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( ! $redirect_to ) {
			$redirect_to = self::get_login_redirect_url();
		}

		wp_safe_redirect( wp_login_url( $redirect_to ) );
		exit;
	}

	/**
	 * Render customer account markup.
	 *
	 * @param array $args Optional block args.
	 * @return string
	 */
	public static function render( $args = array() ) {
		self::redirect_guest_to_login( self::get_login_redirect_url() );

		wp_enqueue_style( 'art-lms-public' );

		$settings = self::normalize_block_args( $args );

		ob_start();
		include ART_LMS_PLUGIN_DIR . 'public/views/account.php';

		return (string) ob_get_clean();
	}
}
