<?php
/**
 * Admin statistics page.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Admin_Statistics
 */
class Art_LMS_Admin_Statistics {

	const PAGE_LIST = 'art-lms-statistics';

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Page is registered from Art_LMS_Admin_Menu.
	}

	/**
	 * Render statistics page.
	 */
	public static function render_page() {
		if ( ! Art_LMS_Security::can_manage() ) {
			return;
		}

		$period   = Art_LMS_Statistics::parse_period_from_request();
		$dashboard = Art_LMS_Statistics::get_dashboard( $period );

		include ART_LMS_PLUGIN_DIR . 'admin/views/page-statistics.php';
	}

	/**
	 * Build period filter URL.
	 *
	 * @param string $period    Period preset.
	 * @param array  $extra     Extra query args.
	 * @return string
	 */
	public static function get_period_url( $period, array $extra = array() ) {
		$args = array_merge(
			array(
				'period' => $period,
			),
			$extra
		);

		if ( Art_LMS_Statistics::PERIOD_CUSTOM !== $period ) {
			unset( $args['date_from'], $args['date_to'] );
		}

		return Art_LMS_Statistics::get_page_url( $args );
	}
}
