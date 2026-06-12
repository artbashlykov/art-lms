<?php

/**

 * Admin menu and pages.

 *

 * @package Art_LMS

 */



defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin screens require capability checks; list filters use sanitized GET parameters.



/**

 * Class Art_LMS_Admin_Menu

 */

class Art_LMS_Admin_Menu {



	const MENU_SLUG = ART_LMS_ADMIN_MENU_SLUG;



	/**

	 * Register hooks.

	 */

	public static function init() {

		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 5 );

		add_action( 'admin_menu', array( __CLASS__, 'finalize_menu' ), 999 );

		add_filter( 'parent_file', array( __CLASS__, 'filter_parent_file' ) );

		add_filter( 'submenu_file', array( __CLASS__, 'filter_submenu_file' ) );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_filter( 'plugin_action_links_' . ART_LMS_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links' ) );

		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );

	}



	/**

	 * Register admin menu items.

	 */

	public static function register_menu() {

		add_menu_page(

			__( 'ART LMS', 'art-lms' ),

			__( 'ART LMS', 'art-lms' ),

			'manage_options',

			self::MENU_SLUG,

			array( __CLASS__, 'render_menu_home' ),

			'dashicons-welcome-learn-more',

			56

		);



		add_submenu_page(

			self::MENU_SLUG,

			__( 'Заказы', 'art-lms' ),

			__( 'Заказы', 'art-lms' ),

			'manage_options',

			Art_LMS_Admin_Orders::PAGE_LIST,

			array( 'Art_LMS_Admin_Orders', 'render_list_page' )

		);



		add_submenu_page(

			self::MENU_SLUG,

			__( 'Материалы', 'art-lms' ),

			__( 'Материалы', 'art-lms' ),

			'manage_options',

			Art_LMS_Admin_Materials::PAGE_LIST,

			array( 'Art_LMS_Admin_Materials', 'render_list_page' )

		);



		add_submenu_page(

			self::MENU_SLUG,

			__( 'Платежные кнопки', 'art-lms' ),

			__( 'Платежные кнопки', 'art-lms' ),

			'manage_options',

			Art_LMS_Admin_Payment_Buttons::PAGE_LIST,

			array( 'Art_LMS_Admin_Payment_Buttons', 'render_list_page' )

		);



		add_submenu_page(

			self::MENU_SLUG,

			__( 'Настройки формы', 'art-lms' ),

			__( 'Настройки формы', 'art-lms' ),

			'manage_options',

			Art_LMS_Admin_Settings::PAGE_TECH,

			array( 'Art_LMS_Admin_Settings', 'render_tech_page' )

		);



		add_submenu_page(

			self::MENU_SLUG,

			__( 'Главные настройки', 'art-lms' ),

			__( 'Главные настройки', 'art-lms' ),

			'manage_options',

			Art_LMS_Admin_Settings::PAGE_SETTINGS,

			array( 'Art_LMS_Admin_Settings', 'render_settings_page' )

		);



		add_submenu_page(

			self::MENU_SLUG,

			__( 'Статистика', 'art-lms' ),

			__( 'Статистика', 'art-lms' ),

			'manage_options',

			Art_LMS_Admin_Statistics::PAGE_LIST,

			array( 'Art_LMS_Admin_Statistics', 'render_page' )

		);



		add_submenu_page(

			self::MENU_SLUG,

			__( 'Редактирование заказа', 'art-lms' ),

			null,

			'manage_options',

			Art_LMS_Admin_Orders::PAGE_EDIT,

			array( 'Art_LMS_Admin_Orders', 'render_edit_page' )

		);



		add_submenu_page(

			self::MENU_SLUG,

			__( 'Просмотр заказа', 'art-lms' ),

			null,

			'manage_options',

			Art_LMS_Admin_Orders::PAGE_VIEW,

			array( 'Art_LMS_Admin_Orders', 'render_view_page' )

		);

	}



	/**

	 * Reorder submenu items.

	 */

	public static function finalize_menu() {

		global $submenu;



		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );



		if ( empty( $submenu[ self::MENU_SLUG ] ) ) {

			return;

		}



		$items_by_slug = array();



		foreach ( $submenu[ self::MENU_SLUG ] as $item ) {

			if ( empty( $item[2] ) ) {

				continue;

			}



			$items_by_slug[ $item[2] ] = $item;

		}



		$ordered   = array();

		$append_fn = function( $slug, $extra_class = '' ) use ( &$ordered, &$items_by_slug ) {

			if ( ! isset( $items_by_slug[ $slug ] ) ) {

				return;

			}

			$item = $items_by_slug[ $slug ];

			if ( $extra_class ) {
				$item[4] = trim( ( $item[4] ?? '' ) . ' ' . $extra_class );
			}

			$ordered[] = $item;

			unset( $items_by_slug[ $slug ] );

		};



		$append_fn( Art_LMS_Admin_Orders::PAGE_LIST );

		$append_fn( Art_LMS_Admin_Materials::PAGE_LIST );

		$append_fn( Art_LMS_Admin_Payment_Buttons::PAGE_LIST );

		$append_fn( Art_LMS_Admin_Settings::PAGE_TECH, 'art-lms-admin-menu-settings-start' );

		$append_fn( Art_LMS_Admin_Settings::PAGE_SETTINGS );

		$append_fn( Art_LMS_Admin_Statistics::PAGE_LIST, 'art-lms-admin-menu-stats-start' );

		$hidden_items = array();

		foreach ( array( Art_LMS_Admin_Orders::PAGE_EDIT, Art_LMS_Admin_Orders::PAGE_VIEW ) as $hidden_slug ) {
			if ( ! isset( $items_by_slug[ $hidden_slug ] ) ) {
				continue;
			}

			$hidden_item = $items_by_slug[ $hidden_slug ];
			$hidden_item[4] = trim( ( $hidden_item[4] ?? '' ) . ' art-lms-admin-hidden-submenu' );
			$hidden_items[] = $hidden_item;

			unset( $items_by_slug[ $hidden_slug ] );
		}

		foreach ( $items_by_slug as $item ) {

			$ordered[] = $item;

		}

		foreach ( $hidden_items as $item ) {
			$ordered[] = $item;
		}



		if ( ! empty( $ordered ) ) {

			$submenu[ self::MENU_SLUG ] = $ordered;

		}

	}




	/**

	 * Keep ART LMS menu open on plugin CPT screens.

	 *

	 * @param string $parent_file Parent file.

	 * @return string

	 */

	public static function filter_parent_file( $parent_file ) {

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;



		if ( ! $screen || ! $screen->post_type ) {

			return $parent_file;

		}



		if ( in_array( $screen->post_type, array( Art_LMS_Materials::POST_TYPE, Art_LMS_Payment_Buttons::POST_TYPE ), true ) ) {

			return self::MENU_SLUG;

		}



		return $parent_file;

	}



	/**

	 * Highlight submenu on plugin CPT screens.

	 *

	 * @param string $submenu_file Submenu file.

	 * @return string

	 */

	public static function filter_submenu_file( $submenu_file ) {

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( in_array( $page, array( Art_LMS_Admin_Orders::PAGE_EDIT, Art_LMS_Admin_Orders::PAGE_VIEW ), true ) ) {
			return Art_LMS_Admin_Orders::PAGE_LIST;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;



		if ( ! $screen || ! $screen->post_type ) {

			return $submenu_file;

		}



		if ( Art_LMS_Materials::POST_TYPE === $screen->post_type ) {

			return Art_LMS_Admin_Materials::PAGE_LIST;

		}



		if ( Art_LMS_Payment_Buttons::POST_TYPE === $screen->post_type ) {

			return Art_LMS_Admin_Payment_Buttons::PAGE_LIST;

		}



		return $submenu_file;

	}



	/**

	 * Default landing page for ART LMS menu.

	 */

	public static function render_menu_home() {

		Art_LMS_Admin_Orders::render_list_page();

	}



	/**

	 * Enqueue admin assets.

	 *

	 * @param string $hook Current admin page hook.

	 */

	public static function enqueue_assets( $hook ) {

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;



		if ( current_user_can( 'manage_options' ) ) {

			wp_enqueue_style(

				'art-lms-admin',

				ART_LMS_PLUGIN_URL . 'assets/css/admin.css',

				array(),

				ART_LMS_VERSION

			);

		}



		if ( $screen && Art_LMS_Payment_Buttons::POST_TYPE === $screen->post_type ) {
			wp_enqueue_script(
				'art-lms-admin-payment-button-meta-box',
				ART_LMS_PLUGIN_URL . 'assets/js/admin-payment-button-meta-box.js',
				array( 'jquery', 'wp-data' ),
				ART_LMS_VERSION,
				true
			);

			wp_localize_script(
				'art-lms-admin-payment-button-meta-box',
				'artLmsPaymentButtonMetaBox',
				array(
					'metaKeys' => Art_LMS_Payment_Buttons::get_payment_button_editor_config()['metaKeys'],
					'strings'  => array(
						'remove'          => __( 'Удалить', 'art-lms' ),
						'unsavedChanges'  => __( 'Есть несохранённые изменения. Выйти без сохранения?', 'art-lms' ),
						'unsavedWarning'  => __( 'Изменения могут быть потеряны.', 'art-lms' ),
						'copy'            => __( 'Скопировать', 'art-lms' ),
						'copied'          => __( 'Скопировано!', 'art-lms' ),
						'copyFailed'      => __( 'Не удалось скопировать.', 'art-lms' ),
					),
					'listUrl'  => Art_LMS_Admin_Payment_Button_Editor::get_list_url(),
				)
			);
		}



		if ( false === strpos( $hook, 'art-lms' ) ) {

			return;

		}



		if (

			false !== strpos( $hook, Art_LMS_Admin_Settings::PAGE_TECH )

			|| false !== strpos( $hook, Art_LMS_Admin_Settings::PAGE_SETTINGS )

		) {

			$admin_settings_deps = array( 'jquery' );

			if (
				false !== strpos( $hook, Art_LMS_Admin_Settings::PAGE_SETTINGS )
				&& Art_LMS_Admin_Settings::TAB_PAYMENTS === ( isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '' )
				&& empty( $_GET['gateway'] )
			) {
				$admin_settings_deps[] = 'jquery-ui-sortable';
			}

			wp_enqueue_script(

				'art-lms-admin-settings',

				ART_LMS_PLUGIN_URL . 'assets/js/admin-settings.js',

				$admin_settings_deps,

				ART_LMS_VERSION,

				true

			);

			if ( false !== strpos( $hook, Art_LMS_Admin_Settings::PAGE_TECH ) ) {
				$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : Art_LMS_Admin_Settings::TAB_CHECKOUT;

				if ( Art_LMS_Admin_Settings::TAB_CHECKOUT === $tab ) {
					wp_enqueue_style(
						'art-lms-public',
						ART_LMS_PLUGIN_URL . 'assets/css/public.css',
						array(),
						ART_LMS_VERSION
					);

					wp_add_inline_style( 'art-lms-public', Art_LMS_Settings::get_checkout_design_css() );

					$builtin_fields = array();

					foreach ( Art_LMS_Settings::get_checkout_field_catalog() as $field_key => $field_title ) {
						$builtin_fields[] = array(
							'key'            => $field_key,
							'defaultLabel'   => $field_title,
							'alwaysEnabled'  => 'email' === $field_key,
							'alwaysRequired' => 'email' === $field_key,
							'input'          => 'email' === $field_key ? 'email' : 'text',
						);
					}

					$preview_config = Art_LMS_Admin_Settings::get_checkout_preview_shared_config();

					wp_localize_script(
						'art-lms-admin-settings',
						'artLmsCheckoutPreview',
						array_merge(
							$preview_config,
							array(
								'builtinFields' => $builtin_fields,
								'messageDefaults' => Art_LMS_Settings::get_default_checkout_form_messages(),
								'strings'       => array_merge(
									$preview_config['strings'],
									array(
										'hint' => __( 'Предпросмотр обновляется сразу при изменении настроек.', 'art-lms' ),
									)
								),
							)
						)
					);
				}

				if ( Art_LMS_Admin_Settings::TAB_CONFIRMATION === $tab ) {
					wp_localize_script(
						'art-lms-admin-settings',
						'artLmsPaymentStatusSettings',
						array(
							'messageDefaults' => Art_LMS_Settings::get_default_payment_status_messages(),
						)
					);
				}

				if ( Art_LMS_Admin_Settings::TAB_DESIGN === $tab ) {
					wp_enqueue_style(
						'art-lms-public',
						ART_LMS_PLUGIN_URL . 'assets/css/public.css',
						array(),
						ART_LMS_VERSION
					);

					wp_add_inline_style( 'art-lms-public', Art_LMS_Settings::get_checkout_design_css() );

					wp_localize_script(
						'art-lms-admin-settings',
						'artLmsCheckoutDesignPreview',
						Art_LMS_Admin_Settings::get_checkout_design_preview_config()
					);
				}

				if ( Art_LMS_Admin_Settings::TAB_EMAIL === $tab ) {
					wp_localize_script(
						'art-lms-admin-settings',
						'artLmsEmailSettings',
						array(
							'ajaxUrl' => admin_url( 'admin-ajax.php' ),
							'nonce'   => wp_create_nonce( 'art_lms_email_settings' ),
							'defaults' => array(
								'purchase'      => array(
									'subject' => Art_LMS_Settings::get_default_purchase_email_subject(),
									'body'    => Art_LMS_Settings::get_default_purchase_email_body(),
								),
								'admin_payment' => array(
									'subject' => Art_LMS_Settings::get_default_admin_payment_email_subject(),
									'body'    => Art_LMS_Settings::get_default_admin_payment_email_body(),
								),
								'email_verification' => array(
									'subject' => Art_LMS_Settings::get_default_email_verification_subject(),
									'body'    => Art_LMS_Settings::get_default_email_verification_body(),
								),
							),
							'strings' => array(
								'previewFailed' => __( 'Не удалось построить предпросмотр.', 'art-lms' ),
								'sending'       => __( 'Отправляем…', 'art-lms' ),
								'sendFailed'    => __( 'Не удалось отправить тестовое письмо.', 'art-lms' ),
							),
						)
					);
				}
			}

			if ( false !== strpos( $hook, Art_LMS_Admin_Settings::PAGE_SETTINGS ) ) {
				$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : Art_LMS_Admin_Settings::TAB_GENERAL;

				if ( Art_LMS_Admin_Settings::TAB_GENERAL === $tab ) {
					wp_localize_script(
						'art-lms-admin-settings',
						'artLmsGeneralSettings',
						array(
							'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
							'nonce'    => wp_create_nonce( 'art_lms_general_settings' ),
							'pageUrls' => Art_LMS_Pages::get_admin_page_urls(),
							'strings'  => array(
								'creating'     => __( 'Создаём страницу…', 'art-lms' ),
								'createFailed' => __( 'Не удалось создать страницу.', 'art-lms' ),
								'editPage'     => __( 'Редактировать страницу', 'art-lms' ),
								'viewPage'     => __( 'Перейти', 'art-lms' ),
							),
						)
					);
				}

				if ( Art_LMS_Admin_Settings::TAB_LOGIN === $tab ) {
					wp_localize_script(
						'art-lms-admin-settings',
						'artLmsLoginSettings',
						array(
							'defaults'       => array_merge(
								Art_LMS_Settings::get_login_design_color_defaults(),
								Art_LMS_Settings::get_login_design_dimension_defaults()
							),
							'formDefaults'   => Art_LMS_Settings::get_login_form_text_defaults(),
							'buttonDefaults' => array_merge(
								Art_LMS_Settings::get_login_button_color_defaults(),
								Art_LMS_Settings::get_login_button_dimension_defaults(),
								array(
									'text' => Art_LMS_Settings::get_default_login()['button']['text'],
								)
							),
							'strings'      => array(
								'disabled'   => __( 'Своя форма входа выключена', 'art-lms' ),
								'copy'       => __( 'Скопировать', 'art-lms' ),
								'copied'     => __( 'Скопировано!', 'art-lms' ),
								'copyFailed' => __( 'Не удалось скопировать.', 'art-lms' ),
							),
						)
					);
				}

			}

		}



		if ( false !== strpos( $hook, Art_LMS_Admin_Orders::PAGE_EDIT ) ) {

			wp_enqueue_script(

				'art-lms-admin-order',

				ART_LMS_PLUGIN_URL . 'assets/js/admin-order.js',

				array( 'jquery' ),

				ART_LMS_VERSION,

				true

			);



			wp_localize_script(

				'art-lms-admin-order',

				'artLmsAdminOrder',

				array(

					'restUrl' => esc_url_raw( rest_url( 'art-lms/v1/admin/lookup-buyer' ) ),

					'nonce'   => wp_create_nonce( 'wp_rest' ),

					'paymentButtons' => Art_LMS_Payment_Buttons::get_order_form_options(),

					'strings' => array(

						'searching'    => __( 'Ищем пользователя…', 'art-lms' ),

						'lookupFailed' => __( 'Не удалось найти пользователя. Попробуйте ещё раз.', 'art-lms' ),

						'materialsPrefix' => __( 'Материалы:', 'art-lms' ),

						'noMaterials' => __( 'У этой кнопки пока нет материалов.', 'art-lms' ),

					),

				)

			);

		}



		if ( false !== strpos( $hook, Art_LMS_Admin_Statistics::PAGE_LIST ) ) {
			self::enqueue_datepicker_style();

			wp_enqueue_script( 'jquery-ui-datepicker' );

			wp_enqueue_script(
				'art-lms-admin-statistics',
				ART_LMS_PLUGIN_URL . 'assets/js/admin-statistics.js',
				array( 'jquery', 'jquery-ui-datepicker' ),
				ART_LMS_VERSION,
				true
			);

			wp_localize_script(
				'art-lms-admin-statistics',
				'artLmsAdminStatistics',
				array(
					'strings'    => array(
						'clearDate' => __( 'Сбросить', 'art-lms' ),
					),
					'datepicker' => self::get_datepicker_i18n(),
				)
			);
		}

		if ( false !== strpos( $hook, Art_LMS_Admin_Orders::PAGE_LIST ) ) {
			self::enqueue_datepicker_style();

			wp_enqueue_script( 'jquery-ui-datepicker' );

			wp_enqueue_script(

				'art-lms-admin-orders-list',

				ART_LMS_PLUGIN_URL . 'assets/js/admin-orders-list.js',

				array( 'jquery', 'jquery-ui-datepicker' ),

				ART_LMS_VERSION,

				true

			);



			wp_localize_script(

				'art-lms-admin-orders-list',

				'artLmsAdminOrders',

				array(

					'strings'    => array(

						'clearDate' => __( 'Сбросить', 'art-lms' ),

					),

					'datepicker' => self::get_datepicker_i18n(),

				)

			);

		}

	}



	/**
	 * Enqueue datepicker widget styles.
	 */
	private static function enqueue_datepicker_style() {
		wp_enqueue_style(
			'art-lms-admin-datepicker',
			ART_LMS_PLUGIN_URL . 'assets/css/admin-datepicker.css',
			array(),
			ART_LMS_VERSION
		);
	}

	/**
	 * jQuery UI datepicker localization for admin list filters.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_datepicker_i18n() {
		return array(
			'closeText'   => __( 'Закрыть', 'art-lms' ),
			'prevText'    => __( 'Назад', 'art-lms' ),
			'nextText'    => __( 'Вперёд', 'art-lms' ),
			'currentText' => __( 'Сегодня', 'art-lms' ),
			'monthNames'  => array(
				__( 'Январь', 'art-lms' ),
				__( 'Февраль', 'art-lms' ),
				__( 'Март', 'art-lms' ),
				__( 'Апрель', 'art-lms' ),
				__( 'Май', 'art-lms' ),
				__( 'Июнь', 'art-lms' ),
				__( 'Июль', 'art-lms' ),
				__( 'Август', 'art-lms' ),
				__( 'Сентябрь', 'art-lms' ),
				__( 'Октябрь', 'art-lms' ),
				__( 'Ноябрь', 'art-lms' ),
				__( 'Декабрь', 'art-lms' ),
			),
			'monthNamesShort' => array(
				__( 'Янв', 'art-lms' ),
				__( 'Фев', 'art-lms' ),
				__( 'Мар', 'art-lms' ),
				__( 'Апр', 'art-lms' ),
				__( 'Май', 'art-lms' ),
				__( 'Июн', 'art-lms' ),
				__( 'Июл', 'art-lms' ),
				__( 'Авг', 'art-lms' ),
				__( 'Сен', 'art-lms' ),
				__( 'Окт', 'art-lms' ),
				__( 'Ноя', 'art-lms' ),
				__( 'Дек', 'art-lms' ),
			),
			'dayNamesMin' => array(
				__( 'Вс', 'art-lms' ),
				__( 'Пн', 'art-lms' ),
				__( 'Вт', 'art-lms' ),
				__( 'Ср', 'art-lms' ),
				__( 'Чт', 'art-lms' ),
				__( 'Пт', 'art-lms' ),
				__( 'Сб', 'art-lms' ),
			),
		);
	}

	/**

	 * Add settings link on the plugins list page.

	 *

	 * @param array $links Plugin action links.

	 * @return array

	 */

	public static function plugin_action_links( $links ) {

		if ( ! current_user_can( 'manage_options' ) ) {

			return $links;

		}



		$settings_link = sprintf(

			'<a href="%s">%s</a>',

			esc_url(

				Art_LMS_Admin_Settings::get_tab_url(

					Art_LMS_Admin_Settings::PAGE_SETTINGS,

					Art_LMS_Admin_Settings::TAB_GENERAL

				)

			),

			esc_html__( 'Настройки', 'art-lms' )

		);



		return array_merge( array( $settings_link ), $links );

	}

	/**

	 * Add author materials link on plugins page.

	 *

	 * @param array  $links Plugin row links.

	 * @param string $file  Plugin basename.

	 * @return string

	 */

	public static function plugin_row_meta( $links, $file ) {

		if ( ART_LMS_PLUGIN_BASENAME !== $file ) {

			return $links;

		}



		$links[] = sprintf(

			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',

			esc_url( ART_LMS_AUTHOR_URL ),

			esc_html__( 'Больше материалов автора', 'art-lms' )

		);



		return $links;

	}

}


