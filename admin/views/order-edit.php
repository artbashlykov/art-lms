<?php
/**
 * Order add/edit admin page.
 *
 * @package Art_LMS
 *
 * @var array  $order    Order form data.
 * @var bool   $is_new   Whether this is a new order.
 * @var string    $list_url Orders list URL.
 * @var string    $error    Error message.
 * @var array  $payment_buttons Payment button options for the form.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$page_title = $is_new ? __( 'Добавить заказ', 'art-lms' ) : sprintf(
	/* translators: %d: order ID */
	__( 'Редактирование заказа #%d', 'art-lms' ),
	$order['id']
);
$can_change_product = $is_new || Art_LMS_Orders::STATUS_PAID !== $order['status'] || empty( $order['product_id'] );
?>
<div class="wrap art-lms-admin art-lms-order-edit-page">
	<h1><?php echo esc_html( $page_title ); ?></h1>

	<?php if ( $error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="art-lms-order-form" data-art-lms-order-form>
		<input type="hidden" name="action" value="art_lms_save_order">
		<input type="hidden" name="order_id" value="<?php echo esc_attr( $order['id'] ); ?>">
		<?php wp_nonce_field( 'art_lms_save_order', 'art_lms_order_nonce' ); ?>

		<div class="art-lms-panel">
			<?php if ( ! $is_new ) : ?>
				<table class="form-table art-lms-order-meta-readonly" role="presentation">
					<tr>
						<th><?php esc_html_e( 'ID заказа', 'art-lms' ); ?></th>
						<td><?php echo esc_html( $order['id'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Платёжный шлюз', 'art-lms' ); ?></th>
						<td><?php echo esc_html( Art_LMS_Orders::get_payment_gateway_label( $order ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'ID для платёжной системы', 'art-lms' ); ?></th>
						<td><code><?php echo esc_html( $order['payment_label'] ); ?></code></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Создан', 'art-lms' ); ?></th>
						<td><?php echo esc_html( $order['created_at'] ); ?></td>
					</tr>
					<?php if ( ! empty( $order['paid_at'] ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Оплачен', 'art-lms' ); ?></th>
							<td><?php echo esc_html( $order['paid_at'] ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( ! empty( $order['product_id'] ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Платёжная кнопка', 'art-lms' ); ?></th>
							<td><?php echo esc_html( Art_LMS_Payment_Buttons::get_product_name( (int) $order['product_id'] ) ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
				<hr>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="art_lms_order_buyer_identity"><?php esc_html_e( 'Email или логин', 'art-lms' ); ?></label></th>
					<td>
						<div class="art-lms-buyer-lookup">
							<input type="text" name="buyer_identity" id="art_lms_order_buyer_identity" class="regular-text" value="<?php echo esc_attr( $order['buyer_identity'] ?? $order['email'] ); ?>" autocomplete="off" required>
							<button type="button" class="button" id="art_lms_lookup_buyer"><?php esc_html_e( 'Найти', 'art-lms' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Введите email или логин WordPress. Если аккаунт найден, имя и телефон подставятся автоматически.', 'art-lms' ); ?></p>
						<p class="art-lms-buyer-lookup-status" id="art_lms_buyer_lookup_status" aria-live="polite"></p>
						<input type="hidden" name="email" id="art_lms_order_email" value="<?php echo esc_attr( $order['email'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="art_lms_order_name"><?php esc_html_e( 'Имя', 'art-lms' ); ?></label></th>
					<td><input type="text" name="name" id="art_lms_order_name" class="regular-text" value="<?php echo esc_attr( $order['name'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="art_lms_order_phone"><?php esc_html_e( 'Телефон', 'art-lms' ); ?></label></th>
					<td><input type="text" name="phone" id="art_lms_order_phone" class="regular-text" value="<?php echo esc_attr( $order['phone'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="art_lms_order_product_id"><?php esc_html_e( 'Платёжная кнопка', 'art-lms' ); ?></label></th>
					<td>
						<?php if ( ! $can_change_product ) : ?>
							<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $order['product_id'] ); ?>">
							<p><strong><?php echo esc_html( Art_LMS_Payment_Buttons::get_product_name( (int) $order['product_id'] ) ); ?></strong></p>
							<p class="description"><?php esc_html_e( 'Для оплаченного заказа продукт изменить нельзя.', 'art-lms' ); ?></p>
						<?php else : ?>
							<select name="product_id" id="art_lms_order_product_id" class="regular-text" required>
								<option value=""><?php esc_html_e( '— Выберите платёжную кнопку —', 'art-lms' ); ?></option>
								<?php foreach ( $payment_buttons as $button ) : ?>
									<option value="<?php echo esc_attr( (string) $button['id'] ); ?>" <?php selected( (int) $order['product_id'], (int) $button['id'] ); ?>>
										<?php
										echo esc_html(
											sprintf(
												'%s — %s ₽',
												$button['title'],
												'' !== $button['price'] ? $button['price'] : '0'
											)
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'После оплаты покупателю откроются материалы, привязанные к этой кнопке.', 'art-lms' ); ?></p>
							<?php if ( ! $is_new && Art_LMS_Orders::STATUS_PAID === $order['status'] && empty( $order['product_id'] ) ) : ?>
								<p class="description"><?php esc_html_e( 'Заказ уже оплачен, но продукт не был указан. Выберите кнопку и сохраните — доступ будет выдан автоматически.', 'art-lms' ); ?></p>
							<?php endif; ?>
							<p class="art-lms-order-product-materials" id="art_lms_order_product_materials" aria-live="polite"></p>
							<?php if ( empty( $payment_buttons ) ) : ?>
								<p class="description"><?php esc_html_e( 'Сначала создайте платёжную кнопку и привяжите к ней материалы.', 'art-lms' ); ?></p>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="art_lms_order_amount"><?php esc_html_e( 'Сумма (₽)', 'art-lms' ); ?></label></th>
					<td>
						<input type="number" step="0.01" min="0.01" name="amount" id="art_lms_order_amount" class="small-text" value="<?php echo esc_attr( $order['amount'] ); ?>" required>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="art_lms_order_status"><?php esc_html_e( 'Статус', 'art-lms' ); ?></label></th>
					<td>
						<select name="status" id="art_lms_order_status">
							<?php foreach ( Art_LMS_Orders::get_status_labels() as $status_key => $status_label ) : ?>
								<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $order['status'], $status_key ); ?>>
									<?php echo esc_html( $status_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( ! $is_new && Art_LMS_Orders::STATUS_PENDING === $order['status'] ) : ?>
							<p class="description"><?php esc_html_e( 'Смените статус на «Оплачен», чтобы выдать доступ.', 'art-lms' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $is_new ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'При создании', 'art-lms' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="mark_as_paid" value="1" checked>
								<?php esc_html_e( 'Сразу отметить как оплаченный и выдать доступ', 'art-lms' ); ?>
							</label>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Уведомление', 'art-lms' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="send_email" value="1" checked>
							<?php esc_html_e( 'Отправить письмо при подтверждении оплаты', 'art-lms' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary button-large">
					<?php
					if ( $is_new ) {
						esc_html_e( 'Добавить', 'art-lms' );
					} else {
						esc_html_e( 'Сохранить', 'art-lms' );
					}
					?>
				</button>
				<a href="<?php echo esc_url( $list_url ); ?>" class="button button-large"><?php esc_html_e( 'К списку заказов', 'art-lms' ); ?></a>
			</p>
		</div>
	</form>
</div>
