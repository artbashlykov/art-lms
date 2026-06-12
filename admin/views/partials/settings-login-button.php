<?php
/**
 * Login button settings partial.
 *
 * @package Art_LMS
 *
 * @var string $option           Option name.
 * @var array  $button_settings  Button settings.
 * @var array  $button_colors    Button color defaults.
 * @var array  $button_dims      Button dimension defaults.
 * @var array  $size_options     Button size options.
 * @var array  $align_options    Button align options.
 * @var bool   $is_enabled       Whether custom login is enabled.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$is_custom_size = 'custom' === ( $button_settings['size'] ?? 'medium' );
?>
<details class="art-lms-panel art-lms-collapsible-panel art-lms-login-settings-button-panel<?php echo esc_attr( $is_enabled ? '' : ' is-disabled' ); ?>">
	<summary class="art-lms-collapsible-panel__summary"><?php esc_html_e( 'Настройка кнопки', 'art-lms' ); ?></summary>
	<div class="art-lms-collapsible-panel__content">
	<p class="description">
		<?php esc_html_e( 'Оформление кнопки отправки формы применяется только при включённой собственной странице входа.', 'art-lms' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="art_lms_login_button_text"><?php esc_html_e( 'Текст кнопки', 'art-lms' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					class="regular-text"
					id="art_lms_login_button_text"
					name="<?php echo esc_attr( $option ); ?>[button][text]"
					value="<?php echo esc_attr( $button_settings['text'] ); ?>"
					maxlength="50"
					<?php disabled( ! $is_enabled ); ?>
				>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="art_lms_login_button_font_size"><?php esc_html_e( 'Размер шрифта кнопки', 'art-lms' ); ?></label>
			</th>
			<td>
				<div class="art-lms-login-design-dimension-control">
					<input
						type="number"
						class="small-text"
						id="art_lms_login_button_font_size"
						name="<?php echo esc_attr( $option ); ?>[button][font_size]"
						value="<?php echo esc_attr( (string) $button_settings['font_size'] ); ?>"
						min="10"
						max="48"
						step="1"
						<?php disabled( ! $is_enabled ); ?>
					>
					<span class="art-lms-login-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
					<button
						type="button"
						class="button art-lms-login-button-reset-dimension"
						data-dimension-key="font_size"
						data-default-value="<?php echo esc_attr( (string) $button_dims['font_size'] ); ?>"
						<?php disabled( ! $is_enabled ); ?>
					>
						<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
					</button>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="art_lms_login_button_size"><?php esc_html_e( 'Размер кнопки', 'art-lms' ); ?></label>
			</th>
			<td>
				<select
					id="art_lms_login_button_size"
					name="<?php echo esc_attr( $option ); ?>[button][size]"
					<?php disabled( ! $is_enabled ); ?>
				>
					<?php foreach ( $size_options as $size_key => $size_label ) : ?>
						<option value="<?php echo esc_attr( $size_key ); ?>" <?php selected( $button_settings['size'], $size_key ); ?>>
							<?php echo esc_html( $size_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr class="art-lms-login-button-custom-size-row<?php echo esc_attr( $is_custom_size ? '' : ' is-hidden' ); ?>">
			<th scope="row"><?php esc_html_e( 'Отступы кнопки', 'art-lms' ); ?></th>
			<td>
				<div class="art-lms-login-button-custom-padding">
					<div class="art-lms-login-design-dimension-control">
						<label for="art_lms_login_button_custom_padding_y"><?php esc_html_e( 'Сверху и снизу', 'art-lms' ); ?></label>
						<input
							type="number"
							class="small-text"
							id="art_lms_login_button_custom_padding_y"
							name="<?php echo esc_attr( $option ); ?>[button][custom_padding_y]"
							value="<?php echo esc_attr( (string) $button_settings['custom_padding_y'] ); ?>"
							min="4"
							max="40"
							step="1"
							<?php disabled( ! $is_enabled || ! $is_custom_size ); ?>
						>
						<span class="art-lms-login-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
						<button
							type="button"
							class="button art-lms-login-button-reset-dimension"
							data-dimension-key="custom_padding_y"
							data-default-value="<?php echo esc_attr( (string) $button_dims['custom_padding_y'] ); ?>"
							<?php disabled( ! $is_enabled || ! $is_custom_size ); ?>
						>
							<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
						</button>
					</div>
					<div class="art-lms-login-design-dimension-control">
						<label for="art_lms_login_button_custom_padding_x"><?php esc_html_e( 'Слева и справа', 'art-lms' ); ?></label>
						<input
							type="number"
							class="small-text"
							id="art_lms_login_button_custom_padding_x"
							name="<?php echo esc_attr( $option ); ?>[button][custom_padding_x]"
							value="<?php echo esc_attr( (string) $button_settings['custom_padding_x'] ); ?>"
							min="8"
							max="80"
							step="1"
							<?php disabled( ! $is_enabled || ! $is_custom_size ); ?>
						>
						<span class="art-lms-login-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
						<button
							type="button"
							class="button art-lms-login-button-reset-dimension"
							data-dimension-key="custom_padding_x"
							data-default-value="<?php echo esc_attr( (string) $button_dims['custom_padding_x'] ); ?>"
							<?php disabled( ! $is_enabled || ! $is_custom_size ); ?>
						>
							<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
						</button>
					</div>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="art_lms_login_button_align"><?php esc_html_e( 'Расположение кнопки', 'art-lms' ); ?></label>
			</th>
			<td>
				<select
					id="art_lms_login_button_align"
					name="<?php echo esc_attr( $option ); ?>[button][align]"
					<?php disabled( ! $is_enabled ); ?>
				>
					<?php foreach ( $align_options as $align_key => $align_label ) : ?>
						<option value="<?php echo esc_attr( $align_key ); ?>" <?php selected( $button_settings['align'], $align_key ); ?>>
							<?php echo esc_html( $align_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="art_lms_login_button_background_color"><?php esc_html_e( 'Цвет фона кнопки', 'art-lms' ); ?></label>
			</th>
			<td>
				<div class="art-lms-login-design-color-control">
					<input
						type="color"
						id="art_lms_login_button_background_color_picker"
						class="art-lms-login-button-color-picker"
						value="<?php echo esc_attr( $button_settings['background_color'] ); ?>"
						<?php disabled( ! $is_enabled ); ?>
					>
					<input
						type="text"
						id="art_lms_login_button_background_color"
						class="art-lms-login-button-color-hex"
						name="<?php echo esc_attr( $option ); ?>[button][background_color]"
						value="<?php echo esc_attr( $button_settings['background_color'] ); ?>"
						pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
						spellcheck="false"
						autocomplete="off"
						maxlength="7"
						placeholder="#000000"
						<?php disabled( ! $is_enabled ); ?>
					>
					<button
						type="button"
						class="button art-lms-login-button-reset-color"
						data-color-key="background_color"
						data-default-color="<?php echo esc_attr( $button_colors['background_color'] ); ?>"
						<?php disabled( ! $is_enabled ); ?>
					>
						<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
					</button>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="art_lms_login_button_text_color"><?php esc_html_e( 'Цвет текста кнопки', 'art-lms' ); ?></label>
			</th>
			<td>
				<div class="art-lms-login-design-color-control">
					<input
						type="color"
						id="art_lms_login_button_text_color_picker"
						class="art-lms-login-button-color-picker"
						value="<?php echo esc_attr( $button_settings['text_color'] ); ?>"
						<?php disabled( ! $is_enabled ); ?>
					>
					<input
						type="text"
						id="art_lms_login_button_text_color"
						class="art-lms-login-button-color-hex"
						name="<?php echo esc_attr( $option ); ?>[button][text_color]"
						value="<?php echo esc_attr( $button_settings['text_color'] ); ?>"
						pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$"
						spellcheck="false"
						autocomplete="off"
						maxlength="7"
						placeholder="#000000"
						<?php disabled( ! $is_enabled ); ?>
					>
					<button
						type="button"
						class="button art-lms-login-button-reset-color"
						data-color-key="text_color"
						data-default-color="<?php echo esc_attr( $button_colors['text_color'] ); ?>"
						<?php disabled( ! $is_enabled ); ?>
					>
						<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
					</button>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="art_lms_login_button_border_radius"><?php esc_html_e( 'Скругление углов кнопки', 'art-lms' ); ?></label>
			</th>
			<td>
				<div class="art-lms-login-design-dimension-control">
					<input
						type="number"
						class="small-text"
						id="art_lms_login_button_border_radius"
						name="<?php echo esc_attr( $option ); ?>[button][border_radius]"
						value="<?php echo esc_attr( (string) $button_settings['border_radius'] ); ?>"
						min="0"
						max="48"
						step="1"
						<?php disabled( ! $is_enabled ); ?>
					>
					<span class="art-lms-login-design-dimension-suffix"><?php esc_html_e( 'px', 'art-lms' ); ?></span>
					<button
						type="button"
						class="button art-lms-login-button-reset-dimension"
						data-dimension-key="border_radius"
						data-default-value="<?php echo esc_attr( (string) $button_dims['border_radius'] ); ?>"
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
