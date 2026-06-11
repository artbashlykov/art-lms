<?php
/**
 * Plisio crypto payment gateway.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

require_once ART_LMS_PLUGIN_DIR . 'includes/gateways/plisio/class-plisio-api.php';
require_once ART_LMS_PLUGIN_DIR . 'includes/gateways/plisio/class-plisio-callback.php';

/**
 * Class Art_LMS_Gateway_Plisio
 */
class Art_LMS_Gateway_Plisio extends Art_LMS_Payment_Gateway {

	/**
	 * Gateway ID.
	 */
	const ID = 'plisio';

	/**
	 * API settings page in Plisio cabinet.
	 */
	const API_SETTINGS_URL = 'https://plisio.net/account/api';

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
			'title'       => __( 'Plisio (криптовалюта)', 'art-lms' ),
			'description' => __( 'Приём криптоплатежей через Plisio: Bitcoin, Ethereum, USDT и другие монеты.', 'art-lms' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_default_settings() {
		return array(
			'enabled'          => 'no',
			'display_name'     => '',
			'secret_key'       => '',
			'allowed_psys_cids' => '',
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

		$settings['secret_key'] = $secret;

		if ( isset( $input['allowed_psys_cids'] ) ) {
			$allowed = sanitize_text_field( (string) $input['allowed_psys_cids'] );
			$allowed = preg_replace( '/[^A-Za-z0-9_,]/', '', $allowed );
			$settings['allowed_psys_cids'] = $allowed;
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
				esc_html__( 'Plisio: некорректный URL платёжной страницы.', 'art-lms' ),
				'',
				array( 'response' => 500 )
			);
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirect to external Plisio payment page.
		wp_redirect( $redirect_url, 302, 'ART LMS Plisio' );
		exit;
	}

	/**
	 * Create a Plisio invoice and return payment URL.
	 *
	 * @param object $order Order object.
	 * @return string|WP_Error
	 */
	public function get_payment_redirect_url( $order ) {
		$api      = $this->get_api_client();
		$settings = Art_LMS_Settings::get_gateway( self::ID );

		if ( ! $api->is_configured() ) {
			return new WP_Error( 'plisio_misconfigured', __( 'Plisio: укажите секретный API-ключ в настройках шлюза.', 'art-lms' ) );
		}

		$reference   = $this->get_order_payment_reference( $order );
		$order_name  = $this->get_payment_description( $order );
		$currency    = $this->map_source_currency( (string) ( $order->currency ?? 'RUB' ) );
		$amount      = number_format( (float) $order->amount, 8, '.', '' );
		$success_url = esc_url_raw( Art_LMS_Checkout::get_order_success_url( $order ) );

		$params = array(
			'order_number'        => $reference,
			'order_name'          => $order_name,
			'source_currency'     => $currency,
			'source_amount'       => $amount,
			'callback_url'        => esc_url_raw( $this->get_webhook_url() ),
			'success_invoice_url' => $success_url,
			'fail_invoice_url'    => $success_url,
			'plugin'              => 'art-lms',
			'version'             => ART_LMS_VERSION,
			'language'            => 'en_US',
		);

		$email = sanitize_email( (string) ( $order->email ?? '' ) );

		if ( is_email( $email ) ) {
			$params['email'] = $email;
		}

		$allowed = trim( (string) ( $settings['allowed_psys_cids'] ?? '' ) );

		if ( '' !== $allowed ) {
			$params['allowed_psys_cids'] = $allowed;
		}

		$response = $api->create_invoice( $params );

		if ( is_wp_error( $response ) ) {
			$this->log_webhook_debug( 'create_invoice_failed', array( 'message' => $response->get_error_message() ) );

			return $response;
		}

		$invoice_data = is_array( $response['data'] ?? null ) ? $response['data'] : array();
		$txn_id       = sanitize_text_field( (string) ( $invoice_data['txn_id'] ?? '' ) );
		$invoice_url  = (string) ( $invoice_data['invoice_url'] ?? '' );

		if ( '' !== $txn_id ) {
			Art_LMS_Orders::update(
				(int) $order->id,
				array(
					'gateway_transaction_id' => $txn_id,
				)
			);
		}

		if ( '' === $invoice_url || ! wp_http_validate_url( $invoice_url ) ) {
			return new WP_Error( 'plisio_no_invoice_url', __( 'Plisio: не получен URL для оплаты.', 'art-lms' ) );
		}

		return $invoice_url;
	}

	/**
	 * Sync payment status via Plisio API when callback is delayed.
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

		$txn_id = sanitize_text_field( (string) ( $order->gateway_transaction_id ?? '' ) );

		if ( '' === $txn_id ) {
			return;
		}

		$verified = $this->verify_operation_paid( $txn_id );

		if ( is_wp_error( $verified ) || empty( $verified['paid'] ) ) {
			return;
		}

		$result = $this->mark_order_paid_from_provider( $order, $verified );

		if ( ! is_wp_error( $result ) ) {
			do_action( 'art_lms_payment_confirmed', (int) $order->id, $verified );
		}
	}

	/**
	 * Handle Plisio callback notification.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$params = $this->get_notification_params( $request );

		if ( empty( $params ) ) {
			return new WP_REST_Response( array( 'error' => 'empty_body' ), 400 );
		}

		$settings   = Art_LMS_Settings::get_gateway( self::ID );
		$secret_key = (string) ( $settings['secret_key'] ?? '' );

		if ( ! Art_LMS_Plisio_Callback::verify( $params, $secret_key ) ) {
			$this->log_webhook_debug( 'invalid_signature', $params );

			return new WP_REST_Response( array( 'error' => 'invalid_signature' ), 403 );
		}

		$status = strtolower( sanitize_text_field( (string) ( $params['status'] ?? '' ) ) );

		if ( ! Art_LMS_Plisio_Callback::is_paid_status( $status ) ) {
			return new WP_REST_Response( array( 'status' => 'ignored' ), 200 );
		}

		$order_number = sanitize_text_field( (string) ( $params['order_number'] ?? '' ) );

		if ( '' === $order_number ) {
			return new WP_REST_Response( array( 'error' => 'missing_order_number' ), 400 );
		}

		$order = $this->find_order_from_notification_labels( array( $order_number ) );

		if ( ! $order ) {
			$this->log_webhook_debug( 'order_not_found', $params );

			return new WP_REST_Response( array( 'error' => 'order_not_found' ), 404 );
		}

		if ( self::ID !== Art_LMS_Orders::get_payment_gateway_slug( $order ) ) {
			return new WP_REST_Response( array( 'error' => 'gateway_mismatch' ), 409 );
		}

		if ( Art_LMS_Orders::STATUS_PAID === $order->status ) {
			return new WP_REST_Response( array( 'status' => 'already_paid' ), 200 );
		}

		$amount_error = $this->validate_notification_amount( $order, $params );

		if ( is_wp_error( $amount_error ) ) {
			$this->log_webhook_debug( $amount_error->get_error_code(), $params );

			$status_code = (int) ( $amount_error->get_error_data()['status'] ?? 400 );

			return new WP_REST_Response( array( 'error' => $amount_error->get_error_code() ), $status_code > 0 ? $status_code : 400 );
		}

		$result = $this->mark_order_paid_from_provider( $order, $this->normalize_notification_data( $params ), $params );

		if ( is_wp_error( $result ) ) {
			$this->log_webhook_debug( $result->get_error_code(), $params );

			$status_code = (int) ( $result->get_error_data()['status'] ?? 400 );

			return new WP_REST_Response( array( 'error' => $result->get_error_code() ), $status_code > 0 ? $status_code : 400 );
		}

		do_action( 'art_lms_payment_confirmed', (int) $order->id, $params );

		return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/**
	 * Mark order paid after Plisio verification.
	 *
	 * @param object              $order    Order object.
	 * @param array<string,mixed> $verified Verified payment payload.
	 * @param array<string,mixed> $raw      Optional raw callback payload.
	 * @return true|WP_Error
	 */
	protected function mark_order_paid_from_provider( $order, array $verified, array $raw = null ) {
		if ( Art_LMS_Orders::STATUS_PAID === $order->status ) {
			return true;
		}

		$transaction_id = sanitize_text_field( (string) ( $verified['txn_id'] ?? $order->gateway_transaction_id ?? '' ) );

		if ( '' !== $transaction_id && Art_LMS_Orders::is_transaction_id_used( $transaction_id, (int) $order->id ) ) {
			return true;
		}

		$payment_method = sanitize_text_field( (string) ( $verified['currency'] ?? 'crypto' ) );

		$marked = Art_LMS_Orders::mark_paid(
			(int) $order->id,
			array(
				'transaction_id' => $transaction_id,
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
	 * Verify operation payment state via Plisio API.
	 *
	 * @param string $txn_id Plisio transaction ID.
	 * @return array|WP_Error
	 */
	protected function verify_operation_paid( $txn_id ) {
		$api = $this->get_api_client();

		if ( ! $api->is_configured() ) {
			return new WP_Error( 'plisio_misconfigured', __( 'Plisio: шлюз не настроен.', 'art-lms' ), array( 'status' => 403 ) );
		}

		$response = $api->get_operation( $txn_id );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'plisio_verify_failed',
				__( 'Не удалось проверить платёж в Plisio.', 'art-lms' ),
				array( 'status' => 403 )
			);
		}

		$operation = is_array( $response['data'] ?? null ) ? $response['data'] : array();
		$status    = strtolower( sanitize_text_field( (string) ( $operation['status'] ?? '' ) ) );

		return array(
			'paid'     => Art_LMS_Plisio_Callback::is_paid_status( $status ),
			'status'   => $status,
			'txn_id'   => sanitize_text_field( (string) ( $operation['txn_id'] ?? $txn_id ) ),
			'currency' => sanitize_text_field( (string) ( $operation['currency'] ?? '' ) ),
			'raw'      => $response,
		);
	}

	/**
	 * @param array<string,mixed> $params Notification parameters.
	 * @return array<string,mixed>
	 */
	protected function normalize_notification_data( array $params ) {
		return array(
			'txn_id'   => sanitize_text_field( (string) ( $params['txn_id'] ?? '' ) ),
			'status'   => sanitize_text_field( (string) ( $params['status'] ?? '' ) ),
			'currency' => sanitize_text_field( (string) ( $params['currency'] ?? '' ) ),
		);
	}

	/**
	 * @param object              $order  Order object.
	 * @param array<string,mixed> $params Notification parameters.
	 * @return true|WP_Error
	 */
	protected function validate_notification_amount( $order, array $params ) {
		if ( ! isset( $params['source_amount'] ) || '' === (string) $params['source_amount'] ) {
			return true;
		}

		$amount   = (float) $params['source_amount'];
		$currency = strtoupper( sanitize_text_field( (string) ( $params['source_currency'] ?? '' ) ) );
		$expected = $this->map_source_currency( (string) ( $order->currency ?? 'RUB' ) );

		if ( '' !== $currency && $currency !== $expected ) {
			return new WP_Error( 'invalid_currency', __( 'Неверная валюта платежа.', 'art-lms' ), array( 'status' => 400 ) );
		}

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

		return true;
	}

	/**
	 * @param string $currency Order currency code.
	 * @return string
	 */
	protected function map_source_currency( $currency ) {
		$currency = strtoupper( sanitize_text_field( $currency ) );

		return 'RUB' === $currency ? 'RUB' : $currency;
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

		$product_name = wp_strip_all_tags( $product_name );
		$product_name = preg_replace( '/\s+/u', ' ', $product_name );
		$product_name = trim( (string) $product_name );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $product_name, 0, 150 );
		}

		return substr( $product_name, 0, 150 );
	}

	/**
	 * @return Art_LMS_Plisio_Api
	 */
	protected function get_api_client() {
		$settings = Art_LMS_Settings::get_gateway( self::ID );

		return new Art_LMS_Plisio_Api( (string) ( $settings['secret_key'] ?? '' ) );
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return array<string, mixed>
	 */
	protected function get_notification_params( WP_REST_Request $request ) {
		$params = $request->get_body_params();

		if ( ! is_array( $params ) || array() === $params ) {
			$params = $request->get_params();
		}

		if ( ( ! is_array( $params ) || array() === $params ) && ! empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Plisio server callback; verified via request signature.
			$params = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Plisio server callback; verified via request signature.
		}

		if ( ! is_array( $params ) ) {
			return array();
		}

		unset( $params['rest_route'] );

		return $params;
	}

	/**
	 * @param string               $event  Event slug.
	 * @param array<string, mixed> $params Notification payload.
	 */
	protected function log_webhook_debug( $event, array $params ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$safe_params = $params;
		unset( $safe_params['verify_hash'] );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'ART LMS Plisio [%s]: %s',
				sanitize_key( (string) $event ),
				wp_json_encode( $safe_params )
			)
		);
	}

