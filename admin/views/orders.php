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
 * @var array<int, string|null> $access_expires_map Order ID => access expires_at.
 * @var bool      $can_manage_orders Whether current user can mutate orders.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$can_manage_orders  = ! empty( $can_manage_orders );
$in_trash           = ! empty( $filters['trash'] );
$add_url            = Art_LMS_Admin_Orders::get_edit_url();
$reset_args         = $in_trash ? array( 'trash' => 1 ) : array();
$reset_url          = Art_LMS_Admin_Orders::get_list_url( $reset_args );
$all_url            = Art_LMS_Admin_Orders::get_list_url();
$trash_url          = Art_LMS_Admin_Orders::get_list_url( array( 'trash' => 1 ) );
$trash_count        = Art_LMS_Orders::count_trashed();
$status_labels      = Art_LMS_Orders::get_status_labels();
$has_filters        = $filters['buyer'] || $filters['status'] || $filters['date_from'] || $filters['date_to'] || $filters['expires_from'] || $filters['expires_to'] || $filters['access_expiry'];
$has_orders         = ! empty( $orders );
$access_expires_map = isset( $access_expires_map ) && is_array( $access_expires_map ) ? $access_expires_map : array();
?>
<div class="wrap art-lms-admin art-lms-orders-page<?php echo $in_trash ? ' art-lms-orders-page--trash' : ''; ?>">
	<h1 class="wp-heading-inline"><?php echo $in_trash ? esc_html__( 'Корзина заказов', 'art-lms' ) : esc_html__( 'Заказы ART LMS', 'art-lms' ); ?></h1>
	<?php if ( $can_manage_orders && ! $in_trash ) : ?>
		<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Добавить', 'art-lms' ); ?></a>
	<?php endif; ?>
	<?php if ( $can_manage_orders && $in_trash && $trash_count > 0 ) : ?>
		<a
			href="<?php echo esc_url( Art_LMS_Admin_Orders::get_empty_trash_url() ); ?>"
			class="page-title-action"
			onclick="return confirm('<?php echo esc_js( __( 'Удалить все заказы из корзины навсегда?', 'art-lms' ) ); ?>');"
		><?php esc_html_e( 'Очистить корзину', 'art-lms' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<ul class="subsubsub art-lms-orders-views">
		<li class="all">
			<a href="<?php echo esc_url( $all_url ); ?>" class="<?php echo $in_trash ? '' : 'current'; ?>">
				<?php esc_html_e( 'Все', 'art-lms' ); ?>
			</a>
			|
		</li>
		<li class="trash">
			<a href="<?php echo esc_url( $trash_url ); ?>" class="<?php echo $in_trash ? 'current' : ''; ?>">
				<?php esc_html_e( 'Корзина', 'art-lms' ); ?>
				<?php if ( $trash_count > 0 ) : ?>
					<span class="count">(<?php echo esc_html( (string) $trash_count ); ?>)</span>
				<?php endif; ?>
			</a>
		</li>
	</ul>

	<form method="get" class="art-lms-orders-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( Art_LMS_Admin_Orders::PAGE_LIST ); ?>">
		<input type="hidden" name="orderby" value="<?php echo esc_attr( $filters['orderby'] ); ?>">
		<input type="hidden" name="order" value="<?php echo esc_attr( $filters['order'] ); ?>">
		<?php if ( $in_trash ) : ?>
			<input type="hidden" name="trash" value="1">
		<?php endif; ?>

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
				<label for="art_lms_orders_date_from"><?php esc_html_e( 'Создан', 'art-lms' ); ?></label>
				<input
					type="text"
					name="date_from"
					id="art_lms_orders_date_from"
					class="art-lms-date-input"
					value="<?php echo esc_attr( $filters['date_from'] ); ?>"
					autocomplete="off"
					placeholder="<?php esc_attr_e( 'гггг-мм-дд', 'art-lms' ); ?>"
				>
				<span aria-hidden="true">—</span>
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
		</div>

		<div class="art-lms-orders-filters-row">
			<label class="screen-reader-text" for="art_lms_orders_access_expiry"><?php esc_html_e( 'Срок доступа', 'art-lms' ); ?></label>
			<select name="access_expiry" id="art_lms_orders_access_expiry">
				<option value="" <?php selected( $filters['access_expiry'], '' ); ?>><?php esc_html_e( 'Любой срок доступа', 'art-lms' ); ?></option>
				<option value="expired" <?php selected( $filters['access_expiry'], 'expired' ); ?>><?php esc_html_e( 'Истёк', 'art-lms' ); ?></option>
				<option value="active" <?php selected( $filters['access_expiry'], 'active' ); ?>><?php esc_html_e( 'Ещё действует', 'art-lms' ); ?></option>
				<option value="none" <?php selected( $filters['access_expiry'], 'none' ); ?>><?php esc_html_e( 'Бессрочный / без срока', 'art-lms' ); ?></option>
			</select>

			<span class="art-lms-orders-date-filter">
				<label for="art_lms_orders_expires_from"><?php esc_html_e( 'Истекает', 'art-lms' ); ?></label>
				<input
					type="text"
					name="expires_from"
					id="art_lms_orders_expires_from"
					class="art-lms-date-input"
					value="<?php echo esc_attr( $filters['expires_from'] ); ?>"
					autocomplete="off"
					placeholder="<?php esc_attr_e( 'гггг-мм-дд', 'art-lms' ); ?>"
				>
				<span aria-hidden="true">—</span>
				<input
					type="text"
					name="expires_to"
					id="art_lms_orders_expires_to"
					class="art-lms-date-input"
					value="<?php echo esc_attr( $filters['expires_to'] ); ?>"
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

	<?php if ( $has_orders ) : ?>
		<?php if ( $can_manage_orders ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="art-lms-orders-bulk-form" class="art-lms-orders-bulk-form">
			<input type="hidden" name="action" value="art_lms_bulk_delete_orders">
			<?php wp_nonce_field( 'art_lms_bulk_delete_orders', 'art_lms_bulk_orders_nonce' ); ?>
			<input type="hidden" name="buyer" value="<?php echo esc_attr( $filters['buyer'] ); ?>">
			<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>">
			<input type="hidden" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
			<input type="hidden" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
			<input type="hidden" name="expires_from" value="<?php echo esc_attr( $filters['expires_from'] ); ?>">
			<input type="hidden" name="expires_to" value="<?php echo esc_attr( $filters['expires_to'] ); ?>">
			<input type="hidden" name="access_expiry" value="<?php echo esc_attr( $filters['access_expiry'] ); ?>">
			<?php if ( $in_trash ) : ?>
				<input type="hidden" name="trash" value="1">
			<?php endif; ?>
			<input type="hidden" name="orderby" value="<?php echo esc_attr( $filters['orderby'] ); ?>">
			<input type="hidden" name="order" value="<?php echo esc_attr( $filters['order'] ); ?>">
			<input type="hidden" name="paged" value="<?php echo esc_attr( (string) $page ); ?>">
		<?php endif; ?>

			<?php Art_LMS_Admin_Orders::render_list_pagination( $filters, $total, $page, $pages, 'top', $can_manage_orders ); ?>

			<table class="widefat striped art-lms-orders-table<?php echo $can_manage_orders ? ' art-lms-orders-table--bulk' : ''; ?>">
				<thead>
					<tr>
						<?php if ( $can_manage_orders ) : ?>
						<td id="cb" class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="art-lms-cb-select-all-top"><?php esc_html_e( 'Выбрать все', 'art-lms' ); ?></label>
							<input id="art-lms-cb-select-all-top" type="checkbox" class="art-lms-orders-cb-select-all">
						</td>
						<?php endif; ?>
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
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'product' ) ); ?>">
							<?php esc_html_e( 'Продукт', 'art-lms' ); ?>
						</th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'access_expiry' ) ); ?>">
							<?php esc_html_e( 'Доступ истекает', 'art-lms' ); ?>
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
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'gateway' ) ); ?>">
							<?php esc_html_e( 'Платёжный шлюз', 'art-lms' ); ?>
						</th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_sort_classes( 'payment_label', $filters ) ); ?>">
							<a href="<?php echo esc_url( Art_LMS_Admin_Orders::get_list_sort_link( 'payment_label', $filters ) ); ?>">
								<span><?php esc_html_e( 'ID для платёжной системы', 'art-lms' ); ?></span>
								<span class="art-lms-sort-indicator" aria-hidden="true"></span>
							</a>
						</th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'actions' ) ); ?>">
							<?php esc_html_e( 'Действия', 'art-lms' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $orders as $order ) : ?>
						<?php
						$edit_url     = Art_LMS_Admin_Orders::get_edit_url( (int) $order->id );
						$view_url     = Art_LMS_Admin_Orders::get_view_url( (int) $order->id );
						$delete_url   = $in_trash
							? Art_LMS_Admin_Orders::get_delete_permanently_url( (int) $order->id, $filters )
							: Art_LMS_Admin_Orders::get_delete_url( (int) $order->id, $filters );
						$restore_url  = $in_trash
							? Art_LMS_Admin_Orders::get_restore_url( (int) $order->id, $filters )
							: '';
						$user         = $order->user_id ? get_userdata( (int) $order->user_id ) : false;
						$cb_id        = 'art-lms-order-cb-' . (int) $order->id;
						$product_name = Art_LMS_Payment_Buttons::get_admin_title( (int) $order->product_id );
						?>
						<?php
						$expires_display = Art_LMS_Access::get_admin_expires_display(
							$access_expires_map[ (int) $order->id ] ?? null
						);
						$access_empty = ( 'none' === $expires_display['state'] );
						$buyer_name      = $order->name ? $order->name : $order->email;
						$profile_url     = $user ? get_edit_user_link( (int) $user->ID ) : '';
						?>
						<tr class="art-lms-order-row">
							<?php if ( $can_manage_orders ) : ?>
							<th scope="row" class="check-column" data-label="<?php esc_attr_e( 'Выбор', 'art-lms' ); ?>">
								<label class="screen-reader-text" for="<?php echo esc_attr( $cb_id ); ?>">
									<?php
									printf(
										/* translators: %d: order ID */
										esc_html__( 'Выбрать заказ #%d', 'art-lms' ),
										(int) $order->id
									);
									?>
								</label>
								<input
									type="checkbox"
									name="order_ids[]"
									id="<?php echo esc_attr( $cb_id ); ?>"
									class="art-lms-order-cb"
									value="<?php echo esc_attr( (string) (int) $order->id ); ?>"
								>
							</th>
							<?php endif; ?>
							<td class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'created_at' ) ); ?>" data-label="<?php esc_attr_e( 'Дата', 'art-lms' ); ?>">
								<?php echo esc_html( Art_LMS_Orders::format_admin_datetime( $order->created_at ) ); ?>
							</td>
							<td class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'buyer' ) ); ?>" data-label="<?php esc_attr_e( 'Покупатель', 'art-lms' ); ?>">
								<div class="art-lms-order-buyer">
									<span class="art-lms-order-buyer__name"><?php echo esc_html( $buyer_name ); ?></span>
									<?php if ( $order->email && $buyer_name !== $order->email ) : ?>
										<span class="art-lms-order-buyer__email"><?php echo esc_html( $order->email ); ?></span>
									<?php endif; ?>
									<span class="art-lms-order-buyer__links">
										<?php if ( $profile_url ) : ?>
											<a href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'Открыть профиль', 'art-lms' ); ?></a>
										<?php endif; ?>
										<?php
										/**
										 * Extra buyer action links (e.g. ART MAX BOT chat).
										 *
										 * @param object        $order Order row.
										 * @param WP_User|false $user  Buyer WP user, or false when unknown.
										 */
										do_action( 'art_lms_orders_buyer_actions', $order, $user );
										?>
									</span>
								</div>
							</td>
							<td class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'product' ) ); ?>" data-label="<?php esc_attr_e( 'Продукт', 'art-lms' ); ?>">
								<?php
								if ( $product_name ) {
									echo esc_html( $product_name );
								} else {
									echo '&mdash;';
								}
								?>
							</td>
							<td class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'access_expiry' ) ); ?><?php echo $access_empty ? ' is-empty-meta' : ''; ?>" data-label="<?php esc_attr_e( 'Доступ истекает', 'art-lms' ); ?>">
								<?php if ( 'expired' === $expires_display['state'] ) : ?>
									<span class="art-lms-access-expired"><?php echo esc_html( $expires_display['label'] ); ?></span>
								<?php else : ?>
									<?php echo esc_html( $expires_display['label'] ); ?>
								<?php endif; ?>
							</td>
							<td class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'amount' ) ); ?>" data-label="<?php esc_attr_e( 'Сумма', 'art-lms' ); ?>">
								<?php echo esc_html( Art_LMS_Orders::format_amount( $order->amount, $order->currency ?? 'RUB' ) ); ?>
							</td>
							<td class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'status' ) ); ?>" data-label="<?php esc_attr_e( 'Статус', 'art-lms' ); ?>">
								<span class="art-lms-order-status art-lms-order-status--<?php echo esc_attr( $order->status ); ?>">
									<?php echo esc_html( Art_LMS_Orders::get_status_label( $order->status ) ); ?>
								</span>
							</td>
							<td class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'gateway' ) ); ?>" data-label="<?php esc_attr_e( 'Платёжный шлюз', 'art-lms' ); ?>">
								<?php echo esc_html( Art_LMS_Orders::get_payment_gateway_label( $order ) ); ?>
							</td>
							<td class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'payment_label' ) ); ?>" data-label="<?php esc_attr_e( 'ID для платёжной системы', 'art-lms' ); ?>">
								<code><?php echo esc_html( $order->payment_label ); ?></code>
							</td>
							<td class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'actions', array( 'art-lms-actions' ) ) ); ?>" data-label="<?php esc_attr_e( 'Действия', 'art-lms' ); ?>">
								<a
									href="<?php echo esc_url( $view_url ); ?>"
									class="art-lms-action-icon"
									title="<?php esc_attr_e( 'Просмотреть', 'art-lms' ); ?>"
								>
									<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'Просмотреть', 'art-lms' ); ?></span>
								</a>
								<?php if ( $can_manage_orders ) : ?>
									<?php if ( $in_trash ) : ?>
								<a
									href="<?php echo esc_url( $restore_url ); ?>"
									class="art-lms-action-icon"
									title="<?php esc_attr_e( 'Восстановить', 'art-lms' ); ?>"
								>
									<span class="dashicons dashicons-undo" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'Восстановить', 'art-lms' ); ?></span>
								</a>
								<a
									href="<?php echo esc_url( $delete_url ); ?>"
									class="art-lms-action-icon art-lms-action-icon--delete submitdelete"
									title="<?php esc_attr_e( 'Удалить навсегда', 'art-lms' ); ?>"
									onclick="return confirm('<?php echo esc_js( __( 'Удалить этот заказ безвозвратно?', 'art-lms' ) ); ?>');"
								>
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'Удалить навсегда', 'art-lms' ); ?></span>
								</a>
									<?php else : ?>
								<a
									href="<?php echo esc_url( $edit_url ); ?>"
									class="art-lms-action-icon"
									title="<?php esc_attr_e( 'Изменить', 'art-lms' ); ?>"
								>
									<span class="dashicons dashicons-edit" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'Изменить', 'art-lms' ); ?></span>
								</a>
								<a
									href="<?php echo esc_url( $delete_url ); ?>"
									class="art-lms-action-icon art-lms-action-icon--delete submitdelete"
									title="<?php esc_attr_e( 'В корзину', 'art-lms' ); ?>"
									onclick="return confirm('<?php echo esc_js( __( 'Переместить этот заказ в корзину?', 'art-lms' ) ); ?>');"
								>
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'В корзину', 'art-lms' ); ?></span>
								</a>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<?php if ( $can_manage_orders ) : ?>
						<td class="manage-column column-cb check-column">
							<label class="screen-reader-text" for="art-lms-cb-select-all-bottom"><?php esc_html_e( 'Выбрать все', 'art-lms' ); ?></label>
							<input id="art-lms-cb-select-all-bottom" type="checkbox" class="art-lms-orders-cb-select-all">
						</td>
						<?php endif; ?>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'created_at' ) ); ?>"><?php esc_html_e( 'Дата', 'art-lms' ); ?></th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'buyer' ) ); ?>"><?php esc_html_e( 'Покупатель', 'art-lms' ); ?></th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'product' ) ); ?>"><?php esc_html_e( 'Продукт', 'art-lms' ); ?></th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'access_expiry' ) ); ?>"><?php esc_html_e( 'Доступ истекает', 'art-lms' ); ?></th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'amount' ) ); ?>"><?php esc_html_e( 'Сумма', 'art-lms' ); ?></th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'status' ) ); ?>"><?php esc_html_e( 'Статус', 'art-lms' ); ?></th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'gateway' ) ); ?>"><?php esc_html_e( 'Платёжный шлюз', 'art-lms' ); ?></th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'payment_label' ) ); ?>"><?php esc_html_e( 'ID для платёжной системы', 'art-lms' ); ?></th>
						<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'actions' ) ); ?>"><?php esc_html_e( 'Действия', 'art-lms' ); ?></th>
					</tr>
				</tfoot>
			</table>

			<?php Art_LMS_Admin_Orders::render_list_pagination( $filters, $total, $page, $pages, 'bottom', $can_manage_orders ); ?>
		<?php if ( $can_manage_orders ) : ?>
		</form>
		<?php endif; ?>
	<?php else : ?>
		<?php Art_LMS_Admin_Orders::render_list_pagination( $filters, $total, $page, $pages, 'top' ); ?>

		<table class="widefat striped art-lms-orders-table">
			<thead>
				<tr>
					<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'created_at' ) ); ?>"><?php esc_html_e( 'Дата', 'art-lms' ); ?></th>
					<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'buyer' ) ); ?>"><?php esc_html_e( 'Покупатель', 'art-lms' ); ?></th>
					<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'product' ) ); ?>"><?php esc_html_e( 'Продукт', 'art-lms' ); ?></th>
					<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'access_expiry' ) ); ?>"><?php esc_html_e( 'Доступ истекает', 'art-lms' ); ?></th>
					<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'amount' ) ); ?>"><?php esc_html_e( 'Сумма', 'art-lms' ); ?></th>
					<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'status' ) ); ?>"><?php esc_html_e( 'Статус', 'art-lms' ); ?></th>
					<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'gateway' ) ); ?>"><?php esc_html_e( 'Платёжный шлюз', 'art-lms' ); ?></th>
					<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'payment_label' ) ); ?>"><?php esc_html_e( 'ID для платёжной системы', 'art-lms' ); ?></th>
					<th scope="col" class="<?php echo esc_attr( Art_LMS_Admin_Orders::get_list_column_class( 'actions' ) ); ?>"><?php esc_html_e( 'Действия', 'art-lms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colspan="<?php echo esc_attr( (string) max( 1, Art_LMS_Admin_Orders::get_visible_list_columns_count() ) ); ?>">
						<?php
						if ( $in_trash ) {
							esc_html_e( 'Корзина пуста.', 'art-lms' );
						} elseif ( $has_filters ) {
							esc_html_e( 'Заказы не найдены. Измените фильтры.', 'art-lms' );
						} else {
							esc_html_e( 'Заказов пока нет.', 'art-lms' );
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php Art_LMS_Admin_Orders::render_list_pagination( $filters, $total, $page, $pages, 'bottom' ); ?>
	<?php endif; ?>
</div>
