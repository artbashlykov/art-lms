<?php
/**
 * Orders management.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Custom orders table queries.

/**
 * Class Art_LMS_Orders
 */
class Art_LMS_Orders {

	const STATUS_PENDING   = 'pending';
	const STATUS_PAID      = 'paid';
	const STATUS_FAILED    = 'failed';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_REFUNDED  = 'refunded';
	const STATUS_EXPIRED   = 'expired';

	/**
	 * Register hooks.
	 */
	public static function init() {
		self::maybe_upgrade_schema();
		self::maybe_upgrade_indexes();
	}

	/**
	 * Add missing DB columns on plugin update.
	 */
	public static function maybe_upgrade_schema() {
		global $wpdb;

		$table          = self::table_name();
		$stored_version = get_option( 'art_lms_orders_schema_version', '' );

		if ( ART_LMS_VERSION === $stored_version ) {
			return;
		}

		$column_exists = static function ( $name ) use ( $wpdb, $table ) {
			// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal schema migration; table name is plugin-controlled.
			$exists = ! empty(
				$wpdb->get_results(
					$wpdb->prepare(
						"SHOW COLUMNS FROM `{$table}` LIKE %s",
						$name
					)
				)
			);
			// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

			return $exists;
		};

		$additions = array(
			'form_data'              => 'ADD COLUMN form_data longtext NULL AFTER phone',
			'gateway_transaction_id' => 'ADD COLUMN gateway_transaction_id varchar(64) NOT NULL DEFAULT \'\' AFTER payment_label',
			'gateway_payment_method' => 'ADD COLUMN gateway_payment_method varchar(32) NOT NULL DEFAULT \'\' AFTER gateway_transaction_id',
			'payment_gateway'        => 'ADD COLUMN payment_gateway varchar(20) NOT NULL DEFAULT \'\' AFTER gateway_payment_method',
		);

		foreach ( $additions as $column => $sql_part ) {
			if ( ! $column_exists( $column ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Internal schema migration; table name is plugin-controlled.
				$wpdb->query( 'ALTER TABLE `' . $table . '` ' . $sql_part );
			}
		}

		if ( $column_exists( 'gateway_transaction_id' ) && empty( $wpdb->get_results( "SHOW INDEX FROM `{$table}` WHERE Key_name = 'gateway_transaction_id'" ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Internal schema migration; table name is plugin-controlled.
			$wpdb->query( 'ALTER TABLE `' . $table . '` ADD KEY gateway_transaction_id (gateway_transaction_id)' );
		}

		update_option( 'art_lms_orders_schema_version', ART_LMS_VERSION, false );
	}

	/**
	 * Add performance indexes to plugin tables on existing installs.
	 */
	public static function maybe_upgrade_indexes() {
		if ( get_option( 'art_lms_db_indexes_version' ) === '1' ) {
			return;
		}

		global $wpdb;

		$orders_table = self::table_name();
		$access_table = Art_LMS_Access::table_name();

		$indexes = array(
			array(
				'table' => $orders_table,
				'name'  => 'status_created_at',
				'sql'   => 'ADD KEY status_created_at (status, created_at)',
			),
			array(
				'table' => $orders_table,
				'name'  => 'status_paid_at',
				'sql'   => 'ADD KEY status_paid_at (status, paid_at)',
			),
			array(
				'table' => $access_table,
				'name'  => 'user_product_status',
				'sql'   => 'ADD KEY user_product_status (user_id, product_id, status)',
			),
			array(
				'table' => $access_table,
				'name'  => 'order_product_status',
				'sql'   => 'ADD KEY order_product_status (order_id, product_id, status)',
			),
		);

		foreach ( $indexes as $index ) {
			if ( self::index_exists( $index['table'], $index['name'] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Internal index migration; table names are plugin-controlled.
			$wpdb->query( 'ALTER TABLE `' . $index['table'] . '` ' . $index['sql'] );
		}

		update_option( 'art_lms_db_indexes_version', '1', false );
	}

	/**
	 * Check whether a table index exists.
	 *
	 * @param string $table      Table name.
	 * @param string $index_name Index name.
	 * @return bool
	 */
	private static function index_exists( $table, $index_name ) {
		global $wpdb;

		$table      = (string) $table;
		$index_name = (string) $index_name;

		if ( '' === $table || '' === $index_name ) {
			return false;
		}

		$indexes = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
				$index_name
			)
		);

		return ! empty( $indexes );
	}

	/**
	 * Get orders table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'art_lms_orders';
	}

	/**
	 * Generate unique order key.
	 *
	 * @return string
	 */
	public static function generate_order_key() {
		return 'art_lms_' . wp_generate_password( 16, false, false );
	}

	/**
	 * Generate external payment reference label for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public static function generate_payment_label( $order_id = 0 ) {
		global $wpdb;

		unset( $order_id );

		$table = self::table_name();

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$label = 'ord_' . strtolower( wp_generate_password( 16, false, false ) );

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM `{$table}` WHERE payment_label = %s LIMIT 1",
					$label
				)
			);

			if ( ! $exists ) {
				return $label;
			}
		}

		return 'ord_' . strtolower( wp_generate_password( 24, false, false ) );
	}

	/**
	 * Whether a webhook value can be a merchant-side payment label.
	 *
	 * @param string $label Candidate label.
	 * @return bool
	 */
	public static function looks_like_merchant_payment_label( $label ) {
		$label = sanitize_text_field( (string) $label );

		if ( '' === $label ) {
			return false;
		}

		// Provider-internal numeric IDs (e.g. Prodamus order_id) are not merchant labels.
		return 1 !== preg_match( '/^\d+$/', $label );
	}

	/**
	 * External payment reference sent to gateways.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	public static function get_payment_reference( $order ) {
		if ( ! is_object( $order ) ) {
			return '';
		}

		return sanitize_text_field( (string) ( $order->payment_label ?? '' ) );
	}

	/**
	 * Find an order by external payment reference from a gateway webhook.
	 *
	 * @param string $reference Payment label from provider notification.
	 * @return object|null
	 */
	public static function find_by_payment_reference( $reference ) {
		$reference = sanitize_text_field( (string) $reference );

		if ( ! self::looks_like_merchant_payment_label( $reference ) ) {
			return null;
		}

		return self::get_by_label( $reference );
	}

	/**
	 * Find a pending or paid order by gateway transaction / payment ID.
	 *
	 * @param string $transaction_id Provider payment ID.
	 * @return object|null
	 */
	public static function find_by_gateway_transaction_id( $transaction_id ) {
		global $wpdb;

		$transaction_id = sanitize_text_field( (string) $transaction_id );

		if ( '' === $transaction_id ) {
			return null;
		}

		$table = self::table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE gateway_transaction_id = %s LIMIT 1",
				$transaction_id
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Create a pending order.
	 *
	 * @param array $data Order data.
	 * @return int|false Order ID or false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;

		self::maybe_upgrade_schema();

		$defaults = array(
			'order_key'     => self::generate_order_key(),
			'user_id'       => 0,
			'product_id'    => 0,
			'email'         => '',
			'name'          => '',
			'phone'         => '',
			'form_data'     => '',
			'amount'        => 0,
			'currency'      => 'RUB',
			'status'        => self::STATUS_PENDING,
			'payment_label'          => '',
			'gateway_payment_method' => '',
			'payment_gateway'        => '',
			'created_at'      => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'order_key'       => sanitize_text_field( $data['order_key'] ),
				'user_id'         => absint( $data['user_id'] ),
				'product_id'      => absint( $data['product_id'] ),
				'email'           => sanitize_email( $data['email'] ),
				'name'            => sanitize_text_field( $data['name'] ),
				'phone'           => sanitize_text_field( $data['phone'] ),
				'form_data'       => is_string( $data['form_data'] ?? '' ) ? $data['form_data'] : '',
				'amount'          => floatval( $data['amount'] ),
				'currency'        => sanitize_text_field( $data['currency'] ),
				'status'          => sanitize_text_field( $data['status'] ),
				'payment_label'          => sanitize_text_field( $data['payment_label'] ),
				'gateway_payment_method' => sanitize_text_field( $data['gateway_payment_method'] ),
				'payment_gateway'        => sanitize_text_field( $data['payment_gateway'] ),
				'created_at'      => $data['created_at'],
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $wpdb->last_error ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'ART LMS order insert failed: ' . $wpdb->last_error );
			}

			return false;
		}

		$order_id = (int) $wpdb->insert_id;
		$label    = self::generate_payment_label( $order_id );

		$wpdb->update(
			self::table_name(),
			array( 'payment_label' => $label ),
			array( 'id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $order_id;
	}

	/**
	 * Create pending order from public checkout submission.
	 *
	 * @param int   $button_id Payment button ID.
	 * @param array $input     Raw submission data.
	 * @return int|WP_Error
	 */
	public static function create_from_checkout( $button_id, array $input ) {
		$button_id = absint( $button_id );

		$button_check = Art_LMS_Payment_Buttons::validate_order_form_button( $button_id );

		if ( is_wp_error( $button_check ) ) {
			return $button_check;
		}

		$snapshot = Art_LMS_Order_Form_Data::parse_submission( $input );
		$values   = array();

		foreach ( $snapshot['fields'] as $row ) {
			if ( 'field' !== ( $row['type'] ?? 'field' ) ) {
				continue;
			}

			$values[ $row['key'] ] = $row['value'];
		}

		$email = sanitize_email( $values['email'] ?? '' );
		$name  = sanitize_text_field( $values['full_name'] ?? '' );
		$phone = sanitize_text_field( $values['phone'] ?? '' );

		if ( is_user_logged_in() ) {
			$profile = Art_LMS_User_Registration::get_buyer_details_for_form( wp_get_current_user()->user_email );

			if ( ! is_email( $email ) && ! empty( $profile['email'] ) ) {
				$email = sanitize_email( $profile['email'] );
			}

			if ( '' === $name && ! empty( $profile['name'] ) ) {
				$name = sanitize_text_field( $profile['name'] );
			}

			if ( '' === $phone && ! empty( $profile['phone'] ) ) {
				$phone = sanitize_text_field( $profile['phone'] );
			}
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				Art_LMS_Settings::format_checkout_form_message( 'invalid_email' )
			);
		}

		foreach ( Art_LMS_Settings::get_checkout_form_fields() as $field ) {
			if ( empty( $field['required'] ) ) {
				continue;
			}

			$value = $values[ $field['key'] ] ?? '';

			if ( '' === trim( (string) $value ) ) {
				return new WP_Error(
					'required_field',
					Art_LMS_Settings::format_checkout_form_message(
						'required_field',
						array(
							'{поле}' => $field['label'],
						)
					)
				);
			}
		}

		foreach ( Art_LMS_Settings::get_checkout_consents()['items'] as $consent ) {
			if ( empty( $consent['required'] ) ) {
				continue;
			}

			$post_key = (string) ( $consent['post_key'] ?? '' );

			if ( empty( $input[ $post_key ] ) ) {
				$label = wp_strip_all_tags( Art_LMS_Settings::format_checkout_consent_label( $consent ) );

				if ( '' === $label ) {
					$label = (string) ( $consent['admin_label'] ?? $consent['key'] );
				}

				return new WP_Error(
					'consent_required',
					Art_LMS_Settings::format_checkout_form_message(
						'consent_required',
						array(
							'{согласие}' => $label,
						)
					)
				);
			}
		}

		$user_result = Art_LMS_User_Registration::resolve_checkout_user(
			array(
				'email'     => $email,
				'name'      => $name,
				'phone'     => $phone,
				'button_id' => $button_id,
				'input'     => $input,
				'snapshot'  => $snapshot,
			)
		);

		if ( is_wp_error( $user_result ) ) {
			return $user_result;
		}

		$payment_gateway = sanitize_key( (string) ( $input['payment_gateway'] ?? '' ) );
		$default_gateway = Art_LMS_Settings::get_default_checkout_gateway();

		if ( '' === $payment_gateway ) {
			$payment_gateway = $default_gateway;
		}

		if ( '' === $payment_gateway || ! Art_LMS_Settings::is_checkout_gateway_available( $payment_gateway ) ) {
			return new WP_Error(
				'payment_method_required',
				Art_LMS_Settings::format_checkout_form_message( 'payment_method_required' )
			);
		}

		return self::create_checkout_order(
			$button_id,
			array(
				'user_id'         => (int) ( $user_result['user_id'] ?? 0 ),
				'email'           => $email,
				'name'            => $name,
				'phone'           => $phone,
				'form_data'       => Art_LMS_Order_Form_Data::encode( $snapshot ),
				'payment_gateway' => $payment_gateway,
			)
		);
	}

	/**
	 * Create a pending checkout order from validated buyer data.
	 *
	 * @param int   $button_id Payment button ID.
	 * @param array $data      Order data.
	 * @return int|WP_Error
	 */
	public static function create_checkout_order( $button_id, array $data ) {
		$button_id = absint( $button_id );
		$button_meta = Art_LMS_Payment_Buttons::get_meta( $button_id );
		$amount      = floatval( $button_meta['price'] ?? 0 );

		if ( $amount <= 0 ) {
			return new WP_Error(
				'invalid_amount',
				Art_LMS_Settings::format_checkout_form_message( 'create_order_failed' )
			);
		}

		$order_id = self::create(
			array(
				'user_id'                => absint( $data['user_id'] ?? 0 ),
				'product_id'             => $button_id,
				'email'                  => sanitize_email( $data['email'] ?? '' ),
				'name'                   => sanitize_text_field( $data['name'] ?? '' ),
				'phone'                  => sanitize_text_field( $data['phone'] ?? '' ),
				'form_data'              => is_string( $data['form_data'] ?? '' ) ? $data['form_data'] : '',
				'amount'                 => $amount,
				'status'                 => self::STATUS_PENDING,
				'payment_gateway'        => sanitize_key( (string) ( $data['payment_gateway'] ?? Art_LMS_Settings::get_active_gateway() ) ),
			)
		);

		if ( ! $order_id ) {
			return new WP_Error(
				'create_failed',
				Art_LMS_Settings::format_checkout_form_message( 'create_order_failed' )
			);
		}

		return $order_id;
	}

	/**
	 * Get order by ID.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null
	 */
	public static function get( $order_id ) {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE id = %d",
				$order_id
			)
		);
	}

