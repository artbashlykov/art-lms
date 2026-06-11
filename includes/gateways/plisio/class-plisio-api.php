<?php
/**
 * Plisio REST API client.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Plisio_Api
 */
class Art_LMS_Plisio_Api {

	const API_BASE = 'https://api.plisio.net/api/v1/';

	/**
	 * API secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * @param string $secret_key API secret key.
	 */
	public function __construct( $secret_key ) {
		$this->secret_key = trim( (string) $secret_key );
	}

	/**
	 * Whether API credentials are configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->secret_key;
	}

	/**
	 * Create a new invoice.
	 *
	 * @param array<string, mixed> $params Invoice parameters.
	 * @return array|WP_Error
	 */
	public function create_invoice( array $params ) {
		return $this->request( 'invoices/new', $params );
	}

	/**
	 * Fetch operation details by Plisio transaction ID.
	 *
	 * @param string $txn_id Plisio transaction ID.
	 * @return array|WP_Error
	 */
	public function get_operation( $txn_id ) {
		$txn_id = sanitize_text_field( (string) $txn_id );

		if ( '' === $txn_id ) {
			return new WP_Error( 'plisio_invalid_txn', __( 'Plisio: не указан ID транзакции.', 'art-lms' ) );
		}

		return $this->request( 'operations/' . rawurlencode( $txn_id ) );
	}

	/**
	 * Execute a GET API request.
	 *
	 * @param string               $path   API path relative to v1.
	 * @param array<string, mixed> $params Query parameters.
	 * @return array|WP_Error
	 */
	private function request( $path, array $params = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'plisio_misconfigured', __( 'Plisio: укажите секретный API-ключ в настройках шлюза.', 'art-lms' ) );
		}

		$params['api_key'] = $this->secret_key;
		$url                 = add_query_arg( $params, self::API_BASE . ltrim( $path, '/' ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'plisio_http_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Plisio: ошибка соединения (%s).', 'art-lms' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$data        = json_decode( $raw_body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'plisio_invalid_response', __( 'Plisio: некорректный ответ API.', 'art-lms' ) );
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = self::extract_error_message( $data );

			return new WP_Error( 'plisio_api_error', $message, array( 'status' => $status_code, 'response' => $data ) );
		}

		if ( 'error' === ( $data['status'] ?? '' ) ) {
			$message = self::extract_error_message( $data );

			return new WP_Error( 'plisio_api_error', $message, array( 'status' => $status_code, 'response' => $data ) );
		}

		return $data;
	}

	/**
	 * @param array<string, mixed> $data API response.
	 * @return string
	 */
	private static function extract_error_message( array $data ) {
		if ( ! empty( $data['data']['message'] ) && is_string( $data['data']['message'] ) ) {
			return sanitize_text_field( $data['data']['message'] );
		}

		if ( ! empty( $data['message'] ) && is_string( $data['message'] ) ) {
			return sanitize_text_field( $data['message'] );
		}

		return __( 'Неизвестная ошибка API Plisio.', 'art-lms' );
	}
}
