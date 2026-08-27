<?php
/**
 * Admin orders management.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin screens require capability checks; list filters use sanitized GET parameters.

/**
 * Class Art_LMS_Admin_Orders
 */
class Art_LMS_Admin_Orders {

	const PAGE_LIST = 'art-lms-orders';
	const PAGE_EDIT = 'art-lms-order-edit';
	const PAGE_VIEW = 'art-lms-order-view';

	const SCREEN_OPTION_PER_PAGE = 'art_lms_orders_per_page';
	const DEFAULT_PER_PAGE       = 20;
	const MAX_PER_PAGE           = 200;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'load-' . ART_LMS_ADMIN_MENU_SLUG . '_page_' . self::PAGE_LIST, array( __CLASS__, 'load_list_page' ) );
		add_filter( 'set_screen_option_' . self::SCREEN_OPTION_PER_PAGE, array( __CLASS__, 'set_screen_option_per_page' ), 10, 3 );
		add_action( 'admin_post_art_lms_save_order', array( __CLASS__, 'handle_save_order' ) );
		add_action( 'admin_post_art_lms_mark_order_paid', array( __CLASS__, 'handle_mark_order_paid' ) );
		add_action( 'admin_post_art_lms_delete_order', array( __CLASS__, 'handle_delete_order' ) );
		add_action( 'admin_post_art_lms_bulk_delete_orders', array( __CLASS__, 'handle_bulk_delete_orders' ) );
		add_action( 'admin_post_art_lms_update_order_status', array( __CLASS__, 'handle_update_order_status' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
	}

	/**
	 * Prepare orders list screen (Screen Options).
	 */
	public static function load_list_page() {
		if ( ! Art_LMS_Security::can_manage() ) {
			return;
		}

		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Заказов на странице', 'art-lms' ),
				'default' => self::DEFAULT_PER_PAGE,
				'option'  => self::SCREEN_OPTION_PER_PAGE,
			)
		);

		$screen = get_current_screen();

		if ( $screen instanceof WP_Screen ) {
			add_filter( 'manage_' . $screen->id . '_columns', array( __CLASS__, 'get_list_columns' ) );
		}
	}

	/**
	 * Columns available in Screen Options for the orders list.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function get_list_columns( $columns = array() ) {
		unset( $columns );

		return array(
			'created_at'    => __( 'Дата', 'art-lms' ),
			'buyer'         => __( 'Покупатель', 'art-lms' ),
			'product'       => __( 'Продукт', 'art-lms' ),
			'access_expiry' => __( 'Доступ истекает', 'art-lms' ),
			'amount'        => __( 'Сумма', 'art-lms' ),
			'status'        => __( 'Статус', 'art-lms' ),
			'gateway'       => __( 'Платёжный шлюз', 'art-lms' ),
			'payment_label' => __( 'ID для платёжной системы', 'art-lms' ),
			'actions'       => __( 'Действия', 'art-lms' ),
		);
	}

	/**
	 * Hidden orders-list columns for the current user.
	 *
	 * @return array<int, string>
	 */
	public static function get_hidden_list_columns() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen instanceof WP_Screen ) {
			return array();
		}

		$hidden = get_hidden_columns( $screen );

		return is_array( $hidden ) ? $hidden : array();
	}

	/**
	 * Whether a list column is visible for the current user.
	 *
	 * @param string $column Column key.
	 * @return bool
	 */
	public static function is_list_column_visible( $column ) {
		return ! in_array( (string) $column, self::get_hidden_list_columns(), true );
	}

	/**
	 * CSS classes for an orders-list column cell or header.
	 *
	 * @param string               $column Column key.
	 * @param array<int, string>   $extra  Extra classes.
	 * @return string
	 */
	public static function get_list_column_class( $column, array $extra = array() ) {
		$classes = array_merge(
			array(
				'column-' . sanitize_html_class( (string) $column ),
			),
			$extra
		);

		if ( ! self::is_list_column_visible( $column ) ) {
			$classes[] = 'hidden';
		}

		return implode( ' ', array_filter( array_map( 'sanitize_html_class', $classes ) ) );
	}

	/**
	 * Number of currently visible data columns (excluding checkbox).
	 *
	 * @return int
	 */
	public static function get_visible_list_columns_count() {
		$count = 0;

		foreach ( array_keys( self::get_list_columns() ) as $column ) {
			if ( self::is_list_column_visible( $column ) ) {
				++$count;
			}
		}

		return max( 1, $count );
	}

	/**
	 * Save orders per page screen option.
	 *
	 * @param mixed  $screen_option Saved value or false.
	 * @param string $option        Option name.
	 * @param mixed  $value         Submitted value.
	 * @return int|false
	 */
	public static function set_screen_option_per_page( $screen_option, $option, $value ) {
		unset( $screen_option, $option );

		return max( 1, min( self::MAX_PER_PAGE, absint( $value ) ) );
	}

	/**
	 * Get orders per page for current user.
	 *
	 * @return int
	 */
	public static function get_list_per_page() {
		$per_page = (int) get_user_option( self::SCREEN_OPTION_PER_PAGE );

		if ( $per_page < 1 ) {
			$per_page = self::DEFAULT_PER_PAGE;
		}

		return max( 1, min( self::MAX_PER_PAGE, $per_page ) );
	}

	/**
	 * Register admin REST routes.
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'art-lms/v1',
			'/admin/lookup-buyer',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_lookup_buyer' ),
				'permission_callback' => array( 'Art_LMS_Security', 'can_manage' ),
				'args'                => array(
					'identity' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Lookup buyer by email or login for admin order form.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function rest_lookup_buyer( WP_REST_Request $request ) {
		$identity = trim( (string) $request->get_param( 'identity' ) );

		if ( '' === $identity ) {
			return new WP_REST_Response(
				array(
					'found'   => false,
					'message' => __( 'Укажите email или логин.', 'art-lms' ),
				),
				400
			);
		}

		$details = Art_LMS_User_Registration::get_buyer_details_for_form( $identity );

		if ( $details['found'] ) {
			$details['message'] = sprintf(
				/* translators: 1: user name or login, 2: email */
				__( 'Пользователь найден: %1$s (%2$s)', 'art-lms' ),
				$details['name'] ? $details['name'] : $details['login'],
				$details['email']
			);
		} else {
			$details['message'] = is_email( $identity )
				? __( 'Аккаунт не найден. Будет создан новый пользователь с этим email.', 'art-lms' )
				: __( 'Пользователь не найден.', 'art-lms' );
		}

		return rest_ensure_response( $details );
	}

	/**
	 * Render orders list page.
	 */
	public static function render_list_page() {
		if ( ! Art_LMS_Security::can_manage() ) {
			return;
		}

		$filters = self::parse_list_filters();
		$result  = Art_LMS_Orders::query_list( $filters );
		$page    = $result['page'];
		$pages   = $result['pages'];

		if ( $page > $pages ) {
			wp_safe_redirect( self::get_list_url( array_merge( $filters, array( 'paged' => $pages ) ) ) );
			exit;
		}

		$orders   = $result['items'];
		$total    = $result['total'];
		$per_page = $result['per_page'];

		$order_ids           = array_map(
			static function ( $order ) {
				return (int) $order->id;
			},
			$orders
		);
		$access_expires_map = Art_LMS_Access::get_expires_map_for_orders( $order_ids );

		include ART_LMS_PLUGIN_DIR . 'admin/views/orders.php';
	}

	/**
	 * Render read-only order details page.
	 */
	public static function render_view_page() {
		if ( ! Art_LMS_Security::can_manage() ) {
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order    = $order_id ? Art_LMS_Orders::get( $order_id ) : null;

		if ( ! $order ) {
			wp_die( esc_html__( 'Заказ не найден.', 'art-lms' ) );
		}

		$form_groups = Art_LMS_Order_Form_Data::get_display_groups( $order );
		$list_url    = self::get_list_url();
		$edit_url    = self::get_edit_url( $order_id );

		include ART_LMS_PLUGIN_DIR . 'admin/views/order-view.php';
	}

	/**
	 * Parse list filters from request.
	 *
	 * @return array
	 */
	public static function parse_list_filters() {
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';

		if ( ! in_array( $orderby, Art_LMS_Orders::get_list_orderby_keys(), true ) ) {
			$orderby = 'created_at';
		}

		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC';

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		if ( '' !== $status && ! isset( Art_LMS_Orders::get_status_labels()[ $status ] ) ) {
			$status = '';
		}

		$date_from = isset( $_GET['date_from'] ) ? Art_LMS_Orders::sanitize_list_date( sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? Art_LMS_Orders::sanitize_list_date( sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) ) : '';

		if ( $date_from && $date_to && $date_from > $date_to ) {
			$swap      = $date_from;
			$date_from = $date_to;
			$date_to   = $swap;
		}

		$expires_from = isset( $_GET['expires_from'] ) ? Art_LMS_Orders::sanitize_list_date( sanitize_text_field( wp_unslash( $_GET['expires_from'] ) ) ) : '';
		$expires_to   = isset( $_GET['expires_to'] ) ? Art_LMS_Orders::sanitize_list_date( sanitize_text_field( wp_unslash( $_GET['expires_to'] ) ) ) : '';

		if ( $expires_from && $expires_to && $expires_from > $expires_to ) {
			$swap         = $expires_from;
			$expires_from = $expires_to;
			$expires_to   = $swap;
		}

		$access_expiry = isset( $_GET['access_expiry'] ) ? sanitize_key( wp_unslash( $_GET['access_expiry'] ) ) : '';

		if ( ! in_array( $access_expiry, array( 'expired', 'active', 'none' ), true ) ) {
			$access_expiry = '';
		}

		return array(
			'buyer'         => isset( $_GET['buyer'] ) ? sanitize_text_field( wp_unslash( $_GET['buyer'] ) ) : '',
			'status'        => $status,
			'date_from'     => $date_from,
			'date_to'       => $date_to,
			'expires_from'  => $expires_from,
			'expires_to'    => $expires_to,
			'access_expiry' => $access_expiry,
			'orderby'       => $orderby,
			'order'         => $order,
			'per_page'      => self::get_list_per_page(),
			'page'          => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
		);
	}

	/**
	 * Render WordPress-style pagination for orders list.
	 *
	 * @param array  $filters      Active list filters.
	 * @param int    $total        Total matching orders.
	 * @param int    $page         Current page.
	 * @param int    $pages        Total pages.
	 * @param string $position     Nav position class: top|bottom.
	 * @param bool   $bulk_actions Whether to show bulk actions controls.
	 */
	public static function render_list_pagination( array $filters, $total, $page, $pages, $position = 'bottom', $bulk_actions = false ) {
		if ( $total <= 0 ) {
			return;
		}

		$page  = max( 1, (int) $page );
		$pages = max( 1, (int) $pages );
		$which = ( 'top' === $position ) ? 'top' : 'bottom';
		?>
		<div class="tablenav <?php echo esc_attr( $position ); ?>">
			<?php if ( $bulk_actions ) : ?>
				<div class="alignleft actions bulkactions">
					<label for="art-lms-bulk-action-<?php echo esc_attr( $which ); ?>" class="screen-reader-text">
						<?php esc_html_e( 'Выберите действие', 'art-lms' ); ?>
					</label>
					<select name="<?php echo esc_attr( 'top' === $which ? 'bulk_action' : 'bulk_action2' ); ?>" id="art-lms-bulk-action-<?php echo esc_attr( $which ); ?>">
						<option value="-1"><?php esc_html_e( 'Действия', 'art-lms' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Удалить', 'art-lms' ); ?></option>
					</select>
					<?php
					submit_button(
						__( 'Применить', 'art-lms' ),
						'action',
						'',
						false,
						array(
							'id' => 'art-lms-doaction-' . $which,
						)
					);
					?>
				</div>
			<?php endif; ?>
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %d: total items */
						esc_html( _n( '%d элемент', '%d элементов', $total, 'art-lms' ) ),
						absint( $total )
					);
					?>
				</span>
				<?php if ( $pages > 1 ) : ?>
					<span class="pagination-links">
						<?php if ( $page > 1 ) : ?>
							<a class="first-page button" href="<?php echo esc_url( self::get_list_url( array_merge( $filters, array( 'paged' => 1 ) ) ) ); ?>">
								<span class="screen-reader-text"><?php esc_html_e( 'Первая страница', 'art-lms' ); ?></span>
								<span aria-hidden="true">«</span>
							</a>
							<a class="prev-page button" href="<?php echo esc_url( self::get_list_url( array_merge( $filters, array( 'paged' => $page - 1 ) ) ) ); ?>">
								<span class="screen-reader-text"><?php esc_html_e( 'Предыдущая страница', 'art-lms' ); ?></span>
								<span aria-hidden="true">‹</span>
							</a>
						<?php else : ?>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
						<?php endif; ?>

						<span class="paging-input">
							<?php echo esc_html( $page ); ?> <?php esc_html_e( 'из', 'art-lms' ); ?> <span class="total-pages"><?php echo esc_html( $pages ); ?></span>
						</span>

						<?php if ( $page < $pages ) : ?>
							<a class="next-page button" href="<?php echo esc_url( self::get_list_url( array_merge( $filters, array( 'paged' => $page + 1 ) ) ) ); ?>">
								<span class="screen-reader-text"><?php esc_html_e( 'Следующая страница', 'art-lms' ); ?></span>
								<span aria-hidden="true">›</span>
							</a>
							<a class="last-page button" href="<?php echo esc_url( self::get_list_url( array_merge( $filters, array( 'paged' => $pages ) ) ) ); ?>">
								<span class="screen-reader-text"><?php esc_html_e( 'Последняя страница', 'art-lms' ); ?></span>
								<span aria-hidden="true">»</span>
							</a>
						<?php else : ?>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
							<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</div>
			<br class="clear">
		</div>
		<?php
	}

	/**
	 * Build admin orders list URL.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	public static function get_list_url( $args = array() ) {
		$url_args = array(
			'page' => self::PAGE_LIST,
		);

		$allowed = array( 'buyer', 'status', 'date_from', 'date_to', 'expires_from', 'expires_to', 'access_expiry', 'orderby', 'order', 'paged' );

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $args ) ) {
				continue;
			}

			$value = $args[ $key ];

			if ( 'status' === $key && '' === $value ) {
				continue;
			}

			if ( 'access_expiry' === $key && '' === $value ) {
				continue;
			}

			if ( 'buyer' === $key && '' === trim( (string) $value ) ) {
				continue;
			}

			if ( in_array( $key, array( 'date_from', 'date_to', 'expires_from', 'expires_to' ), true ) && '' === $value ) {
				continue;
			}

			if ( 'paged' === $key && (int) $value <= 1 ) {
				continue;
			}

			$url_args[ $key ] = $value;
		}

		return add_query_arg( $url_args, admin_url( 'admin.php' ) );
	}

	/**
	 * Get sort link for orders list column.
	 *
	 * @param string $column  Column key.
	 * @param array  $filters Current filters.
	 * @return string
	 */
	public static function get_list_sort_link( $column, array $filters ) {
		$current_order = strtoupper( $filters['order'] );
		$new_order     = 'ASC';

		if ( $filters['orderby'] === $column && 'ASC' === $current_order ) {
			$new_order = 'DESC';
		}

		return self::get_list_url(
			array_merge(
				$filters,
				array(
					'orderby' => $column,
					'order'   => $new_order,
					'paged'   => 1,
				)
			)
		);
	}

	/**
	 * Get CSS classes for sortable column header.
	 *
	 * @param string $column  Column key.
	 * @param array  $filters Current filters.
	 * @return string
	 */
	public static function get_list_sort_classes( $column, array $filters ) {
		$extra = array( 'art-lms-sortable' );

		if ( $filters['orderby'] === $column ) {
			$extra[] = 'is-sorted';
			$extra[] = 'is-' . strtolower( $filters['order'] );
		}

		return self::get_list_column_class( $column, $extra );
	}

	/**
	 * Render order add/edit page.
	 */
	public static function render_edit_page() {
		if ( ! Art_LMS_Security::can_manage() ) {
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order    = $order_id ? Art_LMS_Orders::get_order_form_data( $order_id ) : Art_LMS_Orders::get_default_order_form_data();

		if ( $order_id && ! $order ) {
			wp_die( esc_html__( 'Заказ не найден.', 'art-lms' ) );
		}

		$is_new   = empty( $order['id'] );
		$list_url = admin_url( 'admin.php?page=' . self::PAGE_LIST );
		$error      = isset( $_GET['art_lms_error'] ) ? sanitize_text_field( wp_unslash( $_GET['art_lms_error'] ) ) : '';
		$payment_buttons = Art_LMS_Payment_Buttons::get_order_form_options();

		include ART_LMS_PLUGIN_DIR . 'admin/views/order-edit.php';
	}

	/**
	 * Handle order create/update.
	 */
	public static function handle_save_order() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-lms' ) );
		}

		check_admin_referer( 'art_lms_save_order', 'art_lms_order_nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		$data = array(
			'buyer_identity' => isset( $_POST['buyer_identity'] ) ? sanitize_text_field( wp_unslash( $_POST['buyer_identity'] ) ) : '',
			'email'          => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'name'           => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'phone'          => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'product_id'     => isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0,
			'amount'         => isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '',
			'status'         => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : Art_LMS_Orders::STATUS_PENDING,
			'mark_as_paid'   => isset( $_POST['mark_as_paid'] ) ? 'yes' : 'no',
			'send_email'     => isset( $_POST['send_email'] ) ? 'yes' : 'no',
		);

		if ( $order_id ) {
			$result = Art_LMS_Orders::update_manual( $order_id, $data );
		} else {
			if ( Art_LMS_Orders::STATUS_PAID === $data['status'] ) {
				$data['mark_as_paid'] = 'yes';
			}

			$result = Art_LMS_Orders::create_manual( $data );
		}

		if ( is_wp_error( $result ) ) {
			$redirect = add_query_arg(
				array(
					'page'          => self::PAGE_EDIT,
					'order_id'      => $order_id,
					'art_lms_notice' => 'order_error',
					'art_lms_error' => rawurlencode( $result->get_error_message() ),
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		$saved_id = $order_id ? $order_id : (int) $result;

		if ( $order_id ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'           => self::PAGE_EDIT,
						'order_id'       => $saved_id,
						'art_lms_notice' => 'order_updated',
					),
					admin_url( 'admin.php' )
				)
			);
		} else {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'           => self::PAGE_LIST,
						'art_lms_notice' => 'order_created',
						'order_id'       => $saved_id,
					),
					admin_url( 'admin.php' )
				)
			);
		}
		exit;
	}

	/**
	 * Update order status from the view page.
	 */
	public static function handle_update_order_status() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-lms' ) );
		}

		check_admin_referer( 'art_lms_update_order_status', 'art_lms_order_status_nonce' );

		$order_id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$send_email = isset( $_POST['send_email'] ) ? 'yes' : 'no';

		$result = Art_LMS_Orders::update_status_from_admin( $order_id, $status, 'yes' === $send_email );

		$redirect_args = array(
			'page'     => self::PAGE_VIEW,
			'order_id' => $order_id,
		);

		if ( is_wp_error( $result ) ) {
			$redirect_args['art_lms_notice'] = 'order_error';
			$redirect_args['art_lms_error']  = rawurlencode( $result->get_error_message() );
		} else {
			$redirect_args['art_lms_notice'] = 'order_status_updated';
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Mark existing order as paid manually.
	 */
	public static function handle_mark_order_paid() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-lms' ) );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		check_admin_referer( 'art_lms_mark_order_paid_' . $order_id );

		$result = Art_LMS_Orders::mark_paid_manually( $order_id );

		$redirect_args = array(
			'page'           => self::PAGE_EDIT,
			'order_id'       => $order_id,
			'art_lms_notice' => is_wp_error( $result ) ? 'order_error' : 'order_paid',
		);

		if ( is_wp_error( $result ) ) {
			$redirect_args['art_lms_error'] = rawurlencode( $result->get_error_message() );
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Permanently delete an order.
	 */
	public static function handle_delete_order() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-lms' ) );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		check_admin_referer( 'art_lms_delete_order_' . $order_id );

		$result = Art_LMS_Orders::delete( $order_id );

		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
		$redirect_to = wp_validate_redirect( $redirect_to, self::get_list_url() );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'            => self::PAGE_LIST,
						'art_lms_notice'  => 'order_error',
						'art_lms_error'   => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				'art_lms_notice',
				'order_deleted',
				$redirect_to
			)
		);
		exit;
	}

	/**
	 * Permanently delete selected orders from the list.
	 */
	public static function handle_bulk_delete_orders() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-lms' ) );
		}

		check_admin_referer( 'art_lms_bulk_delete_orders', 'art_lms_bulk_orders_nonce' );

		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';

		if ( isset( $_POST['bulk_action2'] ) && ( '' === $bulk_action || '-1' === $bulk_action ) ) {
			$bulk_action = sanitize_key( wp_unslash( $_POST['bulk_action2'] ) );
		}

		$redirect_args = array(
			'buyer'         => isset( $_POST['buyer'] ) ? sanitize_text_field( wp_unslash( $_POST['buyer'] ) ) : '',
			'status'        => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
			'date_from'     => isset( $_POST['date_from'] ) ? Art_LMS_Orders::sanitize_list_date( sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) ) : '',
			'date_to'       => isset( $_POST['date_to'] ) ? Art_LMS_Orders::sanitize_list_date( sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) ) : '',
			'expires_from'  => isset( $_POST['expires_from'] ) ? Art_LMS_Orders::sanitize_list_date( sanitize_text_field( wp_unslash( $_POST['expires_from'] ) ) ) : '',
			'expires_to'    => isset( $_POST['expires_to'] ) ? Art_LMS_Orders::sanitize_list_date( sanitize_text_field( wp_unslash( $_POST['expires_to'] ) ) ) : '',
			'access_expiry' => isset( $_POST['access_expiry'] ) ? sanitize_key( wp_unslash( $_POST['access_expiry'] ) ) : '',
			'orderby'       => isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : 'created_at',
			'order'         => isset( $_POST['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['order'] ) ) ) : 'DESC',
			'paged'         => isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1,
		);

		if ( ! in_array( $redirect_args['orderby'], Art_LMS_Orders::get_list_orderby_keys(), true ) ) {
			$redirect_args['orderby'] = 'created_at';
		}

		if ( ! in_array( $redirect_args['order'], array( 'ASC', 'DESC' ), true ) ) {
			$redirect_args['order'] = 'DESC';
		}

		if ( '' !== $redirect_args['status'] && ! isset( Art_LMS_Orders::get_status_labels()[ $redirect_args['status'] ] ) ) {
			$redirect_args['status'] = '';
		}

		if ( ! in_array( $redirect_args['access_expiry'], array( 'expired', 'active', 'none' ), true ) ) {
			$redirect_args['access_expiry'] = '';
		}

		$redirect_to = self::get_list_url( $redirect_args );

		if ( 'delete' !== $bulk_action ) {
			wp_safe_redirect( $redirect_to );
			exit;
		}

		$raw_ids = isset( $_POST['order_ids'] ) ? (array) wp_unslash( $_POST['order_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via absint below.
		$ids     = array_values(
			array_filter(
				array_map( 'absint', $raw_ids )
			)
		);

		if ( empty( $ids ) ) {
			wp_safe_redirect(
				add_query_arg(
					'art_lms_notice',
					'orders_bulk_none',
					$redirect_to
				)
			);
			exit;
		}

		$result = Art_LMS_Orders::delete_many( $ids );

		wp_safe_redirect(
			add_query_arg(
				array(
					'art_lms_notice'       => 'orders_bulk_deleted',
					'art_lms_deleted_count' => (int) $result['deleted'],
				),
				$redirect_to
			)
		);
		exit;
	}

	/**
	 * Show admin notices on order pages.
	 */
	public static function render_notices() {
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

		if ( ! in_array( $page, array( self::PAGE_LIST, self::PAGE_EDIT, self::PAGE_VIEW ), true ) ) {
			return;
		}

		$notice   = isset( $_GET['art_lms_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['art_lms_notice'] ) ) : '';
		$error    = isset( $_GET['art_lms_error'] ) ? sanitize_text_field( wp_unslash( $_GET['art_lms_error'] ) ) : '';
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		if ( 'order_created' === $notice ) {
			$message = $order_id
				? sprintf(
					/* translators: %d: order ID */
					__( 'Заказ #%d создан.', 'art-lms' ),
					$order_id
				)
				: __( 'Заказ создан.', 'art-lms' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}

		if ( 'order_updated' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Заказ сохранён.', 'art-lms' ) . '</p></div>';
		}

		if ( 'order_paid' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Заказ отмечен как оплаченный, доступ выдан.', 'art-lms' ) . '</p></div>';
		}

		if ( 'order_status_updated' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Статус заказа обновлён.', 'art-lms' ) . '</p></div>';
		}

		if ( 'order_deleted' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Заказ удалён.', 'art-lms' ) . '</p></div>';
		}

		if ( 'orders_bulk_deleted' === $notice ) {
			$deleted_count = isset( $_GET['art_lms_deleted_count'] ) ? absint( $_GET['art_lms_deleted_count'] ) : 0;
			$message       = sprintf(
				/* translators: %d: number of deleted orders */
				_n( 'Удалён %d заказ.', 'Удалено заказов: %d.', $deleted_count, 'art-lms' ),
				$deleted_count
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}

		if ( 'orders_bulk_none' === $notice ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Выберите хотя бы один заказ.', 'art-lms' ) . '</p></div>';
		}

		if ( 'order_error' === $notice && $error ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
		}
	}

	/**
	 * Get edit page URL.
	 *
	 * @param int $order_id Order ID (0 for new).
	 * @return string
	 */
	public static function get_edit_url( $order_id = 0 ) {
		$args = array( 'page' => self::PAGE_EDIT );

		if ( $order_id ) {
			$args['order_id'] = $order_id;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Get read-only view page URL.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function get_view_url( $order_id ) {
		return add_query_arg(
			array(
				'page'     => self::PAGE_VIEW,
				'order_id' => absint( $order_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Get delete action URL for an order.
	 *
	 * @param int   $order_id     Order ID.
	 * @param array $redirect_args Optional list filters to preserve after delete.
	 * @return string
	 */
	public static function get_delete_url( $order_id, $redirect_args = array() ) {
		$order_id = absint( $order_id );

		$args = array(
			'action'   => 'art_lms_delete_order',
			'order_id' => $order_id,
		);

		$redirect_to = self::get_list_url( $redirect_args );

		if ( $redirect_to ) {
			$args['redirect_to'] = $redirect_to;
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			'art_lms_delete_order_' . $order_id
		);
	}
}
