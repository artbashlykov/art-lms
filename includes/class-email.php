<?php
/**
 * Email notifications.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Email
 */
class Art_LMS_Email {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'art_lms_order_paid', array( __CLASS__, 'send_purchase_email' ), 10, 2 );
		add_action( 'art_lms_order_paid', array( __CLASS__, 'send_admin_payment_email' ), 11, 2 );
	}

	/**
	 * Send email after successful payment.
	 *
	 * @param int    $order_id Order ID.
	 * @param object $order    Order object.
	 */
	public static function send_purchase_email( $order_id, $order ) {
		$settings = Art_LMS_Settings::get_emails();

		if ( 'yes' !== ( $settings['purchase']['enabled'] ?? 'yes' ) ) {
			return;
		}

		if ( ! is_email( $order->email ) ) {
			return;
		}

		$subject = $settings['purchase']['subject'] ?: Art_LMS_Settings::get_default_purchase_email_subject();
		$body    = $settings['purchase']['body'] ?: Art_LMS_Settings::get_default_purchase_email_body();

		self::send_order_email_to(
			$order->email,
			$order_id,
			$order,
			$subject,
			$body
		);
	}

	/**
	 * Send checkout email verification message.
	 *
	 * @param string $to          Recipient email.
	 * @param string $name         Buyer name.
	 * @param string $verify_url   Verification URL.
	 * @param int    $button_id    Payment button ID.
	 * @return true|WP_Error
	 */
	public static function send_checkout_verification_email( $to, $name, $verify_url, $button_id ) {
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'invalid_email', __( 'Укажите корректный email.', 'art-lms' ) );
		}

		$settings = Art_LMS_Settings::get_emails();
		$template = $settings['email_verification'] ?? Art_LMS_Settings::get_default_emails()['email_verification'];

		if ( 'yes' !== ( $template['enabled'] ?? 'yes' ) ) {
			return new WP_Error(
				'verification_email_disabled',
				__( 'Отправка письма подтверждения отключена в настройках.', 'art-lms' )
			);
		}

		$subject = $template['subject'] ?: Art_LMS_Settings::get_default_email_verification_subject();
		$body    = $template['body'] ?: Art_LMS_Settings::get_default_email_verification_body();
		$tokens  = self::build_email_verification_tokens( $to, $name, $verify_url, $button_id );
		$subject = str_replace( array_keys( $tokens ), array_values( $tokens ), (string) $subject );
		$message = str_replace( array_keys( $tokens ), array_values( $tokens ), (string) $body );
		$sender  = Art_LMS_Settings::get_email_sender();
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $sender['email_from_name'] . ' <' . $sender['email_from'] . '>',
		);

		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( ! $sent ) {
			return new WP_Error(
				'send_failed',
				__( 'Не удалось отправить письмо подтверждения. Попробуйте позже.', 'art-lms' )
			);
		}

		return true;
	}

	/**
	 * Build placeholder values for checkout email verification.
	 *
	 * @param string $email      Buyer email.
	 * @param string $name       Buyer name.
	 * @param string $verify_url Verification URL.
	 * @param int    $button_id  Payment button ID.
	 * @return array<string, string>
	 */
	private static function build_email_verification_tokens( $email, $name, $verify_url, $button_id ) {
		$display_name = trim( (string) $name );

		if ( '' === $display_name ) {
			$display_name = $email;
		}

		return array(
			'{имя}'   => $display_name,
			'{email}' => (string) $email,
			'{товар}' => Art_LMS_Payment_Buttons::get_product_name( absint( $button_id ) ),
			'{ссылка}' => esc_url_raw( $verify_url ),
			'{сайт}'  => get_bloginfo( 'name' ),
		);
	}

	/**
	 * Send admin notification about successful payment.
	 *
	 * @param int    $order_id Order ID.
	 * @param object $order    Order object.
	 */
	public static function send_admin_payment_email( $order_id, $order ) {
		$settings = Art_LMS_Settings::get_emails();
		$template = $settings['admin_payment'] ?? Art_LMS_Settings::get_default_emails()['admin_payment'];

		if ( 'yes' !== ( $template['enabled'] ?? 'yes' ) ) {
			return;
		}

		$recipient = sanitize_email( $template['recipient'] ?? '' );

		if ( ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		if ( ! is_email( $recipient ) ) {
			return;
		}

		$subject = $template['subject'] ?: Art_LMS_Settings::get_default_admin_payment_email_subject();
		$body    = $template['body'] ?: Art_LMS_Settings::get_default_admin_payment_email_body();

		self::send_order_email_to(
			$recipient,
			$order_id,
			$order,
			$subject,
			$body,
			false,
			'admin_payment'
		);
	}

	/**
	 * Send a test purchase email to the current admin.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Email subject template.
	 * @param string $body    Email body template.
	 * @return true|WP_Error
	 */
	public static function send_test_purchase_email( $to, $subject, $body ) {
		return self::send_test_order_email( $to, $subject, $body );
	}

	/**
	 * Send a test admin payment email.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Email subject template.
	 * @param string $body    Email body template.
	 * @return true|WP_Error
	 */
	public static function send_test_admin_payment_email( $to, $subject, $body ) {
		return self::send_test_order_email( $to, $subject, $body, 'admin_payment' );
	}

	/**
	 * Send a test order email with sample data.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Email subject template.
	 * @param string $body    Email body template.
	 * @param string $context Email context.
	 * @return true|WP_Error
	 */
	private static function send_test_order_email( $to, $subject, $body, $context = 'purchase' ) {
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'invalid_email', __( 'Укажите корректный email.', 'art-lms' ) );
		}

		$sample_order = self::get_sample_order();
		$sent         = self::send_order_email_to( $to, 12345, $sample_order, $subject, $body, true, $context );

		if ( ! $sent ) {
			return new WP_Error( 'send_failed', __( 'Не удалось отправить письмо. Проверьте настройки почты WordPress.', 'art-lms' ) );
		}

		return true;
	}

	/**
	 * Build preview data for order email templates.
	 *
	 * @param string $subject Subject template.
	 * @param string $body    Body template.
	 * @return array{subject: string, body: string}
	 */
	public static function get_order_email_preview( $subject, $body, $context = 'purchase' ) {
		$sample_order = self::get_sample_order();

		return array(
			'subject' => self::apply_email_tokens( $subject, 12345, $sample_order, $context ),
			'body'    => self::apply_email_tokens( $body, 12345, $sample_order, $context ),
		);
	}

	/**
	 * Build preview data for admin payment email templates.
	 *
	 * @param string $subject Subject template.
	 * @param string $body    Body template.
	 * @return array{subject: string, body: string}
	 */
	public static function get_admin_payment_email_preview( $subject, $body ) {
		return self::get_order_email_preview( $subject, $body, 'admin_payment' );
	}

	/**
	 * Build preview data for purchase email templates.
	 *
	 * @param string $subject Subject template.
	 * @param string $body    Body template.
	 * @return array{subject: string, body: string}
	 */
	public static function get_purchase_email_preview( $subject, $body ) {
		return self::get_order_email_preview( $subject, $body );
	}

	/**
	 * Send order email using configured templates.
	 *
	 * @param string $to         Recipient email.
	 * @param int    $order_id   Order ID.
	 * @param object $order      Order object.
	 * @param string $subject    Subject template.
	 * @param string $body       Body template.
	 * @param string $body       Body template.
	 * @param bool   $is_test    Whether this is a test send.
	 * @param string $context    Email context: purchase or admin_payment.
	 * @return bool
	 */
	private static function send_order_email_to( $to, $order_id, $order, $subject, $body, $is_test = false, $context = 'purchase' ) {
		$subject = self::apply_email_tokens( $subject, $order_id, $order, $context );
		$message = self::apply_email_tokens( $body, $order_id, $order, $context );

		if ( $is_test ) {
			$subject = '[TEST] ' . $subject;
		}

		$sender  = Art_LMS_Settings::get_email_sender();
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $sender['email_from_name'] . ' <' . $sender['email_from'] . '>',
		);

		return wp_mail( $to, $subject, self::wrap_html_email( $message ), $headers );
	}

	/**
	 * Replace purchase email placeholders.
	 *
	 * @param string $text     Template text.
	 * @param int    $order_id Order ID.
	 * @param object $order    Order object.
	 * @return string
	 */
	public static function apply_purchase_email_tokens( $text, $order_id, $order ) {
		return self::apply_email_tokens( $text, $order_id, $order, 'purchase' );
	}

	/**
	 * Replace email placeholders.
	 *
	 * @param string $text     Template text.
	 * @param int    $order_id Order ID.
	 * @param object $order    Order object.
	 * @param string $context  Email context.
	 * @return string
	 */
	public static function apply_email_tokens( $text, $order_id, $order, $context = 'purchase' ) {
		$tokens = self::build_purchase_email_tokens( $order_id, $order );

		if ( 'admin_payment' === $context ) {
			$tokens['{all-fields}'] = esc_html( Art_LMS_Order_Form_Data::format_for_email( $order ) );
		}

		return str_replace( array_keys( $tokens ), array_values( $tokens ), (string) $text );
	}

	/**
	 * Build placeholder values for a purchase email.
	 *
	 * @param int    $order_id Order ID.
	 * @param object $order    Order object.
	 * @return array<string, string>
	 */
	public static function build_purchase_email_tokens( $order_id, $order ) {
		$account_url = Art_LMS_Settings::get_account_url() ?: Art_LMS_Settings::get_login_page_url();
		$name        = ! empty( $order->name ) ? $order->name : $order->email;
		$email       = (string) $order->email;
		$user_id     = absint( $order->user_id ?? 0 );
		$user        = $user_id ? get_userdata( $user_id ) : null;

		$set_password_url = $user
			? Art_LMS_Account::get_set_password_url( $user, $account_url, $order_id )
			: wp_lostpassword_url( $account_url );

		return array(
			'{имя}'               => esc_html( $name ),
			'{email}'             => esc_html( $email ),
			'{номер_заказа}'      => esc_html( (string) absint( $order_id ) ),
			'{сумма}'             => esc_html( self::format_order_amount( $order ) ),
			'{платежный_шлюз}'    => esc_html( Art_LMS_Orders::get_payment_gateway_label( $order ) ),
			'{товар}'             => esc_html( self::get_order_product_title( $order ) ),
			'{кабинет}'           => esc_url( $account_url ),
			'{войти}'             => self::build_email_link( $account_url, __( 'Войти', 'art-lms' ) ),
			'{логин}'             => esc_html( $email ),
			'{установить_пароль}' => self::build_set_password_email_link( $set_password_url ),
			'{материалы}'         => self::get_order_materials_html( $order ),
			'{сайт}'              => esc_html( get_bloginfo( 'name' ) ),
			'{заказ}'             => esc_url( Art_LMS_Admin_Orders::get_edit_url( absint( $order_id ) ) ),
		);
	}

	/**
	 * Build an HTML anchor for the set-password flow.
	 *
	 * @param string $url Reset URL.
	 * @return string
	 */
	private static function build_set_password_email_link( $url ) {
		return self::build_email_link(
			$url,
			__( 'Нажмите здесь, чтобы установить пароль', 'art-lms' )
		);
	}

	/**
	 * Build an HTML email anchor.
	 *
	 * @param string $url   Target URL.
	 * @param string $label Link label.
	 * @return string
	 */
	private static function build_email_link( $url, $label ) {
		$url = esc_url( $url );

		if ( '' === $url ) {
			return esc_html( $label );
		}

		return '<a href="' . $url . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Wrap tokenized email body in a minimal HTML document.
	 *
	 * @param string $body Email body after placeholder replacement.
	 * @return string
	 */
	private static function wrap_html_email( $body ) {
		$body = (string) $body;

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;line-height:1.6;color:#1d2327;">'
			. nl2br( $body, false )
			. '</body></html>';
	}

	/**
	 * Build HTML list of materials for an order.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	private static function get_order_materials_html( $order ) {
		$button_id = absint( $order->product_id ?? 0 );

		if ( ! $button_id ) {
			return '—';
		}

		$meta  = Art_LMS_Payment_Buttons::get_meta( $button_id );
		$lines = array();

		foreach ( $meta['material_ids'] as $material_id ) {
			$material = get_post( $material_id );

			if ( ! $material || Art_LMS_Materials::POST_TYPE !== $material->post_type ) {
				continue;
			}

			$title = get_the_title( $material_id );
			$url   = Art_LMS_Materials::get_url( $material_id );

			if ( ! $title || ! $url ) {
				continue;
			}

			$lines[] = '- ' . self::build_email_link( $url, $title );
		}

		if ( empty( $lines ) ) {
			return '—';
		}

		return implode( '<br>', $lines );
	}

	/**
	 * Get payment button title for order.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	private static function get_order_product_title( $order ) {
		$button_id = absint( $order->product_id ?? 0 );

		if ( ! $button_id ) {
			return '—';
		}

		$title = get_the_title( $button_id );

		return $title ? $title : '—';
	}

	/**
	 * Format order amount with currency.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	private static function format_order_amount( $order ) {
		$amount   = number_format( (float) ( $order->amount ?? 0 ), 2, '.', ' ' );
		$currency = strtoupper( (string) ( $order->currency ?? 'RUB' ) );

		if ( 'RUB' === $currency ) {
			return $amount . ' ₽';
		}

		return trim( $amount . ' ' . $currency );
	}

	/**
	 * Sample order object for preview and test emails.
	 *
	 * @return object
	 */
	private static function get_sample_order() {
		$button_id = 0;
		$buttons   = get_posts(
			array(
				'post_type'      => Art_LMS_Payment_Buttons::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $buttons ) ) {
			$button_id = (int) $buttons[0];
		}

		$user = wp_get_current_user();
		$name = $user->display_name ? $user->display_name : __( 'Иван Иванов', 'art-lms' );
		$email = $user->user_email ? $user->user_email : get_option( 'admin_email' );

		$sample_gateway_id = Art_LMS_Settings::get_default_checkout_gateway();

		if ( '' === $sample_gateway_id ) {
			$enabled_gateways = Art_LMS_Settings::get_checkout_payment_methods();

			if ( ! empty( $enabled_gateways ) ) {
				$sample_gateway_id = (string) ( $enabled_gateways[0]['id'] ?? '' );
			}
		}

		return (object) array(
			'email'           => $email,
			'name'            => $name,
			'phone'           => '+7 900 000-00-00',
			'amount'          => 1990,
			'currency'        => 'RUB',
			'product_id'      => $button_id,
			'payment_gateway' => $sample_gateway_id,
			'form_data'  => Art_LMS_Order_Form_Data::encode(
				array(
					'fields' => array(
						array(
							'key'   => 'full_name',
							'label' => __( 'ФИО', 'art-lms' ),
							'value' => $name,
							'type'  => 'field',
						),
						array(
							'key'   => 'email',
							'label' => __( 'Почта', 'art-lms' ),
							'value' => $email,
							'type'  => 'field',
						),
						array(
							'key'   => 'phone',
							'label' => __( 'Телефон', 'art-lms' ),
							'value' => '+7 900 000-00-00',
							'type'  => 'field',
						),
						array(
							'key'   => 'custom_telegram',
							'label' => 'Telegram',
							'value' => '@example',
							'type'  => 'field',
						),
						array(
							'key'   => 'privacy',
							'label' => __( 'Политика конфиденциальности', 'art-lms' ),
							'value' => __( 'Да', 'art-lms' ),
							'type'  => 'consent',
						),
					),
				)
			),
		);
	}
}
