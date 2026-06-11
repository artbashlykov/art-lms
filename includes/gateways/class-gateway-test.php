<?php
/**
 * Test payment gateway.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Gateway_Test
 */
class Art_LMS_Gateway_Test extends Art_LMS_Payment_Gateway {

	/**
	 * Gateway ID.
	 */
	const ID = 'test';

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
			'title'       => __( 'Тестовый шлюз', 'art-lms' ),
			'description' => __( 'Проверка checkout и заказов без подключения платёжных систем. Заказы создаются со статусом «Ожидает оплаты».', 'art-lms' ),
		);
	}

	/**
	 * @return string
	 */
	public function get_admin_description() {
		return parent::get_admin_description() . ' ' . __( '(НЕ ЗАБУДЬТЕ ВЫКЛЮЧИТЬ ЕГО ПОСЛЕ ТЕСТА)', 'art-lms' );
	}

	/**
	 * @param object|null $order Order object.
	 * @return bool
	 */
	public function should_skip_external_payment( $order = null ) {
		unset( $order );

		return $this->is_enabled();
	}

	/**
	 * @param object $order Order object.
	 * @return string
	 */
	public function get_checkout_redirect_url( $order ) {
		return Art_LMS_Checkout::get_order_success_url( $order );
	}

	/**
	 * @param string $option_name Option key prefix.
	 * @param array  $settings    Stored gateway settings.
	 */
	public function render_admin_settings( $option_name, array $settings ) {
		unset( $option_name );
		$settings = wp_parse_args( $settings, $this->get_default_settings() );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Как работает', 'art-lms' ); ?></th>
				<td>
					<p class="description">
						<?php esc_html_e( 'Платёжные кнопки и форма checkout работают как обычно: создаётся пользователь, сохраняются поля формы, заказ попадает в админку.', 'art-lms' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Внешняя оплата не вызывается. Заказ остаётся в статусе «Ожидает оплаты» — подтвердите его вручную в админке, когда будете готовы выдать доступ.', 'art-lms' ); ?>
					</p>
					<p class="art-lms-payment-test-notice">
						<strong><?php esc_html_e( 'Только для тестирования.', 'art-lms' ); ?></strong>
						<?php esc_html_e( 'Перед запуском на боевом сайте выберите реальный способ приёма платежей.', 'art-lms' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}
}
