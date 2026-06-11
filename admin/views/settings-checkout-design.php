<?php
/**
 * Checkout design settings page.
 *
 * @package Art_LMS
 *
 * @var array $settings Checkout settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$option           = Art_LMS_Settings::OPTION_CHECKOUT;
$design           = Art_LMS_Settings::get_checkout_design();
$color_defaults      = Art_LMS_Settings::get_checkout_design_color_defaults();
$dimension_defaults  = Art_LMS_Settings::get_checkout_design_dimension_defaults();
$template_options    = Art_LMS_Settings::get_checkout_design_template_options();
$size_options     = Art_LMS_Settings::get_checkout_design_button_size_options();
$align_options    = Art_LMS_Settings::get_checkout_design_button_align_options();
$site_preview_url = Art_LMS_Checkout::get_design_preview_url();
?>
<p class="description"><?php esc_html_e( 'На этой вкладке мы настраиваем внешний вид страницы оформления заказа. Поля формы берутся из вкладки «Настройки полей».', 'art-lms' ); ?></p>

<form method="post" action="options.php" class="art-lms-checkout-design-settings-form">
	<?php settings_fields( 'art_lms_checkout_group' ); ?>

	<div class="art-lms-checkout-design-layout">
		<div class="art-lms-checkout-design-settings">
			<div class="art-lms-panel">
				<h2><?php esc_html_e( 'Шаблон страницы', 'art-lms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Оформление', 'art-lms' ); ?></th>
						<td>
							<fieldset class="art-lms-checkout-design-template-options">
								<legend class="screen-reader-text"><?php esc_html_e( 'Шаблон страницы', 'art-lms' ); ?></legend>
								<?php foreach ( $template_options as $template_key => $template_label ) : ?>
									<label class="art-lms-checkout-design-template-option">
										<input
											type="radio"
											name="<?php echo esc_attr( $option ); ?>[design][template]"
											value="<?php echo esc_attr( $template_key ); ?>"
											<?php checked( $design['template'], $template_key ); ?>
										>
										<?php echo esc_html( $template_label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>
			</div>

			<div class="art-lms-panel">
				<h2><?php esc_html_e( 'Размеры', 'art-lms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="checkout_design_form_max_width"><?php esc_html_e( 'Ширина блока формы', 'art-lms' ); ?></label>
						</th>
						<td>
							<div class="art-lms-checkout-design-dimension-control">
								<input
									type="number"
									class="small-text"
									id="checkout_design_form_max_width"
									name="<?php echo esc_attr( $option ); ?>[design][form_max_width]"
									value="<?php echo esc_attr( (string) $design['form_max_width'] ); ?>"
									min="320"
									max="1200"
									step="1"
								>
								<span class="art-lms-checkout-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
								<button
									type="button"
									class="button art-lms-checkout-design-reset-dimension"
									data-dimension-key="form_max_width"
									data-default-value="<?php echo esc_attr( (string) $dimension_defaults['form_max_width'] ); ?>"
								>
									<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
								</button>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="checkout_design_form_padding"><?php esc_html_e( 'Отступы внутри формы', 'art-lms' ); ?></label>
						</th>
						<td>
							<div class="art-lms-checkout-design-dimension-control">
								<input
									type="number"
									class="small-text"
									id="checkout_design_form_padding"
									name="<?php echo esc_attr( $option ); ?>[design][form_padding]"
									value="<?php echo esc_attr( (string) $design['form_padding'] ); ?>"
									min="0"
									max="80"
									step="1"
								>
								<span class="art-lms-checkout-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
								<button
									type="button"
									class="button art-lms-checkout-design-reset-dimension"
									data-dimension-key="form_padding"
									data-default-value="<?php echo esc_attr( (string) $dimension_defaults['form_padding'] ); ?>"
								>
									<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
								</button>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="checkout_design_form_border_radius"><?php esc_html_e( 'Скругление углов формы', 'art-lms' ); ?></label>
						</th>
						<td>
							<div class="art-lms-checkout-design-dimension-control">
								<input
									type="number"
									class="small-text"
									id="checkout_design_form_border_radius"
									name="<?php echo esc_attr( $option ); ?>[design][form_border_radius]"
									value="<?php echo esc_attr( (string) $design['form_border_radius'] ); ?>"
									min="0"
									max="64"
									step="1"
								>
								<span class="art-lms-checkout-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
								<button
									type="button"
									class="button art-lms-checkout-design-reset-dimension"
									data-dimension-key="form_border_radius"
									data-default-value="<?php echo esc_attr( (string) $dimension_defaults['form_border_radius'] ); ?>"
								>
									<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
								</button>
							</div>
						</td>
					</tr>
				</table>
			</div>

			<div class="art-lms-panel">
				<h2><?php esc_html_e( 'Цвета', 'art-lms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="checkout_design_page_background_color"><?php esc_html_e( 'Цвет фона страницы', 'art-lms' ); ?></label>
						</th>
						<td>
							<div class="art-lms-checkout-design-color-control">
								<input
									type="color"
									id="checkout_design_page_background_color"
									class="art-lms-checkout-design-color-input"
									name="<?php echo esc_attr( $option ); ?>[design][page_background_color]"
									value="<?php echo esc_attr( $design['page_background_color'] ); ?>"
								>
								<button
									type="button"
									class="button art-lms-checkout-design-reset-color"
									data-color-key="page_background_color"
									data-default-color="<?php echo esc_attr( $color_defaults['page_background_color'] ); ?>"
								>
									<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
								</button>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="checkout_design_form_background_color"><?php esc_html_e( 'Цвет фона формы', 'art-lms' ); ?></label>
						</th>
						<td>
							<div class="art-lms-checkout-design-color-control">
								<input
									type="color"
									id="checkout_design_form_background_color"
									class="art-lms-checkout-design-color-input"
									name="<?php echo esc_attr( $option ); ?>[design][form_background_color]"
									value="<?php echo esc_attr( $design['form_background_color'] ); ?>"
								>
								<button
									type="button"
									class="button art-lms-checkout-design-reset-color"
									data-color-key="form_background_color"
									data-default-color="<?php echo esc_attr( $color_defaults['form_background_color'] ); ?>"
								>
									<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
								</button>
							</div>
						</td>
					</tr>
				</table>
			</div>

			<div class="art-lms-panel">
				<h2><?php esc_html_e( 'Кнопка', 'art-lms' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="checkout_design_button_color"><?php esc_html_e( 'Цвет фона кнопки', 'art-lms' ); ?></label>
						</th>
						<td>
							<div class="art-lms-checkout-design-color-control">
								<input
									type="color"
									id="checkout_design_button_color"
									class="art-lms-checkout-design-color-input"
									name="<?php echo esc_attr( $option ); ?>[design][button_color]"
									value="<?php echo esc_attr( $design['button_color'] ); ?>"
								>
								<button
									type="button"
									class="button art-lms-checkout-design-reset-color"
									data-color-key="button_color"
									data-default-color="<?php echo esc_attr( $color_defaults['button_color'] ); ?>"
								>
									<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
								</button>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="checkout_design_button_text_color"><?php esc_html_e( 'Цвет текста кнопки', 'art-lms' ); ?></label>
						</th>
						<td>
							<div class="art-lms-checkout-design-color-control">
								<input
									type="color"
									id="checkout_design_button_text_color"
									class="art-lms-checkout-design-color-input"
									name="<?php echo esc_attr( $option ); ?>[design][button_text_color]"
									value="<?php echo esc_attr( $design['button_text_color'] ); ?>"
								>
								<button
									type="button"
									class="button art-lms-checkout-design-reset-color"
									data-color-key="button_text_color"
									data-default-color="<?php echo esc_attr( $color_defaults['button_text_color'] ); ?>"
								>
									<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
								</button>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="checkout_design_button_size"><?php esc_html_e( 'Размер кнопки', 'art-lms' ); ?></label>
						</th>
						<td>
							<div class="art-lms-checkout-design-button-controls">
								<select
									id="checkout_design_button_size"
									name="<?php echo esc_attr( $option ); ?>[design][button_size]"
								>
									<?php foreach ( $size_options as $size_key => $size_label ) : ?>
										<option value="<?php echo esc_attr( $size_key ); ?>" <?php selected( $design['button_size'], $size_key ); ?>>
											<?php echo esc_html( $size_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<select
									id="checkout_design_button_align"
									name="<?php echo esc_attr( $option ); ?>[design][button_align]"
									aria-label="<?php esc_attr_e( 'Положение кнопки', 'art-lms' ); ?>"
								>
									<?php foreach ( $align_options as $align_key => $align_label ) : ?>
										<option value="<?php echo esc_attr( $align_key ); ?>" <?php selected( $design['button_align'], $align_key ); ?>>
											<?php echo esc_html( $align_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="checkout_design_button_text"><?php esc_html_e( 'Текст кнопки', 'art-lms' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								class="regular-text"
								id="checkout_design_button_text"
								name="<?php echo esc_attr( $option ); ?>[design][button_text]"
								value="<?php echo esc_attr( $design['button_text'] ); ?>"
								maxlength="50"
							>
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</div>

		<aside class="art-lms-checkout-design-preview-wrap" aria-label="<?php esc_attr_e( 'Предпросмотр формы', 'art-lms' ); ?>">
			<div class="art-lms-panel art-lms-checkout-preview-panel">
				<div class="art-lms-checkout-design-preview-panel__header">
					<div>
						<h2><?php esc_html_e( 'Предпросмотр формы', 'art-lms' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Так форма оформления заказа будет выглядеть для покупателя.', 'art-lms' ); ?></p>
					</div>
					<?php if ( $site_preview_url ) : ?>
						<a
							class="button button-secondary art-lms-checkout-design-site-preview"
							href="<?php echo esc_url( $site_preview_url ); ?>"
							target="_blank"
							rel="noopener noreferrer"
						>
							<?php esc_html_e( 'Предпросмотр на сайте', 'art-lms' ); ?>
						</a>
					<?php endif; ?>
				</div>
				<div id="art-lms-checkout-design-preview" class="art-lms-checkout-design-preview"></div>
			</div>
		</aside>
	</div>
</form>
