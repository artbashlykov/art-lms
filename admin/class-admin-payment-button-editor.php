<?php
/**
 * Payment button edit screen meta box.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin screens require capability checks; list filters use sanitized GET parameters.

/**
 * Class Art_LMS_Admin_Payment_Button_Editor
 */
class Art_LMS_Admin_Payment_Button_Editor {

	const META_BOX_ID = 'art_lms_payment_button_settings';
	const NONCE_ACTION = 'art_lms_save_payment_button_settings';
	const NONCE_NAME   = 'art_lms_payment_button_settings_nonce';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post_' . Art_LMS_Payment_Buttons::POST_TYPE, array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'use_block_editor' ), 10, 2 );
		add_action( 'all_admin_notices', array( __CLASS__, 'render_editor_toolbar' ) );
		add_action( 'admin_post_art_lms_archive_payment_button', array( __CLASS__, 'handle_archive_payment_button' ) );
		add_action( 'admin_post_art_lms_unarchive_payment_button', array( __CLASS__, 'handle_unarchive_payment_button' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_archive_notices' ) );
	}

	/**
	 * Keep the block editor enabled for payment buttons.
	 *
	 * @param bool   $use       Whether to use block editor.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function use_block_editor( $use, $post_type ) {
		if ( Art_LMS_Payment_Buttons::POST_TYPE === $post_type ) {
			return true;
		}

		return $use;
	}

	/**
	 * Render back link above the editor.
	 */
	public static function render_editor_toolbar() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || Art_LMS_Payment_Buttons::POST_TYPE !== $screen->post_type ) {
			return;
		}

		if ( ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return;
		}

