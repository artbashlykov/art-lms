<?php
/**
 * Product access management.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom access table queries.

/**
 * Class Art_LMS_Access
 */
class Art_LMS_Access {

	const STATUS_ACTIVE  = 'active';
	const STATUS_EXPIRED = 'expired';
	const STATUS_REVOKED = 'revoked';

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Access checks are used by shortcodes and protected content views.
	}

	/**
	 * Get access table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'art_lms_access';
	}

	/**
	 * Grant access to a product.
	 *
	 * @param array $data Access data.
	 * @return int|false Access ID or false.
	 */
	public static function grant( $data ) {
		global $wpdb;

		$defaults = array(
			'user_id'    => 0,
			'product_id' => 0,
			'order_id'   => 0,
			'status'     => self::STATUS_ACTIVE,
			'starts_at'  => current_time( 'mysql' ),
			'expires_at' => null,
			'created_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'user_id'    => absint( $data['user_id'] ),
				'product_id' => absint( $data['product_id'] ),
				'order_id'   => absint( $data['order_id'] ),
				'status'     => sanitize_text_field( $data['status'] ),
				'starts_at'  => $data['starts_at'],
				'expires_at' => $data['expires_at'],
				'created_at' => $data['created_at'],
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Check if user has active access to product.
	 *
	 * @param int $user_id    User ID.
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function user_has_access( $user_id, $product_id ) {
		global $wpdb;

		$user_id    = absint( $user_id );
		$product_id = absint( $product_id );

		if ( ! $user_id || ! $product_id ) {
			return false;
		}

		$table = self::table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}`
				WHERE user_id = %d AND product_id = %d AND status = %s
				ORDER BY id DESC LIMIT 1",
				$user_id,
				$product_id,
				self::STATUS_ACTIVE
			)
		);

		if ( ! $row ) {
			return false;
		}

		if ( ! empty( $row->expires_at ) && strtotime( $row->expires_at ) < current_time( 'timestamp' ) ) {
			self::expire( (int) $row->id );
			return false;
		}

		return true;
	}

	/**
	 * Get all active access records for user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_user_access_list( $user_id ) {
		global $wpdb;

		$table = self::table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}`
				WHERE user_id = %d AND status = %s
				ORDER BY created_at DESC",
				absint( $user_id ),
				self::STATUS_ACTIVE
			)
		);

		$active = array();

		foreach ( $rows as $row ) {
			if ( ! empty( $row->expires_at ) && strtotime( $row->expires_at ) < current_time( 'timestamp' ) ) {
				self::expire( (int) $row->id );
				continue;
			}

			$active[] = $row;
		}

		return $active;
	}

	/**
	 * Mark access as expired.
	 *
	 * @param int $access_id Access ID.
	 * @return bool
	 */
	public static function expire( $access_id ) {
		global $wpdb;

		return (bool) $wpdb->update(
			self::table_name(),
			array( 'status' => self::STATUS_EXPIRED ),
			array( 'id' => absint( $access_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Reassign active access rows for an order to another user.
	 *
	 * @param int $order_id Order ID.
	 * @param int $user_id  New user ID.
	 * @return bool
	 */
	public static function reassign_by_order_id( $order_id, $user_id ) {
		global $wpdb;

		$order_id = absint( $order_id );
		$user_id  = absint( $user_id );

		if ( ! $order_id || ! $user_id ) {
			return false;
		}

		$result = $wpdb->update(
			self::table_name(),
			array( 'user_id' => $user_id ),
			array(
				'order_id' => $order_id,
				'status'   => self::STATUS_ACTIVE,
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Revoke all active access rows granted by an order.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public static function revoke_by_order_id( $order_id ) {
		global $wpdb;

		$result = $wpdb->update(
			self::table_name(),
			array( 'status' => self::STATUS_REVOKED ),
			array(
				'order_id' => absint( $order_id ),
				'status'   => self::STATUS_ACTIVE,
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Delete all access rows linked to an order.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public static function delete_by_order_id( $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return false;
		}

		$result = $wpdb->delete(
			self::table_name(),
			array( 'order_id' => $order_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Check whether an order already granted active access to a material.
	 *
	 * @param int $order_id   Order ID.
	 * @param int $product_id Material post ID.
	 * @return bool
	 */
	public static function has_active_for_order_product( $order_id, $product_id ) {
		global $wpdb;

		$table = self::table_name();

		$access_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}`
				WHERE order_id = %d AND product_id = %d AND status = %s
				LIMIT 1",
				absint( $order_id ),
				absint( $product_id ),
				self::STATUS_ACTIVE
			)
		);

		return ! empty( $access_id );
	}

	// phpcs:enable
}
