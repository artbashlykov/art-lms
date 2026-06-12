<?php
/**
 * Custom login page settings.
 *
 * @package Art_LMS
 *
 * @var array $settings Login settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$option          = Art_LMS_Settings::OPTION_LOGIN;
$slug            = Art_LMS_Settings::get_login_slug();
$is_enabled      = Art_LMS_Settings::is_custom_login_enabled();
$home_url        = home_url( '/' );
$preview_url     = $is_enabled ? Art_LMS_Custom_Login::get_url() : '';
$form_settings    = Art_LMS_Settings::get_login_form();
$form_defaults    = Art_LMS_Settings::get_login_form_text_defaults();
$button_settings  = Art_LMS_Settings::get_login_button();
$button_colors    = Art_LMS_Settings::get_login_button_color_defaults();
$button_dims      = Art_LMS_Settings::get_login_button_dimension_defaults();
$size_options     = Art_LMS_Settings::get_login_button_size_options();
$align_options    = Art_LMS_Settings::get_login_button_align_options();
$design               = Art_LMS_Settings::get_login_design();
$color_defaults       = Art_LMS_Settings::get_login_design_color_defaults();
$dimension_defaults   = Art_LMS_Settings::get_login_design_dimension_defaults();
$title_enabled        = 'yes' === ( $form_settings['title_enabled'] ?? 'yes' );
$subtitle_enabled     = 'yes' === ( $form_settings['subtitle_enabled'] ?? 'no' );
$remember_enabled     = 'yes' === ( $form_settings['remember_enabled'] ?? 'yes' );
$lost_password_enabled = 'yes' === ( $form_settings['lost_password_enabled'] ?? 'yes' );
$button_wrapper_class = Art_LMS_Settings::get_login_button_wrapper_class();
$preview_canvas_style = sprintf( 'background-color:%s;', esc_attr( $design['page_background_color'] ) );
$preview_form_style   = sprintf(
	'background-color:%s;border-color:%s;max-width:%dpx;padding:%dpx;border-radius:%dpx;',
	esc_attr( $design['form_background_color'] ),
	esc_attr( $design['form_border_color'] ),
	(int) $design['form_max_width'],
	(int) $design['form_padding'],
	(int) $design['form_border_radius']
);
?>
<div class="art-lms-settings-login-page">
	<form method="post" action="options.php" class="art-lms-login-settings-form">
		<?php settings_fields( 'art_lms_login_group' ); ?>

		<div class="art-lms-login-settings-layout">
			<div class="art-lms-login-settings-main">
				<div class="art-lms-panel">
					<h2><?php esc_html_e( 'О разделе', 'art-lms' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Здесь настраивается собственная страница входа вместо стандартного адреса WordPress wp-login.php. Покупатели и гости сайта будут попадать на выбранный URL. Служебные действия (выход, сброс пароля) по-прежнему обрабатываются через wp-login.php.', 'art-lms' ); ?>
					</p>
				</div>

				<div class="art-lms-panel">
					<h2><?php esc_html_e( 'Своя форма входа', 'art-lms' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Статус', 'art-lms' ); ?></th>
							<td>
								<label for="art_lms_login_enabled">
									<input
										type="checkbox"
										id="art_lms_login_enabled"
										name="<?php echo esc_attr( $option ); ?>[enabled]"
										value="1"
										<?php checked( $is_enabled ); ?>
									>
									<?php esc_html_e( 'Использовать свою страницу входа', 'art-lms' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Пока переключатель выключен, сайт использует стандартный wp-login.php. Адрес и оформление из этого раздела не применяются.', 'art-lms' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="art-lms-panel art-lms-login-settings-url-panel<?php echo esc_attr( $is_enabled ? '' : ' is-disabled' ); ?>">
					<h2><?php esc_html_e( 'Адрес страницы входа', 'art-lms' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="art_lms_login_slug"><?php esc_html_e( 'URL страницы', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-slug-field">
									<span class="art-lms-login-slug-field__prefix"><?php echo esc_html( untrailingslashit( $home_url ) ); ?>/</span>
									<input
										type="text"
										class="regular-text"
										id="art_lms_login_slug"
										name="<?php echo esc_attr( $option ); ?>[slug]"
										value="<?php echo esc_attr( $slug ); ?>"
										autocomplete="off"
										spellcheck="false"
										pattern="[a-z0-9\-]+"
										<?php disabled( ! $is_enabled ); ?>
									>
									<span class="art-lms-login-slug-field__suffix">/</span>
								</div>
								<p class="description">
									<?php esc_html_e( 'Используйте латиницу, цифры и дефис. После включения формы все ссылки «Войти» в ART LMS будут вести на этот адрес.', 'art-lms' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<details class="art-lms-panel art-lms-collapsible-panel art-lms-login-settings-design-panel<?php echo esc_attr( $is_enabled ? '' : ' is-disabled' ); ?>">
					<summary class="art-lms-collapsible-panel__summary"><?php esc_html_e( 'Дизайн формы', 'art-lms' ); ?></summary>
					<div class="art-lms-collapsible-panel__content">
					<p class="description">
						<?php esc_html_e( 'Цвета применяются на странице входа только при включённой собственной форме. Можно выбрать оттенок или ввести код в формате #RRGGBB, например #f1f5f9.', 'art-lms' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="art_lms_login_page_background_color"><?php esc_html_e( 'Цвет фона страницы', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-design-color-control">
									<input
										type="color"
										id="art_lms_login_page_background_color_picker"
										class="art-lms-login-design-color-picker"
										value="<?php echo esc_attr( $design['page_background_color'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
									<input
										type="text"
										id="art_lms_login_page_background_color"
										class="art-lms-login-design-color-hex"
										name="<?php echo esc_attr( $option ); ?>[design][page_background_color]"
										value="<?php echo esc_attr( $design['page_background_color'] ); ?>"
										pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
										spellcheck="false"
										autocomplete="off"
										maxlength="7"
										placeholder="#000000"
										<?php disabled( ! $is_enabled ); ?>
									>
									<button
										type="button"
										class="button art-lms-login-design-reset-color"
										data-color-key="page_background_color"
										data-default-color="<?php echo esc_attr( $color_defaults['page_background_color'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
										<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
									</button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_form_background_color"><?php esc_html_e( 'Цвет фона формы', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-design-color-control">
									<input
										type="color"
										id="art_lms_login_form_background_color_picker"
										class="art-lms-login-design-color-picker"
										value="<?php echo esc_attr( $design['form_background_color'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
									<input
										type="text"
										id="art_lms_login_form_background_color"
										class="art-lms-login-design-color-hex"
										name="<?php echo esc_attr( $option ); ?>[design][form_background_color]"
										value="<?php echo esc_attr( $design['form_background_color'] ); ?>"
										pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
										spellcheck="false"
										autocomplete="off"
										maxlength="7"
										placeholder="#000000"
										<?php disabled( ! $is_enabled ); ?>
									>
									<button
										type="button"
										class="button art-lms-login-design-reset-color"
										data-color-key="form_background_color"
										data-default-color="<?php echo esc_attr( $color_defaults['form_background_color'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
										<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
									</button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_form_border_color"><?php esc_html_e( 'Цвет обводки формы', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-design-color-control">
									<input
										type="color"
										id="art_lms_login_form_border_color_picker"
										class="art-lms-login-design-color-picker"
										value="<?php echo esc_attr( $design['form_border_color'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
									<input
										type="text"
										id="art_lms_login_form_border_color"
										class="art-lms-login-design-color-hex"
										name="<?php echo esc_attr( $option ); ?>[design][form_border_color]"
										value="<?php echo esc_attr( $design['form_border_color'] ); ?>"
										pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
										spellcheck="false"
										autocomplete="off"
										maxlength="7"
										placeholder="#000000"
										<?php disabled( ! $is_enabled ); ?>
									>
									<button
										type="button"
										class="button art-lms-login-design-reset-color"
										data-color-key="form_border_color"
										data-default-color="<?php echo esc_attr( $color_defaults['form_border_color'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
										<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
									</button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_form_max_width"><?php esc_html_e( 'Ширина блока формы', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-design-dimension-control">
									<input
										type="number"
										class="small-text art-lms-login-design-dimension-input"
										id="art_lms_login_form_max_width"
										name="<?php echo esc_attr( $option ); ?>[design][form_max_width]"
										value="<?php echo esc_attr( (string) $design['form_max_width'] ); ?>"
										min="280"
										max="720"
										step="1"
										data-dimension-key="form_max_width"
										<?php disabled( ! $is_enabled ); ?>
									>
									<span class="art-lms-login-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
									<button
										type="button"
										class="button art-lms-login-design-reset-dimension"
										data-dimension-key="form_max_width"
										data-default-value="<?php echo esc_attr( (string) $dimension_defaults['form_max_width'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
										<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
									</button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_form_padding"><?php esc_html_e( 'Отступы внутри формы', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-design-dimension-control">
									<input
										type="number"
										class="small-text art-lms-login-design-dimension-input"
										id="art_lms_login_form_padding"
										name="<?php echo esc_attr( $option ); ?>[design][form_padding]"
										value="<?php echo esc_attr( (string) $design['form_padding'] ); ?>"
										min="0"
										max="80"
										step="1"
										data-dimension-key="form_padding"
										<?php disabled( ! $is_enabled ); ?>
									>
									<span class="art-lms-login-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
									<button
										type="button"
										class="button art-lms-login-design-reset-dimension"
										data-dimension-key="form_padding"
										data-default-value="<?php echo esc_attr( (string) $dimension_defaults['form_padding'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
										<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
									</button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_form_border_radius"><?php esc_html_e( 'Скругление углов формы', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-design-dimension-control">
									<input
										type="number"
										class="small-text art-lms-login-design-dimension-input"
										id="art_lms_login_form_border_radius"
										name="<?php echo esc_attr( $option ); ?>[design][form_border_radius]"
										value="<?php echo esc_attr( (string) $design['form_border_radius'] ); ?>"
										min="0"
										max="48"
										step="1"
										data-dimension-key="form_border_radius"
										<?php disabled( ! $is_enabled ); ?>
									>
									<span class="art-lms-login-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
									<button
										type="button"
										class="button art-lms-login-design-reset-dimension"
										data-dimension-key="form_border_radius"
										data-default-value="<?php echo esc_attr( (string) $dimension_defaults['form_border_radius'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
										<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
									</button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_field_label_font_size"><?php esc_html_e( 'Размер подписей полей', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-design-dimension-control">
									<input
										type="number"
										class="small-text art-lms-login-design-dimension-input"
										id="art_lms_login_field_label_font_size"
										name="<?php echo esc_attr( $option ); ?>[design][field_label_font_size]"
										value="<?php echo esc_attr( (string) $design['field_label_font_size'] ); ?>"
										min="10"
										max="32"
										step="1"
										data-dimension-key="field_label_font_size"
										<?php disabled( ! $is_enabled ); ?>
									>
									<span class="art-lms-login-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
									<button
										type="button"
										class="button art-lms-login-design-reset-dimension"
										data-dimension-key="field_label_font_size"
										data-default-value="<?php echo esc_attr( (string) $dimension_defaults['field_label_font_size'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
										<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
									</button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_field_input_font_size"><?php esc_html_e( 'Размер текста в полях', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-design-dimension-control">
									<input
										type="number"
										class="small-text art-lms-login-design-dimension-input"
										id="art_lms_login_field_input_font_size"
										name="<?php echo esc_attr( $option ); ?>[design][field_input_font_size]"
										value="<?php echo esc_attr( (string) $design['field_input_font_size'] ); ?>"
										min="10"
										max="32"
										step="1"
										data-dimension-key="field_input_font_size"
										<?php disabled( ! $is_enabled ); ?>
									>
									<span class="art-lms-login-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
									<button
										type="button"
										class="button art-lms-login-design-reset-dimension"
										data-dimension-key="field_input_font_size"
										data-default-value="<?php echo esc_attr( (string) $dimension_defaults['field_input_font_size'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
										<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
									</button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_field_border_color"><?php esc_html_e( 'Цвет обводки полей', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-design-color-control">
									<input
										type="color"
										id="art_lms_login_field_border_color_picker"
										class="art-lms-login-design-color-picker"
										value="<?php echo esc_attr( $design['field_border_color'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
									<input
										type="text"
										id="art_lms_login_field_border_color"
										class="art-lms-login-design-color-hex"
										name="<?php echo esc_attr( $option ); ?>[design][field_border_color]"
										value="<?php echo esc_attr( $design['field_border_color'] ); ?>"
										pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
										spellcheck="false"
										autocomplete="off"
										maxlength="7"
										placeholder="#000000"
										<?php disabled( ! $is_enabled ); ?>
									>
									<button
										type="button"
										class="button art-lms-login-design-reset-color"
										data-color-key="field_border_color"
										data-default-color="<?php echo esc_attr( $color_defaults['field_border_color'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
										<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
									</button>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_field_focus_border_color"><?php esc_html_e( 'Цвет обводки поля в фокусе', 'art-lms' ); ?></label>
							</th>
							<td>
								<div class="art-lms-login-design-color-control">
									<input
										type="color"
										id="art_lms_login_field_focus_border_color_picker"
										class="art-lms-login-design-color-picker"
										value="<?php echo esc_attr( $design['field_focus_border_color'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
									<input
										type="text"
										id="art_lms_login_field_focus_border_color"
										class="art-lms-login-design-color-hex"
										name="<?php echo esc_attr( $option ); ?>[design][field_focus_border_color]"
										value="<?php echo esc_attr( $design['field_focus_border_color'] ); ?>"
										pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
										spellcheck="false"
										autocomplete="off"
										maxlength="7"
										placeholder="#000000"
										<?php disabled( ! $is_enabled ); ?>
									>
									<button
										type="button"
										class="button art-lms-login-design-reset-color"
										data-color-key="field_focus_border_color"
										data-default-color="<?php echo esc_attr( $color_defaults['field_focus_border_color'] ); ?>"
										<?php disabled( ! $is_enabled ); ?>
									>
										<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
									</button>
								</div>
							</td>
						</tr>
					</table>
					</div>
				</details>

				<details class="art-lms-panel art-lms-collapsible-panel art-lms-login-settings-form-panel<?php echo esc_attr( $is_enabled ? '' : ' is-disabled' ); ?>">
					<summary class="art-lms-collapsible-panel__summary"><?php esc_html_e( 'Настройки формы', 'art-lms' ); ?></summary>
					<div class="art-lms-collapsible-panel__content">
					<p class="description">
						<?php esc_html_e( 'Тексты и элементы формы применяются только при включённой собственной странице входа.', 'art-lms' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Заголовок формы', 'art-lms' ); ?></th>
							<td>
								<label for="art_lms_login_title_enabled">
									<input
										type="checkbox"
										id="art_lms_login_title_enabled"
										name="<?php echo esc_attr( $option ); ?>[form][title_enabled]"
										value="1"
										<?php checked( $title_enabled ); ?>
										<?php disabled( ! $is_enabled ); ?>
									>
									<?php esc_html_e( 'Показывать заголовок', 'art-lms' ); ?>
								</label>
								<p>
									<label class="screen-reader-text" for="art_lms_login_title_text"><?php esc_html_e( 'Текст заголовка', 'art-lms' ); ?></label>
									<input
										type="text"
										class="regular-text"
										id="art_lms_login_title_text"
										name="<?php echo esc_attr( $option ); ?>[form][title_text]"
										value="<?php echo esc_attr( $form_settings['title_text'] ); ?>"
										maxlength="100"
										<?php disabled( ! $is_enabled || ! $title_enabled ); ?>
									>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Подзаголовок', 'art-lms' ); ?></th>
							<td>
								<label for="art_lms_login_subtitle_enabled">
									<input
										type="checkbox"
										id="art_lms_login_subtitle_enabled"
										name="<?php echo esc_attr( $option ); ?>[form][subtitle_enabled]"
										value="1"
										<?php checked( $subtitle_enabled ); ?>
										<?php disabled( ! $is_enabled ); ?>
									>
									<?php esc_html_e( 'Показывать подзаголовок под заголовком', 'art-lms' ); ?>
								</label>
								<p>
									<label class="screen-reader-text" for="art_lms_login_subtitle_text"><?php esc_html_e( 'Текст подзаголовка', 'art-lms' ); ?></label>
									<input
										type="text"
										class="regular-text"
										id="art_lms_login_subtitle_text"
										name="<?php echo esc_attr( $option ); ?>[form][subtitle_text]"
										value="<?php echo esc_attr( $form_settings['subtitle_text'] ); ?>"
										maxlength="200"
										placeholder="<?php echo esc_attr( $form_defaults['subtitle_text'] ); ?>"
										<?php disabled( ! $is_enabled || ! $subtitle_enabled ); ?>
									>
								</p>
								<p class="description"><?php esc_html_e( 'Короткая подсказка под заголовком — например, зачем нужен вход в ЛМС.', 'art-lms' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_username_label"><?php esc_html_e( 'Подпись поля Email', 'art-lms' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									class="regular-text"
									id="art_lms_login_username_label"
									name="<?php echo esc_attr( $option ); ?>[form][username_label]"
									value="<?php echo esc_attr( $form_settings['username_label'] ); ?>"
									maxlength="80"
									<?php disabled( ! $is_enabled ); ?>
								>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="art_lms_login_password_label"><?php esc_html_e( 'Подпись поля пароля', 'art-lms' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									class="regular-text"
									id="art_lms_login_password_label"
									name="<?php echo esc_attr( $option ); ?>[form][password_label]"
									value="<?php echo esc_attr( $form_settings['password_label'] ); ?>"
									maxlength="80"
									<?php disabled( ! $is_enabled ); ?>
								>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Запомнить меня', 'art-lms' ); ?></th>
							<td>
								<label for="art_lms_login_remember_enabled">
									<input
										type="checkbox"
										id="art_lms_login_remember_enabled"
										name="<?php echo esc_attr( $option ); ?>[form][remember_enabled]"
										value="1"
										<?php checked( $remember_enabled ); ?>
										<?php disabled( ! $is_enabled ); ?>
									>
									<?php esc_html_e( 'Показывать чекбокс «Запомнить меня»', 'art-lms' ); ?>
								</label>
								<p>
									<label class="screen-reader-text" for="art_lms_login_remember_label"><?php esc_html_e( 'Текст чекбокса', 'art-lms' ); ?></label>
									<input
										type="text"
										class="regular-text"
										id="art_lms_login_remember_label"
										name="<?php echo esc_attr( $option ); ?>[form][remember_label]"
										value="<?php echo esc_attr( $form_settings['remember_label'] ); ?>"
										maxlength="80"
										<?php disabled( ! $is_enabled || ! $remember_enabled ); ?>
									>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Забыли пароль?', 'art-lms' ); ?></th>
							<td>
								<label for="art_lms_login_lost_password_enabled">
									<input
										type="checkbox"
										id="art_lms_login_lost_password_enabled"
										name="<?php echo esc_attr( $option ); ?>[form][lost_password_enabled]"
										value="1"
										<?php checked( $lost_password_enabled ); ?>
										<?php disabled( ! $is_enabled ); ?>
									>
									<?php esc_html_e( 'Показывать ссылку восстановления пароля', 'art-lms' ); ?>
								</label>
								<p>
									<label class="screen-reader-text" for="art_lms_login_lost_password_text"><?php esc_html_e( 'Текст ссылки', 'art-lms' ); ?></label>
									<input
										type="text"
										class="regular-text"
										id="art_lms_login_lost_password_text"
										name="<?php echo esc_attr( $option ); ?>[form][lost_password_text]"
										value="<?php echo esc_attr( $form_settings['lost_password_text'] ); ?>"
										maxlength="80"
										<?php disabled( ! $is_enabled || ! $lost_password_enabled ); ?>
									>
								</p>
							</td>
						</tr>
					</table>
					</div>
				</details>

				<?php include ART_LMS_PLUGIN_DIR . 'admin/views/partials/settings-login-button.php'; ?>

				<?php submit_button(); ?>
			</div>

			<aside class="art-lms-login-settings-preview-wrap" aria-label="<?php esc_attr_e( 'Предпросмотр формы входа', 'art-lms' ); ?>">
				<div class="art-lms-panel art-lms-login-settings-preview-panel">
					<div class="art-lms-login-settings-preview-panel__header">
						<h2><?php esc_html_e( 'Предпросмотр', 'art-lms' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Так форма будет выглядеть на сайте после включения и сохранения настроек.', 'art-lms' ); ?></p>
						<div class="art-lms-login-preview__url-row">
							<input
								type="text"
								readonly
								class="art-lms-login-preview__url-input"
								id="art-lms-login-preview-url"
								value="<?php echo esc_attr( ( $is_enabled && $preview_url ) ? $preview_url : __( 'Своя форма входа выключена', 'art-lms' ) ); ?>"
								aria-label="<?php esc_attr_e( 'URL страницы входа', 'art-lms' ); ?>"
							>
							<button
								type="button"
								class="button art-lms-login-preview__url-copy"
								id="art-lms-login-preview-url-copy"
								<?php disabled( ! $is_enabled || ! $preview_url ); ?>
								aria-label="<?php esc_attr_e( 'Скопировать ссылку', 'art-lms' ); ?>"
								title="<?php esc_attr_e( 'Скопировать', 'art-lms' ); ?>"
							>
								<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
							</button>
						</div>
					</div>

					<div
						id="art-lms-login-preview"
						class="art-lms-login-preview<?php echo esc_attr( $is_enabled ? '' : ' is-disabled' ); ?>"
						data-home-url="<?php echo esc_attr( untrailingslashit( $home_url ) ); ?>"
						<?php if ( $preview_url ) : ?>
							data-preview-url="<?php echo esc_attr( $preview_url ); ?>"
						<?php endif; ?>
					>
						<div class="art-lms-login-preview__canvas" id="art-lms-login-preview-canvas" style="<?php echo esc_attr( $preview_canvas_style ); ?>">
							<div class="art-lms-login-preview__form <?php echo esc_attr( $button_wrapper_class ); ?>" id="art-lms-login-preview-form" style="<?php echo esc_attr( $preview_form_style ); ?>">
								<h3
									class="art-lms-login-preview__title<?php echo esc_attr( $title_enabled ? '' : ' is-hidden' ); ?>"
									id="art-lms-login-preview-title"
								><?php echo esc_html( $form_settings['title_text'] ); ?></h3>
								<p
									class="art-lms-login-preview__subtitle<?php echo esc_attr( ( $subtitle_enabled && '' !== trim( (string) $form_settings['subtitle_text'] ) ) ? '' : ' is-hidden' ); ?>"
									id="art-lms-login-preview-subtitle"
								><?php echo esc_html( $form_settings['subtitle_text'] ); ?></p>
								<p class="art-lms-login-preview__field">
									<label id="art-lms-login-preview-username-label"><?php echo esc_html( $form_settings['username_label'] ); ?></label>
									<span class="art-lms-login-preview__input" aria-hidden="true"></span>
								</p>
								<p class="art-lms-login-preview__field">
									<label id="art-lms-login-preview-password-label"><?php echo esc_html( $form_settings['password_label'] ); ?></label>
									<span class="art-lms-login-preview__input" aria-hidden="true"></span>
								</p>
								<p
									class="art-lms-login-preview__remember<?php echo esc_attr( $remember_enabled ? '' : ' is-hidden' ); ?>"
									id="art-lms-login-preview-remember"
								>
									<span class="art-lms-login-preview__checkbox" aria-hidden="true"></span>
									<span id="art-lms-login-preview-remember-label"><?php echo esc_html( $form_settings['remember_label'] ); ?></span>
								</p>
								<p class="art-lms-login-preview__submit-wrap" id="art-lms-login-preview-submit-wrap">
									<span class="art-lms-login-preview__submit" id="art-lms-login-preview-submit"><?php echo esc_html( $button_settings['text'] ); ?></span>
								</p>
								<p
									class="art-lms-login-preview__lost<?php echo esc_attr( $lost_password_enabled ? '' : ' is-hidden' ); ?>"
									id="art-lms-login-preview-lost"
								><?php echo esc_html( $form_settings['lost_password_text'] ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</aside>
		</div>
	</form>
</div>
