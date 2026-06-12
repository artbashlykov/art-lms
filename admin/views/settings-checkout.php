<?php
/**
 * Checkout settings page.
 *
 * @package Art_LMS
 *
 * @var array $settings Checkout settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$option         = Art_LMS_Settings::OPTION_CHECKOUT;
$field_catalog  = Art_LMS_Settings::get_checkout_field_catalog();
$fields         = $settings['fields'] ?? array();
$custom_fields  = $settings['custom_fields'] ?? array();
$consents       = $settings['consents'] ?? array();
$custom_consents = $settings['custom_consents'] ?? array();
$consent_catalog = Art_LMS_Settings::get_checkout_consent_catalog();
$slug           = Art_LMS_Settings::get_checkout_slug();
$home_url       = home_url( '/' );
?>
<p class="description"><?php esc_html_e( 'На этой вкладке мы настраиваем ОДНУ ОБЩУЮ форму оформления заказов, на которую попадут клиенты со всех платежных кнопок', 'art-lms' ); ?></p>

<form method="post" action="options.php" class="art-lms-checkout-settings-form">
	<?php settings_fields( 'art_lms_checkout_group' ); ?>

	<div class="art-lms-panel">
		<h2><?php esc_html_e( 'URL оформления', 'art-lms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Адрес checkout', 'art-lms' ); ?></th>
				<td>
					<div class="art-lms-checkout-slug-field">
						<span class="art-lms-checkout-slug-field__prefix"><?php echo esc_html( untrailingslashit( $home_url ) ); ?>/</span>
						<input
							type="text"
							class="regular-text"
							id="art_lms_checkout_slug"
							name="<?php echo esc_attr( $option ); ?>[slug]"
							value="<?php echo esc_attr( $slug ); ?>"
							autocomplete="off"
							spellcheck="false"
							pattern="[a-z0-9\-]+"
							required
						>
						<span class="art-lms-checkout-slug-field__suffix">/</span>
					</div>
					<p class="description">
						<?php esc_html_e( 'По этому адресу откроется оформление заказа после нажатия на платежную кнопку. Используйте латиницу, цифры и дефис. После смены адреса обновите ссылки на платежных кнопках и в рассылках.', 'art-lms' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<div class="art-lms-checkout-settings-layout">
		<div class="art-lms-checkout-settings-main">

	<div class="art-lms-panel">
		<h2><?php esc_html_e( 'Заголовок формы', 'art-lms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="art_lms_checkout_form_title"><?php esc_html_e( 'Текст заголовка', 'art-lms' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						class="regular-text"
						id="art_lms_checkout_form_title"
						name="<?php echo esc_attr( $option ); ?>[form_title]"
						value="<?php echo esc_attr( Art_LMS_Settings::get_checkout_form_title() ); ?>"
						maxlength="100"
					>
					<p class="description"><?php esc_html_e( 'Отображается вверху формы оформления заказа.', 'art-lms' ); ?></p>
				</td>
			</tr>
		</table>
	</div>

	<div class="art-lms-panel">
		<h2><?php esc_html_e( 'Стандартные поля', 'art-lms' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Базовые поля формы оформления.', 'art-lms' ); ?></p>
		<table class="widefat striped art-lms-settings-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Поле', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Показывать', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Обязательное', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Подпись', 'art-lms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $field_catalog as $field_key => $field_title ) : ?>
					<?php $field = $fields[ $field_key ] ?? array(); ?>
					<tr>
						<td><strong><?php echo esc_html( $field_title ); ?></strong></td>
						<td>
							<?php if ( 'email' === $field_key ) : ?>
								<input type="hidden" name="<?php echo esc_attr( $option ); ?>[fields][email][enabled]" value="1">
								<?php esc_html_e( 'Всегда', 'art-lms' ); ?>
							<?php else : ?>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[fields][<?php echo esc_attr( $field_key ); ?>][enabled]" value="1" <?php checked( $field['enabled'] ?? 'yes', 'yes' ); ?>>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( 'email' === $field_key ) : ?>
								<input type="hidden" name="<?php echo esc_attr( $option ); ?>[fields][email][required]" value="1">
								<?php esc_html_e( 'Да', 'art-lms' ); ?>
							<?php else : ?>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[fields][<?php echo esc_attr( $field_key ); ?>][required]" value="1" <?php checked( $field['required'] ?? 'no', 'yes' ); ?>>
							<?php endif; ?>
						</td>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr( $option ); ?>[fields][<?php echo esc_attr( $field_key ); ?>][label]"
								value="<?php echo esc_attr( $field['label'] ?? $field_title ); ?>"
								class="regular-text"
							>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="art-lms-panel art-lms-checkout-custom-fields" data-option="<?php echo esc_attr( $option ); ?>">
		<h2><?php esc_html_e( 'Дополнительные поля', 'art-lms' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Добавьте любые текстовые поля для сбора данных на checkout.', 'art-lms' ); ?></p>
		<table class="widefat striped art-lms-settings-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Подпись', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Показывать', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Обязательное', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Действия', 'art-lms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $custom_fields as $index => $custom_field ) : ?>
					<tr class="art-lms-custom-field-row">
						<td>
							<input type="hidden" name="<?php echo esc_attr( $option ); ?>[custom_fields][<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $custom_field['id'] ?? '' ); ?>">
							<input
								type="text"
								name="<?php echo esc_attr( $option ); ?>[custom_fields][<?php echo esc_attr( (string) $index ); ?>][label]"
								value="<?php echo esc_attr( $custom_field['label'] ?? '' ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Например: Telegram', 'art-lms' ); ?>"
							>
						</td>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[custom_fields][<?php echo esc_attr( (string) $index ); ?>][enabled]" value="1" <?php checked( $custom_field['enabled'] ?? 'yes', 'yes' ); ?>>
						</td>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[custom_fields][<?php echo esc_attr( (string) $index ); ?>][required]" value="1" <?php checked( $custom_field['required'] ?? 'no', 'yes' ); ?>>
						</td>
						<td>
							<button type="button" class="button-link-delete art-lms-remove-custom-field"><?php esc_html_e( 'Удалить', 'art-lms' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button art-lms-add-custom-field"><?php esc_html_e( 'Добавить поле', 'art-lms' ); ?></button>
		</p>
	</div>

	<div class="art-lms-panel art-lms-checkout-consents" data-option="<?php echo esc_attr( $option ); ?>">
		<h2><?php esc_html_e( 'Согласие на обработку', 'art-lms' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Поле «Текст ссылки» автоматически становится ссылкой на выбранную страницу и добавляется к основному тексту.', 'art-lms' ); ?>
		</p>
		<table class="widefat striped art-lms-settings-table art-lms-checkout-consents-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Согласие', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Показывать', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Обязательное', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Текст', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Текст ссылки', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Страница', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Действия', 'art-lms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $consent_catalog as $consent_key => $consent_title ) : ?>
					<?php $consent = $consents[ $consent_key ] ?? array(); ?>
					<tr class="art-lms-consent-row art-lms-consent-row--builtin" data-consent-key="<?php echo esc_attr( $consent_key ); ?>">
						<td><strong><?php echo esc_html( $consent_title ); ?></strong></td>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[consents][<?php echo esc_attr( $consent_key ); ?>][enabled]" value="1" <?php checked( $consent['enabled'] ?? 'yes', 'yes' ); ?>>
						</td>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[consents][<?php echo esc_attr( $consent_key ); ?>][required]" value="1" <?php checked( $consent['required'] ?? 'yes', 'yes' ); ?>>
						</td>
						<td>
							<input
								type="text"
								class="regular-text art-lms-consent-text"
								name="<?php echo esc_attr( $option ); ?>[consents][<?php echo esc_attr( $consent_key ); ?>][text]"
								value="<?php echo esc_attr( Art_LMS_Settings::normalize_checkout_consent_text( $consent['text'] ?? '' ) ); ?>"
							>
						</td>
						<td>
							<input
								type="text"
								class="regular-text art-lms-consent-link-text"
								name="<?php echo esc_attr( $option ); ?>[consents][<?php echo esc_attr( $consent_key ); ?>][link_text]"
								value="<?php echo esc_attr( $consent['link_text'] ?? '' ); ?>"
							>
						</td>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $option . '[consents][' . $consent_key . '][page_id]' ),
									'id'                => esc_attr( 'checkout_consent_page_' . $consent_key ),
									'selected'          => (int) ( $consent['page_id'] ?? 0 ),
									'show_option_none'  => esc_html__( '— Не выбрано —', 'art-lms' ),
									'option_none_value' => 0,
								)
							);
							?>
						</td>
						<td></td>
					</tr>
				<?php endforeach; ?>
				<?php foreach ( $custom_consents as $index => $consent ) : ?>
					<tr class="art-lms-consent-row art-lms-consent-row--custom art-lms-custom-consent-row">
						<td>
							<input type="hidden" name="<?php echo esc_attr( $option ); ?>[custom_consents][<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $consent['id'] ?? '' ); ?>">
							<input
								type="text"
								class="regular-text art-lms-consent-label"
								name="<?php echo esc_attr( $option ); ?>[custom_consents][<?php echo esc_attr( (string) $index ); ?>][label]"
								value="<?php echo esc_attr( $consent['label'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'Название согласия', 'art-lms' ); ?>"
							>
						</td>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[custom_consents][<?php echo esc_attr( (string) $index ); ?>][enabled]" value="1" <?php checked( $consent['enabled'] ?? 'yes', 'yes' ); ?>>
						</td>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[custom_consents][<?php echo esc_attr( (string) $index ); ?>][required]" value="1" <?php checked( $consent['required'] ?? 'yes', 'yes' ); ?>>
						</td>
						<td>
							<input
								type="text"
								class="regular-text art-lms-consent-text"
								name="<?php echo esc_attr( $option ); ?>[custom_consents][<?php echo esc_attr( (string) $index ); ?>][text]"
								value="<?php echo esc_attr( Art_LMS_Settings::normalize_checkout_consent_text( $consent['text'] ?? '' ) ); ?>"
							>
						</td>
						<td>
							<input
								type="text"
								class="regular-text art-lms-consent-link-text"
								name="<?php echo esc_attr( $option ); ?>[custom_consents][<?php echo esc_attr( (string) $index ); ?>][link_text]"
								value="<?php echo esc_attr( $consent['link_text'] ?? '' ); ?>"
							>
						</td>
						<td class="art-lms-consent-page-cell">
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $option . '[custom_consents][' . $index . '][page_id]' ),
									'id'                => esc_attr( 'checkout_custom_consent_page_' . $index ),
									'selected'          => (int) ( $consent['page_id'] ?? 0 ),
									'show_option_none'  => esc_html__( '— Не выбрано —', 'art-lms' ),
									'option_none_value' => 0,
								)
							);
							?>
						</td>
						<td>
							<button type="button" class="button-link-delete art-lms-remove-custom-consent"><?php esc_html_e( 'Удалить', 'art-lms' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button art-lms-add-custom-consent"><?php esc_html_e( 'Добавить', 'art-lms' ); ?></button>
		</p>
	</div>

	<details class="art-lms-panel art-lms-collapsible-panel">
		<summary class="art-lms-collapsible-panel__summary"><?php esc_html_e( 'Сообщения под формой', 'art-lms' ); ?></summary>
		<div class="art-lms-collapsible-panel__content">
		<p class="description"><?php esc_html_e( 'Тексты ошибок, которые покупатель увидит под формой при проверке данных или сбое отправки.', 'art-lms' ); ?></p>
		<?php
		$form_messages   = Art_LMS_Settings::get_checkout_form_messages();
		$message_catalog = Art_LMS_Settings::get_checkout_form_message_catalog();
		?>
		<table class="widefat striped art-lms-settings-table art-lms-checkout-messages-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ситуация', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Сообщение', 'art-lms' ); ?></th>
					<th><?php esc_html_e( 'Действие', 'art-lms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $message_catalog as $message_key => $message_meta ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $message_meta['label'] ); ?></strong>
							<?php if ( ! empty( $message_meta['description'] ) ) : ?>
								<p class="description"><?php echo esc_html( $message_meta['description'] ); ?></p>
							<?php endif; ?>
						</td>
						<td>
							<textarea
								class="large-text art-lms-checkout-message-input"
								id="art_lms_checkout_message_<?php echo esc_attr( $message_key ); ?>"
								name="<?php echo esc_attr( $option ); ?>[messages][<?php echo esc_attr( $message_key ); ?>]"
								rows="2"
							><?php echo esc_textarea( $form_messages[ $message_key ] ?? '' ); ?></textarea>
						</td>
						<td>
							<button
								type="button"
								class="button art-lms-checkout-message-reset"
								data-target="art_lms_checkout_message_<?php echo esc_attr( $message_key ); ?>"
								data-reset-key="<?php echo esc_attr( $message_key ); ?>"
							>
								<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	</details>

		</div>

		<aside class="art-lms-checkout-settings-preview" aria-label="<?php esc_attr_e( 'Предпросмотр формы', 'art-lms' ); ?>">
			<div class="art-lms-panel art-lms-checkout-preview-panel">
				<h2><?php esc_html_e( 'Предпросмотр формы', 'art-lms' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Так форма будет выглядеть для покупателя на checkout.', 'art-lms' ); ?></p>
				<div id="art-lms-checkout-preview" class="art-lms-checkout-preview"></div>
			</div>
		</aside>
	</div>

	<?php submit_button(); ?>
</form>

<script type="text/html" id="tmpl-art-lms-custom-field-row">
	<tr class="art-lms-custom-field-row">
		<td>
			<input type="hidden" name="{{option}}[custom_fields][{{index}}][id]" value="{{id}}">
			<input type="text" name="{{option}}[custom_fields][{{index}}][label]" value="" class="regular-text" placeholder="<?php echo esc_attr__( 'Например: Telegram', 'art-lms' ); ?>">
		</td>
		<td>
			<input type="checkbox" name="{{option}}[custom_fields][{{index}}][enabled]" value="1" checked>
		</td>
		<td>
			<input type="checkbox" name="{{option}}[custom_fields][{{index}}][required]" value="1">
		</td>
		<td>
			<button type="button" class="button-link-delete art-lms-remove-custom-field"><?php echo esc_html__( 'Удалить', 'art-lms' ); ?></button>
		</td>
	</tr>
</script>

<script type="text/html" id="tmpl-art-lms-custom-consent-row">
	<tr class="art-lms-consent-row art-lms-consent-row--custom art-lms-custom-consent-row">
		<td>
			<input type="hidden" name="{{option}}[custom_consents][{{index}}][id]" value="{{id}}">
			<input type="text" class="regular-text art-lms-consent-label" name="{{option}}[custom_consents][{{index}}][label]" value="" placeholder="<?php echo esc_attr__( 'Название согласия', 'art-lms' ); ?>">
		</td>
		<td>
			<input type="checkbox" name="{{option}}[custom_consents][{{index}}][enabled]" value="1" checked>
		</td>
		<td>
			<input type="checkbox" name="{{option}}[custom_consents][{{index}}][required]" value="1" checked>
		</td>
		<td>
			<input type="text" class="regular-text art-lms-consent-text" name="{{option}}[custom_consents][{{index}}][text]" value="<?php echo esc_attr__( 'Я согласен с', 'art-lms' ); ?>">
		</td>
		<td>
			<input type="text" class="regular-text art-lms-consent-link-text" name="{{option}}[custom_consents][{{index}}][link_text]" value="">
		</td>
		<td class="art-lms-consent-page-cell"></td>
		<td>
			<button type="button" class="button-link-delete art-lms-remove-custom-consent"><?php echo esc_html__( 'Удалить', 'art-lms' ); ?></button>
		</td>
	</tr>
</script>
