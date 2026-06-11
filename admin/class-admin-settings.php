<?php
/**
 * Admin settings pages.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin screens require capability checks; list filters use sanitized GET parameters.

/**
 * Class Art_LMS_Admin_Settings
 */
class Art_LMS_Admin_Settings {

	const PAGE_TECH     = 'art-lms-tech';
	const PAGE_SETTINGS = 'art-lms-settings';

	const TAB_CHECKOUT      = 'checkout';
	const TAB_DESIGN        = 'design';
	const TAB_CONFIRMATION  = 'confirmation';
	const TAB_EMAIL         = 'email';
	const TAB_GENERAL  = 'general';
	const TAB_PAYMENTS = 'payments';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_ajax_art_lms_preview_purchase_email', array( __CLASS__, 'ajax_preview_purchase_email' ) );
		add_action( 'wp_ajax_art_lms_send_test_purchase_email', array( __CLASS__, 'ajax_send_test_purchase_email' ) );
		add_action( 'wp_ajax_art_lms_create_settings_page', array( __CLASS__, 'ajax_create_settings_page' ) );
	}

	/**
	 * Register all settings groups.
	 */
	public static function register_settings() {
		register_setting(
			'art_lms_general_group',
			Art_LMS_Settings::OPTION_GENERAL,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Art_LMS_Settings', 'sanitize_general' ),
			)
		);

		register_setting(
			'art_lms_payment_group',
			Art_LMS_Settings::OPTION_PAYMENT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Art_LMS_Settings', 'sanitize_payment' ),
			)
		);

		register_setting(
			'art_lms_checkout_group',
			Art_LMS_Settings::OPTION_CHECKOUT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Art_LMS_Settings', 'sanitize_checkout' ),
			)
		);

		register_setting(
			'art_lms_email_group',
			Art_LMS_Settings::OPTION_EMAIL,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Art_LMS_Settings', 'sanitize_emails' ),
			)
		);
	}

	/**
	 * Render technical settings hub page.
	 */
	public static function render_tech_page() {
		if ( ! Art_LMS_Security::can_manage() ) {
			return;
		}

		$active_tab = self::get_current_tab(
			array( self::TAB_CHECKOUT, self::TAB_DESIGN, self::TAB_CONFIRMATION, self::TAB_EMAIL ),
			self::TAB_CHECKOUT
		);

		if ( isset( $_GET['tab'] ) && 'account' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ) {
			wp_safe_redirect( self::get_tab_url( self::PAGE_SETTINGS, self::TAB_GENERAL ) );
			exit;
		}

		include ART_LMS_PLUGIN_DIR . 'admin/views/page-tech.php';
	}

	/**
	 * Render main settings hub page.
	 */
	public static function render_settings_page() {
		if ( ! Art_LMS_Security::can_manage() ) {
			return;
		}

		if ( isset( $_GET['tab'] ) && 'account' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ) {
			wp_safe_redirect( self::get_tab_url( self::PAGE_SETTINGS, self::TAB_GENERAL ) );
			exit;
		}

		$active_tab = self::get_current_tab(
			array( self::TAB_GENERAL, self::TAB_PAYMENTS ),
			self::TAB_GENERAL
		);

		include ART_LMS_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	/**
	 * Show a success notice after options.php saves settings.
	 */
	public static function render_settings_saved_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- options.php redirect flag only.
		if ( empty( $_GET['settings-updated'] ) ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		esc_html_e( 'Настройки сохранены.', 'art-lms' );
		echo '</p></div>';
	}

	/**
	 * Render tab navigation.
	 *
	 * @param string $page        Admin page slug.
	 * @param array  $tabs        Tab ID => label.
	 * @param string $current_tab Active tab ID.
	 */
	public static function render_tabs( $page, $tabs, $current_tab ) {
		echo '<nav class="nav-tab-wrapper art-lms-admin-tabs" aria-label="' . esc_attr__( 'Вкладки', 'art-lms' ) . '">';

		foreach ( $tabs as $tab_id => $label ) {
			$url   = self::get_tab_url( $page, $tab_id );
			$class = 'nav-tab';

			if ( $current_tab === $tab_id ) {
				$class .= ' nav-tab-active';
			}

			printf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}

		echo '</nav>';
	}

	/**
	 * Build admin URL for a settings tab.
	 *
	 * @param string $page Admin page slug.
	 * @param string $tab  Tab slug.
	 * @return string
	 */
	public static function get_tab_url( $page, $tab ) {
		return add_query_arg(
			array(
				'page' => $page,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Resolve active tab slug.
	 *
	 * @param array  $allowed Allowed tab slugs.
	 * @param string $default Default tab slug.
	 * @return string
	 */
	public static function get_current_tab( $allowed, $default ) {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $default;

		return in_array( $tab, $allowed, true ) ? $tab : $default;
	}

	/**
	 * Load checkout settings partial.
	 */
	public static function render_checkout_partial() {
		$settings = Art_LMS_Settings::get_checkout();
		include ART_LMS_PLUGIN_DIR . 'admin/views/settings-checkout.php';
	}

	/**
	 * Load checkout design settings partial.
	 */
	public static function render_design_partial() {
		$settings = Art_LMS_Settings::get_checkout();
		include ART_LMS_PLUGIN_DIR . 'admin/views/settings-checkout-design.php';
	}

	/**
	 * Load payment confirmation page settings partial.
	 */
	public static function render_confirmation_partial() {
		$settings = Art_LMS_Settings::get_checkout();
		include ART_LMS_PLUGIN_DIR . 'admin/views/settings-checkout-confirmation.php';
	}

	/**
	 * Render title + description fields for payment status settings.
	 *
	 * @param string $option           Settings option name.
	 * @param array  $messages         Current messages.
	 * @param string $title_key        Title field key.
	 * @param string $description_key  Description field key.
	 */
	public static function render_payment_status_message_fields( $option, array $messages, $title_key, $description_key ) {
		?>
		<table class="form-table art-lms-payment-status-settings-fields" role="presentation">
			<tr>
				<th scope="row">
					<label for="art_lms_payment_status_<?php echo esc_attr( $title_key ); ?>">
						<?php esc_html_e( 'Заголовок', 'art-lms' ); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						class="large-text"
						id="art_lms_payment_status_<?php echo esc_attr( $title_key ); ?>"
						name="<?php echo esc_attr( $option ); ?>[payment_status][<?php echo esc_attr( $title_key ); ?>]"
						value="<?php echo esc_attr( $messages[ $title_key ] ?? '' ); ?>"
					>
					<p class="description">
						<button
							type="button"
							class="button-link art-lms-payment-status-message-reset"
							data-target="art_lms_payment_status_<?php echo esc_attr( $title_key ); ?>"
							data-reset-key="<?php echo esc_attr( $title_key ); ?>"
						>
							<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
						</button>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_payment_status_<?php echo esc_attr( $description_key ); ?>">
						<?php esc_html_e( 'Описание', 'art-lms' ); ?>
					</label>
				</th>
				<td>
					<textarea
						class="large-text art-lms-payment-status-message-input"
						id="art_lms_payment_status_<?php echo esc_attr( $description_key ); ?>"
						name="<?php echo esc_attr( $option ); ?>[payment_status][<?php echo esc_attr( $description_key ); ?>]"
						rows="6"
					><?php echo esc_textarea( $messages[ $description_key ] ?? '' ); ?></textarea>
					<p class="description">
						<button
							type="button"
							class="button-link art-lms-payment-status-message-reset"
							data-target="art_lms_payment_status_<?php echo esc_attr( $description_key ); ?>"
							data-reset-key="<?php echo esc_attr( $description_key ); ?>"
						>
							<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
						</button>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Shared checkout admin preview payload.
	 *
	 * @return array
	 */
	public static function get_checkout_preview_shared_config() {
		$fields   = array();
		$consents = array();

		foreach ( Art_LMS_Settings::get_checkout_form_fields() as $field ) {
			$fields[] = array(
				'label'    => $field['label'],
				'required' => ! empty( $field['required'] ),
				'input'    => $field['input'],
			);
		}

		foreach ( Art_LMS_Settings::get_checkout_consents()['items'] as $consent ) {
			$consents[] = array(
				'text'     => $consent['text'],
				'linkText' => $consent['link_text'],
				'pageId'   => (int) ( $consent['page_id'] ?? 0 ),
				'required' => ! empty( $consent['required'] ),
			);
		}

		return array(
			'fields'   => $fields,
			'consents' => array(
				'title' => '',
				'items' => $consents,
			),
			'design'   => Art_LMS_Settings::get_checkout_admin_preview_design_state(),
			'defaults' => array_merge(
				Art_LMS_Settings::get_checkout_design_color_defaults(),
				Art_LMS_Settings::get_checkout_design_dimension_defaults(),
				Art_LMS_Settings::get_checkout_design_text_defaults(),
				array(
					'button_text' => Art_LMS_Settings::get_default_checkout()['design']['button_text'],
				)
			),
			'strings'  => array(
				'title'        => Art_LMS_Settings::get_checkout_form_title(),
				'productTitle'        => __( 'Пример платежной кнопки', 'art-lms' ),
				'productComparePrice' => __( '2 490 ₽', 'art-lms' ),
				'productPrice'        => __( '1 990 ₽', 'art-lms' ),
				'pay'          => __( 'Оплатить', 'art-lms' ),
				'empty'        => __( 'Нет полей для отображения. Включите хотя бы одно поле.', 'art-lms' ),
				'header'       => __( 'Шапка сайта', 'art-lms' ),
				'footer'       => __( 'Подвал сайта', 'art-lms' ),
			),
		);
	}

	/**
	 * Build preview payload for checkout design tab.
	 *
	 * @return array
	 */
	public static function get_checkout_design_preview_config() {
		$config = self::get_checkout_preview_shared_config();

		$config['strings']['empty'] = __( 'Нет полей для отображения. Настройте форму на вкладке «Настройки полей».', 'art-lms' );

		return $config;
	}

	/**
	 * Load email settings partial.
	 */
	public static function render_email_partial() {
		$settings = Art_LMS_Settings::get_emails();
		include ART_LMS_PLUGIN_DIR . 'admin/views/settings-email.php';
	}

	/**
	 * Load general settings partial.
	 */
	public static function render_general_partial() {
		$settings = Art_LMS_Settings::get_general();
		include ART_LMS_PLUGIN_DIR . 'admin/views/settings-general.php';
	}

	/**
	 * Load payment settings partial.
	 */
	public static function render_payments_partial() {
		$settings   = Art_LMS_Settings::get_payment();
		$gateway_id = isset( $_GET['gateway'] ) ? sanitize_key( wp_unslash( $_GET['gateway'] ) ) : '';

		if ( $gateway_id ) {
			$gateway = Art_LMS_Payment_Gateway_Registry::get( $gateway_id );

			if ( $gateway ) {
				include ART_LMS_PLUGIN_DIR . 'admin/views/settings-payment-gateway.php';
				return;
			}
		}

		include ART_LMS_PLUGIN_DIR . 'admin/views/settings-payments.php';
	}

	/**
	 * Build admin URL for a single gateway settings page.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return string
	 */
	public static function get_gateway_settings_url( $gateway_id ) {
		return add_query_arg(
			array(
				'page'    => self::PAGE_SETTINGS,
				'tab'     => self::TAB_PAYMENTS,
				'gateway' => sanitize_key( (string) $gateway_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * AJAX: preview purchase email with sample data.
	 */
	public static function ajax_preview_purchase_email() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_send_json_error(
				array( 'message' => __( 'Недостаточно прав.', 'art-lms' ) ),
				403
			);
		}

		check_ajax_referer( 'art_lms_email_settings', 'nonce' );

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body    = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		$email_type = isset( $_POST['email_type'] ) ? sanitize_key( wp_unslash( $_POST['email_type'] ) ) : 'purchase';

		$preview = 'admin_payment' === $email_type
			? Art_LMS_Email::get_admin_payment_email_preview( $subject, $body )
			: Art_LMS_Email::get_order_email_preview( $subject, $body );

		wp_send_json_success( $preview );
	}

	/**
	 * AJAX: send test order email.
	 */
	public static function ajax_send_test_purchase_email() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_send_json_error(
				array( 'message' => __( 'Недостаточно прав.', 'art-lms' ) ),
				403
			);
		}

		check_ajax_referer( 'art_lms_email_settings', 'nonce' );

		$email_type = isset( $_POST['email_type'] ) ? sanitize_key( wp_unslash( $_POST['email_type'] ) ) : 'purchase';
		$subject    = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body       = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		$user       = wp_get_current_user();

		if ( 'admin_payment' === $email_type ) {
			$to = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';
			$result = Art_LMS_Email::send_test_admin_payment_email( $to, $subject, $body );
		} else {
			$to = $user->user_email ? $user->user_email : get_option( 'admin_email' );
			$result = Art_LMS_Email::send_test_purchase_email( $to, $subject, $body );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				400
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: email address */
					__( 'Тестовое письмо отправлено на %s.', 'art-lms' ),
					$to
				),
			)
		);
	}

	/**
	 * AJAX: create plugin page from template and assign it in settings.
	 */
	public static function ajax_create_settings_page() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_send_json_error(
				array( 'message' => __( 'Недостаточно прав.', 'art-lms' ) ),
				403
			);
		}

		check_ajax_referer( 'art_lms_general_settings', 'nonce' );

		$page_type = isset( $_POST['page_type'] ) ? sanitize_key( wp_unslash( $_POST['page_type'] ) ) : '';
		$result    = Art_LMS_Pages::create_and_assign( $page_type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				400
			);
		}

		$message = ! empty( $result['created'] )
			? __( 'Страница создана и выбрана.', 'art-lms' )
			: __( 'Страница уже была создана ранее и выбрана.', 'art-lms' );

		wp_send_json_success(
			array_merge(
				$result,
				array(
					'message' => $message,
				)
			)
		);
	}
}
