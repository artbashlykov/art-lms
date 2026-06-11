<?php
/**
 * Plugin page templates and quick-create helpers.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Pages
 */
class Art_LMS_Pages {

	const META_KEY = '_art_lms_plugin_page';

	const TEMPLATE_PAGE_IDS_OPTION = 'art_lms_template_page_ids';

	const TEMPLATE_PAGES_BACKFILLED_OPTION = 'art_lms_template_pages_backfilled';

	const TYPE_ACCOUNT = 'account';
	const TYPE_SUCCESS = 'success';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_backfill_template_page_ids' ), 5 );
	}

	/**
	 * Create or reuse a plugin page from template and assign it in settings.
	 *
	 * @param string $type Page type.
	 * @return array|WP_Error
	 */
	public static function create_and_assign( $type ) {
		$type = sanitize_key( $type );

		if ( ! in_array( $type, array( self::TYPE_ACCOUNT, self::TYPE_SUCCESS ), true ) ) {
			return new WP_Error( 'invalid_type', __( 'Неизвестный тип страницы.', 'art-lms' ) );
		}

		self::maybe_backfill_template_page_ids();

		$existing_id = self::find_template_page_id( $type );

		if ( $existing_id ) {
			$page_id = $existing_id;
		} else {
			$page_id = self::create_template_page( $type );

			if ( is_wp_error( $page_id ) ) {
				return $page_id;
			}
		}

		$assigned = self::assign_page_to_settings( $type, $page_id );

		if ( is_wp_error( $assigned ) ) {
			return $assigned;
		}

		return array(
			'page_id'    => (int) $page_id,
			'page_title' => get_the_title( $page_id ),
			'edit_url'   => get_edit_post_link( $page_id, 'raw' ),
			'view_url'   => get_permalink( $page_id ),
			'created'    => ! $existing_id,
		);
	}

	/**
	 * Find an existing plugin template page.
	 *
	 * @param string $type Page type.
	 * @return int
	 */
	public static function find_template_page_id( $type ) {
		$type = sanitize_key( $type );

		$stored = self::get_stored_template_page_ids();

		if ( ! empty( $stored[ $type ] ) && self::is_template_page( (int) $stored[ $type ], $type ) ) {
			return (int) $stored[ $type ];
		}

		$settings_page_id = self::get_settings_page_id_for_type( $type );

		if ( $settings_page_id && self::is_template_page( $settings_page_id, $type ) ) {
			self::store_template_page_id( $type, $settings_page_id );
			return $settings_page_id;
		}

		return 0;
	}

	/**
	 * Backfill template page IDs for installs created before option caching.
	 *
	 * @param bool $force Skip admin capability checks (plugin activation).
	 */
	public static function maybe_backfill_template_page_ids( $force = false ) {
		if ( get_option( self::TEMPLATE_PAGES_BACKFILLED_OPTION ) ) {
			return;
		}

		if ( ! $force && ( ! is_admin() || ! current_user_can( 'manage_options' ) ) ) {
			return;
		}

		foreach ( array( self::TYPE_ACCOUNT, self::TYPE_SUCCESS ) as $type ) {
			if ( self::find_template_page_id( $type ) ) {
				continue;
			}

			$page_id = self::discover_legacy_template_page_id( $type );

			if ( $page_id ) {
				self::store_template_page_id( $type, $page_id );
			}
		}

		update_option( self::TEMPLATE_PAGES_BACKFILLED_OPTION, ART_LMS_VERSION, false );
	}

	/**
	 * Get cached template page IDs keyed by page type.
	 *
	 * @return array<string, int>
	 */
	private static function get_stored_template_page_ids() {
		$stored = get_option( self::TEMPLATE_PAGE_IDS_OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Persist a template page ID for quick lookup.
	 *
	 * @param string $type    Page type.
	 * @param int    $page_id Page ID.
	 */
	private static function store_template_page_id( $type, $page_id ) {
		$type    = sanitize_key( $type );
		$page_id = absint( $page_id );

		if ( ! $type || ! $page_id ) {
			return;
		}

		$stored          = self::get_stored_template_page_ids();
		$stored[ $type ] = $page_id;

		update_option( self::TEMPLATE_PAGE_IDS_OPTION, $stored, false );
	}

	/**
	 * Get configured page ID from plugin settings for a template type.
	 *
	 * @param string $type Page type.
	 * @return int
	 */
	private static function get_settings_page_id_for_type( $type ) {
		if ( self::TYPE_ACCOUNT === $type ) {
			return Art_LMS_Settings::get_account_page_id();
		}

		if ( self::TYPE_SUCCESS === $type ) {
			return Art_LMS_Settings::get_success_page_id();
		}

		return 0;
	}

	/**
	 * Whether a page is a plugin template of the given type.
	 *
	 * @param int    $page_id Page ID.
	 * @param string $type    Page type.
	 * @return bool
	 */
	private static function is_template_page( $page_id, $type ) {
		$page_id = absint( $page_id );

		if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
			return false;
		}

		return sanitize_key( $type ) === sanitize_key( (string) get_post_meta( $page_id, self::META_KEY, true ) );
	}

	/**
	 * One-time legacy lookup by post meta before template IDs were cached.
	 *
	 * @param string $type Page type.
	 * @return int
	 */
	private static function discover_legacy_template_page_id( $type ) {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Runs once per site during admin backfill; result is cached in options.
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_key'       => self::META_KEY,
				'meta_value'     => sanitize_key( $type ),
				'fields'         => 'ids',
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		return ! empty( $pages ) ? (int) $pages[0] : 0;
	}

	/**
	 * Create a page from plugin template.
	 *
	 * @param string $type Page type.
	 * @return int|WP_Error
	 */
	public static function create_template_page( $type ) {
		$config = self::get_template_config( $type );

		if ( ! $config ) {
			return new WP_Error( 'invalid_type', __( 'Неизвестный тип страницы.', 'art-lms' ) );
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $config['title'],
				'post_name'    => $config['slug'],
				'post_content' => $config['content'],
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		update_post_meta( $page_id, self::META_KEY, sanitize_key( $type ) );
		self::store_template_page_id( $type, $page_id );

		return (int) $page_id;
	}

	/**
	 * Assign page ID to plugin settings.
	 *
	 * @param string $type    Page type.
	 * @param int    $page_id Page ID.
	 * @return true|WP_Error
	 */
	public static function assign_page_to_settings( $type, $page_id ) {
		$page_id = absint( $page_id );

		if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
			return new WP_Error( 'invalid_page', __( 'Страница не найдена.', 'art-lms' ) );
		}

		if ( self::TYPE_ACCOUNT === $type ) {
			Art_LMS_Settings::assign_account_page( $page_id );
			return true;
		}

		if ( self::TYPE_SUCCESS === $type ) {
			Art_LMS_Settings::assign_success_page( $page_id );
			return true;
		}

		return new WP_Error( 'invalid_type', __( 'Неизвестный тип страницы.', 'art-lms' ) );
	}

	/**
	 * Get template config for page type.
	 *
	 * @param string $type Page type.
	 * @return array|null
	 */
	public static function get_template_config( $type ) {
		if ( self::TYPE_ACCOUNT === $type ) {
			return array(
				'title'   => __( 'Личный кабинет', 'art-lms' ),
				'slug'    => 'lichnyj-kabinet',
				'content' => self::get_account_page_content(),
			);
		}

		if ( self::TYPE_SUCCESS === $type ) {
			return array(
				'title'   => __( 'Оплата успешна', 'art-lms' ),
				'slug'    => 'oplata-uspeshna',
				'content' => self::get_success_page_content(),
			);
		}

		return null;
	}

	/**
	 * Get manual setup hint for page type.
	 *
	 * @param string $type Page type.
	 * @return string
	 */
	public static function get_manual_setup_hint( $type ) {
		if ( self::TYPE_ACCOUNT === $type ) {
			return __( 'Создайте страницу вручную и разместите блок «АРТ ЛМС: Личный кабинет» или shortcode [art_lms_account].', 'art-lms' );
		}

		if ( self::TYPE_SUCCESS === $type ) {
			return __( 'Создайте страницу вручную и разместите блок «АРТ ЛМС: Статус оплаты» или shortcode [art_lms_payment_status].', 'art-lms' );
		}

		return '';
	}

	/**
	 * Get page permalinks for admin page pickers.
	 *
	 * @return array<int, string>
	 */
	public static function get_admin_page_urls() {
		$pages = get_pages(
			array(
				'post_status' => array( 'publish', 'private', 'draft' ),
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);

		$urls = array();

		foreach ( $pages as $page ) {
			$url = get_permalink( $page );

			if ( $url ) {
				$urls[ (int) $page->ID ] = $url;
			}
		}

		return $urls;
	}

	/**
	 * Block markup for account page.
	 *
	 * @return string
	 */
	private static function get_account_page_content() {
		$heading = esc_html__( 'Личный кабинет', 'art-lms' );

		return "<!-- wp:heading {\"level\":1} -->\n<h1 class=\"wp-block-heading\">{$heading}</h1>\n<!-- /wp:heading -->\n\n<!-- wp:art-lms/customer-account /-->";
	}

	/**
	 * Block markup for success page.
	 *
	 * @return string
	 */
	private static function get_success_page_content() {
		$heading = esc_html__( 'Спасибо за покупку!', 'art-lms' );

		return "<!-- wp:heading {\"level\":1} -->\n<h1 class=\"wp-block-heading\">{$heading}</h1>\n<!-- /wp:heading -->\n\n<!-- wp:art-lms/payment-status /-->";
	}
}
