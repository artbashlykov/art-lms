<?php
/**
 * Base payment gateway.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Abstract payment gateway.
 */
abstract class Art_LMS_Payment_Gateway {

	/**
	 * Gateway ID slug.
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * Gateway metadata for admin UI.
	 *
	 * @return array{id: string, title: string, description: string}
	 */
	abstract public function get_meta();

	/**
	 * Default gateway settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_default_settings() {
		return array(
			'enabled'      => 'no',
			'display_name' => '',
		);
	}

	/**
	 * Sanitize gateway settings from admin form.
	 *
	 * @param array $input    Submitted settings.
	 * @param array $existing Stored settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input, array $existing ) {
		$settings = wp_parse_args( $existing, $this->get_default_settings() );

		if ( ! empty( $input['_save_gateway'] ) ) {
			$settings['enabled'] = ! empty( $input['enabled'] ) ? 'yes' : 'no';
		}

		if ( array_key_exists( 'display_name', $input ) ) {
			$settings['display_name'] = sanitize_text_field( (string) $input['display_name'] );
		}

		return $settings;
	}

	/**
	 * Whether this gateway is enabled in payment settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$settings = Art_LMS_Settings::get_gateway( $this->get_id() );

		return ( $settings['enabled'] ?? 'no' ) === 'yes';
	}

	/**
	 * Whether checkout should skip an external payment provider.
	 *
	 * @param object|null $order Order object.
	 * @return bool
	 */
	public function should_skip_external_payment( $order = null ) {
		unset( $order );

		return false;
	}

	/**
	 * Build intermediate checkout redirect URL before external payment.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	public function get_checkout_redirect_url( $order ) {
		return add_query_arg(
			Art_LMS_Checkout::QUERY_PAY,
			$order->order_key,
			home_url( '/' )
		);
	}

	/**
	 * Redirect the buyer to the payment provider or intermediate step.
	 *
	 * @param object $order Order object.
	 */
	public function render_payment_redirect( $order ) {
		wp_safe_redirect( $this->get_checkout_redirect_url( $order ) );
		exit;
	}

	/**
	 * REST webhook route for this gateway.
	 *
	 * @return string
	 */
	public function get_webhook_route() {
		return '/notify/' . $this->get_id();
	}

	/**
	 * Primary webhook URL for admin settings.
	 *
	 * @return string
	 */
	public function get_webhook_url() {
		return rest_url( 'art-lms/v1' . $this->get_webhook_route() );
	}

	/**
	 * Register gateway-specific REST webhook routes.
	 */
	public function register_webhook_routes() {
	}

