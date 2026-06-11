<?php
/**
 * Prodamus payment gateway.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

require_once ART_LMS_PLUGIN_DIR . 'includes/gateways/prodamus/class-prodamus-hmac.php';

/**
 * Class Art_LMS_Gateway_Prodamus
 */
class Art_LMS_Gateway_Prodamus extends Art_LMS_Payment_Gateway {

	/**
	 * Gateway ID.
	 */
	const ID = 'prodamus';

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
			'title'       => __( 'Prodamus', 'art-lms' ),
			'description' => __( 'Приём платежей через платёжную форму Prodamus: карты, СБП, рассрочки и другие методы.', 'art-lms' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_default_settings() {
		return array(
			'enabled'      => 'no',
			'display_name' => '',
			'payform_url'  => '',
			'secret_key'   => '',
			'test_mode'    => 'no',
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

		$payform_url = isset( $input['payform_url'] ) ? esc_url_raw( trim( (string) $input['payform_url'] ) ) : ( $settings['payform_url'] ?? '' );
		$payform_url = trailingslashit( $payform_url );

		$settings['payform_url'] = $payform_url;
		$settings['secret_key']  = $secret;

		if ( isset( $input['payform_url'] ) ) {
			$settings['test_mode'] = ! empty( $input['test_mode'] ) ? 'yes' : 'no';
		}

		unset( $settings['payment_methods'], $settings['sys'] );

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
				esc_html__( 'Prodamus: некорректный URL платёжной формы.', 'art-lms' ),
				'',
				array( 'response' => 500 )
			);
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirect to external Prodamus payment page.
		wp_redirect( $redirect_url, 302, 'ART LMS Prodamus' );
		exit;
	}

	/**
	 * Build signed Prodamus payment URL.
	 *
	 * @param object $order Order object.
	 * @return string|WP_Error
	 */
	public function get_payment_redirect_url( $order ) {
		$settings = Art_LMS_Settings::get_gateway( self::ID );
		$payform  = trailingslashit( (string) ( $settings['payform_url'] ?? '' ) );
		$secret   = (string) ( $settings['secret_key'] ?? '' );

		if ( '' === $payform || ! wp_http_validate_url( $payform ) ) {
			return new WP_Error( 'prodamus_misconfigured', __( 'Prodamus: укажите URL платёжной формы в настройках шлюза.', 'art-lms' ) );
		}

		if ( '' === $secret ) {
			return new WP_Error( 'prodamus_misconfigured', __( 'Prodamus: укажите секретный ключ в настройках шлюза.', 'art-lms' ) );
		}

		$payload = $this->build_payment_payload( $order );
		$payload['signature'] = Art_LMS_Prodamus_Hmac::create( $payload, $secret );

		return $payform . '?' . $this->build_query( $payload );
	}

	/**
	 * @param object $order Order object.
	 * @return array<string, mixed>
	 */
	protected function build_payment_payload( $order ) {
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

		$price   = number_format( (float) $order->amount, 2, '.', '' );
		$contact = $this->resolve_order_customer_contact( $order );
		$data    = array(
			'do'               => 'pay',
			'order_id'         => $this->get_order_payment_reference( $order ),
			'customer_extra'   => $product_name,
			'products'         => array(
				array(
					'name'     => $product_name,
					'price'    => $price,
					'quantity' => '1',
					'type'     => 'course',
					'sku'      => (string) (int) $order->product_id,
				),
			),
			'urlSuccess'         => esc_url_raw( Art_LMS_Checkout::get_order_success_url( $order ) ),
			'urlReturn'          => esc_url_raw( $this->get_return_url( $order ) ),
			'urlNotification'    => esc_url_raw( $this->get_webhook_url() ),
			'currency'           => 'rub',
			'installments_disabled' => '0',
		);

		if ( '' !== $contact['email'] ) {
			$data['customer_email'] = $contact['email'];
		}

		if ( '' !== $contact['phone'] ) {
			$data['customer_phone'] = $contact['phone'];
		}

		if ( $this->is_test_mode() ) {
			$data['demo_mode'] = '1';
		}

		return $data;
	}

	/**
	 * Resolve buyer contact details for the Prodamus payment link.
	 *
	 * @param object $order Order object.
	 * @return array{email: string, phone: string}
	 */
	protected function resolve_order_customer_contact( $order ) {
		$email = sanitize_email( (string) ( $order->email ?? '' ) );
		$phone = sanitize_text_field( (string) ( $order->phone ?? '' ) );

		foreach ( Art_LMS_Order_Form_Data::decode( $order->form_data ?? '' )['fields'] as $row ) {
			$key = (string) ( $row['key'] ?? '' );

			if ( 'email' === $key && ! is_email( $email ) ) {
				$email = sanitize_email( (string) ( $row['value'] ?? '' ) );
			}

			if ( 'phone' === $key && '' === $phone ) {
				$phone = sanitize_text_field( (string) ( $row['value'] ?? '' ) );
			}
		}

		if ( ( ! is_email( $email ) || '' === $phone ) && ! empty( $order->user_id ) ) {
			$user = get_user_by( 'id', (int) $order->user_id );

			if ( $user ) {
				if ( ! is_email( $email ) ) {
					$email = sanitize_email( $user->user_email );
				}

				if ( '' === $phone ) {
					$phone = sanitize_text_field( (string) get_user_meta( $user->ID, 'art_lms_phone', true ) );
				}
			}
		}

		return array(
			'email' => is_email( $email ) ? $email : '',
			'phone' => $this->format_customer_phone( $phone ),
		);
	}

	/**
	 * @param object $order Order object.
	 * @return string
	 */
	protected function get_return_url( $order ) {
		$checkout_url = Art_LMS_Settings::get_checkout_url( (int) $order->product_id );

		if ( $checkout_url ) {
			return $checkout_url;
		}

		return home_url( '/' );
	}

	/**
	 * Primary webhook URL for admin settings.
	 *
	 * @return string
	 */
	public function get_webhook_url() {
		$url = apply_filters( 'art_lms_prodamus_webhook_url', parent::get_webhook_url(), $this );

		return is_string( $url ) ? $url : parent::get_webhook_url();
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$params = $this->get_notification_params( $request );
		$secret = (string) ( Art_LMS_Settings::get_gateway( self::ID )['secret_key'] ?? '' );
		$sign   = $this->get_request_sign_header( $request );

		if ( '' === $secret ) {
			return $this->webhook_response( 'Secret not configured', 500 );
		}

		if ( '' === $sign ) {
			$this->log_webhook_debug( 'missing_signature', $params );

			return $this->webhook_response( 'Missing signature', 403 );
		}

		$submit = $this->get_submit_payload( $params );

		if ( empty( $submit ) ) {
			$this->log_webhook_debug( 'missing_submit', $params );

			return $this->webhook_response( 'Missing submit payload', 400 );
		}

		if ( ! $this->verify_notification_signature( $params, $secret, $sign ) ) {
			$this->log_webhook_debug( 'invalid_signature', $params );

			return $this->webhook_response( 'Invalid signature', 403 );
		}

		$payment_status = strtolower( sanitize_text_field( (string) ( $submit['payment_status'] ?? $params['payment_status'] ?? '' ) ) );

		if ( 'success' !== $payment_status ) {
			return $this->webhook_response( 'Ignored status', 200 );
		}

		$order = $this->find_order_from_notification_labels(
			array(
				$submit['order_num'] ?? '',
				$params['order_num'] ?? '',
				$submit['order_id'] ?? '',
				$params['order_id'] ?? '',
			)
		);

		if ( ! $order ) {
			$this->log_webhook_debug( 'order_not_found', $params );

			return $this->webhook_response( 'Order not found', 404 );
		}

		if ( self::ID !== Art_LMS_Orders::get_payment_gateway_slug( $order ) ) {
			return $this->webhook_response( 'Gateway mismatch', 409 );
		}

		if ( Art_LMS_Orders::STATUS_PAID === $order->status ) {
			return $this->webhook_response( 'success', 200 );
		}

		$amount = $this->get_notification_amount( $submit, $params );

		if ( abs( $amount - (float) $order->amount ) > 0.01 ) {
			$this->log_webhook_debug( 'amount_mismatch', $params );

			return $this->webhook_response( 'Amount mismatch', 400 );
		}

		$transaction_id = sanitize_text_field( (string) ( $submit['order_id'] ?? $params['order_id'] ?? '' ) );

		if ( '' !== $transaction_id && Art_LMS_Orders::is_transaction_id_used( $transaction_id, (int) $order->id ) ) {
			return $this->webhook_response( 'Duplicate operation', 409 );
		}

		$marked = Art_LMS_Orders::mark_paid(
			(int) $order->id,
			array(
				'transaction_id' => $transaction_id,
				'payment_method' => sanitize_text_field( (string) ( $submit['payment_type'] ?? $params['payment_type'] ?? '' ) ),
				'raw'            => $params,
			)
		);

		if ( ! $marked ) {
			$this->log_webhook_debug( 'mark_paid_failed', $params );

			return $this->webhook_response( 'Unable to mark paid', 500 );
		}

		do_action( 'art_lms_payment_confirmed', (int) $order->id, $params );

		return $this->webhook_response( 'success', 200 );
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return array<string, mixed>
	 */
	protected function get_notification_params( WP_REST_Request $request ) {
		if ( ! empty( $_POST ) && is_array( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Prodamus server callback; verified via HMAC signature.
			return wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Prodamus server callback; verified via HMAC signature.
		}

		$params = $request->get_body_params();

		if ( ! is_array( $params ) || array() === $params ) {
			$params = $request->get_json_params();
		}

		if ( ! is_array( $params ) || array() === $params ) {
			$params = $request->get_params();
		}

		if ( ! is_array( $params ) || array() === $params ) {
			$params = $this->parse_raw_notification_body();
		}

		return is_array( $params ) ? $params : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function parse_raw_notification_body() {
		$raw = file_get_contents( 'php://input' );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$json = json_decode( $raw, true );

		if ( is_array( $json ) ) {
			return $json;
		}

		$parsed = array();
		parse_str( $raw, $parsed );

		return is_array( $parsed ) ? $parsed : array();
	}

	/**
	 * @param array<string, mixed> $params Notification payload.
	 * @param string               $secret Secret key.
	 * @param string               $sign   Request signature.
	 * @return bool
	 */
	protected function verify_notification_signature( array $params, $secret, $sign ) {
		$secrets  = array( (string) $secret );
		$payloads = $this->get_signature_verification_payloads( $params );

		if ( $this->is_test_mode() ) {
			$secrets[] = (string) $secret . 'demo';
		}

		foreach ( $secrets as $key ) {
			foreach ( $payloads as $payload ) {
				if ( Art_LMS_Prodamus_Hmac::verify( $payload, $key, $sign ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $params Notification payload.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_signature_verification_payloads( array $params ) {
		$payloads = array();

		if ( isset( $params['submit'] ) && is_array( $params['submit'] ) ) {
			$payloads[] = $params['submit'];
		}

		$full = $params;
		unset( $full['signature'] );
		$payloads[] = $full;

		$submit = $this->get_submit_payload( $params );
		if ( ! empty( $submit ) ) {
			$payloads[] = $submit;
		}

		$unique = array();
		$seen   = array();

		foreach ( $payloads as $payload ) {
			if ( ! is_array( $payload ) || array() === $payload ) {
				continue;
			}

			$key = wp_json_encode( $payload );

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ]  = true;
			$unique[]      = $payload;
		}

		return $unique;
	}

	/**
	 * @param string $body   Response body.
	 * @param int    $status HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function webhook_response( $body, $status = 200 ) {
		return new WP_REST_Response(
			$body,
			$status,
			array(
				'Content-Type' => 'text/plain; charset=utf-8',
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return string
	 */
	protected function get_request_sign_header( WP_REST_Request $request ) {
		$sign = $request->get_header( 'sign' );

		if ( ! $sign && ! empty( $_SERVER['HTTP_SIGN'] ) ) {
			$sign = sanitize_text_field( wp_unslash( $_SERVER['HTTP_SIGN'] ) );
		}

		return is_string( $sign ) ? trim( $sign ) : '';
	}

	/**
	 * @param array<string, mixed> $params Notification payload.
	 * @return array<string, mixed>
	 */
	protected function get_submit_payload( array $params ) {
		if ( isset( $params['submit'] ) && is_array( $params['submit'] ) ) {
			return $params['submit'];
		}

		$submit = $params;
		unset( $submit['signature'] );

		return $submit;
	}

	/**
	 * @param array<string, mixed> $submit Submit payload.
	 * @param array<string, mixed> $params Full payload.
	 * @return float
	 */
	protected function get_notification_amount( array $submit, array $params ) {
		$sum = $submit['sum'] ?? $params['sum'] ?? 0;

		return (float) $sum;
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
		unset( $safe_params['signature'] );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'ART LMS Prodamus webhook [%s]: %s',
				sanitize_key( (string) $event ),
				wp_json_encode( $safe_params )
			)
		);
	}

	/**
	 * @param string $phone Raw phone.
	 * @return string
	 */
	protected function format_customer_phone( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );

		if ( '' === $digits ) {
			return '';
		}

		if ( 11 === strlen( $digits ) && '8' === $digits[0] ) {
			$digits = '7' . substr( $digits, 1 );
		}

		if ( 10 === strlen( $digits ) ) {
			$digits = '7' . $digits;
		}

		return '+' . $digits;
	}

	/**
	 * @param array<string, mixed> $payload Payment payload.
	 * @return string
	 */
	protected function build_query( array $payload ) {
		$flat = array();

		foreach ( $payload as $key => $value ) {
			if ( 'products' === $key && is_array( $value ) ) {
				foreach ( $value as $index => $product ) {
					if ( ! is_array( $product ) ) {
						continue;
					}

					foreach ( $product as $product_key => $product_value ) {
						$flat[ 'products[' . $index . '][' . $product_key . ']' ] = $product_value;
					}
				}
				continue;
			}

			$flat[ $key ] = $value;
		}

		return http_build_query( $flat, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Whether gateway test mode is enabled.
	 *
	 * @return bool
	 */
	public function is_test_mode() {
		$settings = Art_LMS_Settings::get_gateway( self::ID );

		return ( $settings['test_mode'] ?? 'no' ) === 'yes';
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
		$is_test_mode  = ( $settings['test_mode'] ?? 'no' ) === 'yes';
		$field_name    = $option_name . '[gateways][' . self::ID . ']';
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="prodamus_payform_url"><?php esc_html_e( 'URL платёжной формы', 'art-lms' ); ?></label></th>
				<td>
					<input
						type="url"
						id="prodamus_payform_url"
						name="<?php echo esc_attr( $option_name ); ?>[gateways][<?php echo esc_attr( self::ID ); ?>][payform_url]"
						value="<?php echo esc_attr( $settings['payform_url'] ?? '' ); ?>"
						class="regular-text"
						placeholder="https://example.payform.ru/"
					>
					<p class="description"><?php esc_html_e( 'Адрес вашей платёжной страницы Prodamus из личного кабинета.', 'art-lms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="prodamus_secret_key"><?php esc_html_e( 'Секретный ключ', 'art-lms' ); ?></label></th>
				<td>
					<input
						type="password"
						id="prodamus_secret_key"
						name="<?php echo esc_attr( $option_name ); ?>[gateways][<?php echo esc_attr( self::ID ); ?>][secret_key]"
						value="<?php echo esc_attr( $secret_masked ); ?>"
						class="regular-text"
						autocomplete="new-password"
						placeholder="<?php echo esc_attr( ! empty( $settings['secret_key'] ) ? __( 'Ключ сохранён — измените чтобы заменить', 'art-lms' ) : '' ); ?>"
					>
					<p class="description"><?php esc_html_e( 'Секретный ключ платёжной формы для подписи ссылок и проверки webhook.', 'art-lms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Тестовый режим', 'art-lms' ); ?></th>
				<td>
					<label for="prodamus_test_mode">
						<input
							type="checkbox"
							id="prodamus_test_mode"
							name="<?php echo esc_attr( $field_name ); ?>[test_mode]"
							value="1"
							<?php checked( $is_test_mode ); ?>
						>
						<?php esc_html_e( 'Включить тестовый режим', 'art-lms' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Проверьте оплату перед запуском на боевом сайте. В тестовом режиме Prodamus принимает только тестовые карты, реальные деньги не списываются.', 'art-lms' ); ?>
					</p>
					<?php if ( $is_test_mode ) : ?>
						<p class="art-lms-payment-test-notice">
							<strong><?php esc_html_e( 'Тестовый режим включён.', 'art-lms' ); ?></strong>
							<?php esc_html_e( 'Выключите его перед приёмом реальных платежей.', 'art-lms' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'URL для HTTP-уведомлений', 'art-lms' ); ?></th>
				<td>
					<div class="art-lms-gateway-webhook-url-row">
						<input
							type="text"
							id="art-lms-prodamus-webhook-url"
							readonly
							value="<?php echo esc_url( $webhook_url ); ?>"
							class="regular-text"
							onclick="this.select();"
						>
						<button
							type="button"
							class="button art-lms-gateway-webhook-url-row__copy"
							id="art-lms-copy-prodamus-webhook"
							data-copy-target="art-lms-prodamus-webhook-url"
						>
							<?php esc_html_e( 'Скопировать', 'art-lms' ); ?>
						</button>
					</div>
					<p class="description">
						<?php esc_html_e( 'Передаётся в каждом платеже как urlNotification. URL должен быть доступен из интернета (HTTPS). При локальной разработке через ngrok укажите ngrok-адрес в «Адрес WordPress» и «Адрес сайта».', 'art-lms' ); ?>
					</p>
					<script>
						(function () {
							var btn = document.getElementById('art-lms-copy-prodamus-webhook');
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
}