	/**
	 * Base URL of the configured payment confirmation page.
	 *
	 * @return string
	 */
	protected function get_confirmation_page_url() {
		$general = Art_LMS_Settings::get_general();
		$page_id = (int) ( $general['success_page_id'] ?? 0 );

		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? esc_url_raw( $url ) : '';
	}

	/**
	 * @param string $option_name Option key prefix.
	 * @param array  $settings    Stored gateway settings.
	 */
	public function render_admin_settings( $option_name, array $settings ) {
		$settings              = wp_parse_args( $settings, $this->get_default_settings() );
		$secret_raw            = (string) ( $settings['secret_key'] ?? '' );
		$secret_masked         = '' === $secret_raw ? '' : str_repeat( '*', (int) min( strlen( $secret_raw ), 64 ) );
		$webhook_url           = $this->get_webhook_url();
		$confirmation_page_url = $this->get_confirmation_page_url();
		$confirmation_page_id  = (int) ( Art_LMS_Settings::get_general()['success_page_id'] ?? 0 );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="plisio_secret_key"><?php esc_html_e( 'Секретный API-ключ', 'art-lms' ); ?></label>
					<a href="<?php echo esc_url( self::API_SETTINGS_URL ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'API-настройки Plisio', 'art-lms' ); ?>
					</a>
				</th>
				<td>
					<input
						type="password"
						id="plisio_secret_key"
						name="<?php echo esc_attr( $option_name ); ?>[gateways][<?php echo esc_attr( self::ID ); ?>][secret_key]"
						value="<?php echo esc_attr( $secret_masked ); ?>"
						class="regular-text"
						autocomplete="new-password"
						placeholder="<?php echo esc_attr( ! empty( $settings['secret_key'] ) ? __( 'Ключ сохранён — измените чтобы заменить', 'art-lms' ) : '' ); ?>"
					>
					<p class="description">
						<?php esc_html_e( 'Secret key из личного кабинета Plisio. Также добавьте IP вашего сервера в whitelist API Plisio.', 'art-lms' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="plisio_allowed_psys_cids"><?php esc_html_e( 'Разрешённые криптовалюты', 'art-lms' ); ?></label></th>
				<td>
					<input
						type="text"
						id="plisio_allowed_psys_cids"
						name="<?php echo esc_attr( $option_name ); ?>[gateways][<?php echo esc_attr( self::ID ); ?>][allowed_psys_cids]"
						value="<?php echo esc_attr( $settings['allowed_psys_cids'] ?? '' ); ?>"
						class="regular-text"
						placeholder="BTC,ETH,USDT_TRX"
					>
					<p class="description">
						<?php esc_html_e( 'Необязательно. ID монет через запятую (например BTC,ETH,USDT_TRX). Пустое поле — покупатель выбирает монету на странице Plisio.', 'art-lms' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Callback URL', 'art-lms' ); ?></th>
				<td>
					<div class="art-lms-gateway-webhook-url-row">
						<input
							type="text"
							id="art-lms-plisio-webhook-url"
							readonly
							value="<?php echo esc_url( $webhook_url ); ?>"
							class="regular-text"
							onclick="this.select();"
						>
						<button
							type="button"
							class="button art-lms-gateway-webhook-url-row__copy art-lms-plisio-copy-url"
							data-copy-target="art-lms-plisio-webhook-url"
						>
							<?php esc_html_e( 'Скопировать', 'art-lms' ); ?>
						</button>
					</div>
					<p class="description">
						<?php esc_html_e( 'Укажите этот URL в поле Status URL в кабинете Plisio или оставьте передачу через callback_url при создании счёта.', 'art-lms' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Страница подтверждения оплаты', 'art-lms' ); ?>
					<?php if ( $confirmation_page_url ) : ?>
						<a href="<?php echo esc_url( $confirmation_page_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( get_the_title( $confirmation_page_id ) ); ?>
						</a>
					<?php endif; ?>
				</th>
				<td>
					<?php if ( $confirmation_page_url ) : ?>
						<div class="art-lms-gateway-webhook-url-row">
							<input
								type="text"
								id="art-lms-plisio-confirmation-page-url"
								readonly
								value="<?php echo esc_url( $confirmation_page_url ); ?>"
								class="regular-text"
								onclick="this.select();"
							>
							<button
								type="button"
								class="button art-lms-gateway-webhook-url-row__copy art-lms-plisio-copy-url"
								data-copy-target="art-lms-plisio-confirmation-page-url"
							>
								<?php esc_html_e( 'Скопировать', 'art-lms' ); ?>
							</button>
						</div>
						<p class="description">
							<?php esc_html_e( 'Этот URL автоматически передаётся в Plisio как страница возврата после оплаты. Его также можно указать в Status URL кабинета Plisio.', 'art-lms' ); ?>
						</p>
					<?php else : ?>
						<p class="description">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: %s: settings URL */
									__( 'Страница успешной оплаты не выбрана. Укажите её в %s.', 'art-lms' ),
									'<a href="' . esc_url( Art_LMS_Admin_Settings::get_tab_url( Art_LMS_Admin_Settings::PAGE_SETTINGS, Art_LMS_Admin_Settings::TAB_GENERAL ) ) . '">' . esc_html__( 'ART LMS → Настройки → Общие', 'art-lms' ) . '</a>'
								)
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<script>
			(function () {
				document.querySelectorAll('.art-lms-plisio-copy-url').forEach(function (btn) {
					btn.addEventListener('click', async function () {
						var targetId = btn.getAttribute('data-copy-target');
						var input = targetId ? document.getElementById(targetId) : null;

						if (!input) {
							return;
						}

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
							setTimeout(function () {
								btn.textContent = old;
							}, 1200);
						} catch (e) {
							// No-op.
						}
					});
				});
			})();
		</script>
		<?php
	}
}