	/**
	 * Payment reference sent to the provider for this order.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	protected function get_order_payment_reference( $order ) {
		return Art_LMS_Orders::get_payment_reference( $order );
	}

	/**
	 * Resolve an order from webhook notification label candidates.
	 *
	 * Gateways may send the merchant reference in different fields
	 * (label, order_num, order_id, etc.). Candidates are checked in order.
	 *
	 * @param array<int, mixed> $candidates Raw values from provider payload.
	 * @return object|null
	 */
	protected function find_order_from_notification_labels( array $candidates ) {
		foreach ( $candidates as $candidate ) {
			$order = Art_LMS_Orders::find_by_payment_reference( $candidate );

			if ( $order ) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * Admin list/detail description for a gateway.
	 *
	 * @return string
	 */
	public function get_admin_description() {
		$meta = $this->get_meta();

		return (string) ( $meta['description'] ?? '' );
	}

	/**
	 * Render gateway settings panel in admin.
	 *
	 * @param string $option_name Option key prefix.
	 * @param array  $settings    Stored gateway settings.
	 */
	public function render_admin_settings( $option_name, array $settings ) {
		unset( $option_name, $settings );
	}

	/**
	 * Render enabled/disabled status control for admin UI.
	 *
	 * @param string $option_name Option key prefix.
	 * @param array  $settings    Stored gateway settings.
	 * @param string $context     UI context: list|table.
	 */
	public function render_gateway_status_control( $option_name, array $settings, $context = 'list' ) {
		$settings   = wp_parse_args( $settings, $this->get_default_settings() );
		$is_enabled = ( $settings['enabled'] ?? 'no' ) === 'yes';
		$field_name = $option_name . '[gateways][' . $this->get_id() . ']';
		$state_class = $is_enabled ? 'is-enabled' : 'is-disabled';

		ob_start();
		?>
		<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[_save_gateway]" value="1">
		<div
			class="art-lms-gateway-status-control <?php echo esc_attr( $state_class ); ?>"
			data-enabled-label="<?php echo esc_attr__( 'Включен', 'art-lms' ); ?>"
			data-disabled-label="<?php echo esc_attr__( 'Выключен', 'art-lms' ); ?>"
		>
			<label class="art-lms-gateway-status-switch">
				<input
					type="checkbox"
					class="art-lms-gateway-status-switch__input"
					name="<?php echo esc_attr( $field_name ); ?>[enabled]"
					value="1"
					<?php checked( $is_enabled ); ?>
				>
				<span class="art-lms-gateway-status-switch__track" aria-hidden="true"></span>
				<span class="screen-reader-text">
					<?php
					if ( $is_enabled ) {
						esc_html_e( 'Включен', 'art-lms' );
					} else {
						esc_html_e( 'Выключен', 'art-lms' );
					}
					?>
				</span>
			</label>
			<span class="art-lms-gateway-status-control__label" aria-hidden="true">
				<?php
				if ( $is_enabled ) {
					esc_html_e( 'Включен', 'art-lms' );
				} else {
					esc_html_e( 'Выключен', 'art-lms' );
				}
				?>
			</span>
		</div>
		<?php
		$control_html = ob_get_clean();

		if ( 'table' === $context ) {
			?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Статус', 'art-lms' ); ?></th>
				<td><?php echo $control_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<?php
			return;
		}

		echo $control_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Transient key for the latest webhook test result.
	 *
	 * @return string
	 */
	protected function get_webhook_test_transient_key() {
		return 'art_lms_gateway_webhook_test_' . $this->get_id();
	}

	/**
	 * Store the latest webhook test result for admin UI.
	 *
	 * @param bool   $ok      Whether the test succeeded.
	 * @param string $message Short status message.
	 */
	protected function record_webhook_test( $ok, $message ) {
		set_transient(
			$this->get_webhook_test_transient_key(),
			array(
				'ok'      => (bool) $ok,
				'message' => sanitize_text_field( (string) $message ),
				'time'    => current_time( 'timestamp' ),
			),
			7 * DAY_IN_SECONDS
		);
	}

	/**
	 * Get the latest webhook test result.
	 *
	 * @return array{ok: bool, message: string, time: int}|null
	 */
	protected function get_last_webhook_test() {
		$data = get_transient( $this->get_webhook_test_transient_key() );

		if ( ! is_array( $data ) || empty( $data['time'] ) ) {
			return null;
		}

		return array(
			'ok'      => ! empty( $data['ok'] ),
			'message' => (string) ( $data['message'] ?? '' ),
			'time'    => (int) $data['time'],
		);
	}

	/**
	 * Pending-state hint shown before the first webhook test.
	 *
	 * @return string
	 */
	protected function get_webhook_test_pending_message() {
		return __( 'Нажмите «Протестировать» в настройках платёжной системы — результат появится здесь.', 'art-lms' );
	}

	/**
	 * Success message shown after a valid test webhook.
	 *
	 * @return string
	 */
	protected function get_webhook_test_success_message() {
		return __( 'Тестовое уведомление получено. URL доступен, настройки webhook корректны.', 'art-lms' );
	}

	/**
	 * External URL where the merchant can test HTTP notifications.
	 *
	 * @return string
	 */
	protected function get_webhook_test_settings_url() {
		return '';
	}

	/**
	 * Render pending-state hint inside the webhook test status block.
	 */
	protected function render_webhook_test_pending_message() {
		echo esc_html( $this->get_webhook_test_pending_message() );
	}

	/**
	 * Render webhook test status inside the gateway settings card.
	 */
	protected function render_webhook_test_status() {
		$last_test = $this->get_last_webhook_test();
		$state     = 'pending';

		if ( $last_test ) {
			$state = $last_test['ok'] ? 'success' : 'error';
		}

		$when = $last_test
			? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_test['time'] )
			: '';
		?>
		<div
			class="art-lms-gateway-webhook-status art-lms-gateway-webhook-status--<?php echo esc_attr( $state ); ?>"
			data-gateway="<?php echo esc_attr( $this->get_id() ); ?>"
		>
			<p class="art-lms-gateway-webhook-status__title">
				<?php esc_html_e( 'Проверка HTTP-уведомлений', 'art-lms' ); ?>
			</p>
			<?php if ( 'success' === $state ) : ?>
				<p class="art-lms-gateway-webhook-status__text">
					<?php echo esc_html( $this->get_webhook_test_success_message() ); ?>
				</p>
				<p class="art-lms-gateway-webhook-status__meta">
					<?php
					printf(
						/* translators: %s: formatted date and time */
						esc_html__( 'Последняя проверка: %s', 'art-lms' ),
						esc_html( $when )
					);
					?>
				</p>
			<?php elseif ( 'error' === $state ) : ?>
				<p class="art-lms-gateway-webhook-status__text">
					<?php
					printf(
						/* translators: %s: error message */
						esc_html__( 'Тест не прошёл: %s', 'art-lms' ),
						esc_html( $last_test['message'] )
					);
					?>
				</p>
				<p class="art-lms-gateway-webhook-status__meta">
					<?php
					printf(
						/* translators: %s: formatted date and time */
						esc_html__( 'Последняя попытка: %s', 'art-lms' ),
						esc_html( $when )
					);
					?>
				</p>
			<?php else : ?>
				<p class="art-lms-gateway-webhook-status__text">
					<?php $this->render_webhook_test_pending_message(); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
