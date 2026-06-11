<?php
/**
 * Payment buttons custom post type.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public LMS URLs use shareable GET parameters (checkout, account, payment status).

/**
 * Class Art_LMS_Payment_Buttons
 */
class Art_LMS_Payment_Buttons {

	const POST_TYPE = 'art_lms_pay_button';

	const POST_STATUS_ARCHIVED = 'archived';

	const CHECKOUT_QUERY_ARG = 'art_lms_button';

	const META_PRICE         = '_art_lms_price';
	const META_COMPARE_PRICE = '_art_lms_compare_price';
	const META_PRODUCT_NAME  = '_art_lms_product_name';
	const META_ACCESS_DAYS   = '_art_lms_access_days';
	const META_MATERIAL_IDS  = '_art_lms_material_ids';
	const META_ENABLED       = '_art_lms_button_enabled';

	const ADMIN_VISIBILITY_QUERY_ARG = 'art_lms_button_visibility';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_list_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_list_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'filter_admin_row_actions' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_admin_list_query' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_admin_list_filters' ) );
		add_filter( 'posts_join', array( __CLASS__, 'filter_admin_list_search_join' ), 10, 2 );
		add_filter( 'posts_search', array( __CLASS__, 'filter_admin_list_search_where' ), 10, 2 );
		add_filter( 'posts_groupby', array( __CLASS__, 'filter_admin_list_search_groupby' ), 10, 2 );
	}

	/**
	 * Register payment button post type.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Платежные кнопки', 'art-lms' ),
			'singular_name'      => __( 'Платежная кнопка', 'art-lms' ),
			'add_new'            => __( 'Добавить', 'art-lms' ),
			'add_new_item'       => __( 'Добавить платежную кнопку', 'art-lms' ),
			'edit_item'          => __( 'Редактировать кнопку', 'art-lms' ),
			'new_item'           => __( 'Новая кнопка', 'art-lms' ),
			'search_items'       => __( 'Искать кнопки', 'art-lms' ),
			'not_found'          => __( 'Кнопки не найдены', 'art-lms' ),
			'not_found_in_trash' => __( 'В корзине кнопок нет', 'art-lms' ),
			'menu_name'          => __( 'Платежные кнопки', 'art-lms' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'              => 'manage_options',
					'read_post'              => 'manage_options',
					'delete_post'            => 'manage_options',
					'edit_posts'             => 'manage_options',
					'edit_others_posts'      => 'manage_options',
					'publish_posts'          => 'manage_options',
					'read_private_posts'     => 'manage_options',
					'delete_posts'           => 'manage_options',
					'delete_private_posts'   => 'manage_options',
					'delete_published_posts' => 'manage_options',
					'delete_others_posts'    => 'manage_options',
					'edit_private_posts'     => 'manage_options',
					'edit_published_posts'   => 'manage_options',
					'create_posts'           => 'manage_options',
				),
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'show_in_rest'        => true,
			)
		);

		self::register_post_status();
	}

	/**
	 * Register archived post status for payment buttons.
	 */
	public static function register_post_status() {
		register_post_status(
			self::POST_STATUS_ARCHIVED,
			array(
				'label'                     => _x( 'Архив', 'payment button post status', 'art-lms' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of archived payment buttons */
				'label_count'               => _n_noop(
					'Архив <span class="count">(%s)</span>',
					'Архив <span class="count">(%s)</span>',
					'art-lms'
				),
				'post_type'                 => array( self::POST_TYPE ),
			)
		);
	}

	/**
	 * Register meta for REST API.
	 */
	public static function register_meta() {
		$string_meta = array(
			self::META_PRICE,
			self::META_COMPARE_PRICE,
			self::META_PRODUCT_NAME,
		);

		foreach ( $string_meta as $meta_key ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'default'       => '',
					'auth_callback' => array( __CLASS__, 'meta_auth_callback' ),
				)
			);
		}

		register_post_meta(
			self::POST_TYPE,
			self::META_ACCESS_DAYS,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'integer',
				'default'       => 0,
				'auth_callback' => array( __CLASS__, 'meta_auth_callback' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_MATERIAL_IDS,
			array(
				'show_in_rest'  => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
				),
				'single'        => true,
				'type'          => 'array',
				'default'       => array(),
				'auth_callback' => array( __CLASS__, 'meta_auth_callback' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_ENABLED,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'boolean',
				'default'       => true,
				'auth_callback' => array( __CLASS__, 'meta_auth_callback' ),
			)
		);
	}

	/**
	 * Meta auth callback.
	 *
	 * @return bool
	 */
	public static function meta_auth_callback() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Shared config for payment button editor UI (meta box + admin script).
	 *
	 * @return array
	 */
	public static function get_payment_button_editor_config() {
		return array(
			'metaKeys' => array(
				'productName'  => self::META_PRODUCT_NAME,
				'price'        => self::META_PRICE,
				'comparePrice' => self::META_COMPARE_PRICE,
				'accessDays'   => self::META_ACCESS_DAYS,
				'materialIds'  => self::META_MATERIAL_IDS,
				'enabled'      => self::META_ENABLED,
			),
			'accessPresets' => array(
				array(
					'value' => '0',
					'label' => __( 'Без ограничения', 'art-lms' ),
				),
				array(
					'value' => '30',
					'label' => __( '30 дней', 'art-lms' ),
				),
				array(
					'value' => '90',
					'label' => __( '90 дней', 'art-lms' ),
				),
				array(
					'value' => '180',
					'label' => __( '180 дней', 'art-lms' ),
				),
				array(
					'value' => '365',
					'label' => __( '1 год', 'art-lms' ),
				),
				array(
					'value' => 'custom',
					'label' => __( 'Свой срок…', 'art-lms' ),
				),
			),
		);
	}

	/**
	 * Read button meta.
	 *
	 * @param int $button_id Button post ID.
	 * @return array
	 */
	public static function get_meta( $button_id ) {
		$material_ids = get_post_meta( $button_id, self::META_MATERIAL_IDS, true );

		if ( ! is_array( $material_ids ) ) {
			$material_ids = array();
		}

		$material_ids = array_values(
			array_filter(
				array_map( 'absint', $material_ids )
			)
		);

		return array(
			'product_name'  => get_post_meta( $button_id, self::META_PRODUCT_NAME, true ),
			'price'         => get_post_meta( $button_id, self::META_PRICE, true ),
			'compare_price' => get_post_meta( $button_id, self::META_COMPARE_PRICE, true ),
			'access_days'   => (int) get_post_meta( $button_id, self::META_ACCESS_DAYS, true ),
			'material_ids'  => $material_ids,
			'enabled'       => self::is_enabled( $button_id ),
		);
	}

	/**
	 * Check whether payment button is enabled in admin settings.
	 *
	 * @param int $button_id Button post ID.
	 * @return bool
	 */
	public static function is_enabled( $button_id ) {
		$button_id = absint( $button_id );

		if ( ! $button_id ) {
			return false;
		}

		$value = get_post_meta( $button_id, self::META_ENABLED, true );

		if ( '' === $value ) {
			return true;
		}

		return rest_sanitize_boolean( $value );
	}

	/**
	 * Payment button options for admin manual order form.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_order_form_options() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array();

		foreach ( $posts as $post ) {
			$button_id = (int) $post->ID;
			$meta      = self::get_meta( $button_id );
			$materials = array();

			foreach ( $meta['material_ids'] as $material_id ) {
				$title = get_the_title( $material_id );

				if ( $title ) {
					$materials[] = $title;
				}
			}

			$options[] = array(
				'id'          => $button_id,
				'title'       => self::get_product_name( $button_id ),
				'admin_title' => get_the_title( $button_id ),
				'price'       => trim( (string) $meta['price'] ),
				'materials'   => $materials,
			);
		}

		return $options;
	}

	/**
	 * Validate payment button for manual admin order.
	 *
	 * @param int $button_id Button ID.
	 * @return true|WP_Error
	 */
	public static function validate_order_form_button( $button_id ) {
		$button_id = absint( $button_id );

		if ( ! $button_id ) {
			return new WP_Error( 'missing_product', __( 'Выберите платёжную кнопку.', 'art-lms' ) );
		}

		$button = get_post( $button_id );

		if ( ! $button || self::POST_TYPE !== $button->post_type ) {
			return new WP_Error( 'invalid_product', __( 'Платёжная кнопка не найдена.', 'art-lms' ) );
		}

		if ( self::is_archived( $button_id ) ) {
			return new WP_Error( 'archived_product', __( 'Платёжная кнопка находится в архиве.', 'art-lms' ) );
		}

		if ( 'publish' !== $button->post_status ) {
			return new WP_Error( 'invalid_product', __( 'Платёжная кнопка не опубликована.', 'art-lms' ) );
		}

		if ( ! self::is_enabled( $button_id ) ) {
			return new WP_Error(
				'disabled_product',
				Art_LMS_Settings::format_checkout_form_message( 'button_disabled' )
			);
		}

		$meta = self::get_meta( $button_id );

		if ( empty( $meta['material_ids'] ) ) {
			return new WP_Error( 'no_materials', __( 'У выбранной кнопки нет материалов. Добавьте материалы в настройках кнопки.', 'art-lms' ) );
		}

		return true;
	}

	/**
	 * Product name for checkout and frontend output.
	 *
	 * @param int $button_id Button post ID.
	 * @return string
	 */
	public static function get_product_name( $button_id ) {
		$button_id = absint( $button_id );

		if ( ! $button_id ) {
			return '';
		}

		$name = trim( (string) get_post_meta( $button_id, self::META_PRODUCT_NAME, true ) );

		if ( '' !== $name ) {
			return $name;
		}

		return get_the_title( $button_id );
	}

	/**
	 * Admin-only payment button title (post title).
	 *
	 * @param int $button_id Button post ID.
	 * @return string
	 */
	public static function get_admin_title( $button_id ) {
		$button_id = absint( $button_id );

		if ( ! $button_id ) {
			return '';
		}

		return get_the_title( $button_id );
	}

	/**
	 * Admin edit URL for a payment button post.
	 *
	 * @param int $button_id Button post ID.
	 * @return string
	 */
	public static function get_admin_edit_url( $button_id ) {
		$button_id = absint( $button_id );

		if ( ! $button_id || ! current_user_can( 'edit_post', $button_id ) ) {
			return '';
		}

		$url = get_edit_post_link( $button_id, 'raw' );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Format price for display.
	 *
	 * @param string|float $price Raw price.
	 * @return string
	 */
	public static function format_price( $price ) {
		$price = trim( (string) $price );

		if ( '' === $price ) {
			return '';
		}

		return $price . ' ₽';
	}

	/**
	 * Get shortcode for payment button.
	 *
	 * @param int $button_id Button post ID.
	 * @return string
	 */
	public static function get_shortcode( $button_id ) {
		$button_id = absint( $button_id );

		if ( ! $button_id ) {
			return '';
		}

		return '[art_lms_payment_button id="' . $button_id . '"]';
	}

	/**
	 * Get direct checkout URL for payment button.
	 *
	 * @param int $button_id Button post ID.
	 * @return string
	 */
	public static function get_checkout_link( $button_id ) {
		$button_id = absint( $button_id );

		if ( ! $button_id ) {
			return '';
		}

		return Art_LMS_Settings::get_checkout_url( $button_id );
	}

	/**
	 * Default payment button label for block/shortcode output.
	 *
	 * @return string
	 */
	public static function get_default_button_text() {
		return __( 'Оформить', 'art-lms' );
	}

	/**
	 * Normalize display options for payment button markup.
	 *
	 * @param array $args Render args.
	 * @return array
	 */
	public static function normalize_display_args( $args = array() ) {
		$defaults = array(
			'button_text'        => self::get_default_button_text(),
			'hide_product_name'  => false,
			'hide_compare_price' => false,
			'hide_price'         => false,
		);

		$args = wp_parse_args( $args, $defaults );

		$args['button_text'] = sanitize_text_field( (string) $args['button_text'] );

		if ( '' === $args['button_text'] ) {
			$args['button_text'] = $defaults['button_text'];
		}

		$args['hide_product_name']  = (bool) $args['hide_product_name'];
		$args['hide_compare_price'] = (bool) $args['hide_compare_price'];
		$args['hide_price']         = (bool) $args['hide_price'];

		if ( $args['hide_price'] ) {
			$args['hide_compare_price'] = true;
		}

		return $args;
	}

	/**
	 * Default design options for payment button block output.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_design_defaults() {
		return array(
			'button_align'            => 'center',
			'button_font_size'        => 0,
			'button_text_color'       => '',
			'button_background_color' => '',
			'button_border_radius'    => 0,
		);
	}

	/**
	 * Normalize design options for payment button markup.
	 *
	 * @param array $args Render args.
	 * @return array<string, mixed>
	 */
	public static function normalize_design_args( $args = array() ) {
		$defaults = self::get_design_defaults();
		$args     = wp_parse_args( $args, $defaults );

		$aligns = array( 'left', 'center', 'right' );
		$align  = sanitize_key( (string) $args['button_align'] );

		if ( ! in_array( $align, $aligns, true ) ) {
			$align = $defaults['button_align'];
		}

		$args['button_align']            = $align;
		$args['button_font_size']        = max( 0, min( 48, absint( $args['button_font_size'] ) ) );
		$args['button_text_color']       = sanitize_hex_color( (string) $args['button_text_color'] ) ?: '';
		$args['button_background_color'] = sanitize_hex_color( (string) $args['button_background_color'] ) ?: '';
		$args['button_border_radius']    = max( 0, min( 32, absint( $args['button_border_radius'] ) ) );

		return $args;
	}

	/**
	 * Build inline CSS for a payment button CTA.
	 *
	 * @param array $design Normalized design args.
	 * @return string
	 */
	public static function build_button_inline_style( $design ) {
		$rules = array();

		if ( ! empty( $design['button_font_size'] ) ) {
			$rules[] = 'font-size:' . (int) $design['button_font_size'] . 'px';
		}

		if ( ! empty( $design['button_text_color'] ) ) {
			$rules[] = 'color:' . $design['button_text_color'];
		}

		if ( ! empty( $design['button_background_color'] ) ) {
			$rules[] = 'background-color:' . $design['button_background_color'];
		}

		if ( ! empty( $design['button_border_radius'] ) ) {
			$rules[] = 'border-radius:' . (int) $design['button_border_radius'] . 'px';
		}

		return implode( ';', $rules );
	}

	/**
	 * Render payment button markup for block/shortcode.
	 *
	 * @param int   $button_id Button post ID.
	 * @param array $args      Optional render args.
	 * @return string
	 */
	public static function render( $button_id, $args = array() ) {
		$button_id = absint( $button_id );

		if ( ! $button_id || ! self::is_active( $button_id ) ) {
			return self::render_notice( __( 'Платежная кнопка недоступна.', 'art-lms' ), 'error' );
		}

		$meta = self::get_meta( $button_id );

		if ( '' === trim( (string) $meta['price'] ) ) {
			return self::render_notice( __( 'У платежной кнопки не указана цена.', 'art-lms' ), 'error' );
		}

		$checkout_url = Art_LMS_Settings::get_checkout_url( $button_id );

		if ( ! $checkout_url ) {
			return self::render_notice( __( 'Checkout не настроен.', 'art-lms' ), 'error' );
		}

		wp_enqueue_style( 'art-lms-public' );

		$product_name = self::get_product_name( $button_id );
		$display      = self::normalize_display_args( $args );
		$design       = self::normalize_design_args( $args );

		ob_start();
		include ART_LMS_PLUGIN_DIR . 'public/views/payment-button.php';

		return (string) ob_get_clean();
	}

	/**
	 * Render frontend notice markup.
	 *
	 * @param string $message Notice text.
	 * @param string $type    Notice type.
	 * @return string
	 */
	public static function render_notice( $message, $type = 'warning' ) {
		wp_enqueue_style( 'art-lms-public' );

		$class = 'art-lms-notice';

		if ( 'error' === $type ) {
			$class .= ' art-lms-notice--warning';
		}

		return '<p class="' . esc_attr( $class ) . '">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Calculate access expiration from access days setting.
	 *
	 * @param int $access_days Access days (0 = unlimited).
	 * @return string|null MySQL datetime UTC or null.
	 */
	public static function calculate_expires_at( $access_days ) {
		$access_days = absint( $access_days );

		if ( $access_days <= 0 ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', strtotime( '+' . $access_days . ' days', current_time( 'timestamp' ) ) );
	}

	/**
	 * Grant material access after order is paid.
	 *
	 * @param object $order    Order row.
	 * @param int    $order_id Order ID.
	 */
	public static function grant_access_for_order( $order, $order_id ) {
		$button_id = (int) $order->product_id;

		if ( ! $button_id ) {
			return;
		}

		$button = get_post( $button_id );

		if ( ! $button || self::POST_TYPE !== $button->post_type ) {
			return;
		}

		$meta       = self::get_meta( $button_id );
		$expires_at = self::calculate_expires_at( $meta['access_days'] );

		foreach ( $meta['material_ids'] as $material_id ) {
			$material = get_post( $material_id );

			if ( ! $material || Art_LMS_Materials::POST_TYPE !== $material->post_type ) {
				continue;
			}

			if ( Art_LMS_Access::has_active_for_order_product( (int) $order_id, (int) $material_id ) ) {
				continue;
			}

			Art_LMS_Access::grant(
				array(
					'user_id'    => (int) $order->user_id,
					'product_id' => (int) $material_id,
					'order_id'   => (int) $order_id,
					'expires_at' => $expires_at,
				)
			);
		}
	}

	/**
	 * Check if payment button is available for checkout.
	 *
	 * @param int $button_id Button ID.
	 * @return bool
	 */
	public static function is_active( $button_id ) {
		return '' === self::get_checkout_unavailable_reason( $button_id );
	}

	/**
	 * Explain why payment button cannot be used on checkout.
	 *
	 * @param int $button_id Button ID.
	 * @return string Empty string when button is available.
	 */
	public static function get_checkout_unavailable_reason( $button_id ) {
		$button_id = absint( $button_id );

		if ( ! $button_id ) {
			return 'missing';
		}

		$post = get_post( $button_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return 'not_found';
		}

		if ( self::is_archived( $button_id ) ) {
			return 'archived';
		}

		if ( ! self::is_enabled( $button_id ) ) {
			return 'disabled';
		}

		if ( 'publish' !== $post->post_status ) {
			return 'not_published';
		}

		return '';
	}

	/**
	 * Whether payment button is archived.
	 *
	 * @param int $button_id Button post ID.
	 * @return bool
	 */
	public static function is_archived( $button_id ) {
		$post = get_post( absint( $button_id ) );

		return $post && self::POST_TYPE === $post->post_type && self::POST_STATUS_ARCHIVED === $post->post_status;
	}

	/**
	 * Move payment button to archive.
	 *
	 * @param int $button_id Button post ID.
	 * @return int|WP_Error
	 */
	public static function archive( $button_id ) {
		$button_id = absint( $button_id );
		$post      = get_post( $button_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'invalid_button', __( 'Платежная кнопка не найдена.', 'art-lms' ) );
		}

		if ( self::POST_STATUS_ARCHIVED === $post->post_status ) {
			return $button_id;
		}

		return wp_update_post(
			array(
				'ID'          => $button_id,
				'post_status' => self::POST_STATUS_ARCHIVED,
			),
			true
		);
	}

	/**
	 * Restore payment button from archive.
	 *
	 * @param int $button_id Button post ID.
	 * @return int|WP_Error
	 */
	public static function unarchive( $button_id ) {
		$button_id = absint( $button_id );
		$post      = get_post( $button_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'invalid_button', __( 'Платежная кнопка не найдена.', 'art-lms' ) );
		}

		if ( self::POST_STATUS_ARCHIVED !== $post->post_status ) {
			return $button_id;
		}

		return wp_update_post(
			array(
				'ID'          => $button_id,
				'post_status' => 'publish',
			),
			true
		);
	}

	/**
	 * Build admin URL to archive a payment button.
	 *
	 * @param int $button_id Button post ID.
	 * @return string
	 */
	public static function get_archive_action_url( $button_id ) {
		$button_id = absint( $button_id );

		return wp_nonce_url(
			admin_url( 'admin-post.php?action=art_lms_archive_payment_button&post_id=' . $button_id ),
			'art_lms_archive_payment_button_' . $button_id
		);
	}

	/**
	 * Build admin URL to restore a payment button from archive.
	 *
	 * @param int $button_id Button post ID.
	 * @return string
	 */
	public static function get_unarchive_action_url( $button_id ) {
		$button_id = absint( $button_id );

		return wp_nonce_url(
			admin_url( 'admin-post.php?action=art_lms_unarchive_payment_button&post_id=' . $button_id ),
			'art_lms_unarchive_payment_button_' . $button_id
		);
	}

	/**
	 * Add archive action to payment buttons list table.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Current post.
	 * @return array
	 */
	public static function filter_admin_row_actions( $actions, $post ) {
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		if ( ! Art_LMS_Security::can_manage() ) {
			return $actions;
		}

		if ( 'publish' === $post->post_status ) {
			$actions['art_lms_archive'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url( self::get_archive_action_url( (int) $post->ID ) ),
				esc_attr(
					sprintf(
						/* translators: %s: payment button title */
						__( 'Архивировать «%s»', 'art-lms' ),
						get_the_title( $post )
					)
				),
				esc_html__( 'Архивировать', 'art-lms' )
			);
		}

		if ( self::POST_STATUS_ARCHIVED === $post->post_status ) {
			$actions['art_lms_unarchive'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url( self::get_unarchive_action_url( (int) $post->ID ) ),
				esc_attr(
					sprintf(
						/* translators: %s: payment button title */
						__( 'Вернуть из архива «%s»', 'art-lms' ),
						get_the_title( $post )
					)
				),
				esc_html__( 'Вернуть из архива', 'art-lms' )
			);
		}

		return $actions;
	}

	/**
	 * Admin list table columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function filter_list_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['art_lms_product_name'] = __( 'Продукт', 'art-lms' );
				$new['art_lms_price']       = __( 'Цена', 'art-lms' );
				$new['art_lms_access_days'] = __( 'Срок доступа', 'art-lms' );
				$new['art_lms_materials']   = __( 'Материалы', 'art-lms' );
				$new['art_lms_button_status'] = __( 'Статус кнопки', 'art-lms' );
				$new['art_lms_embed']       = __( 'Вставка', 'art-lms' );
			}
		}

		return $new;
	}

	/**
	 * Render admin list column value.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function render_list_column( $column, $post_id ) {
		$meta = self::get_meta( $post_id );

		if ( 'art_lms_product_name' === $column ) {
			echo esc_html( self::get_product_name( $post_id ) ?: '—' );
			return;
		}

		if ( 'art_lms_price' === $column ) {
			echo esc_html( $meta['price'] ? $meta['price'] . ' ₽' : '—' );
			return;
		}

		if ( 'art_lms_access_days' === $column ) {
			echo esc_html( self::format_access_days_label( $meta['access_days'] ) );
			return;
		}

		if ( 'art_lms_materials' === $column ) {
			echo esc_html( (string) count( $meta['material_ids'] ) );
			return;
		}

		if ( 'art_lms_button_status' === $column ) {
			self::render_list_status_label( $post_id );
			return;
		}

		if ( 'art_lms_embed' === $column ) {
			self::render_list_embed_actions( $post_id );
		}
	}

	/**
	 * Render enabled/disabled label in list table.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function render_list_status_label( $post_id ) {
		if ( self::is_archived( $post_id ) ) {
			echo '<span class="art-lms-button-status art-lms-button-status--archived">' . esc_html__( 'В архиве', 'art-lms' ) . '</span>';
			return;
		}

		if ( self::is_enabled( $post_id ) ) {
			echo '<span class="art-lms-button-status art-lms-button-status--active">' . esc_html__( 'Активна', 'art-lms' ) . '</span>';
			return;
		}

		echo '<span class="art-lms-button-status art-lms-button-status--inactive">' . esc_html__( 'Выключена', 'art-lms' ) . '</span>';
	}

	/**
	 * Render shortcode and checkout copy controls in list table.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function render_list_embed_actions( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			echo esc_html( '—' );
			return;
		}

		$shortcode    = self::get_shortcode( $post_id );
		$checkout_url = self::get_checkout_link( $post_id );
		$shortcode_id = 'art-lms-list-shortcode-' . $post_id;
		$checkout_id  = 'art-lms-list-checkout-' . $post_id;
		?>
		<div class="art-lms-list-copy-actions">
			<div class="art-lms-list-copy-actions__item">
				<span class="art-lms-list-copy-actions__label"><?php esc_html_e( 'Шорткод', 'art-lms' ); ?></span>
				<code
					class="art-lms-list-copy-actions__value art-lms-shortcode-select"
					id="<?php echo esc_attr( $shortcode_id ); ?>"
					title="<?php echo esc_attr( $shortcode ); ?>"
					tabindex="0"
					role="textbox"
				><?php echo esc_html( $shortcode ); ?></code>
				<button
					type="button"
					class="button art-lms-copy-button art-lms-list-copy-button"
					data-copy-target="#<?php echo esc_attr( $shortcode_id ); ?>"
					data-copy-value="<?php echo esc_attr( $shortcode ); ?>"
					aria-label="<?php esc_attr_e( 'Скопировать шорткод', 'art-lms' ); ?>"
					title="<?php esc_attr_e( 'Скопировать', 'art-lms' ); ?>"
				>
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
				</button>
			</div>
			<div class="art-lms-list-copy-actions__item">
				<span class="art-lms-list-copy-actions__label"><?php esc_html_e( 'Ссылка', 'art-lms' ); ?></span>
				<?php if ( $checkout_url ) : ?>
					<a
						class="art-lms-list-copy-actions__value art-lms-list-copy-actions__link"
						id="<?php echo esc_attr( $checkout_id ); ?>"
						href="<?php echo esc_url( $checkout_url ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						title="<?php echo esc_attr( $checkout_url ); ?>"
					><?php echo esc_html( $checkout_url ); ?></a>
					<button
						type="button"
						class="button art-lms-copy-button art-lms-list-copy-button"
						data-copy-target="#<?php echo esc_attr( $checkout_id ); ?>"
						data-copy-value="<?php echo esc_attr( $checkout_url ); ?>"
						data-copy-mode="text"
						aria-label="<?php esc_attr_e( 'Скопировать ссылку', 'art-lms' ); ?>"
						title="<?php esc_attr_e( 'Скопировать', 'art-lms' ); ?>"
					>
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					</button>
				<?php else : ?>
					<span class="art-lms-list-copy-actions__empty"><?php esc_html_e( 'Checkout не настроен', 'art-lms' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Human-readable access period label.
	 *
	 * @param int $access_days Access days.
	 * @return string
	 */
	public static function format_access_days_label( $access_days ) {
		$access_days = absint( $access_days );

		if ( $access_days <= 0 ) {
			return __( 'Без ограничения', 'art-lms' );
		}

		return sprintf(
			/* translators: %d: number of days */
			_n( '%d день', '%d дней', $access_days, 'art-lms' ),
			$access_days
		);
	}

	/**
	 * Checkout label for limited access period.
	 *
	 * @param int $access_days Access days.
	 * @return string Empty string when access is unlimited.
	 */
	public static function get_checkout_access_label( $access_days ) {
		$access_days = absint( $access_days );

		if ( $access_days <= 0 ) {
			return '';
		}

		return sprintf(
			/* translators: %s: access period label, e.g. "30 дней" */
			__( 'Срок доступа: %s', 'art-lms' ),
			self::format_access_days_label( $access_days )
		);
	}

	/**
	 * Whether query is the admin payment buttons list table.
	 *
	 * @param WP_Query $query Query object.
	 * @return bool
	 */
	public static function is_admin_list_query( $query ) {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
			return false;
		}

		return self::POST_TYPE === $query->get( 'post_type' );
	}

	/**
	 * Apply visibility filter on admin list query.
	 *
	 * @param WP_Query $query Query object.
	 */
	public static function filter_admin_list_query( $query ) {
		if ( ! self::is_admin_list_query( $query ) ) {
			return;
		}

		$visibility = self::get_admin_visibility_filter();

		if ( 'hide_disabled' === $visibility ) {
			$query->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     => self::META_ENABLED,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_ENABLED,
						'value'   => '0',
						'compare' => '!=',
					),
				)
			);
			return;
		}

		if ( 'only_disabled' === $visibility ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => self::META_ENABLED,
						'value'   => '0',
						'compare' => '=',
					),
				)
			);
		}
	}

	/**
	 * Render visibility filter on admin list screen.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function render_admin_list_filters( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		$current = self::get_admin_visibility_filter();
		?>
		<label for="art-lms-button-visibility" class="screen-reader-text">
			<?php esc_html_e( 'Видимость кнопок', 'art-lms' ); ?>
		</label>
		<select name="<?php echo esc_attr( self::ADMIN_VISIBILITY_QUERY_ARG ); ?>" id="art-lms-button-visibility">
			<option value="all" <?php selected( $current, 'all' ); ?>>
				<?php esc_html_e( 'Все кнопки', 'art-lms' ); ?>
			</option>
			<option value="hide_disabled" <?php selected( $current, 'hide_disabled' ); ?>>
				<?php esc_html_e( 'Скрыть выключенные', 'art-lms' ); ?>
			</option>
			<option value="only_disabled" <?php selected( $current, 'only_disabled' ); ?>>
				<?php esc_html_e( 'Только выключенные', 'art-lms' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Get selected visibility filter from request.
	 *
	 * @return string
	 */
	public static function get_admin_visibility_filter() {
		$visibility = isset( $_GET[ self::ADMIN_VISIBILITY_QUERY_ARG ] )
			? sanitize_key( wp_unslash( $_GET[ self::ADMIN_VISIBILITY_QUERY_ARG ] ) )
			: 'all';

		if ( ! in_array( $visibility, array( 'all', 'hide_disabled', 'only_disabled' ), true ) ) {
			return 'all';
		}

		return $visibility;
	}

	/**
	 * Join product meta when searching admin list by title and product.
	 *
	 * @param string   $join  SQL join clause.
	 * @param WP_Query $query Query object.
	 * @return string
	 */
	public static function filter_admin_list_search_join( $join, $query ) {
		if ( ! self::is_admin_list_query( $query ) || ! $query->get( 's' ) ) {
			return $join;
		}

		global $wpdb;

		$join .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS art_lms_btn_product_search ON ({$wpdb->posts}.ID = art_lms_btn_product_search.post_id AND art_lms_btn_product_search.meta_key = %s)",
			self::META_PRODUCT_NAME
		);

		return $join;
	}

	/**
	 * Extend admin search to product name meta.
	 *
	 * @param string   $search Search SQL clause.
	 * @param WP_Query $query  Query object.
	 * @return string
	 */
	public static function filter_admin_list_search_where( $search, $query ) {
		if ( ! self::is_admin_list_query( $query ) || ! $query->get( 's' ) || '' === $search ) {
			return $search;
		}

		global $wpdb;

		$term = $query->get( 's' );
		$like = '%' . $wpdb->esc_like( $term ) . '%';

		$search .= $wpdb->prepare( ' OR (art_lms_btn_product_search.meta_value LIKE %s)', $like );

		return $search;
	}

	/**
	 * Prevent duplicate rows when search joins post meta.
	 *
	 * @param string   $groupby Group by clause.
	 * @param WP_Query $query   Query object.
	 * @return string
	 */
	public static function filter_admin_list_search_groupby( $groupby, $query ) {
		if ( ! self::is_admin_list_query( $query ) || ! $query->get( 's' ) ) {
			return $groupby;
		}

		global $wpdb;

		return "{$wpdb->posts}.ID";
	}
}
