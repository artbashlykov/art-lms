<?php
/**
 * YooKassa payment gateway.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

require_once ART_LMS_PLUGIN_DIR . 'includes/gateways/yookassa/class-yookassa-api.php';
require_once ART_LMS_PLUGIN_DIR . 'includes/gateways/yookassa/class-yookassa-receipt.php';

/**
 * Class Art_LMS_Gateway_Yookassa
 */
class Art_LMS_Gateway_Yookassa extends Art_LMS_Payment_Gateway {

	/**
	 * Gateway ID.
	 */
	const ID = 'yookassa';

	/**
	 * Merchant webhook settings in YooKassa cabinet.
	 */
	const WEBHOOK_SETTINGS_URL = 'https://yookassa.ru/my/merchant/integration/http-notifications';

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
			'title'       => __( 'ЮKassa', 'art-lms' ),
			'description' => __( 'Приём платежей через ЮKassa: банковские карты, СБП, ЮMoney и другие способы. Для ИП, самозанятых и юрлиц.', 'art-lms' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_default_settings() {
		return array(
			'enabled'                 => 'no',
			'display_name'            => '',
			'shop_id'                 => '',
			'secret_key'              => '',
			'receipts_enabled'        => 'no',
			'receipt_vat_code'        => 1,
			'receipt_payment_subject' => 'service',
			'receipt_payment_mode'    => 'full_payment',
			'receipt_tax_system_code' => '',
		);
	}

	/**
	 * @param array $input    Submitted settings.
	 * @param array $existing Stored settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input, array $existing ) {
		$settings     = wp_parse_args( $existing, $this->get_default_settings() );
		$secret       = isset( $input['secret_key'] ) ? sanitize_text_field( $input['secret_key'] ) : '';
		$existing_key = $settings['secret_key'] ?? '';

		if ( '' === $secret || preg_match( '/^\*+$/', trim( $secret ) ) ) {
			$secret = $existing_key;
		}

		$settings = parent::sanitize_settings( $input, $existing );

		$settings['shop_id']    = isset( $input['shop_id'] ) ? sanitize_text_field( $input['shop_id'] ) : ( $settings['shop_id'] ?? '' );
		$settings['secret_key'] = $secret;
		unset( $settings['test_mode'] );

		if ( isset( $input['shop_id'] ) || isset( $input['secret_key'] ) || isset( $input['receipt_vat_code'] ) || array_key_exists( 'receipts_enabled', $input ) ) {
			$settings['receipts_enabled'] = ! empty( $input['receipts_enabled'] ) ? 'yes' : 'no';
		}

		if ( isset( $input['receipt_vat_code'] ) ) {
			$settings['receipt_vat_code'] = Art_LMS_Yookassa_Receipt::sanitize_vat_code( $input['receipt_vat_code'] );
		}

		if ( isset( $input['receipt_payment_subject'] ) ) {
			$settings['receipt_payment_subject'] = Art_LMS_Yookassa_Receipt::sanitize_payment_subject( $input['receipt_payment_subject'] );
		}

		if ( isset( $input['receipt_payment_mode'] ) ) {
			$settings['receipt_payment_mode'] = Art_LMS_Yookassa_Receipt::sanitize_payment_mode( $input['receipt_payment_mode'] );
		}

		if ( isset( $input['receipt_tax_system_code'] ) ) {
			$settings['receipt_tax_system_code'] = (string) Art_LMS_Yookassa_Receipt::sanitize_tax_system_code( $input['receipt_tax_system_code'] );
		}

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
		$redirect_url = $this->get_payment_redirect_url( $order );

		if ( is_wp_error( $redirect_url ) ) {
			wp_die(
				esc_html( $redirect_url->get_error_message() ),
				'',
				array( 'response' => 500 )
			);
		}

		$redirect_url = esc_url_raw( (string) $redirect_url );

		if ( ! wp_http_validate_url( $redirect_url ) ) {
			wp_die(
				esc_html__( 'ЮKassa: некорректный URL платёжной страницы.', 'art-lms' ),
				'',
				array( 'response' => 500 )
			);
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirect to external YooKassa payment page.
		wp_redirect( $redirect_url, 302, 'ART LMS YooKassa' );
		exit;
	}

	/**
	 * Create a YooKassa payment and return confirmation URL.
	 *
	 * @param object $order Order object.
	 * @return string|WP_Error
	 */
	public function get_payment_redirect_url( $order ) {
		$api = $this->get_api_client();

		if ( ! $api->is_configured() ) {
			return new WP_Error( 'yookassa_misconfigured', __( 'ЮKassa: укажите shopId и секретный ключ в настройках шлюза.', 'art-lms' ) );
		}

		$reference   = $this->get_order_payment_reference( $order );
		$description = $this->get_payment_description( $order );
		$amount      = number_format( (float) $order->amount, 2, '.', '' );
		$currency    = strtoupper( (string) ( $order->currency ?? 'RUB' ) );

		if ( 'RUB' !== $currency ) {
			return new WP_Error( 'yookassa_currency', __( 'ЮKassa: поддерживается только валюта RUB.', 'art-lms' ) );
		}

		$payload = array(
			'amount'       => array(
				'value'    => $amount,
				'currency' => 'RUB',
			),
			'capture'      => true,
			'confirmation' => array(
				'type'       => 'redirect',
				'return_url' => esc_url_raw( Art_LMS_Checkout::get_order_success_url( $order ) ),
			),
			'description'  => $description,
			'metadata'     => array(
				'order_reference' => $reference,
				'order_id'        => (string) absint( $order->id ?? 0 ),
				'cms'             => 'art_lms',
			),
		);

		$gateway_settings = Art_LMS_Settings::get_gateway( self::ID );
		$receipt          = Art_LMS_Yookassa_Receipt::build_from_order( $order, $gateway_settings, $description );

		if ( is_wp_error( $receipt ) ) {
			return $receipt;
		}

		if ( is_array( $receipt ) ) {
			$payload['receipt'] = $receipt;
		}

		$response = $api->create_payment( $payload, $reference );

		if ( is_wp_error( $response ) ) {
			$this->log_webhook_debug( 'create_payment_failed', array( 'message' => $response->get_error_message() ) );

			return $response;
		}

		$payment_id = sanitize_text_field( (string) ( $response['id'] ?? '' ) );

		if ( '' !== $payment_id ) {
			Art_LMS_Orders::update(
				(int) $order->id,
				array(
					'gateway_transaction_id' => $payment_id,
				)
			);
		}

		$confirmation_url = (string) ( $response['confirmation']['confirmation_url'] ?? '' );

		if ( '' === $confirmation_url || ! wp_http_validate_url( $confirmation_url ) ) {
			return new WP_Error( 'yookassa_no_confirmation', __( 'ЮKassa: не получен URL для оплаты.', 'art-lms' ) );
		}

		return $confirmation_url;
	}

	/**
	 * Handle YooKassa HTTP notification.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$payload = $this->get_notification_payload( $request );

		if ( empty( $payload ) ) {
			return new WP_REST_Response( array( 'error' => 'empty_body' ), 400 );
		}

		$event  = sanitize_key( (string) ( $payload['event'] ?? '' ) );
		$object = is_array( $payload['object'] ?? null ) ? $payload['object'] : array();

		if ( '' === $event || empty( $object ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_payload' ), 400 );
		}

		if ( 'payment.succeeded' !== $event ) {
			return new WP_REST_Response( array( 'status' => 'ignored' ), 200 );
		}

		$payment_id = sanitize_text_field( (string) ( $object['id'] ?? '' ) );

		if ( '' === $payment_id ) {
			return new WP_REST_Response( array( 'error' => 'missing_payment_id' ), 400 );
		}

		$verified = $this->verify_payment_notification( $payment_id, $object );

		if ( is_wp_error( $verified ) ) {
			$this->record_webhook_test( false, $verified->get_error_message() );
			$this->log_webhook_debug( 'verification_failed', $payload );

			$status = (int) ( $verified->get_error_data()['status'] ?? 400 );

			return new WP_REST_Response( array( 'error' => $verified->get_error_code() ), $status > 0 ? $status : 400 );
		}

		$metadata = is_array( $verified['metadata'] ?? null ) ? $verified['metadata'] : array();
		$order    = $this->resolve_order_from_notification( $payment_id, $metadata );

		if ( ! $order && ! empty( $verified['test'] ) ) {
			$this->record_webhook_test( true, __( 'Тестовое уведомление ЮKassa получено.', 'art-lms' ) );

			return new WP_REST_Response( array( 'status' => 'test_ok' ), 200 );
		}

		if ( ! $order ) {
			$this->record_webhook_test(
				false,
				sprintf(
					/* translators: %s: payment ID */
					__( 'Заказ для платежа %s не найден.', 'art-lms' ),
					$payment_id
				)
			);
			$this->log_webhook_debug( 'order_not_found', $payload );

			return new WP_REST_Response( array( 'error' => 'order_not_found' ), 404 );
		}

		if ( Art_LMS_Orders::STATUS_PAID === $order->status ) {
			return new WP_REST_Response( array( 'status' => 'already_paid' ), 200 );
		}

		$result = $this->mark_order_paid_from_provider( $order, $verified, $payload );

		if ( is_wp_error( $result ) ) {
			$this->record_webhook_test( false, $result->get_error_message() );
			$this->log_webhook_debug( $result->get_error_code(), $payload );

			$status = (int) ( $result->get_error_data()['status'] ?? 400 );

			return new WP_REST_Response( array( 'error' => $result->get_error_code() ), $status > 0 ? $status : 400 );
		}

		$this->record_webhook_test( true, __( 'Оплата подтверждена по уведомлению ЮKassa.', 'art-lms' ) );

		do_action( 'art_lms_payment_confirmed', (int) $order->id, $payload );

		return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/**
	 * Sync payment status via YooKassa API when webhook is delayed (success page polling).
	 *
	 * @param object $order Order object.
	 */
	public function maybe_sync_order_payment( $order ) {
		if ( ! is_object( $order ) || Art_LMS_Orders::STATUS_PENDING !== ( $order->status ?? '' ) ) {
			return;
		}

		if ( self::ID !== Art_LMS_Orders::get_payment_gateway_slug( $order ) ) {
			return;
		}

		$payment_id = sanitize_text_field( (string) ( $order->gateway_transaction_id ?? '' ) );

		if ( '' === $payment_id ) {
			return;
		}

		$verified = $this->verify_payment_notification( $payment_id, array( 'id' => $payment_id ) );

		if ( is_wp_error( $verified ) || 'succeeded' !== ( $verified['status'] ?? '' ) ) {
			return;
		}

		$result = $this->mark_order_paid_from_provider( $order, $verified );

		if ( ! is_wp_error( $result ) ) {
			do_action( 'art_lms_payment_confirmed', (int) $order->id, $verified );
		}
	}

	/**
	 * Resolve order from webhook metadata and stored payment ID.
	 *
	 * @param string              $payment_id Provider payment ID.
	 * @param array<string,mixed> $metadata   Payment metadata.
	 * @return object|null
	 */
	protected function resolve_order_from_notification( $payment_id, array $metadata ) {
		$order = Art_LMS_Orders::find_by_gateway_transaction_id( $payment_id );

		if ( $order ) {
			return $order;
		}

		$order = $this->find_order_from_notification_labels(
			array(
				$metadata['order_reference'] ?? '',
			)
		);

		if ( $order ) {
			return $order;
		}

		$internal_id = absint( $metadata['order_id'] ?? 0 );

		if ( $internal_id ) {
			$candidate = Art_LMS_Orders::get( $internal_id );

			if ( $candidate && self::ID === Art_LMS_Orders::get_payment_gateway_slug( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Mark order paid after YooKassa payment verification.
	 *
	 * @param object              $order    Order object.
	 * @param array<string,mixed> $verified Verified payment payload from API.
	 * @param array<string,mixed> $raw      Optional raw webhook payload for storage.
	 * @return true|WP_Error
	 */
	protected function mark_order_paid_from_provider( $order, array $verified, array $raw = null ) {
		if ( Art_LMS_Orders::STATUS_PAID === $order->status ) {
			return true;
		}

		$payment_id = sanitize_text_field( (string) ( $verified['id'] ?? '' ) );
		$amount     = isset( $verified['amount']['value'] ) ? (float) $verified['amount']['value'] : 0.0;
		$currency   = strtoupper( (string) ( $verified['amount']['currency'] ?? '' ) );

		if ( abs( $amount - (float) $order->amount ) > 0.01 ) {
			return new WP_Error(
				'amount_mismatch',
				sprintf(
					/* translators: 1: received amount, 2: order amount */
					__( 'Сумма в уведомлении (%1$s) не совпала с заказом (%2$s).', 'art-lms' ),
					number_format( $amount, 2, '.', '' ),
					number_format( (float) $order->amount, 2, '.', '' )
				),
				array( 'status' => 400 )
			);
		}

		if ( 'RUB' !== $currency ) {
			return new WP_Error( 'invalid_currency', __( 'Неверная валюта платежа.', 'art-lms' ), array( 'status' => 400 ) );
		}

		if ( '' !== $payment_id && Art_LMS_Orders::is_transaction_id_used( $payment_id, (int) $order->id ) ) {
			return true;
		}

		$payment_method = '';

		if ( is_array( $verified['payment_method'] ?? null ) ) {
			$payment_method = sanitize_text_field( (string) ( $verified['payment_method']['type'] ?? '' ) );
		}

		$marked = Art_LMS_Orders::mark_paid(
			(int) $order->id,
			array(
				'transaction_id' => $payment_id,
				'payment_method' => $payment_method,
				'raw'            => is_array( $raw ) ? $raw : $verified,
			)
		);

		if ( ! $marked ) {
			return new WP_Error( 'mark_paid_failed', __( 'Не удалось отметить заказ оплаченным.', 'art-lms' ), array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * Verify payment status via YooKassa API.
	 *
	 * @param string               $payment_id Payment ID from webhook.
	 * @param array<string, mixed> $object     Object from webhook payload.
	 * @return array|WP_Error
	 */
	protected function verify_payment_notification( $payment_id, array $object ) {
		$api      = $this->get_api_client();
		$verified = $api->get_payment( $payment_id );

		if ( is_wp_error( $verified ) ) {
			return new WP_Error(
				'yookassa_verify_failed',
				__( 'Не удалось проверить платёж в ЮKassa.', 'art-lms' ),
				array( 'status' => 403 )
			);
		}

		if ( 'succeeded' !== ( $verified['status'] ?? '' ) ) {
			return new WP_Error(
				'yookassa_not_succeeded',
				__( 'Платёж в ЮKassa ещё не завершён.', 'art-lms' ),
				array( 'status' => 400 )
			);
		}

		$object_id = sanitize_text_field( (string) ( $object['id'] ?? '' ) );

		if ( $object_id && $object_id !== ( $verified['id'] ?? '' ) ) {
			return new WP_Error(
				'yookassa_id_mismatch',
				__( 'ID платежа в уведомлении не совпал с данными API.', 'art-lms' ),
				array( 'status' => 403 )
			);
		}

		return $verified;
	}

	/**
	 * @param object $order Order object.
	 * @return string
	 */
	protected function get_payment_description( $order ) {
		$button_meta  = Art_LMS_Payment_Buttons::get_meta( (int) $order->product_id );
		$product_name = trim( (string) ( $button_meta['product_name'] ?? '' ) );

		if ( '' === $product_name ) {
			$product_name = get_the_title( (int) $order->product_id );
		}

		if ( '' === $product_name ) {
			$product_name = sprintf(
				/* translators: %d: order ID */
				__( 'Заказ #%d', 'art-lms' ),
				(int) $order->id
			);
		}

		return $product_name;
	}

	/**
	 * @return Art_LMS_Yookassa_Api
	 */
	protected function get_api_client() {
		$settings = Art_LMS_Settings::get_gateway( self::ID );

		return new Art_LMS_Yookassa_Api(
			(string) ( $settings['shop_id'] ?? '' ),
			(string) ( $settings['secret_key'] ?? '' )
		);
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return array<string, mixed>
	 */
	protected function get_notification_payload( WP_REST_Request $request ) {
		$raw = $request->get_body();

		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$data = json_decode( $raw, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * @param string               $event  Event slug.
	 * @param array<string, mixed> $params Notification payload.
	 */
	protected function log_webhook_debug( $event, array $params ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'ART LMS YooKassa [%s]: %s',
				sanitize_key( (string) $event ),
				wp_json_encode( $params )
			)
		);
	}

	/**
	 * @param string $option_name Option key prefix.
	 * @param array  $settings    Stored gateway settings.
	 */
	public function render_admin_settings( $option_name, array $settings ) {
		$settings      = wp_parse_args( $settings, $this->get_default_settings() );
		$secret_raw    = (string) ( $settings['secret_key'] ?? '' );
		$secret_masked = '' === $secret_raw ? '' : str_repeat( '*', (int) min( strlen( $secret_raw ), 64 ) );
		$webhook_url   = $this->get_webhook_url();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="yookassa_shop_id"><?php esc_html_e( 'Shop ID (идентификатор магазина)', 'art-lms' ); ?></label></th>
				<td>
					<input
						type="text"
						id="yookassa_shop_id"
						name="<?php echo esc_attr( $option_name ); ?>[gateways][<?php echo esc_attr( self::ID ); ?>][shop_id]"
						value="<?php echo esc_attr( $settings['shop_id'] ?? '' ); ?>"
						class="regular-text"
						autocomplete="off"
					>
					<p class="description"><?php esc_html_e( 'Берётся в личном кабинете ЮKassa → Настройки → shopId.', 'art-lms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="yookassa_secret_key"><?php esc_html_e( 'Секретный ключ', 'art-lms' ); ?></label></th>
				<td>
					<input
						type="password"
						id="yookassa_secret_key"
						name="<?php echo esc_attr( $option_name ); ?>[gateways][<?php echo esc_attr( self::ID ); ?>][secret_key]"
						value="<?php echo esc_attr( $secret_masked ); ?>"
						class="regular-text"
						autocomplete="new-password"
						placeholder="<?php echo esc_attr( ! empty( $settings['secret_key'] ) ? __( 'Ключ сохранён — измените чтобы заменить', 'art-lms' ) : '' ); ?>"
					>
					<p class="description"><?php esc_html_e( 'Секретный ключ API из личного кабинета ЮKassa. Для тестового магазина ключ начинается с test_, для боевого — с live_.', 'art-lms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'URL для HTTP-уведомлений', 'art-lms' ); ?>
					<a href="<?php echo esc_url( self::WEBHOOK_SETTINGS_URL ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Настройка уведомлений', 'art-lms' ); ?>
					</a>
				</th>
				<td>
					<div class="art-lms-gateway-webhook-url-row">
						<input
							type="text"
							id="art-lms-yookassa-webhook-url"
							readonly
							value="<?php echo esc_url( $webhook_url ); ?>"
							class="regular-text"
							onclick="this.select();"
						>
						<button
							type="button"
							class="button art-lms-gateway-webhook-url-row__copy"
							id="art-lms-copy-yookassa-webhook"
							data-copy-target="art-lms-yookassa-webhook-url"
						>
							<?php esc_html_e( 'Скопировать', 'art-lms' ); ?>
						</button>
					</div>
					<p class="description">
						<?php esc_html_e( 'Скопируйте URL в кабинет ЮKassa и включите событие payment.succeeded. URL должен быть доступен по HTTPS из интернета.', 'art-lms' ); ?>
					</p>
					<script>
						(function () {
							var btn = document.getElementById('art-lms-copy-yookassa-webhook');
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
									// No-op.
								}
							});
						})();
					</script>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render receipt settings block below main gateway settings.
	 *
	 * @param string $option_name Option key prefix.
	 * @param array  $settings    Stored gateway settings.
	 */
	public function render_receipt_admin_settings( $option_name, array $settings ) {
		$settings              = wp_parse_args( $settings, $this->get_default_settings() );
		$field_name            = $option_name . '[gateways][' . self::ID . ']';
		$receipts_on           = Art_LMS_Yookassa_Receipt::is_enabled( $settings );
		$receipts_state_class  = $receipts_on ? 'is-enabled' : 'is-disabled';
		$receipts_status_label = $receipts_on ? __( 'Включены', 'art-lms' ) : __( 'Выключены', 'art-lms' );
		$vat_code              = Art_LMS_Yookassa_Receipt::sanitize_vat_code( $settings['receipt_vat_code'] ?? 1 );
		$payment_subject       = Art_LMS_Yookassa_Receipt::sanitize_payment_subject( $settings['receipt_payment_subject'] ?? 'service' );
		$payment_mode          = Art_LMS_Yookassa_Receipt::sanitize_payment_mode( $settings['receipt_payment_mode'] ?? 'full_payment' );
		$tax_system            = (string) Art_LMS_Yookassa_Receipt::sanitize_tax_system_code( $settings['receipt_tax_system_code'] ?? '' );
		?>
		<details class="art-lms-panel art-lms-collapsible-panel art-lms-yookassa-receipts-panel"<?php echo $receipts_on ? ' open' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<summary class="art-lms-collapsible-panel__summary">
				<span class="art-lms-collapsible-panel__summary-title"><?php esc_html_e( 'Чеки 54-ФЗ', 'art-lms' ); ?></span>
				<span
					id="art-lms-yookassa-receipts-status"
					class="art-lms-collapsible-panel__summary-status <?php echo esc_attr( $receipts_state_class ); ?>"
					data-enabled-label="<?php echo esc_attr__( 'Включены', 'art-lms' ); ?>"
					data-disabled-label="<?php echo esc_attr__( 'Выключены', 'art-lms' ); ?>"
				><?php echo esc_html( $receipts_status_label ); ?></span>
			</summary>
			<div class="art-lms-collapsible-panel__content">
				<p class="description">
					<?php esc_html_e( 'Для ИП и ООО с подключённой онлайн-кассой в ЮKassa. Самозанятые пробивают чеки отдельно через «Мой налог».', 'art-lms' ); ?>
					<a href="https://yookassa.ru/docs/support/merchant/payments/settings54fz" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Справка ЮKassa', 'art-lms' ); ?>
					</a>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Отправка чеков', 'art-lms' ); ?></th>
						<td>
							<label for="yookassa_receipts_enabled">
								<input
									type="checkbox"
									id="yookassa_receipts_enabled"
									name="<?php echo esc_attr( $field_name ); ?>[receipts_enabled]"
									value="1"
									<?php checked( $receipts_on ); ?>
								>
								<?php esc_html_e( 'Передавать данные для чека при создании платежа', 'art-lms' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Email покупателя на checkout обязателен. Название позиции в чеке — из платёжной кнопки.', 'art-lms' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="yookassa_receipt_vat_code"><?php esc_html_e( 'Ставка НДС', 'art-lms' ); ?></label></th>
						<td>
							<select id="yookassa_receipt_vat_code" name="<?php echo esc_attr( $field_name ); ?>[receipt_vat_code]">
								<?php foreach ( Art_LMS_Yookassa_Receipt::get_vat_code_options() as $code => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $vat_code, $code ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="yookassa_receipt_payment_subject"><?php esc_html_e( 'Предмет расчёта', 'art-lms' ); ?></label></th>
						<td>
							<select id="yookassa_receipt_payment_subject" name="<?php echo esc_attr( $field_name ); ?>[receipt_payment_subject]">
								<?php foreach ( Art_LMS_Yookassa_Receipt::get_payment_subject_options() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $payment_subject, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Для курсов и цифрового доступа обычно подходит «Услуга».', 'art-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="yookassa_receipt_payment_mode"><?php esc_html_e( 'Способ расчёта', 'art-lms' ); ?></label></th>
						<td>
							<select id="yookassa_receipt_payment_mode" name="<?php echo esc_attr( $field_name ); ?>[receipt_payment_mode]">
								<?php foreach ( Art_LMS_Yookassa_Receipt::get_payment_mode_options() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $payment_mode, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'При мгновенной выдаче доступа после оплаты — «Полный расчёт».', 'art-lms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="yookassa_receipt_tax_system_code"><?php esc_html_e( 'Система налогообложения', 'art-lms' ); ?></label></th>
						<td>
							<select id="yookassa_receipt_tax_system_code" name="<?php echo esc_attr( $field_name ); ?>[receipt_tax_system_code]">
								<option value="0" <?php selected( $tax_system, '0' ); ?>><?php esc_html_e( 'Не указывать', 'art-lms' ); ?></option>
								<?php foreach ( Art_LMS_Yookassa_Receipt::get_tax_system_options() as $code => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $tax_system, (string) $code ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</div>
		</details>
		<?php
	}
}
