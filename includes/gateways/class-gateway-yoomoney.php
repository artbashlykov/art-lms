<?php
/**
 * YooMoney wallet payment gateway.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Gateway_Yoomoney
 */
class Art_LMS_Gateway_Yoomoney extends Art_LMS_Payment_Gateway {

	/**
	 * Gateway ID.
	 */
	const ID = 'yoomoney';

	/**
	 * QuickPay endpoint.
	 */
	const QUICKPAY_URL = 'https://yoomoney.ru/quickpay/confirm';

	/**
	 * @return string
	 */
	public function get_id() {
		return self::ID;
	}

	/**
	 * @return array{id: string, title: string, description: string}
	 */
	public function get_meta() {
		return array(
			'id'          => self::ID,
			'title'       => __( 'ЮMoney (кошелёк)', 'art-lms' ),
			'description' => __( 'Приём платежей на личный кошелёк ЮMoney для физлиц.', 'art-lms' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_default_settings() {
		return array(
			'enabled'             => 'no',
			'display_name'        => '',
			'wallet'              => '',
			'notification_secret' => '',
		);
	}

	/**
	 * @param array $input    Submitted settings.
	 * @param array $existing Stored settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input, array $existing ) {
		$settings = wp_parse_args( $existing, $this->get_default_settings() );
		$secret           = isset( $input['notification_secret'] ) ? sanitize_text_field( $input['notification_secret'] ) : '';
		$existing_secret  = $settings['notification_secret'] ?? '';

		// Admin UI shows masked value (***). If user didn't change it, keep existing secret.
		if ( '' === $secret || preg_match( '/^\*+$/', trim( $secret ) ) ) {
			$secret = $existing_secret;
		}

		$settings = parent::sanitize_settings( $input, $existing );

		$settings['wallet']              = isset( $input['wallet'] ) ? sanitize_text_field( $input['wallet'] ) : ( $settings['wallet'] ?? '' );
		$settings['notification_secret'] = $secret;
		unset( $settings['default_payment_method'] );

		return $settings;
	}

	/**
	 * Register webhook routes.
	 */
	public function register_webhook_routes() {
		register_rest_route(
			'art-lms/v1',
			$this->get_webhook_route(),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @param object $order Order object.
	 */
	public function render_payment_redirect( $order ) {
		$fields = $this->get_quickpay_fields( $order );
		$meta   = $this->get_meta();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<title><?php esc_html_e( 'Переход к оплате…', 'art-lms' ); ?></title>
		</head>
		<body>
			<p>
				<?php
				printf(
					/* translators: %s: payment gateway title */
					esc_html__( 'Переход к оплате через %s…', 'art-lms' ),
					esc_html( $meta['title'] )
				);
				?>
			</p>
			<form id="art-lms-quickpay-form" method="POST" action="<?php echo esc_url( self::QUICKPAY_URL ); ?>">
				<?php foreach ( $fields as $key => $value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<?php endforeach; ?>
				<noscript>
					<button type="submit"><?php esc_html_e( 'Перейти к оплате', 'art-lms' ); ?></button>
				</noscript>
			</form>
			<script>document.getElementById('art-lms-quickpay-form').submit();</script>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Build QuickPay form fields for an order.
	 *
	 * @param object $order Order object.
	 * @return array<string, string>
	 */
	public function get_quickpay_fields( $order ) {
		$general  = Art_LMS_Settings::get_general();
		$settings = Art_LMS_Settings::get_gateway( self::ID );

		$success_url = ! empty( $general['success_page_id'] )
			? get_permalink( (int) $general['success_page_id'] )
			: home_url( '/' );

		$success_url = add_query_arg(
			array(
				'art_lms_order' => $order->order_key,
			),
			$success_url
		);

		return array(
			'receiver'      => sanitize_text_field( $settings['wallet'] ?? '' ),
			'quickpay-form' => 'button',
			'targets'       => 'art_lms_payment',
			'paymentType'   => 'AC',
			'sum'           => number_format( (float) $order->amount, 2, '.', '' ),
			'label'         => $this->get_order_payment_reference( $order ),
			'successURL'    => esc_url_raw( $success_url ),
		);
	}

	/**
	 * Handle provider HTTP notification.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$params = $this->get_notification_params( $request );
		$secret = Art_LMS_Settings::get_gateway( self::ID )['notification_secret'] ?? '';

		$is_test_candidate = empty( $params['label'] ?? '' )
			&& empty( $params['amount'] ?? '' )
			&& empty( $params['operation_id'] ?? '' )
			&& empty( $params['currency'] ?? '' )
			&& count( $params ) <= 2; // usually only `sign` (and sometimes `test_notification`).

		if ( ! $this->verify_notification_sign( $params, $secret ) ) {
			$this->record_webhook_test(
				false,
				$is_test_candidate
					? __( 'Неверная подпись. Проверьте секрет HTTP-уведомлений.', 'art-lms' )
					: __( 'Получено уведомление об оплате с неверной подписью. Проверьте секрет HTTP-уведомлений.', 'art-lms' )
			);
			$this->log_webhook_debug( 'invalid_signature', $params );

			return new WP_REST_Response( 'Invalid signature', 403 );
		}

		$is_test = ! empty( $params['test_notification'] )
			|| empty( $params['label'] ?? '' )
			&& empty( $params['amount'] ?? '' )
			&& empty( $params['operation_id'] ?? '' );

		$label = isset( $params['label'] ) ? sanitize_text_field( $params['label'] ) : '';

		// YooMoney “Test” notifications may come with empty payload.
		if ( $is_test && empty( $label ) ) {
			$this->record_webhook_test( true, __( 'Тестовое уведомление получено.', 'art-lms' ) );

			return new WP_REST_Response( 'OK', 200 );
		}

		if ( empty( $label ) ) {
			return new WP_REST_Response( 'Missing label', 400 );
		}

		$order = $this->find_order_from_notification_labels( array( $label ) );

		if ( ! $order ) {
			$this->record_webhook_test(
				false,
				sprintf(
					/* translators: %s: payment label from provider */
					__( 'Заказ с меткой %s не найден.', 'art-lms' ),
					$label
				)
			);
			$this->log_webhook_debug( 'order_not_found', $params );

			return new WP_REST_Response( 'Order not found', 404 );
		}

		if ( Art_LMS_Orders::STATUS_PAID === $order->status ) {
			return new WP_REST_Response( 'OK', 200 );
		}

		$amount   = $this->get_notification_amount( $params );
		$currency = isset( $params['currency'] ) ? sanitize_text_field( $params['currency'] ) : '';

		if ( abs( $amount - (float) $order->amount ) > 0.01 ) {
			$this->record_webhook_test(
				false,
				sprintf(
					/* translators: 1: received amount, 2: order amount */
					__( 'Сумма в уведомлении (%1$s) не совпала с заказом (%2$s).', 'art-lms' ),
					number_format( $amount, 2, '.', '' ),
					number_format( (float) $order->amount, 2, '.', '' )
				)
			);
			$this->log_webhook_debug( 'amount_mismatch', $params );

			return new WP_REST_Response( 'Amount mismatch', 400 );
		}

		if ( '643' !== $currency && 'RUB' !== strtoupper( $currency ) ) {
			return new WP_REST_Response( 'Invalid currency', 400 );
		}

		$transaction_id = isset( $params['operation_id'] ) ? sanitize_text_field( $params['operation_id'] ) : '';

		if ( ! empty( $transaction_id ) && Art_LMS_Orders::is_transaction_id_used( $transaction_id, (int) $order->id ) ) {
			return new WP_REST_Response( 'Duplicate operation', 409 );
		}

		$marked = Art_LMS_Orders::mark_paid(
			(int) $order->id,
			array(
				'transaction_id'  => $transaction_id,
				'payment_method'  => isset( $params['payment_type'] ) ? sanitize_text_field( $params['payment_type'] ) : '',
				'raw'             => $params,
			)
		);

		if ( ! $marked ) {
			$this->log_webhook_debug( 'mark_paid_failed', $params );

			return new WP_REST_Response( 'Unable to mark paid', 500 );
		}

		$this->record_webhook_test( true, __( 'Оплата подтверждена по HTTP-уведомлению.', 'art-lms' ) );

		do_action( 'art_lms_payment_confirmed', (int) $order->id, $params );

		return new WP_REST_Response( 'OK', 200 );
	}

	/**
	 * Normalize notification payload from REST request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array<string, mixed>
	 */
	protected function get_notification_params( WP_REST_Request $request ) {
		$params = $request->get_body_params();

		if ( ! is_array( $params ) || array() === $params ) {
			$params = $request->get_params();
		}

		if ( ( ! is_array( $params ) || array() === $params ) && ! empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- YooMoney server callback; verified via notification hash.
			$params = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- YooMoney server callback; verified via notification hash.
		}

		return is_array( $params ) ? $params : array();
	}

	/**
	 * Resolve paid amount from provider notification.
	 *
	 * @param array $params Notification parameters.
	 * @return float
	 */
	protected function get_notification_amount( array $params ) {
		if ( isset( $params['withdraw_amount'] ) && '' !== (string) $params['withdraw_amount'] ) {
			return (float) $params['withdraw_amount'];
		}

		return isset( $params['amount'] ) ? (float) $params['amount'] : 0.0;
	}

	/**
	 * Write webhook diagnostics to debug.log when enabled.
	 *
	 * @param string               $event  Event slug.
	 * @param array<string, mixed> $params Notification payload.
	 */
	protected function log_webhook_debug( $event, array $params ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$safe_params = $params;
		unset( $safe_params['sign'] );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'ART LMS YooMoney webhook [%s]: %s',
				sanitize_key( (string) $event ),
				wp_json_encode( $safe_params )
			)
		);
	}

	/**
	 * Verify notification signature.
	 *
	 * @param array  $params Notification parameters.
	 * @param string $secret Notification secret.
	 * @return bool
	 */
	public function verify_notification_sign( $params, $secret ) {
		if ( empty( $params['sign'] ) || empty( $secret ) ) {
			return false;
		}

		$received_sign = $params['sign'];
		unset( $params['sign'] );

		ksort( $params );

		$parts = array();
		foreach ( $params as $key => $value ) {
			$parts[] = $key . '=' . rawurlencode( (string) $value );
		}

		$string = implode( '&', $parts );
		$hash   = hash_hmac( 'sha256', $string, $secret );

		return hash_equals( strtolower( $hash ), strtolower( (string) $received_sign ) );
	}

	/**
	 * YooMoney HTTP notifications settings page.
	 */
	const HTTP_NOTIFICATIONS_URL = 'https://yoomoney.ru/transfer/myservices/http-notification';

	/**
	 * @return string
	 */
	protected function get_webhook_test_settings_url() {
		return self::HTTP_NOTIFICATIONS_URL;
	}

	/**
	 * @return string
	 */
	protected function get_webhook_test_success_message() {
		return __( 'Тестовое уведомление от ЮMoney получено. URL доступен, секрет настроен корректно.', 'art-lms' );
	}

	/**
	 * Render pending-state hint with a link to YooMoney HTTP notifications settings.
	 */
	protected function render_webhook_test_pending_message() {
		$url = $this->get_webhook_test_settings_url();

		echo esc_html__( 'Нажмите «Протестировать» в ', 'art-lms' );
		echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">';
		echo esc_html__( 'настройках HTTP-уведомлений ЮMoney', 'art-lms' );
		echo '</a>';
		echo esc_html__( ' и затем обновите эту страницу — результат доставляемости уведомления появится в этом блоке.', 'art-lms' );
	}

	/**
	 * @param string $option_name Option key prefix.
	 * @param array  $settings    Stored gateway settings.
	 */
	public function render_admin_settings( $option_name, array $settings ) {
		$settings    = wp_parse_args( $settings, $this->get_default_settings() );
		$webhook_url = $this->get_webhook_url();
		$secret_raw    = (string) ( $settings['notification_secret'] ?? '' );
		$secret_masked = '' === $secret_raw ? '' : str_repeat( '*', (int) min( strlen( $secret_raw ), 64 ) );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="yoomoney_wallet"><?php esc_html_e( 'Номер кошелька', 'art-lms' ); ?></label></th>
				<td>
					<input type="text" id="yoomoney_wallet" name="<?php echo esc_attr( $option_name ); ?>[gateways][<?php echo esc_attr( self::ID ); ?>][wallet]" value="<?php echo esc_attr( $settings['wallet'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="notification_secret"><?php esc_html_e( 'Секрет HTTP-уведомлений', 'art-lms' ); ?></label></th>
				<td>
					<input
						type="password"
						id="notification_secret"
						name="<?php echo esc_attr( $option_name ); ?>[gateways][<?php echo esc_attr( self::ID ); ?>][notification_secret]"
						value="<?php echo esc_attr( $secret_masked ); ?>"
						class="regular-text"
						autocomplete="new-password"
						placeholder="<?php echo esc_attr( ! empty( $settings['notification_secret'] ) ? __( 'Секрет сохранён — измените чтобы заменить', 'art-lms' ) : '' ); ?>"
					>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'URL для HTTP-уведомлений', 'art-lms' ); ?>
					<a href="<?php echo esc_url( self::HTTP_NOTIFICATIONS_URL ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Берем его тут', 'art-lms' ); ?>
					</a>
				</th>
				<td>
					<div class="art-lms-gateway-webhook-url-row">
						<input
							type="text"
							id="art-lms-yoomoney-webhook-url"
							readonly
							value="<?php echo esc_url( $webhook_url ); ?>"
							class="regular-text"
							onclick="this.select();"
						>
						<button
							type="button"
							class="button art-lms-gateway-webhook-url-row__copy"
							id="art-lms-copy-yoomoney-webhook"
							data-copy-target="art-lms-yoomoney-webhook-url"
						>
							<?php esc_html_e( 'Скопировать', 'art-lms' ); ?>
						</button>
					</div>
					<?php $this->render_webhook_test_status(); ?>
					<script>
						(function () {
							var btn = document.getElementById('art-lms-copy-yoomoney-webhook');
							var input = document.getElementById(btn ? btn.getAttribute('data-copy-target') : '');
							if (!btn || !input) return;

							btn.addEventListener('click', async function () {
								var text = input.value || '';
								try {
									if (navigator.clipboard && navigator.clipboard.writeText) {
										await navigator.clipboard.writeText(text);
									} else {
										input.focus();
										input.select();
										document.execCommand('copy');
									}
									var old = btn.textContent;
									btn.textContent = '<?php echo esc_js( __( 'Скопировано', 'art-lms' ) ); ?>';
									setTimeout(function(){ btn.textContent = old; }, 1200);
								} catch (e) {
									// No-op: fallback copy above handles most cases.
								}
							});
						})();
					</script>
				</td>
			</tr>
		</table>
		<?php
	}
}
