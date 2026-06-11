<?php
/**
 * Payment confirmation page settings.
 *
 * @package Art_LMS
 *
 * @var array $settings Checkout settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$option           = Art_LMS_Settings::OPTION_CHECKOUT;
$messages         = Art_LMS_Settings::get_payment_status_messages();
$defaults         = Art_LMS_Settings::get_default_payment_status_messages();
$placeholder_hint = Art_LMS_Settings::get_payment_status_placeholder_hint();
$general_settings = Art_LMS_Settings::get_general();
$success_page_id  = (int) ( $general_settings['success_page_id'] ?? 0 );
$success_page_url = $success_page_id ? get_permalink( $success_page_id ) : '';
$account_page_id  = (int) ( $general_settings['account_page_id'] ?? 0 );

$sections = array(
	'success' => array(
		'title'       => __( 'Успешная оплата', 'art-lms' ),
		'description' => __( 'Сообщение после подтверждения платежа.', 'art-lms' ),
		'open'        => true,
		'title_key'   => 'paid_title',
		'description_key' => 'paid_description',
		'show_account_toggle' => true,
	),
	'pending' => array(
		'title'       => __( 'Ожидание оплаты', 'art-lms' ),
		'description' => __( 'Сообщение, пока платёжная система подтверждает оплату.', 'art-lms' ),
		'open'        => false,
		'title_key'   => 'pending_title',
		'description_key' => 'pending_description',
	),
	'failed'  => array(
		'title'       => __( 'Неудачная оплата и ошибки', 'art-lms' ),
		'description' => __( 'Сообщения при отмене, долгом ожидании или проблемах со ссылкой.', 'art-lms' ),
		'open'        => false,
		'groups'      => array(
			array(
				'label'           => __( 'Оплата не прошла', 'art-lms' ),
				'title_key'       => 'failed_title',
				'description_key' => 'failed_description',
			),
			array(
				'label'           => __( 'Долгое ожидание подтверждения', 'art-lms' ),
				'description'     => __( 'Показывается, если подтверждение не пришло в течение 15 минут.', 'art-lms' ),
				'title_key'       => 'timeout_title',
				'description_key' => 'timeout_description',
			),
			array(
				'label'           => __( 'Заказ не найден', 'art-lms' ),
				'title_key'       => 'not_found_title',
				'description_key' => 'not_found_description',
			),
			array(
				'label'           => __( 'Нет ссылки на заказ', 'art-lms' ),
				'title_key'       => 'missing_order_title',
				'description_key' => 'missing_order_description',
			),
		),
	),
);
?>
<form method="post" action="options.php" class="art-lms-checkout-confirmation-settings-form">
	<?php settings_fields( 'art_lms_checkout_group' ); ?>

	<div class="art-lms-panel">
		<h2><?php esc_html_e( 'Страница подтверждения оплаты', 'art-lms' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Настройте тексты блока статуса оплаты на странице, куда покупатель попадает после checkout. Блок добавляется shortcode [art_lms_payment_status] или блоком «АРТ ЛМС: Статус оплаты».', 'art-lms' ); ?>
		</p>
		<p class="description"><?php echo esc_html( $placeholder_hint ); ?></p>
		<?php if ( $success_page_url ) : ?>
			<p class="description">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: linked page title */
						__( 'Текущая страница успешной оплаты: %s', 'art-lms' ),
						'<a href="' . esc_url( $success_page_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( get_the_title( $success_page_id ) ) . '</a>'
					)
				);
				?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Страница успешной оплаты пока не выбрана — укажите её в ART LMS → Настройки → Общие.', 'art-lms' ); ?>
			</p>
		<?php endif; ?>
	</div>

	<?php foreach ( $sections as $section_key => $section ) : ?>
		<details class="art-lms-panel art-lms-collapsible-panel"<?php echo ! empty( $section['open'] ) ? ' open' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<summary class="art-lms-collapsible-panel__summary"><?php echo esc_html( $section['title'] ); ?></summary>
			<div class="art-lms-collapsible-panel__content">
				<?php if ( ! empty( $section['description'] ) ) : ?>
					<p class="description"><?php echo esc_html( $section['description'] ); ?></p>
				<?php endif; ?>

				<?php if ( 'failed' === $section_key ) : ?>
					<?php foreach ( $section['groups'] as $group ) : ?>
						<div class="art-lms-payment-status-settings-group">
							<h3 class="art-lms-payment-status-settings-group__title"><?php echo esc_html( $group['label'] ); ?></h3>
							<?php if ( ! empty( $group['description'] ) ) : ?>
								<p class="description"><?php echo esc_html( $group['description'] ); ?></p>
							<?php endif; ?>
							<?php
							Art_LMS_Admin_Settings::render_payment_status_message_fields(
								$option,
								$messages,
								$group['title_key'],
								$group['description_key']
							);
							?>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<?php
					Art_LMS_Admin_Settings::render_payment_status_message_fields(
						$option,
						$messages,
						$section['title_key'],
						$section['description_key']
					);
					?>
					<?php if ( ! empty( $section['show_account_toggle'] ) ) : ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Кнопка личного кабинета', 'art-lms' ); ?></th>
								<td>
									<?php
									$show_account_button = 'yes' === ( $messages['paid_show_account_button'] ?? 'yes' );
									$toggle_class        = $show_account_button ? 'is-enabled' : 'is-disabled';
									?>
									<div
										class="art-lms-gateway-status-control <?php echo esc_attr( $toggle_class ); ?>"
										data-enabled-label="<?php echo esc_attr__( 'Показывать', 'art-lms' ); ?>"
										data-disabled-label="<?php echo esc_attr__( 'Скрывать', 'art-lms' ); ?>"
									>
										<label class="art-lms-gateway-status-switch">
											<input
												type="checkbox"
												class="art-lms-gateway-status-switch__input"
												name="<?php echo esc_attr( $option ); ?>[payment_status][paid_show_account_button]"
												value="1"
												<?php checked( $show_account_button ); ?>
											>
											<span class="art-lms-gateway-status-switch__track" aria-hidden="true"></span>
											<span class="screen-reader-text">
												<?php
												if ( $show_account_button ) {
													esc_html_e( 'Показывать', 'art-lms' );
												} else {
													esc_html_e( 'Скрывать', 'art-lms' );
												}
												?>
											</span>
										</label>
										<span class="art-lms-gateway-status-control__label" aria-hidden="true">
											<?php
											if ( $show_account_button ) {
												esc_html_e( 'Показывать', 'art-lms' );
											} else {
												esc_html_e( 'Скрывать', 'art-lms' );
											}
											?>
										</span>
									</div>
									<p class="description">
										<?php
										if ( $account_page_id ) {
											esc_html_e( 'Кнопка отображается только если в общих настройках выбрана страница личного кабинета.', 'art-lms' );
										} else {
											esc_html_e( 'Сначала выберите страницу личного кабинета в ART LMS → Настройки → Общие.', 'art-lms' );
										}
										?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="art_lms_payment_status_account_button_label">
										<?php esc_html_e( 'Текст кнопки', 'art-lms' ); ?>
									</label>
								</th>
								<td>
									<input
										type="text"
										class="regular-text"
										id="art_lms_payment_status_account_button_label"
										name="<?php echo esc_attr( $option ); ?>[payment_status][account_button_label]"
										value="<?php echo esc_attr( $messages['account_button_label'] ?? '' ); ?>"
									>
									<p class="description">
										<button
											type="button"
											class="button-link art-lms-payment-status-message-reset"
											data-target="art_lms_payment_status_account_button_label"
											data-reset-key="account_button_label"
										>
											<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
										</button>
									</p>
								</td>
							</tr>
						</table>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</details>
	<?php endforeach; ?>

	<?php submit_button(); ?>
</form>
