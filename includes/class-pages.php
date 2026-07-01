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

	const TYPE_ACCOUNT = 'account';
	const TYPE_SUCCESS = 'success';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'display_post_states', array( __CLASS__, 'add_page_post_states' ), 10, 2 );
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

		if ( ! empty( $stored[ $type ] ) ) {
			$stored_id = (int) $stored[ $type ];

			if ( self::is_template_page( $stored_id, $type ) ) {
				return $stored_id;
			}

			self::remove_stored_template_page_id( $type );
		}

		$settings_page_id = self::get_settings_page_id_for_type( $type );

		if ( $settings_page_id && self::is_template_page( $settings_page_id, $type ) ) {
			self::store_template_page_id( $type, $settings_page_id );
			return $settings_page_id;
		}

		return 0;
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
	 * Whether a page can be selected or assigned in plugin settings.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	private static function is_usable_page( $page_id ) {
		$page_id = absint( $page_id );

		if ( ! $page_id ) {
			return false;
		}

		$post = get_post( $page_id );

		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return false;
		}

		return in_array( $post->post_status, array( 'publish', 'draft', 'private', 'pending', 'future' ), true );
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

		if ( ! self::is_usable_page( $page_id ) ) {
			return false;
		}

		return sanitize_key( $type ) === sanitize_key( (string) get_post_meta( $page_id, self::META_KEY, true ) );
	}

	/**
	 * Remove a cached template page ID.
	 *
	 * @param string $type Page type.
	 */
	private static function remove_stored_template_page_id( $type ) {
		$type = sanitize_key( $type );

		if ( ! $type ) {
			return;
		}

		$stored = self::get_stored_template_page_ids();

		if ( empty( $stored[ $type ] ) ) {
			return;
		}

		unset( $stored[ $type ] );

		update_option( self::TEMPLATE_PAGE_IDS_OPTION, $stored, false );
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

		if ( ! self::is_usable_page( $page_id ) ) {
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
	 * Show ART LMS page roles in the Pages list table.
	 *
	 * @param string[] $post_states An array of post display states.
	 * @param WP_Post  $post        Current post object.
	 * @return string[]
	 */
	public static function add_page_post_states( $post_states, $post ) {
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return $post_states;
		}

		$page_id = (int) $post->ID;

		if ( $page_id === Art_LMS_Settings::get_account_page_id() ) {
			$post_states['art_lms_account'] = __( 'ART LMS - кабинет', 'art-lms' );
		}

		if ( $page_id === Art_LMS_Settings::get_success_page_id() ) {
			$post_states['art_lms_success'] = __( 'ART LMS - страница оплаты', 'art-lms' );
		}

		return $post_states;
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
				'slug'    => 'cabinet',
				'content' => self::get_account_page_content(),
			);
		}

		if ( self::TYPE_SUCCESS === $type ) {
			return array(
				'title'   => __( 'Проверка оплаты', 'art-lms' ),
				'slug'    => 'payment-check',
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
		return '<!-- wp:art-lms/customer-account /-->';
	}

	/**
	 * Block markup for success page.
	 *
	 * @return string
	 */
	private static function get_success_page_content() {
		return '<!-- wp:art-lms/payment-status /-->';
	}
}
