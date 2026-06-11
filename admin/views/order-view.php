<?php
/**
 * Read-only order details view.
 *
 * @package Art_LMS
 *
 * @var object $order        Order object.
 * @var array  $form_groups  Parsed form field groups.
 * @var string $list_url     Orders list URL.
 * @var string $edit_url     Order edit URL.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$status_labels = Art_LMS_Orders::get_status_labels();
$product_name  = Art_LMS_Payment_Buttons::get_product_name( (int) $order->product_id );
$can_send_email = Art_LMS_Orders::STATUS_PAID !== $order->status;
?>
<div class="wrap art-lms-admin art-lms-order-view-page">
	<h1>
		<?php
		printf(
			/* translators: %d: order ID */
			esc_html__( 'Заказ #%d', 'art-lms' ),
			(int) $order->id
		);
		?>
	</h1>

	<?php $delete_url = Art_LMS_Admin_Orders::get_delete_url( (int) $order->id ); ?>
	<p class="art-lms-order-view-actions">
		<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( '← К списку заказов', 'art-lms' ); ?></a>
		<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-primary"><?php esc_html_e( 'Изменить', 'art-lms' ); ?></a>
		<a
			href="<?php echo esc_url( $delete_url ); ?>"
			class="button button-link-delete"
			onclick="return confirm('<?php echo esc_js( __( 'Удалить этот заказ безвозвратно?', 'art-lms' ) ); ?>');"
		><?php esc_html_e( 'Удалить', 'art-lms' ); ?></a>
	</p>

	<div class="art-lms-panel">
		<h2><?php esc_html_e( 'Основные данные', 'art-lms' ); ?></h2>
		<table class="widefat striped art-lms-order-view-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Дата создания', 'art-lms' ); ?></th>
					<td><?php echo esc_html( Art_LMS_Orders::format_admin_datetime( $order->created_at ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Статус', 'art-lms' ); ?></th>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="art-lms-order-status-form">
							<?php wp_nonce_field( 'art_lms_update_order_status', 'art_lms_order_status_nonce' ); ?>
							<input type="hidden" name="action" value="art_lms_update_order_status">
							<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->id ); ?>">

							<div class="art-lms-order-status-form__controls">
								<select name="status" id="art_lms_order_view_status" class="art-lms-order-status-select">
									<?php foreach ( $status_labels as $status_key => $status_label ) : ?>
										<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $order->status, $status_key ); ?>>
											<?php echo esc_html( $status_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>

								<span class="art-lms-order-status art-lms-order-status-preview art-lms-order-status--<?php echo esc_attr( $order->status ); ?>" id="art_lms_order_status_preview">
									<?php echo esc_html( Art_LMS_Orders::get_status_label( $order->status ) ); ?>
								</span>

								<button type="submit" class="button"><?php esc_html_e( 'Сохранить', 'art-lms' ); ?></button>
							</div>

							<?php if ( $can_send_email ) : ?>
								<p class="description art-lms-order-status-form__hint">
									<label>
										<input type="checkbox" name="send_email" value="1" checked>
										<?php esc_html_e( 'Отправить письма при смене статуса на «Оплачен»', 'art-lms' ); ?>
									</label>
								</p>
							<?php endif; ?>
						</form>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Сумма', 'art-lms' ); ?></th>
					<td><?php echo esc_html( number_format( (float) $order->amount, 2, '.', ' ' ) ); ?> ₽</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Платёжная кнопка', 'art-lms' ); ?></th>
					<td><?php echo esc_html( $product_name ? $product_name : '—' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Платёжный шлюз', 'art-lms' ); ?></th>
					<td><?php echo esc_html( Art_LMS_Orders::get_payment_gateway_label( $order ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'ID для платёжной системы', 'art-lms' ); ?></th>
					<td><code><?php echo esc_html( $order->payment_label ); ?></code></td>
				</tr>
				<?php if ( ! empty( $order->paid_at ) && '0000-00-00 00:00:00' !== $order->paid_at ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Оплачен', 'art-lms' ); ?></th>
						<td><?php echo esc_html( Art_LMS_Orders::format_admin_datetime( $order->paid_at ) ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div class="art-lms-panel">
		<h2><?php esc_html_e( 'Поля формы', 'art-lms' ); ?></h2>

		<?php if ( empty( $form_groups['fields'] ) ) : ?>
			<p class="description"><?php esc_html_e( 'Данные формы для этого заказа не сохранены.', 'art-lms' ); ?></p>
		<?php else : ?>
			<table class="widefat striped art-lms-order-view-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Поле', 'art-lms' ); ?></th>
						<th><?php esc_html_e( 'Значение', 'art-lms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $form_groups['fields'] as $row ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
							<td><?php echo esc_html( $row['value'] !== '' ? $row['value'] : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="art-lms-panel">
		<h2><?php esc_html_e( 'Согласия', 'art-lms' ); ?></h2>

		<?php if ( empty( $form_groups['consents'] ) ) : ?>
			<p class="description"><?php esc_html_e( 'Согласия для этого заказа не сохранены.', 'art-lms' ); ?></p>
		<?php else : ?>
			<table class="widefat striped art-lms-order-view-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Согласие', 'art-lms' ); ?></th>
						<th><?php esc_html_e( 'Ответ', 'art-lms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $form_groups['consents'] as $row ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
							<td><?php echo esc_html( $row['value'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<script>
(function () {
	var select = document.getElementById('art_lms_order_view_status');
	var preview = document.getElementById('art_lms_order_status_preview');
	var labels = <?php
	echo wp_json_encode(
		$status_labels,
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);
	?>;

	if (!select || !preview) {
		return;
	}

	select.addEventListener('change', function () {
		var key = select.value;

		preview.className = 'art-lms-order-status art-lms-order-status-preview art-lms-order-status--' + key;
		preview.textContent = labels[key] || key;
	});
})();
</script>