		$list_url = admin_url( 'edit.php?post_type=' . Art_LMS_Payment_Buttons::POST_TYPE );
		?>
		<div class="art-lms-payment-button-toolbar">
			<a class="art-lms-payment-button-back" href="<?php echo esc_url( $list_url ); ?>">
				<span aria-hidden="true">&larr;</span>
				<?php esc_html_e( 'Ко всем платежным кнопкам', 'art-lms' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Get list URL for payment buttons admin screen.
	 *
	 * @return string
	 */
	public static function get_list_url() {
		return admin_url( 'edit.php?post_type=' . Art_LMS_Payment_Buttons::POST_TYPE );
	}

	/**
	 * Archive payment button from admin action.
	 */
	public static function handle_archive_payment_button() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-lms' ) );
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
		check_admin_referer( 'art_lms_archive_payment_button_' . $post_id );

		$result = Art_LMS_Payment_Buttons::archive( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = add_query_arg(
				array(
					'post_type' => Art_LMS_Payment_Buttons::POST_TYPE,
					'archived'  => '1',
				),
				admin_url( 'edit.php' )
			);
		} else {
			$redirect = add_query_arg( 'archived', '1', $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Restore payment button from archive.
	 */
	public static function handle_unarchive_payment_button() {
		if ( ! Art_LMS_Security::can_manage() ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'art-lms' ) );
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
		check_admin_referer( 'art_lms_unarchive_payment_button_' . $post_id );

		$result = Art_LMS_Payment_Buttons::unarchive( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = add_query_arg(
				array(
					'post_type' => Art_LMS_Payment_Buttons::POST_TYPE,
					'unarchived' => '1',
				),
				admin_url( 'edit.php' )
			);
		} else {
			$redirect = add_query_arg( 'unarchived', '1', $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Show archive action notices on payment button screens.
	 */
	public static function render_archive_notices() {
		if ( ! Art_LMS_Security::can_manage() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || Art_LMS_Payment_Buttons::POST_TYPE !== $screen->post_type ) {
			return;
		}

		if ( ! empty( $_GET['archived'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Платежная кнопка перенесена в архив.', 'art-lms' ) . '</p></div>';
		}

		if ( ! empty( $_GET['unarchived'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Платежная кнопка возвращена из архива.', 'art-lms' ) . '</p></div>';
		}
	}

	/**
	 * Register settings meta box.
	 */
	public static function register_meta_box() {
		add_meta_box(
			self::META_BOX_ID,
			__( 'Настройки платежной кнопки', 'art-lms' ),
			array( __CLASS__, 'render_meta_box' ),
			Art_LMS_Payment_Buttons::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'art_lms_payment_button_status',
			__( 'Статус кнопки', 'art-lms' ),
			array( __CLASS__, 'render_status_meta_box' ),
			Art_LMS_Payment_Buttons::POST_TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'art_lms_payment_button_links',
			__( 'Ссылки и вставка', 'art-lms' ),
			array( __CLASS__, 'render_links_meta_box' ),
			Art_LMS_Payment_Buttons::POST_TYPE,
			'side',
			'low'
		);
	}

	/**
	 * Render settings form.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$meta       = Art_LMS_Payment_Buttons::get_meta( $post->ID );
		$materials_catalog = Art_LMS_Admin_Payment_Button_Editor::get_materials_catalog();
		$config     = Art_LMS_Payment_Buttons::get_payment_button_editor_config();
		$presets    = $config['accessPresets'] ?? array();
		$access_days = (int) ( $meta['access_days'] ?? 0 );
		$preset_values = wp_list_pluck( $presets, 'value' );
		$access_mode   = in_array( (string) $access_days, array_map( 'strval', $preset_values ), true )
			? (string) $access_days
			: 'custom';

		include ART_LMS_PLUGIN_DIR . 'admin/views/payment-button-meta-box.php';
	}

	/**
	 * Render sidebar status meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_status_meta_box( $post ) {
		$is_enabled = Art_LMS_Payment_Buttons::is_enabled( (int) $post->ID );

		include ART_LMS_PLUGIN_DIR . 'admin/views/payment-button-status-meta-box.php';
	}

	/**
	 * Render sidebar links meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_links_meta_box( $post ) {
		include ART_LMS_PLUGIN_DIR . 'admin/views/payment-button-links-meta-box.php';
	}

	/**
	 * Save settings from meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_meta_box( $post_id, $post ) {
		unset( $post );

		if ( ! Art_LMS_Security::can_manage() ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$product_name  = isset( $_POST['art_lms_product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['art_lms_product_name'] ) ) : '';
		$compare_price = isset( $_POST['art_lms_compare_price'] ) ? sanitize_text_field( wp_unslash( $_POST['art_lms_compare_price'] ) ) : '';
		$price         = isset( $_POST['art_lms_price'] ) ? sanitize_text_field( wp_unslash( $_POST['art_lms_price'] ) ) : '';
		$access_mode   = isset( $_POST['art_lms_access_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['art_lms_access_mode'] ) ) : '0';
		$access_custom = isset( $_POST['art_lms_access_days_custom'] ) ? absint( wp_unslash( $_POST['art_lms_access_days_custom'] ) ) : 0;
		$material_ids = array();

		if ( isset( $_POST['art_lms_material_ids'] ) && is_array( $_POST['art_lms_material_ids'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Material IDs are normalized with absint().
			$raw_material_ids = wp_unslash( $_POST['art_lms_material_ids'] );
			$material_ids     = array_values(
				array_filter(
					array_map( 'absint', $raw_material_ids )
				)
			);
		}

		if ( 'custom' === $access_mode ) {
			$access_days = max( 1, $access_custom );
		} else {
			$access_days = absint( $access_mode );
		}

		update_post_meta( $post_id, Art_LMS_Payment_Buttons::META_PRODUCT_NAME, $product_name );
		update_post_meta( $post_id, Art_LMS_Payment_Buttons::META_COMPARE_PRICE, $compare_price );
		update_post_meta( $post_id, Art_LMS_Payment_Buttons::META_PRICE, $price );
		update_post_meta( $post_id, Art_LMS_Payment_Buttons::META_ACCESS_DAYS, $access_days );
		update_post_meta( $post_id, Art_LMS_Payment_Buttons::META_MATERIAL_IDS, $material_ids );

		if ( isset( $_POST['art_lms_button_enabled'] ) ) {
			$enabled = '1' === sanitize_text_field( wp_unslash( $_POST['art_lms_button_enabled'] ) );
			update_post_meta( $post_id, Art_LMS_Payment_Buttons::META_ENABLED, $enabled );
		}
	}

	/**
	 * Get materials for checkbox list.
	 *
	 * @return WP_Post[]
	 */
	public static function get_materials() {
		return get_posts(
			array(
				'post_type'      => Art_LMS_Materials::POST_TYPE,
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			)
		);
	}

	/**
	 * Get materials catalog for the picker UI.
	 *
	 * @return array<int, string>
	 */
	public static function get_materials_catalog() {
		$catalog = array();

		foreach ( self::get_materials() as $material ) {
			$catalog[ (int) $material->ID ] = get_the_title( $material );
		}

		return $catalog;
	}

	/**
	 * Get selected materials in saved order.
	 *
	 * @param int[] $selected_ids Selected material IDs.
	 * @return array<int, string>
	 */
	public static function get_selected_materials( $selected_ids ) {
		$catalog = self::get_materials_catalog();
		$items   = array();

		foreach ( $selected_ids as $material_id ) {
			$material_id = absint( $material_id );

			if ( $material_id && isset( $catalog[ $material_id ] ) ) {
				$items[ $material_id ] = $catalog[ $material_id ];
			}
		}

		return $items;
	}
}
