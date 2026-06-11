<?php
/**
 * Orders admin list.
 *
 * @package Art_LMS
 *
 * @var array $orders   Orders list.
 * @var array $filters  Active list filters.
 * @var int       $total    Total matching orders.
 * @var int       $per_page Orders per page.
 * @var int       $page     Current page.
 * @var int       $pages    Total pages.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$add_url       = Art_LMS_Admin_Orders::get_edit_url();
$reset_url     = Art_LMS_Admin_Orders::get_list_url();
$status_labels = Art_LMS_Orders::get_status_labels();
$has_filters   = $filters['buyer'] || $filters['status'] || $filters['date_from'] || $filters['date_to'];
?>
<div class="wrap art-lms-admin art-lms-orders-page">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Заказы ART LMS', 'art-lms' ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Добавить', 'art-lms' ); ?></a>
	<hr class="wp-header-end">

	<form method="get" class="art-lms-orders-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( Art_LMS_Admin_Orders::PAGE_LIST ); ?>">
		<input type="hidden" name="orderby" value="<?php echo esc_attr( $filters['orderby'] ); ?>">
		<input type="hidden" name="order" value="<?php echo esc_attr( $filters['order'] ); ?>">

		<div class="art-lms-orders-filters-row">
			<label class="screen-reader-text" for="art_lms_orders_buyer"><?php esc_html_e( 'Покупатель или ID для платёжной системы', 'art-lms' ); ?></label>
			<input
				type="search"
				name="buyer"
				id="art_lms_orders_buyer"
				class="regular-text"
				value="<?php echo esc_attr( $filters['buyer'] ); ?>"
				placeholder="<?php esc_attr_e( 'Email, логин или ID для платёжной системы', 'art-lms' ); ?>"
			>

			<label class="screen-reader-text" for="art_lms_orders_status"><?php esc_html_e( 'Статус', 'art-lms' ); ?></label>
			<select name="status" id="art_lms_orders_status">
				<option value=""><?php esc_html_e( 'Все статусы', 'art-lms' ); ?></option>
				<?php foreach ( $status_labels as $status_key => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $filters['status'], $status_key ); ?>>
						<?php echo esc_html( $status_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<span class="art-lms-orders-date-filter">
				<label for="art_lms_orders_date_from"><?php esc_html_e( 'С', 'art-lms' ); ?></label>
				<input
					type="text"
					name="date_from"
					id="art_lms_orders_date_from"
					class="art-lms-date-input"
					value="<?php echo esc_attr( $filters['date_from'] ); ?>"
					autocomplete="off"
					placeholder="<?php esc_attr_e( 'гггг-мм-дд', 'art-lms' ); ?>"
				>
				<label for="art_lms_orders_date_to"><?php esc_html_e( 'По', 'art-lms' ); ?></label>
				<input
					type="text"
					name="date_to"
					id="art_lms_orders_date_to"
					class="art-lms-date-input"
					value="<?php echo esc_attr( $filters['date_to'] ); ?>"
					autocomplete="off"
					placeholder="<?php esc_attr_e( 'гггг-мм-дд', 'art-lms' ); ?>"
				>
			</span>

			<button type="submit" class="button"><?php esc_html_e( 'Найти', 'art-lms' ); ?></button>

			<?php if ( $has_filters ) : ?>
				<a href="<?php echo esc_url( $reset_url ); ?>" class="button"><?php esc_html_e( 'Сбросить', 'art-lms' ); ?></a>
			<?php endif; ?>
		</div>
	</form>

	<?php Art_LMS_Admin_Orders::render_list_pagination( $filters, $total, $page, $pages, 'top' ); ?>

	<table class="widefat striped art-lms-orders-table">
		<thead>
			<tr>
				<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_sort_classes( 'created_at', $filters ) ); ?>">
					<a href="<?php echo esc_url( Art_LMS_Admin_Orders::get_list_sort_link( 'created_at', $filters ) ); ?>">
						<span><?php esc_html_e( 'Дата', 'art-lms' ); ?></span>
						<span class="art-lms-sort-indicator" aria-hidden="true"></span>
					</a>
				</th>
				<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_sort_classes( 'buyer', $filters ) ); ?>">
					<a href="<?php echo esc_url( Art_LMS_Admin_Orders::get_list_sort_link( 'buyer', $filters ) ); ?>">
						<span><?php esc_html_e( 'Покупатель', 'art-lms' ); ?></span>
						<span class="art-lms-sort-indicator" aria-hidden="true"></span>
					</a>
				</th>
				<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_sort_classes( 'amount', $filters ) ); ?>">
					<a href="<?php echo esc_url( Art_LMS_Admin_Orders::get_list_sort_link( 'amount', $filters ) ); ?>">
						<span><?php esc_html_e( 'Сумма', 'art-lms' ); ?></span>
						<span class="art-lms-sort-indicator" aria-hidden="true"></span>
					</a>
				</th>
				<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_sort_classes( 'status', $filters ) ); ?>">
					<a href="<?php echo esc_url( Art_LMS_Admin_Orders::get_list_sort_link( 'status', $filters ) ); ?>">
						<span><?php esc_html_e( 'Статус', 'art-lms' ); ?></span>
						<span class="art-lms-sort-indicator" aria-hidden="true"></span>
					</a>
				</th>
				<th scope="col"><?php esc_html_e( 'Платёжный шлюз', 'art-lms' ); ?></th>
				<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_sort_classes( 'payment_label', $filters ) ); ?>">
					<a href="<?php echo esc_url( Art_LMS_Admin_Orders::get_list_sort_link( 'payment_label', $filters ) ); ?>">
						<span><?php esc_html_e( 'ID для платёжной системы', 'art-lms' ); ?></span>
						<span class="art-lms-sort-indicator" aria-hidden="true"></span>
					</a>
				</th>
				<th scope="col"><?php esc_html_e( 'Действия', 'art-lms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $orders ) ) : ?>
				<tr>
					<td colspan="7">
						<?php
						if ( $has_filters ) {
							esc_html_e( 'Заказы не найдены. Измените фильтры.', 'art-lms' );
						} else {
							esc_html_e( 'Заказов пока нет.', 'art-lms' );
						}
						?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $orders as $order ) : ?>
					<?php
					$edit_url = Art_LMS_Admin_Orders::get_edit_url( (int) $order->id );
					$user     = $order->user_id ? get_userdata( (int) $order->user_id ) : false;
					?>
					<tr>
						<td><?php echo esc_html( Art_LMS_Orders::format_admin_datetime( $order->created_at ) ); ?></td>
						<td>
							<?php echo esc_html( $order->name ? $order->name : $order->email ); ?><br>
							<small><?php echo esc_html( $order->email ); ?></small>
							<?php
							$profile_url = $user ? get_edit_user_link( (int) $user->ID ) : '';
							if ( $profile_url ) :
								?>
								<br><small><a href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Открыть профиль', 'art-lms' ); ?></a></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format( (float) $order->amount, 2, '.', ' ' ) ); ?> ₽</td>
						<td>
							<span class="art-lms-order-status art-lms-order-status--<?php echo esc_attr( $order->status ); ?>">
								<?php echo esc_html( Art_LMS_Orders::get_status_label( $order->status ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( Art_LMS_Orders::get_payment_gateway_label( $order ) ); ?></td>
						<td><code><?php echo esc_html( $order->payment_label ); ?></code></td>
						<td class="art-lms-actions">
							<a href="<?php echo esc_url( Art_LMS_Admin_Orders::get_view_url( (int) $order->id ) ); ?>"><?php esc_html_e( 'Просмотреть', 'art-lms' ); ?></a>
							<span class="art-lms-actions-sep" aria-hidden="true">|</span>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Изменить', 'art-lms' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php Art_LMS_Admin_Orders::render_list_pagination( $filters, $total, $page, $pages, 'bottom' ); ?>
</div>