	/**
	 * Get order by payment label.
	 *
	 * @param string $label Payment label.
	 * @return object|null
	 */
	public static function get_by_label( $label ) {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE payment_label = %s",
				$label
			)
		);
	}

	/**
	 * Get order by order key.
	 *
	 * @param string $order_key Order key.
	 * @return object|null
	 */
	public static function get_by_key( $order_key ) {
		global $wpdb;

		$table = self::table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE order_key = %s",
				$order_key
			)
		);
	}

	/**
	 * Update order fields.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Fields to update.
	 * @return bool
	 */
	public static function update( $order_id, $data ) {
		global $wpdb;

		$allowed = array(
			'user_id'               => '%d',
			'product_id'            => '%d',
			'email'                 => '%s',
			'name'                  => '%s',
			'phone'                 => '%s',
			'form_data'             => '%s',
			'amount'                => '%f',
			'currency'              => '%s',
			'status'                => '%s',
			'gateway_transaction_id' => '%s',
			'paid_at'                => '%s',
			'expires_at'             => '%s',
			'raw_notification'       => '%s',
			'gateway_payment_method' => '%s',
			'payment_gateway'        => '%s',
		);

		$update = array();
		$format = array();

		foreach ( $allowed as $field => $field_format ) {
			if ( array_key_exists( $field, $data ) ) {
				$update[ $field ] = $data[ $field ];
				$format[]         = $field_format;
			}
		}

		if ( empty( $update ) ) {
			return false;
		}

		$result = $wpdb->update(
			self::table_name(),
			$update,
			array( 'id' => absint( $order_id ) ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark order as paid.
	 *
	 * @param int    $order_id     Order ID.
	 * @param array  $payment_data Payment notification data.
	 * @return bool
	 */
	public static function mark_paid( $order_id, $payment_data = array() ) {
		global $wpdb;

		$order = self::get( $order_id );

		if ( ! $order || self::STATUS_PAID === $order->status ) {
			return false;
		}

		$order = self::ensure_order_user( $order );

		if ( is_wp_error( $order ) ) {
			return false;
		}

		$expires_at = null;

		$transaction_id = isset( $payment_data['transaction_id'] ) ? sanitize_text_field( $payment_data['transaction_id'] ) : '';

		$update_data = array(
			'status'                 => self::STATUS_PAID,
			'gateway_transaction_id' => $transaction_id,
			'paid_at'                => current_time( 'mysql' ),
			'expires_at'             => $expires_at,
			'raw_notification'       => isset( $payment_data['raw'] ) ? wp_json_encode( $payment_data['raw'] ) : '',
		);

		$gateway_payment_method = isset( $payment_data['payment_method'] ) ? sanitize_text_field( $payment_data['payment_method'] ) : '';

		if ( '' !== $gateway_payment_method ) {
			$update_data['gateway_payment_method'] = $gateway_payment_method;
		}

		$field_formats = array(
			'status'                 => '%s',
			'gateway_transaction_id' => '%s',
			'paid_at'                => '%s',
			'expires_at'             => '%s',
			'raw_notification'       => '%s',
			'gateway_payment_method' => '%s',
		);
		$update        = array();
		$formats       = array();

		foreach ( $field_formats as $field => $field_format ) {
			if ( array_key_exists( $field, $update_data ) ) {
				$update[ $field ] = $update_data[ $field ];
				$formats[]        = $field_format;
			}
		}

		$result = $wpdb->update(
			self::table_name(),
			$update,
			array(
				'id'     => absint( $order_id ),
				'status' => self::STATUS_PENDING,
			),
			$formats,
			array( '%d', '%s' )
		);

		if ( false === $result || 0 === $result ) {
			return false;
		}

		$order = self::get( $order_id );

		if ( ! $order ) {
			return false;
		}

		Art_LMS_Payment_Buttons::grant_access_for_order( $order, $order_id );

		if ( empty( $payment_data['skip_email'] ) ) {
			do_action( 'art_lms_order_paid', $order_id, $order );
		}

		return true;
	}

	/**
	 * Ensure order has a linked WordPress user before granting access.
	 *
	 * @param object $order Order object.
	 * @return object|WP_Error
	 */
	public static function ensure_order_user( $order ) {
		if ( absint( $order->user_id ?? 0 ) ) {
			return $order;
		}

		$user_result = Art_LMS_User_Registration::get_or_create_user(
			array(
				'email' => $order->email ?? '',
				'name'  => $order->name ?? '',
				'phone' => $order->phone ?? '',
			)
		);

		if ( is_wp_error( $user_result ) ) {
			return $user_result;
		}

		self::update(
			(int) $order->id,
			array(
				'user_id' => (int) $user_result['user_id'],
			)
		);

		$fresh_order = self::get( (int) $order->id );

		return $fresh_order ? $fresh_order : $order;
	}

	/**
	 * Check whether a gateway transaction ID was already used by another paid order.
	 *
	 * @param string $transaction_id Gateway transaction ID.
	 * @param int    $current_id     Current order ID.
	 * @return bool
	 */
	public static function is_transaction_id_used( $transaction_id, $current_id ) {
		global $wpdb;

		$transaction_id = sanitize_text_field( (string) $transaction_id );

		if ( '' === $transaction_id ) {
			return false;
		}

		$table = self::table_name();

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}`
				WHERE gateway_transaction_id = %s AND id != %d AND status = %s LIMIT 1",
				$transaction_id,
				absint( $current_id ),
				self::STATUS_PAID
			)
		);

		return ! empty( $existing );
	}

	/**
	 * Get recent orders for admin.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function get_recent( $limit = 50 ) {
		$result = self::query_list(
			array(
				'per_page' => $limit,
				'page'     => 1,
			)
		);

		return $result['items'];
	}

	/**
	 * Format order amount for display.
	 *
	 * @param float|int|string $amount   Amount.
	 * @param string           $currency Currency code.
	 * @return string
	 */
	public static function format_amount( $amount, $currency = 'RUB' ) {
		$formatted = number_format( (float) $amount, 2, ',', ' ' );
		$currency  = strtoupper( trim( (string) $currency ) );

		if ( 'RUB' === $currency || '' === $currency ) {
			return $formatted . ' ₽';
		}

		return $formatted . ' ' . $currency;
	}

	/**
	 * Query orders for admin list with filters and sorting.
	 *
	 * @param array $args Query arguments.
	 * @return array{items: array, total: int, per_page: int, page: int, pages: int}
	 */
	public static function query_list( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'buyer'     => '',
			'status'    => '',
			'date_from' => '',
			'date_to'   => '',
			'orderby'   => 'created_at',
			'order'     => 'DESC',
			'per_page'  => 50,
			'page'      => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$table       = self::table_name();
		$users_table = $wpdb->users;

		$joins = array(
			"LEFT JOIN {$users_table} u ON u.ID = o.user_id",
		);

		$where  = array( '1=1' );
		$params = array();

		$buyer = trim( (string) $args['buyer'] );

		if ( '' !== $buyer ) {
			$like    = '%' . $wpdb->esc_like( $buyer ) . '%';
			$where[] = '( o.email LIKE %s OR o.name LIKE %s OR o.payment_label LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s )';
			$params  = array_merge( $params, array( $like, $like, $like, $like, $like ) );
		}

		$status = sanitize_text_field( $args['status'] );

		if ( '' !== $status && isset( self::get_status_labels()[ $status ] ) ) {
			$where[] = 'o.status = %s';
			$params[] = $status;
		}

		$date_from = self::sanitize_list_date( $args['date_from'] ?? '' );
		$date_to   = self::sanitize_list_date( $args['date_to'] ?? '' );

		if ( $date_from && $date_to && $date_from > $date_to ) {
			$swap      = $date_from;
			$date_from = $date_to;
			$date_to   = $swap;
		}

		if ( $date_from ) {
			$where[]  = 'o.created_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}

		if ( $date_to ) {
			$where[]  = 'o.created_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$orderby_map = array(
			'id'            => 'o.id',
			'created_at'    => 'o.created_at',
			'buyer'         => 'o.name',
			'amount'        => 'o.amount',
			'status'        => 'o.status',
			'payment_label' => 'o.payment_label',
		);

		$orderby_key = isset( $orderby_map[ $args['orderby'] ] ) ? $args['orderby'] : 'created_at';
		$orderby_sql = $orderby_map[ $orderby_key ];
		$order       = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$join_sql  = implode( ' ', $joins );
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin order list; WHERE fragments use prepare placeholders only. InterpolatedNotPrepared covered at class level.
		$total = $params
			? (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT( DISTINCT o.id ) FROM `{$table}` o {$join_sql} WHERE {$where_sql}",
					...$params
				)
			)
			: (int) $wpdb->get_var(
				"SELECT COUNT( DISTINCT o.id ) FROM `{$table}` o {$join_sql} WHERE {$where_sql}"
			);

		$per_page = max( 1, min( 200, absint( $args['per_page'] ) ) );
		$page     = max( 1, absint( $args['page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.* FROM `{$table}` o {$join_sql} WHERE {$where_sql} ORDER BY {$orderby_sql} {$order} LIMIT %d OFFSET %d",
				...array_merge( $params, array( $per_page, $offset ) )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return array(
			'items'    => $items ? $items : array(),
			'total'    => $total,
			'per_page' => $per_page,
			'page'     => $page,
			'pages'    => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}

	/**
	 * Format datetime for admin UI (e.g. 06 июня 2026, 14:30).
	 *
	 * @param string $mysql_datetime MySQL datetime string.
	 * @return string
	 */
	public static function format_admin_datetime( $mysql_datetime ) {
		if ( empty( $mysql_datetime ) || '0000-00-00 00:00:00' === $mysql_datetime ) {
			return '—';
		}

		$timestamp = strtotime( $mysql_datetime );

		if ( ! $timestamp ) {
			return $mysql_datetime;
		}

		$months = array(
			1  => 'января',
			2  => 'февраля',
			3  => 'марта',
			4  => 'апреля',
			5  => 'мая',
			6  => 'июня',
			7  => 'июля',
			8  => 'августа',
			9  => 'сентября',
			10 => 'октября',
			11 => 'ноября',
			12 => 'декабря',
		);

		$month_num = (int) wp_date( 'n', $timestamp );

		return sprintf(
			'%s %s %s, %s',
			wp_date( 'd', $timestamp ),
			isset( $months[ $month_num ] ) ? $months[ $month_num ] : wp_date( 'F', $timestamp ),
			wp_date( 'Y', $timestamp ),
			wp_date( 'H:i', $timestamp )
		);
	}

	/**
	 * Get allowed orderby keys for admin list.
	 *
	 * @return string[]
	 */
	public static function get_list_orderby_keys() {
		return array( 'created_at', 'buyer', 'amount', 'status', 'payment_label' );
	}

	/**
	 * Sanitize Y-m-d date for admin list filters.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	public static function sanitize_list_date( $date ) {
		$date = sanitize_text_field( (string) $date );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $date ) );

		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * Get order status labels.
	 *
	 * @return array
	 */
	public static function get_status_labels() {
		return array(
			self::STATUS_PENDING   => __( 'Ожидает оплаты', 'art-lms' ),
			self::STATUS_PAID      => __( 'Оплачен', 'art-lms' ),
			self::STATUS_FAILED    => __( 'Ошибка', 'art-lms' ),
			self::STATUS_CANCELLED => __( 'Отменён', 'art-lms' ),
			self::STATUS_REFUNDED  => __( 'Возврат', 'art-lms' ),
			self::STATUS_EXPIRED   => __( 'Истёк', 'art-lms' ),
		);
	}

	/**
	 * Get payment gateway labels for admin UI (custom display names).
	 *
	 * @return array<string, string>
	 */
	public static function get_payment_gateway_labels() {
		$labels = array(
			'manual' => __( 'Вручную', 'art-lms' ),
		);

		foreach ( Art_LMS_Settings::get_ordered_gateway_ids() as $gateway_id ) {
			$labels[ $gateway_id ] = Art_LMS_Settings::get_gateway_display_name( $gateway_id );
		}

		return $labels;
	}

	/**
	 * Get internal gateway title for admin order views.
	 *
	 * @param string $gateway_id Gateway slug.
	 * @return string
	 */
	public static function get_payment_gateway_internal_label( $gateway_id ) {
		$gateway_id = sanitize_key( (string) $gateway_id );

		if ( '' === $gateway_id ) {
			return '—';
		}

		if ( 'manual' === $gateway_id ) {
			return __( 'Вручную', 'art-lms' );
		}

		$gateway = Art_LMS_Payment_Gateway_Registry::get( $gateway_id );

		if ( $gateway ) {
			$meta = $gateway->get_meta();

			return (string) ( $meta['title'] ?? $gateway_id );
		}

		return $gateway_id;
	}

	/**
	 * Resolve payment gateway slug for an order.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	public static function get_payment_gateway_slug( $order ) {
		if ( is_array( $order ) ) {
			$order = (object) $order;
		}

		return is_object( $order ) ? (string) ( $order->payment_gateway ?? '' ) : '';
	}

	/**
	 * Get human-readable payment gateway label for an order.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	public static function get_payment_gateway_label( $order ) {
		return self::get_payment_gateway_internal_label( self::get_payment_gateway_slug( $order ) );
	}

	/**
	 * Get human-readable status label.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function get_status_label( $status ) {
		$labels = self::get_status_labels();

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Create order manually from admin.
	 *
	 * @param array $data Order data.
	 * @return int|WP_Error
	 */
	public static function create_manual( $data ) {
		$buyer_identity = sanitize_text_field( $data['buyer_identity'] ?? '' );
		$email          = sanitize_email( $data['email'] ?? '' );
		$name           = sanitize_text_field( $data['name'] ?? '' );
		$phone          = sanitize_text_field( $data['phone'] ?? '' );
		$product_id     = absint( $data['product_id'] ?? 0 );
		$mark_paid      = ( $data['mark_as_paid'] ?? 'no' ) === 'yes';
		$send_email     = ( $data['send_email'] ?? 'no' ) === 'yes';
		$amount         = ( isset( $data['amount'] ) && '' !== $data['amount'] ) ? floatval( $data['amount'] ) : 0;

		$button_check = Art_LMS_Payment_Buttons::validate_order_form_button( $product_id );

		if ( is_wp_error( $button_check ) ) {
			return $button_check;
		}

		$button_meta = Art_LMS_Payment_Buttons::get_meta( $product_id );

		if ( $amount <= 0 && '' !== trim( (string) $button_meta['price'] ) ) {
			$amount = floatval( $button_meta['price'] );
		}

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Укажите сумму заказа.', 'art-lms' ) );
		}

		$resolved_email = Art_LMS_User_Registration::resolve_buyer_email( $buyer_identity, $email );

		if ( is_wp_error( $resolved_email ) ) {
			return $resolved_email;
		}

		$email = $resolved_email;

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Укажите корректный email.', 'art-lms' ) );
		}

		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			$profile = Art_LMS_User_Registration::get_buyer_details_for_form( $buyer_identity ? $buyer_identity : $email );

			if ( '' === $name && ! empty( $profile['name'] ) ) {
				$name = $profile['name'];
			}

			if ( '' === $phone && ! empty( $profile['phone'] ) ) {
				$phone = $profile['phone'];
			}
		}

		$user_result = Art_LMS_User_Registration::get_or_create_user(
			array(
				'email' => $email,
				'name'  => $name,
				'phone' => $phone,
			)
		);

		if ( is_wp_error( $user_result ) ) {
			return $user_result;
		}

		$order_id = self::create(
			array(
				'user_id'      => (int) $user_result['user_id'],
				'product_id'   => $product_id,
				'email'        => $email,
				'name'         => $name,
				'phone'        => $phone,
				'form_data'    => Art_LMS_Order_Form_Data::encode(
					Art_LMS_Order_Form_Data::build_snapshot_from_columns( $name, $email, $phone )
				),
				'amount'       => $amount,
				'status'       => self::STATUS_PENDING,
				'payment_gateway' => 'manual',
			)
		);

		if ( ! $order_id ) {
			return new WP_Error( 'create_failed', __( 'Не удалось создать заказ.', 'art-lms' ) );
		}

		if ( $mark_paid ) {
			$paid = self::mark_paid_manually( $order_id, $send_email );

			if ( is_wp_error( $paid ) ) {
				return $paid;
			}
		}

		return $order_id;
	}

	/**
	 * Update order manually from admin.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Order data.
	 * @return true|WP_Error
	 */
	public static function update_manual( $order_id, $data ) {
		$order = self::get( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Заказ не найден.', 'art-lms' ) );
		}

		$buyer_identity = sanitize_text_field( $data['buyer_identity'] ?? '' );
		$email          = sanitize_email( $data['email'] ?? '' );
		$name           = sanitize_text_field( $data['name'] ?? '' );
		$phone          = sanitize_text_field( $data['phone'] ?? '' );
		$product_id     = absint( $data['product_id'] ?? $order->product_id );
		$send_email     = ( $data['send_email'] ?? 'no' ) === 'yes';

		$status_labels = self::get_status_labels();
		$new_status    = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : $order->status;
		$new_status    = isset( $status_labels[ $new_status ] ) ? $new_status : $order->status;
		$needs_access_repair = self::STATUS_PAID === $order->status && ! absint( $order->product_id );
		$product_locked      = self::STATUS_PAID === $order->status && absint( $order->product_id );

		if ( $product_locked && $product_id !== (int) $order->product_id ) {
			$product_id = (int) $order->product_id;
		}

		if ( self::STATUS_PAID !== $order->status || $needs_access_repair ) {
			$button_check = Art_LMS_Payment_Buttons::validate_order_form_button( $product_id );

			if ( is_wp_error( $button_check ) ) {
				return $button_check;
			}
		}

		$resolved_email = Art_LMS_User_Registration::resolve_buyer_email( $buyer_identity, $email );

		if ( is_wp_error( $resolved_email ) ) {
			return $resolved_email;
		}

		$email = $resolved_email;

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Укажите корректный email.', 'art-lms' ) );
		}

		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			$profile = Art_LMS_User_Registration::get_buyer_details_for_form( $buyer_identity ? $buyer_identity : $email );

			if ( '' === $name && ! empty( $profile['name'] ) ) {
				$name = $profile['name'];
			}

			if ( '' === $phone && ! empty( $profile['phone'] ) ) {
				$phone = $profile['phone'];
			}
		}

		$old_user_id = (int) $order->user_id;
		$user_id     = $old_user_id;

		if ( $email !== $order->email ) {
			$user_result = Art_LMS_User_Registration::get_or_create_user(
				array(
					'email' => $email,
					'name'  => $name,
					'phone' => $phone,
				)
			);

			if ( is_wp_error( $user_result ) ) {
				return $user_result;
			}

			$user_id = (int) $user_result['user_id'];
		} elseif ( $user_id ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name ? $name : $email,
					'first_name'   => $name,
				)
			);

			if ( $phone ) {
				update_user_meta( $user_id, 'art_lms_phone', $phone );
			}
		}

		$amount = ( isset( $data['amount'] ) && '' !== $data['amount'] ) ? floatval( $data['amount'] ) : floatval( $order->amount );

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Укажите сумму заказа.', 'art-lms' ) );
		}

		$old_status = $order->status;
		$transition_to_paid = ( self::STATUS_PAID !== $old_status && self::STATUS_PAID === $new_status );

		$updated = self::update(
			$order_id,
			array(
				'user_id'    => $user_id,
				'product_id' => $product_id,
				'email'      => $email,
				'name'       => $name,
				'phone'      => $phone,
				'amount'     => $amount,
				'form_data'  => Art_LMS_Order_Form_Data::refresh_snapshot_from_columns(
					$order->form_data ?? '',
					$name,
					$email,
					$phone
				),
			)
		);

		if ( false === $updated ) {
			return new WP_Error( 'update_failed', __( 'Не удалось сохранить заказ.', 'art-lms' ) );
		}

		if ( self::STATUS_PAID === $old_status && $user_id && $user_id !== $old_user_id ) {
			Art_LMS_Access::reassign_by_order_id( $order_id, $user_id );
		}

		if ( $needs_access_repair && $product_id ) {
			$fresh_order = self::get( $order_id );

			if ( $fresh_order ) {
				Art_LMS_Payment_Buttons::grant_access_for_order( $fresh_order, $order_id );
			}
		}

		if ( $transition_to_paid ) {
			return self::mark_paid_manually( $order_id, $send_email );
		}

		if ( self::STATUS_PAID === $old_status && self::STATUS_PAID !== $new_status ) {
			Art_LMS_Access::revoke_by_order_id( $order_id );
		}

		if ( $new_status !== $old_status ) {
			self::update(
				$order_id,
				array(
					'status' => $new_status,
				)
			);
		}

		return true;
	}

	/**
	 * Update order status from admin view page.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $new_status New status slug.
	 * @param bool   $send_email Send emails when marking as paid.
	 * @return true|WP_Error
	 */
	public static function update_status_from_admin( $order_id, $new_status, $send_email = true ) {
		$order = self::get( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Заказ не найден.', 'art-lms' ) );
		}

		$status_labels = self::get_status_labels();

		if ( ! isset( $status_labels[ $new_status ] ) ) {
			return new WP_Error( 'invalid_status', __( 'Некорректный статус заказа.', 'art-lms' ) );
		}

		if ( $new_status === $order->status ) {
			return true;
		}

		return self::update_manual(
			$order_id,
			array(
				'buyer_identity' => $order->email,
				'email'          => $order->email,
				'name'           => $order->name,
				'phone'          => $order->phone,
				'product_id'     => (int) $order->product_id,
				'amount'         => $order->amount,
				'status'         => $new_status,
				'send_email'     => $send_email ? 'yes' : 'no',
			)
		);
	}

	/**
	 * Get default form values for new order.
	 *
	 * @return array
	 */
	public static function get_default_order_form_data() {
		return array(
			'id'              => 0,
			'product_id'      => 0,
			'buyer_identity'  => '',
			'email'           => '',
			'name'            => '',
			'phone'           => '',
			'amount'          => '',
			'status'          => self::STATUS_PENDING,
			'payment_label'   => '',
			'order_key'       => '',
			'created_at'      => '',
			'paid_at'         => '',
		);
	}

	/**
	 * Get order data for admin form.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public static function get_order_form_data( $order_id ) {
		$order = self::get( $order_id );

		if ( ! $order ) {
			return null;
		}

		$buyer_identity = $order->email;

		if ( $order->user_id ) {
			$user = get_userdata( (int) $order->user_id );

			if ( $user ) {
				$buyer_identity = $user->user_email;
			}
		}

		return array(
			'id'              => (int) $order->id,
			'product_id'      => (int) $order->product_id,
			'buyer_identity'  => $buyer_identity,
			'email'           => $order->email,
			'name'            => $order->name,
			'phone'           => $order->phone,
			'amount'          => $order->amount,
			'status'          => $order->status,
			'payment_label'   => $order->payment_label,
			'payment_gateway'        => $order->payment_gateway ?? '',
			'gateway_payment_method' => $order->gateway_payment_method ?? '',
			'gateway_transaction_id' => $order->gateway_transaction_id ?? '',
			'order_key'       => $order->order_key,
			'created_at'      => $order->created_at,
			'paid_at'         => $order->paid_at,
		);
	}

	/**
	 * Mark order as paid from admin (manual confirmation).
	 *
	 * @param int  $order_id   Order ID.
	 * @param bool $send_email Send purchase email.
	 * @return bool|WP_Error
	 */
	public static function mark_paid_manually( $order_id, $send_email = true ) {
		$order = self::get( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Заказ не найден.', 'art-lms' ) );
		}

		if ( self::STATUS_PAID === $order->status ) {
			return new WP_Error( 'already_paid', __( 'Заказ уже оплачен.', 'art-lms' ) );
		}

		if ( ! absint( $order->product_id ) ) {
			return new WP_Error( 'missing_product', __( 'У заказа не указана платёжная кнопка. Выберите продукт перед подтверждением оплаты.', 'art-lms' ) );
		}

		$button_check = Art_LMS_Payment_Buttons::validate_order_form_button( (int) $order->product_id );

		if ( is_wp_error( $button_check ) ) {
			return $button_check;
		}

		$paid = self::mark_paid(
			$order_id,
			array(
				'transaction_id' => 'manual_' . $order_id . '_' . time(),
				'skip_email'     => ! $send_email,
				'raw'          => array(
					'source'     => 'admin_manual',
					'admin_user' => get_current_user_id(),
				),
			)
		);

		if ( ! $paid ) {
			return new WP_Error( 'mark_failed', __( 'Не удалось подтвердить оплату.', 'art-lms' ) );
		}

		return true;
	}

	/**
	 * Permanently delete an order and revoke related access.
	 *
	 * @param int $order_id Order ID.
	 * @return true|WP_Error
	 */
	public static function delete( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return new WP_Error( 'invalid_order', __( 'Заказ не найден.', 'art-lms' ) );
		}

		$order = self::get( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Заказ не найден.', 'art-lms' ) );
		}

		Art_LMS_Access::revoke_by_order_id( $order_id );
		Art_LMS_Access::delete_by_order_id( $order_id );

		$deleted = $wpdb->delete(
			self::table_name(),
			array( 'id' => $order_id ),
			array( '%d' )
		);

		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Не удалось удалить заказ.', 'art-lms' ) );
		}

		return true;
	}

	// phpcs:enable
}
