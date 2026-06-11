<?php
/**
 * YooKassa REST API client (no external SDK).
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Yookassa_Api
 */
class Art_LMS_Yookassa_Api {

	const API_BASE = 'https://api.yookassa.ru/v3/';

	/**
	 * Shop ID.
	 *
	 * @var string
	 */
	private $shop_id;

	/**
	 * Secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * @param string $shop_id    Shop ID.
	 * @param string $secret_key Secret key.
	 */
	public function __construct( $shop_id, $secret_key ) {
		$this->shop_id    = trim( (string) $shop_id );
		$this->secret_key = trim( (string) $secret_key );
	}

	/**
	 * Whether API credentials are configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->shop_id && '' !== $this->secret_key;
	}

	/**
	 * Create a payment and return provider response.
	 *
	 * @param array  $payload         Payment payload.
	 * @param string $idempotence_key Unique request key.
	 * @return array|WP_Error
	 */
	public function create_payment( array $payload, $idempotence_key ) {
		return $this->request( 'POST', 'payments', $payload, $idempotence_key );
	}

	/**
	 * Fetch payment details from YooKassa.
	 *
	 * @param string $payment_id Payment ID.
	 * @return array|WP_Error
	 */
	public function get_payment( $payment_id ) {
		$payment_id = sanitize_text_field( (string) $payment_id );

		if ( '' === $payment_id ) {
			return new WP_Error( 'yookassa_invalid_payment', __( 'ЮKassa: не указан ID платежа.', 'art-lms' ) );
		}

		return $this->request( 'GET', 'payments/' . rawurlencode( $payment_id ) );
	}

	/**
	 * Execute an API request.
	 *
	 * @param string $method          HTTP method.
	 * @param string $path            API path relative to v3.
	 * @param array  $body            Optional JSON body.
	 * @param string $idempotence_key Optional idempotence key.
	 * @return array|WP_Error
	 */
	private function request( $method, $path, array $body = array(), $idempotence_key = '' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'yookassa_misconfigured', __( 'ЮKassa: укажите shopId и секретный ключ в настройках шлюза.', 'art-lms' ) );
		}

		$url = self::API_BASE . ltrim( $path, '/' );

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode( $this->shop_id . ':' . $this->secret_key ),
			'Content-Type'  => 'application/json',
		);

		if ( '' !== $idempotence_key ) {
			$headers['Idempotence-Key'] = sanitize_text_field( (string) $idempotence_key );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => $headers,
			'timeout' => 30,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'yookassa_http_error',
				sprintf(
					/* translators: %s: error message */
					__( 'ЮKassa: ошибка соединения (%s).', 'art-lms' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $raw_body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'yookassa_invalid_response', __( 'ЮKassa: некорректный ответ API.', 'art-lms' ) );
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = (string) ( $data['description'] ?? $data['message'] ?? __( 'Неизвестная ошибка API.', 'art-lms' ) );

			return new WP_Error( 'yookassa_api_error', $message, array( 'status' => $status_code, 'response' => $data ) );
		}

		return $data;
	}
}
