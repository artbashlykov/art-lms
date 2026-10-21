<?php
/**
 * Users list enhancements (admin).
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Admin_Users
 */
class Art_LMS_Admin_Users {

	const COLUMN_REGISTERED = 'art_lms_registered';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'manage_users_columns', array( __CLASS__, 'filter_columns' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_column' ), 10, 3 );
		add_filter( 'manage_users_sortable_columns', array( __CLASS__, 'filter_sortable_columns' ) );
		add_action( 'pre_get_users', array( __CLASS__, 'filter_users_query' ) );
	}

	/**
	 * Add registration date column.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function filter_columns( $columns ) {
		$columns = is_array( $columns ) ? $columns : array();

		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			// Place after email when present; otherwise append.
			if ( 'email' === $key ) {
				$new[ self::COLUMN_REGISTERED ] = __( 'Дата регистрации', 'art-lms' );
			}
		}

		if ( ! isset( $new[ self::COLUMN_REGISTERED ] ) ) {
			$new[ self::COLUMN_REGISTERED ] = __( 'Дата регистрации', 'art-lms' );
		}

		return $new;
	}

	/**
	 * Make registration date sortable (maps to core user_registered).
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public static function filter_sortable_columns( $columns ) {
		$columns = is_array( $columns ) ? $columns : array();

		// Value must be a WP_User_Query orderby key — "registered" is supported by core.
		$columns[ self::COLUMN_REGISTERED ] = 'registered';

		return $columns;
	}

	/**
	 * Render registration date cell.
	 *
	 * @param string $output      Custom column output.
	 * @param string $column_name Column key.
	 * @param int    $user_id     User ID.
	 * @return string
	 */
	public static function render_column( $output, $column_name, $user_id ) {
		if ( self::COLUMN_REGISTERED !== $column_name ) {
			return $output;
		}

		$user = get_userdata( absint( $user_id ) );

		if ( ! $user || empty( $user->user_registered ) ) {
			return '—';
		}

		// user_registered is GMT; mysql2date converts to site timezone.
		$formatted = mysql2date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$user->user_registered,
			true
		);

		return $formatted ? esc_html( $formatted ) : '—';
	}

	/**
	 * Ensure sorting by our column uses core registered orderby.
	 *
	 * @param WP_User_Query $query User query.
	 */
	public static function filter_users_query( $query ) {
		if ( ! is_admin() || ! $query instanceof WP_User_Query ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( self::COLUMN_REGISTERED === $orderby ) {
			$query->set( 'orderby', 'registered' );
		}
	}
}
