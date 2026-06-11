<?php
/**
 * Gutenberg blocks registration.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Blocks
 */
class Art_LMS_Blocks {

	/**
	 * Register hooks.
	 */
	public static function init() {
		self::register_assets();

		add_action( 'init', array( __CLASS__, 'register_blocks' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'register_block_category' ), 10, 2 );
	}

	/**
	 * Register block editor assets.
	 */
	public static function register_assets() {
		wp_register_style(
			'art-lms-blocks-editor',
			ART_LMS_PLUGIN_URL . 'assets/css/blocks-editor.css',
			array(),
			ART_LMS_VERSION
		);

		wp_register_script(
			'art-lms-blocks-editor',
			ART_LMS_PLUGIN_URL . 'assets/js/blocks-editor.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-data',
				'wp-hooks',
			),
			ART_LMS_VERSION,
			true
		);
	}

	/**
	 * Enqueue shared block editor assets.
	 */
	public static function enqueue_editor_assets() {
		wp_enqueue_style( 'art-lms-blocks-editor' );
		wp_enqueue_script( 'art-lms-blocks-editor' );
	}

	/**
	 * Register block category.
	 *
	 * @param array                   $categories Block categories.
	 * @param WP_Block_Editor_Context $context    Editor context.
	 * @return array
	 */
	public static function register_block_category( $categories, $context ) {
		unset( $context );

		foreach ( $categories as $category ) {
			if ( is_array( $category ) && ( $category['slug'] ?? '' ) === 'art-lms' ) {
				return $categories;
			}
		}

		array_unshift(
			$categories,
			array(
				'slug'  => 'art-lms',
				'title' => __( 'АРТ ЛМС', 'art-lms' ),
				'icon'  => 'welcome-learn-more',
			)
		);

		return $categories;
	}

	/**
	 * Customer account block attribute schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_customer_account_block_attributes() {
		$defaults = Art_LMS_Account::get_block_defaults();

		return array(
			'materialsTitle'        => array(
				'type'    => 'string',
				'default' => $defaults['materials_title'],
			),
			'emptyMessage'          => array(
				'type'    => 'string',
				'default' => $defaults['empty_message'],
			),
			'openButtonText'        => array(
				'type'    => 'string',
				'default' => $defaults['open_button_text'],
			),
			'logoutLinkText'        => array(
				'type'    => 'string',
				'default' => $defaults['logout_link_text'],
			),
			'resetPasswordLinkText' => array(
				'type'    => 'string',
				'default' => $defaults['reset_password_link_text'],
			),
			'hideMaterialsTitle'    => array(
				'type'    => 'boolean',
				'default' => $defaults['hide_materials_title'],
			),
			'hideAccessLabel'       => array(
				'type'    => 'boolean',
				'default' => $defaults['hide_access_label'],
			),
			'hideOpenButton'        => array(
				'type'    => 'boolean',
				'default' => $defaults['hide_open_button'],
			),
			'hideLogoutLink'        => array(
				'type'    => 'boolean',
				'default' => $defaults['hide_logout_link'],
			),
			'hideResetPassword'     => array(
				'type'    => 'boolean',
				'default' => $defaults['hide_reset_password'],
			),
			'containerWidthMode'    => array(
				'type'    => 'string',
				'default' => $defaults['container_width_mode'],
			),
			'containerCustomWidth'  => array(
				'type'    => 'number',
				'default' => $defaults['container_custom_width'],
			),
			'hideBorder'            => array(
				'type'    => 'boolean',
				'default' => $defaults['hide_border'],
			),
			'borderColor'           => array(
				'type'    => 'string',
				'default' => $defaults['border_color'],
			),
			'borderRadius'          => array(
				'type'    => 'number',
				'default' => $defaults['border_radius'],
			),
			'materialsTitleFontSize' => array(
				'type'    => 'number',
				'default' => $defaults['materials_title_font_size'],
			),
			'buttonFontSize'        => array(
				'type'    => 'number',
				'default' => $defaults['button_font_size'],
			),
			'buttonTextColor'       => array(
				'type'    => 'string',
				'default' => $defaults['button_text_color'],
			),
			'buttonBackgroundColor' => array(
				'type'    => 'string',
				'default' => $defaults['button_background_color'],
			),
			'buttonBorderRadius'    => array(
				'type'    => 'number',
				'default' => $defaults['button_border_radius'],
			),
		);
	}

	/**
	 * Register plugin blocks (server render only).
	 */
	public static function register_blocks() {
		register_block_type(
			'art-lms/customer-account',
			array(
				'editor_style'    => 'art-lms-blocks-editor',
				'attributes'      => self::get_customer_account_block_attributes(),
				'render_callback' => array( __CLASS__, 'render_customer_account' ),
			)
		);

		register_block_type(
			'art-lms/payment-status',
			array(
				'render_callback' => array( __CLASS__, 'render_payment_status' ),
			)
		);

		register_block_type(
			'art-lms/payment-button',
			array(
				'editor_style'    => 'art-lms-blocks-editor',
				'attributes'      => array(
					'buttonId'        => array(
						'type'    => 'number',
						'default' => 0,
					),
					'buttonText'      => array(
						'type'    => 'string',
						'default' => __( 'Оформить', 'art-lms' ),
					),
					'hideProductName' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'hideComparePrice' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'hidePrice'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'buttonAlign'     => array(
						'type'    => 'string',
						'default' => 'center',
					),
					'buttonFontSize'  => array(
						'type'    => 'number',
						'default' => 0,
					),
					'buttonTextColor' => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonBackgroundColor' => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonBorderRadius' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'render_callback' => array( __CLASS__, 'render_payment_button' ),
			)
		);
	}

	/**
	 * Render customer account block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content.
	 * @param WP_Block $block      Block instance.
	 * @return string
	 */
	public static function render_customer_account( $attributes, $content, $block ) {
		unset( $content, $block );

		return Art_LMS_Account::render( Art_LMS_Account::block_attributes_to_args( $attributes ) );
	}

	/**
	 * Render payment status block.
	 *
	 * @return string
	 */
	public static function render_payment_status() {
		return Art_LMS_Shortcodes::render_payment_status();
	}

	/**
	 * Render payment button block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content.
	 * @param WP_Block $block      Block instance.
	 * @return string
	 */
	public static function render_payment_button( $attributes, $content, $block ) {
		unset( $content, $block );

		return Art_LMS_Payment_Buttons::render(
			absint( $attributes['buttonId'] ?? 0 ),
			array(
				'button_text'             => $attributes['buttonText'] ?? '',
				'hide_product_name'       => ! empty( $attributes['hideProductName'] ),
				'hide_compare_price'      => ! empty( $attributes['hideComparePrice'] ),
				'hide_price'              => ! empty( $attributes['hidePrice'] ),
				'button_align'            => $attributes['buttonAlign'] ?? 'center',
				'button_font_size'        => absint( $attributes['buttonFontSize'] ?? 0 ),
				'button_text_color'       => $attributes['buttonTextColor'] ?? '',
				'button_background_color' => $attributes['buttonBackgroundColor'] ?? '',
				'button_border_radius'    => absint( $attributes['buttonBorderRadius'] ?? 0 ),
			)
		);
	}
}
