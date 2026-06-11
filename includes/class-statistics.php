<?php
/**
 * Admin statistics queries.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin statistics; custom orders table queries.

/**
 * Class Art_LMS_Statistics
 */
class Art_LMS_Statistics {

	const PERIOD_7      = '7';
	const PERIOD_30     = '30';
	const PERIOD_90     = '90';
	const PERIOD_ALL    = 'all';
	const PERIOD_CUSTOM = 'custom';

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Query helpers are used from admin statistics page.
	}

	/**
	 * Get available period presets.
	 *
	 * @return array<string, string>
	 */
	public static function get_period_presets() {
		return array(
			self::PERIOD_7   => __( '7 дней', 'art-lms' ),
			self::PERIOD_30  => __( '30 дней', 'art-lms' ),
			self::PERIOD_90  => __( '90 дней', 'art-lms' ),
			self::PERIOD_ALL => __( 'Всё время', 'art-lms' ),
		);
	}

	/**
	 * Parse period filter from request.
	 *
	 * @param array|null $request Request data.
	 * @return array{period: string, date_from: string, date_to: string, label: string}
	 */
	public static function parse_period_from_request( $request = null ) {
		$request = is_array( $request ) ? $request : $_GET;
		$period  = isset( $request['period'] ) ? sanitize_key( wp_unslash( $request['period'] ) ) : self::PERIOD_30;
		$preset  = self::get_period_presets();

		if ( ! isset( $preset[ $period ] ) && self::PERIOD_CUSTOM !== $period ) {
			$period = self::PERIOD_30;
		}

		$date_from = '';
		$date_to   = '';
		$label     = $preset[ self::PERIOD_30 ];

		if ( self::PERIOD_CUSTOM === $period ) {
			$date_from = Art_LMS_Orders::sanitize_list_date( $request['date_from'] ?? '' );
			$date_to   = Art_LMS_Orders::sanitize_list_date( $request['date_to'] ?? '' );

			if ( $date_from && $date_to && $date_from > $date_to ) {
				$swap      = $date_from;
				$date_from = $date_to;
				$date_to   = $swap;
			}

			if ( $date_from && $date_to ) {
				$label = sprintf(
					/* translators: 1: start date, 2: end date */
					__( '%1$s — %2$s', 'art-lms' ),
					self::format_chart_day_label( $date_from ),
					self::format_chart_day_label( $date_to )
				);
			} elseif ( $date_from ) {
				$label = sprintf(
					/* translators: %s: start date */
					__( 'С %s', 'art-lms' ),
					self::format_chart_day_label( $date_from )
				);
			} elseif ( $date_to ) {
				$label = sprintf(
					/* translators: %s: end date */
					__( 'По %s', 'art-lms' ),
					self::format_chart_day_label( $date_to )
				);
			} else {
				$period = self::PERIOD_30;
			}
		}

		if ( self::PERIOD_ALL === $period ) {
			$label = $preset[ self::PERIOD_ALL ];
		} elseif ( isset( $preset[ $period ] ) ) {
			$days      = absint( $period );
			$date_to   = wp_date( 'Y-m-d', current_time( 'timestamp' ) );
			$date_from = wp_date( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) ) );
			$label     = $preset[ $period ];
		}

		return array(
			'period'    => $period,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'label'     => $label,
		);
	}

	/**
	 * Build dashboard payload for admin statistics page.
	 *
	 * @param array $period_args Period arguments.
	 * @return array
	 */
	public static function get_dashboard( array $period_args ) {
		$period_args = wp_parse_args(
			$period_args,
			array(
				'period'    => self::PERIOD_30,
				'date_from' => '',
				'date_to'   => '',
				'label'     => '',
			)
		);

		$revenue_rows = self::get_revenue_by_day( $period_args );
		$total        = self::get_paid_totals( $period_args );

		return array(
			'period'         => $period_args,
			'kpis'           => self::get_kpis( $period_args, $total ),
			'funnel'         => self::get_order_funnel( $period_args ),
			'gateways'       => self::get_gateway_sales( $period_args ),
			'revenue_by_day' => $revenue_rows,
			'products'       => self::get_product_sales( $period_args, (float) $total['revenue'] ),
			'chart_max'      => self::get_chart_max_value( $revenue_rows ),
		);
	}

	/**
	 * Get KPI cards data.
	 *
	 * @param array $period_args Period arguments.
	 * @param array $totals      Paid totals.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_kpis( array $period_args, array $totals = null ) {
		if ( null === $totals ) {
			$totals = self::get_paid_totals( $period_args );
		}

		$revenue     = (float) ( $totals['revenue'] ?? 0 );
		$paid_count  = (int) ( $totals['orders_count'] ?? 0 );
		$average     = $paid_count > 0 ? $revenue / $paid_count : 0;
		$new_buyers  = self::get_new_buyers_count( $period_args );

		return array(
			array(
				'key'   => 'revenue',
				'label' => __( 'Выручка', 'art-lms' ),
				'value' => self::format_money( $revenue ),
			),
			array(
				'key'   => 'paid_orders',
				'label' => __( 'Оплаченных заказов', 'art-lms' ),
				'value' => number_format_i18n( $paid_count ),
			),
			array(
				'key'   => 'average_check',
				'label' => __( 'Средний чек', 'art-lms' ),
				'value' => self::format_money( $average ),
			),
			array(
				'key'   => 'new_buyers',
				'label' => __( 'Новых покупателей', 'art-lms' ),
				'value' => number_format_i18n( $new_buyers ),
			),
		);
	}

	/**
	 * Get paid revenue totals for period.
	 *
	 * @param array $period_args Period arguments.
	 * @return array{revenue: float, orders_count: int}
	 */
	public static function get_paid_totals( array $period_args ) {
		global $wpdb;

		$table  = Art_LMS_Orders::table_name();
		$params = array( Art_LMS_Orders::STATUS_PAID );
		$where  = array( 'status = %s', 'paid_at IS NOT NULL', "paid_at <> '0000-00-00 00:00:00'" );
		$where  = array_merge( $where, self::build_paid_at_where( $period_args, $params ) );

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal orders table; WHERE uses prepare placeholders only.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) AS revenue, COUNT(*) AS orders_count FROM `{$table}` WHERE {$where_sql}",
				...$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return array(
			'revenue'      => $row ? (float) $row->revenue : 0,
			'orders_count' => $row ? (int) $row->orders_count : 0,
		);
	}

	/**
	 * Count buyers whose first paid order is in period.
	 *
	 * @param array $period_args Period arguments.
	 * @return int
	 */
	public static function get_new_buyers_count( array $period_args ) {
		global $wpdb;

		$table          = Art_LMS_Orders::table_name();
		$params         = array( Art_LMS_Orders::STATUS_PAID );
		$having_clauses = self::build_first_paid_having_clauses( $period_args, $params );
		$having_sql     = $having_clauses ? implode( ' AND ', $having_clauses ) : '1=1';
		$buyer_expr     = "CASE WHEN user_id > 0 THEN CONCAT('u:', user_id) ELSE CONCAT('e:', LOWER(email)) END";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal orders table; HAVING uses prepare placeholders only.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT MIN(paid_at) AS first_paid
					FROM `{$table}`
					WHERE status = %s
						AND paid_at IS NOT NULL
						AND paid_at <> '0000-00-00 00:00:00'
						AND ( user_id > 0 OR email <> '' )
					GROUP BY {$buyer_expr}
					HAVING {$having_sql}
				) buyers",
				...$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Get revenue grouped by day.
	 *
	 * @param array $period_args Period arguments.
	 * @return array<int, array{date: string, label: string, revenue: float, orders_count: int}>
	 */
	public static function get_revenue_by_day( array $period_args ) {
		global $wpdb;

		$table  = Art_LMS_Orders::table_name();
		$params = array( Art_LMS_Orders::STATUS_PAID );
		$where  = array( 'status = %s', 'paid_at IS NOT NULL', "paid_at <> '0000-00-00 00:00:00'" );
		$where  = array_merge( $where, self::build_paid_at_where( $period_args, $params ) );

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal orders table; WHERE uses prepare placeholders only.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(paid_at) AS day_key, COALESCE(SUM(amount), 0) AS revenue, COUNT(*) AS orders_count
				FROM `{$table}`
				WHERE {$where_sql}
				GROUP BY DATE(paid_at)
				ORDER BY day_key ASC",
				...$params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$map  = array();

		foreach ( $rows ? $rows : array() as $row ) {
			$map[ $row['day_key'] ] = array(
				'revenue'      => (float) $row['revenue'],
				'orders_count' => (int) $row['orders_count'],
			);
		}

		return self::fill_revenue_series( $period_args, $map );
	}

	/**
	 * Get sales grouped by payment button.
	 *
	 * @param array $period_args  Period arguments.
	 * @param float $total_revenue Total revenue for share calculation.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_product_sales( array $period_args, $total_revenue = 0.0 ) {
		global $wpdb;

		$table  = Art_LMS_Orders::table_name();
		$params = array( Art_LMS_Orders::STATUS_PAID );
		$where  = array( 'status = %s', 'product_id > 0', 'paid_at IS NOT NULL', "paid_at <> '0000-00-00 00:00:00'" );
		$where  = array_merge( $where, self::build_paid_at_where( $period_args, $params ) );

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal orders table; WHERE uses prepare placeholders only.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, COUNT(*) AS sales_count, COALESCE(SUM(amount), 0) AS revenue
				FROM `{$table}`
				WHERE {$where_sql}
				GROUP BY product_id
				ORDER BY revenue DESC, sales_count DESC",
				...$params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$items   = array();
		$total   = $total_revenue > 0 ? $total_revenue : 0;

		foreach ( $rows ? $rows : array() as $row ) {
			$product_id = (int) $row['product_id'];
			$revenue    = (float) $row['revenue'];
			$title      = Art_LMS_Payment_Buttons::get_admin_title( $product_id );

			if ( ! $title ) {
				$title = sprintf(
					/* translators: %d: payment button ID */
					__( 'Кнопка #%d', 'art-lms' ),
					$product_id
				);
			}

			$items[] = array(
				'product_id'  => $product_id,
				'title'       => $title,
				'edit_url'    => Art_LMS_Payment_Buttons::get_admin_edit_url( $product_id ),
				'sales_count' => (int) $row['sales_count'],
				'revenue'     => $revenue,
				'revenue_fmt' => self::format_money( $revenue ),
				'share'       => $total > 0 ? round( ( $revenue / $total ) * 100, 1 ) : 0,
			);

			if ( $total <= 0 ) {
				$total += $revenue;
			}
		}

		if ( $total_revenue <= 0 && $total > 0 ) {
			foreach ( $items as $index => $item ) {
				$items[ $index ]['share'] = round( ( $item['revenue'] / $total ) * 100, 1 );
			}
		}

		return $items;
	}

	/**
	 * Get order funnel for period (by order creation date).
	 *
	 * @param array $period_args Period arguments.
	 * @return array{total: int, conversion: float, steps: array<int, array<string, mixed>>}
	 */
	public static function get_order_funnel( array $period_args ) {
		global $wpdb;

		$table  = Art_LMS_Orders::table_name();
		$params = array();
		$where  = array_merge( array( '1=1' ), self::build_created_at_where( $period_args, $params ) );
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal orders table; WHERE uses prepare placeholders only.
		$rows = $params
			? $wpdb->get_results(
				$wpdb->prepare(
					"SELECT status, COUNT(*) AS orders_count FROM `{$table}` WHERE {$where_sql} GROUP BY status",
					...$params
				),
				ARRAY_A
			)
			: $wpdb->get_results(
				"SELECT status, COUNT(*) AS orders_count FROM `{$table}` WHERE {$where_sql} GROUP BY status",
				ARRAY_A
			);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$counts  = array();

		foreach ( $rows ? $rows : array() as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['orders_count'];
		}

		$paid_count    = (int) ( $counts[ Art_LMS_Orders::STATUS_PAID ] ?? 0 );
		$pending_count = (int) ( $counts[ Art_LMS_Orders::STATUS_PENDING ] ?? 0 );
		$other_count   = 0;

		foreach ( $counts as $status => $count ) {
			if ( in_array( $status, array( Art_LMS_Orders::STATUS_PAID, Art_LMS_Orders::STATUS_PENDING ), true ) ) {
				continue;
			}

			$other_count += (int) $count;
		}

		$total      = array_sum( $counts );
		$conversion = $total > 0 ? round( ( $paid_count / $total ) * 100, 1 ) : 0.0;
		$period_filters   = self::get_orders_period_filters( $period_args );

		$steps = array(
			array(
				'key'   => 'total',
				'label' => __( 'Всего заказов', 'art-lms' ),
				'count' => $total,
				'share' => $total > 0 ? 100.0 : 0.0,
				'url'   => self::get_orders_list_url( $period_filters ),
			),
			array(
				'key'   => 'paid',
				'label' => __( 'Оплачено', 'art-lms' ),
				'count' => $paid_count,
				'share' => $total > 0 ? round( ( $paid_count / $total ) * 100, 1 ) : 0.0,
				'url'   => self::get_orders_list_url(
					array_merge(
						$period_filters,
						array( 'status' => Art_LMS_Orders::STATUS_PAID )
					)
				),
			),
			array(
				'key'   => 'pending',
				'label' => __( 'Ожидает оплаты', 'art-lms' ),
				'count' => $pending_count,
				'share' => $total > 0 ? round( ( $pending_count / $total ) * 100, 1 ) : 0.0,
				'url'   => self::get_orders_list_url(
					array_merge(
						$period_filters,
						array( 'status' => Art_LMS_Orders::STATUS_PENDING )
					)
				),
			),
		);

		if ( $other_count > 0 ) {
			$steps[] = array(
				'key'   => 'other',
				'label' => __( 'Прочие статусы', 'art-lms' ),
				'count' => $other_count,
				'share' => $total > 0 ? round( ( $other_count / $total ) * 100, 1 ) : 0.0,
				'url'   => self::get_orders_list_url( $period_filters ),
			);
		}

		return array(
			'total'      => $total,
			'conversion' => $conversion,
			'steps'      => $steps,
		);
	}

	/**
	 * Get sales grouped by payment gateway.
	 *
	 * @param array $period_args Period arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_gateway_sales( array $period_args ) {
		global $wpdb;

		$table        = Art_LMS_Orders::table_name();
		$params       = array();
		$where        = array_merge( array( '1=1' ), self::build_created_at_where( $period_args, $params ) );
		$query_params = array_merge( array( Art_LMS_Orders::STATUS_PAID, Art_LMS_Orders::STATUS_PAID ), $params );
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal orders table; WHERE uses prepare placeholders only.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					CASE WHEN payment_gateway <> '' THEN payment_gateway ELSE 'unknown' END AS gateway_id,
					COUNT(*) AS orders_count,
					SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) AS paid_count,
					COALESCE(SUM(CASE WHEN status = %s THEN amount ELSE 0 END), 0) AS revenue
				FROM `{$table}`
				WHERE {$where_sql}
				GROUP BY gateway_id
				ORDER BY revenue DESC, orders_count DESC",
				...$query_params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$items       = array();
		$revenue_sum = 0.0;

		foreach ( $rows ? $rows : array() as $row ) {
			$revenue_sum += (float) $row['revenue'];
		}

		foreach ( $rows ? $rows : array() as $row ) {
			$gateway_id   = (string) $row['gateway_id'];
			$orders_count = (int) $row['orders_count'];
			$paid_count   = (int) $row['paid_count'];
			$revenue      = (float) $row['revenue'];

			if ( 'unknown' === $gateway_id ) {
				$title = __( 'Не указан', 'art-lms' );
			} else {
				$title = Art_LMS_Orders::get_payment_gateway_internal_label( $gateway_id );
			}

			$items[] = array(
				'gateway_id'      => $gateway_id,
				'title'           => $title,
				'orders_count'    => $orders_count,
				'paid_count'      => $paid_count,
				'revenue'         => $revenue,
				'revenue_fmt'     => self::format_money( $revenue ),
				'conversion'    => $orders_count > 0 ? round( ( $paid_count / $orders_count ) * 100, 1 ) : 0.0,
				'revenue_share' => $revenue_sum > 0 ? round( ( $revenue / $revenue_sum ) * 100, 1 ) : 0.0,
			);
		}

		return $items;
	}

	/**
	 * Build orders list URL for statistics drill-down.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	public static function get_orders_list_url( array $args = array() ) {
		return Art_LMS_Admin_Orders::get_list_url( $args );
	}

	/**
	 * Extract order list date filters from statistics period.
	 *
	 * @param array $period_args Period arguments.
	 * @return array<string, string>
	 */
	public static function get_orders_period_filters( array $period_args ) {
		$filters = array();

		if ( ! empty( $period_args['date_from'] ) ) {
			$filters['date_from'] = $period_args['date_from'];
		}

		if ( ! empty( $period_args['date_to'] ) ) {
			$filters['date_to'] = $period_args['date_to'];
		}

		return $filters;
	}

	/**
	 * Format money for admin UI.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public static function format_money( $amount, $currency = 'RUB' ) {
		$formatted = number_format( (float) $amount, 2, ',', ' ' );

		if ( 'RUB' === strtoupper( $currency ) ) {
			return $formatted . ' ₽';
		}

		return trim( $formatted . ' ' . strtoupper( $currency ) );
	}

	/**
	 * Build page URL with period args.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function get_page_url( array $args = array() ) {
		$defaults = array(
			'page' => Art_LMS_Admin_Statistics::PAGE_LIST,
		);

		return add_query_arg( wp_parse_args( $args, $defaults ), admin_url( 'admin.php' ) );
	}

	/**
	 * Format day label for chart axis.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return string
	 */
	public static function format_chart_day_label( $date ) {
		$timestamp = strtotime( (string) $date );

		if ( ! $timestamp ) {
			return (string) $date;
		}

		return wp_date( 'j M', $timestamp );
	}

	/**
	 * Get max revenue value for chart scaling.
	 *
	 * @param array $rows Revenue rows.
	 * @return float
	 */
	private static function get_chart_max_value( array $rows ) {
		$max = 0.0;

		foreach ( $rows as $row ) {
			$max = max( $max, (float) ( $row['revenue'] ?? 0 ) );
		}

		return $max;
	}

	/**
	 * Fill missing days in revenue series.
	 *
	 * @param array $period_args Period arguments.
	 * @param array $map         Existing day map.
	 * @return array<int, array{date: string, label: string, revenue: float, orders_count: int}>
	 */
	private static function fill_revenue_series( array $period_args, array $map ) {
		$date_from = $period_args['date_from'] ?? '';
		$date_to   = $period_args['date_to'] ?? '';

		if ( ! $date_from || ! $date_to ) {
			$series = array();

			foreach ( $map as $day_key => $data ) {
				$series[] = array(
					'date'         => $day_key,
					'label'        => self::format_chart_day_label( $day_key ),
					'revenue'      => (float) $data['revenue'],
					'orders_count' => (int) $data['orders_count'],
				);
			}

			return $series;
		}

		$series    = array();
		$current   = strtotime( $date_from . ' 00:00:00' );
		$end       = strtotime( $date_to . ' 00:00:00' );
		$guard     = 0;

		while ( $current <= $end && $guard < 400 ) {
			$day_key = wp_date( 'Y-m-d', $current );
			$data    = $map[ $day_key ] ?? array(
				'revenue'      => 0,
				'orders_count' => 0,
			);

			$series[] = array(
				'date'         => $day_key,
				'label'        => self::format_chart_day_label( $day_key ),
				'revenue'      => (float) $data['revenue'],
				'orders_count' => (int) $data['orders_count'],
			);

			$current = strtotime( '+1 day', $current );
			++$guard;
		}

		return $series;
	}

	/**
	 * Build paid_at WHERE clauses.
	 *
	 * @param array $period_args Period arguments.
	 * @param array $params      Query params passed by reference.
	 * @return array<int, string>
	 */
	private static function build_paid_at_where( array $period_args, array &$params ) {
		$clauses   = array();
		$date_from = $period_args['date_from'] ?? '';
		$date_to   = $period_args['date_to'] ?? '';

		if ( $date_from ) {
			$clauses[] = 'paid_at >= %s';
			$params[]  = $date_from . ' 00:00:00';
		}

		if ( $date_to ) {
			$clauses[] = 'paid_at <= %s';
			$params[]  = $date_to . ' 23:59:59';
		}

		return $clauses;
	}

	/**
	 * Build HAVING clauses for first paid date filters.
	 *
	 * @param array $period_args Period arguments.
	 * @param array $params      Query params passed by reference.
	 * @return array<int, string>
	 */
	private static function build_first_paid_having_clauses( array $period_args, array &$params ) {
		$parts     = array();
		$date_from = $period_args['date_from'] ?? '';
		$date_to   = $period_args['date_to'] ?? '';

		if ( $date_from ) {
			$parts[]  = 'first_paid >= %s';
			$params[] = $date_from . ' 00:00:00';
		}

		if ( $date_to ) {
			$parts[]  = 'first_paid <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		return $parts;
	}

	/**
	 * Build created_at WHERE clauses.
	 *
	 * @param array $period_args Period arguments.
	 * @param array $params      Query params passed by reference.
	 * @return array<int, string>
	 */
	private static function build_created_at_where( array $period_args, array &$params ) {
		$clauses   = array();
		$date_from = $period_args['date_from'] ?? '';
		$date_to   = $period_args['date_to'] ?? '';

		if ( $date_from ) {
			$clauses[] = 'created_at >= %s';
			$params[]  = $date_from . ' 00:00:00';
		}

		if ( $date_to ) {
			$clauses[] = 'created_at <= %s';
			$params[]  = $date_to . ' 23:59:59';
		}

		return $clauses;
	}

	// phpcs:enable
}
