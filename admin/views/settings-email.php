<?php
/**
 * Email settings page.
 *
 * @package Art_LMS
 *
 * @var array $settings Email settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$option        = Art_LMS_Settings::OPTION_EMAIL;
$defaults      = Art_LMS_Settings::get_default_emails();
$purchase      = $settings['purchase'] ?? $defaults['purchase'];
$admin_payment = $settings['admin_payment'] ?? $defaults['admin_payment'];
$email_verification = $settings['email_verification'] ?? $defaults['email_verification'];
$placeholders  = Art_LMS_Settings::get_purchase_email_placeholder_catalog();
$admin_placeholders = Art_LMS_Settings::get_admin_payment_email_placeholder_catalog();
$verification_placeholders = Art_LMS_Settings::get_email_verification_placeholder_catalog();
$current_user  = wp_get_current_user();
$test_email    = $current_user->user_email ? $current_user->user_email : get_option( 'admin_email' );
?>
<p class="description"><?php esc_html_e( 'Настройте отправителя, письмо покупателю и уведомление о новой оплате на вашу почту.', 'art-lms' ); ?></p>

<form method="post" action="options.php" class="art-lms-email-settings-form">
	<?php settings_fields( 'art_lms_email_group' ); ?>

	<div class="art-lms-panel">
		<h2><?php esc_html_e( 'Отправитель', 'art-lms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="art_lms_email_from"><?php esc_html_e( 'Email отправителя', 'art-lms' ); ?></label></th>
				<td>
					<input
						type="email"
						class="regular-text"
						id="art_lms_email_from"
						name="<?php echo esc_attr( $option ); ?>[email_from]"
						value="<?php echo esc_attr( $settings['email_from'] ?? '' ); ?>"
					>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="art_lms_email_from_name"><?php esc_html_e( 'Имя отправителя', 'art-lms' ); ?></label></th>
				<td>
					<input
						type="text"
						class="regular-text"
						id="art_lms_email_from_name"
						name="<?php echo esc_attr( $option ); ?>[email_from_name]"
						value="<?php echo esc_attr( $settings['email_from_name'] ?? '' ); ?>"
					>
					<p class="description"><?php esc_html_e( 'Эти данные используются во всех письмах ART LMS.', 'art-lms' ); ?></p>
				</td>
			</tr>
		</table>
	</div>

	<details class="art-lms-panel art-lms-collapsible-panel">
		<summary class="art-lms-collapsible-panel__summary"><?php esc_html_e( 'Письмо клиенту на почту, после оплаты', 'art-lms' ); ?></summary>
		<div class="art-lms-collapsible-panel__content">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Отправка', 'art-lms' ); ?></th>
				<td>
					<label>
						<input
							type="checkbox"
							name="<?php echo esc_attr( $option ); ?>[purchase][enabled]"
							value="1"
							<?php checked( $purchase['enabled'], 'yes' ); ?>
						>
						<?php esc_html_e( 'Отправлять письмо покупателю после успешной оплаты', 'art-lms' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_purchase_email_subject"><?php esc_html_e( 'Тема письма', 'art-lms' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						class="large-text"
						id="art_lms_purchase_email_subject"
						name="<?php echo esc_attr( $option ); ?>[purchase][subject]"
						value="<?php echo esc_attr( $purchase['subject'] ); ?>"
					>
					<p>
						<button
							type="button"
							class="button art-lms-email-reset"
							data-target="art_lms_purchase_email_subject"
							data-defaults-group="purchase"
							data-reset-key="subject"
						>
							<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
						</button>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_purchase_email_body"><?php esc_html_e( 'Текст письма', 'art-lms' ); ?></label>
				</th>
				<td>
					<textarea
						class="large-text code"
						id="art_lms_purchase_email_body"
						name="<?php echo esc_attr( $option ); ?>[purchase][body]"
						rows="14"
					><?php echo esc_textarea( $purchase['body'] ); ?></textarea>
					<p>
						<button
							type="button"
							class="button art-lms-email-reset"
							data-target="art_lms_purchase_email_body"
							data-defaults-group="purchase"
							data-reset-key="body"
						>
							<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
						</button>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Подстановки', 'art-lms' ); ?></th>
				<td>
					<ul class="art-lms-email-placeholders">
						<?php foreach ( $placeholders as $token => $description ) : ?>
							<li>
								<code><?php echo esc_html( $token ); ?></code>
								<?php echo esc_html( $description ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Проверка', 'art-lms' ); ?></th>
				<td>
					<p class="art-lms-email-actions">
						<button type="button" class="button art-lms-email-preview-button" data-email-type="purchase">
							<?php esc_html_e( 'Предпросмотр', 'art-lms' ); ?>
						</button>
						<button type="button" class="button art-lms-email-test-button" data-email-type="purchase">
							<?php
							printf(
								/* translators: %s: email address */
								esc_html__( 'Отправить тест на %s', 'art-lms' ),
								esc_html( $test_email )
							);
							?>
						</button>
					</p>
					<div class="art-lms-email-preview" id="art-lms-purchase-email-preview" hidden>
						<h3><?php esc_html_e( 'Предпросмотр', 'art-lms' ); ?></h3>
						<p><strong><?php esc_html_e( 'Тема:', 'art-lms' ); ?></strong> <span class="art-lms-email-preview-subject"></span></p>
						<pre class="art-lms-email-preview-body"></pre>
					</div>
					<p class="art-lms-email-feedback" id="art-lms-purchase-email-feedback" aria-live="polite"></p>
				</td>
			</tr>
		</table>
		</div>
	</details>

	<details class="art-lms-panel art-lms-collapsible-panel">
		<summary class="art-lms-collapsible-panel__summary"><?php esc_html_e( 'Письмо на вашу почту о новой оплате', 'art-lms' ); ?></summary>
		<div class="art-lms-collapsible-panel__content">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Отправка', 'art-lms' ); ?></th>
				<td>
					<label>
						<input
							type="checkbox"
							name="<?php echo esc_attr( $option ); ?>[admin_payment][enabled]"
							value="1"
							<?php checked( $admin_payment['enabled'], 'yes' ); ?>
						>
						<?php esc_html_e( 'Отправлять уведомление о новой оплате', 'art-lms' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_admin_payment_recipient"><?php esc_html_e( 'Куда отправлять', 'art-lms' ); ?></label>
				</th>
				<td>
					<input
						type="email"
						class="regular-text"
						id="art_lms_admin_payment_recipient"
						name="<?php echo esc_attr( $option ); ?>[admin_payment][recipient]"
						value="<?php echo esc_attr( $admin_payment['recipient'] ); ?>"
					>
					<p class="description"><?php esc_html_e( 'Обычно это ваш рабочий email.', 'art-lms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_admin_payment_email_subject"><?php esc_html_e( 'Тема письма', 'art-lms' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						class="large-text"
						id="art_lms_admin_payment_email_subject"
						name="<?php echo esc_attr( $option ); ?>[admin_payment][subject]"
						value="<?php echo esc_attr( $admin_payment['subject'] ); ?>"
					>
					<p>
						<button
							type="button"
							class="button art-lms-email-reset"
							data-target="art_lms_admin_payment_email_subject"
							data-defaults-group="admin_payment"
							data-reset-key="subject"
						>
							<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
						</button>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_admin_payment_email_body"><?php esc_html_e( 'Текст письма', 'art-lms' ); ?></label>
				</th>
				<td>
					<textarea
						class="large-text code"
						id="art_lms_admin_payment_email_body"
						name="<?php echo esc_attr( $option ); ?>[admin_payment][body]"
						rows="14"
					><?php echo esc_textarea( $admin_payment['body'] ); ?></textarea>
					<p>
						<button
							type="button"
							class="button art-lms-email-reset"
							data-target="art_lms_admin_payment_email_body"
							data-defaults-group="admin_payment"
							data-reset-key="body"
						>
							<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
						</button>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Подстановки', 'art-lms' ); ?></th>
				<td>
					<ul class="art-lms-email-placeholders">
						<?php foreach ( $admin_placeholders as $token => $description ) : ?>
							<li>
								<code><?php echo esc_html( $token ); ?></code>
								<?php echo esc_html( $description ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Проверка', 'art-lms' ); ?></th>
				<td>
					<p class="art-lms-email-actions">
						<button type="button" class="button art-lms-email-preview-button" data-email-type="admin_payment">
							<?php esc_html_e( 'Предпросмотр', 'art-lms' ); ?>
						</button>
						<button type="button" class="button art-lms-email-test-button" data-email-type="admin_payment">
							<?php esc_html_e( 'Отправить тест', 'art-lms' ); ?>
						</button>
					</p>
					<p class="description"><?php esc_html_e( 'Тест отправится на адрес из поля «Куда отправлять».', 'art-lms' ); ?></p>
					<div class="art-lms-email-preview" id="art-lms-admin-payment-email-preview" hidden>
						<h3><?php esc_html_e( 'Предпросмотр', 'art-lms' ); ?></h3>
						<p><strong><?php esc_html_e( 'Тема:', 'art-lms' ); ?></strong> <span class="art-lms-email-preview-subject"></span></p>
						<pre class="art-lms-email-preview-body"></pre>
					</div>
					<p class="art-lms-email-feedback" id="art-lms-admin-payment-email-feedback" aria-live="polite"></p>
				</td>
			</tr>
		</table>
		</div>
	</details>

	<details class="art-lms-panel art-lms-collapsible-panel">
		<summary class="art-lms-collapsible-panel__summary"><?php esc_html_e( 'Письмо подтверждения email перед оплатой', 'art-lms' ); ?></summary>
		<div class="art-lms-collapsible-panel__content">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Отправка', 'art-lms' ); ?></th>
				<td>
					<label>
						<input
							type="checkbox"
							name="<?php echo esc_attr( $option ); ?>[email_verification][enabled]"
							value="1"
							<?php checked( $email_verification['enabled'], 'yes' ); ?>
						>
						<?php esc_html_e( 'Отправлять письмо подтверждения для новых покупателей', 'art-lms' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Используется, когда в общих настройках выбран режим «С подтверждением email».', 'art-lms' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_email_verification_subject"><?php esc_html_e( 'Тема письма', 'art-lms' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						class="large-text"
						id="art_lms_email_verification_subject"
						name="<?php echo esc_attr( $option ); ?>[email_verification][subject]"
						value="<?php echo esc_attr( $email_verification['subject'] ); ?>"
					>
					<p>
						<button
							type="button"
							class="button art-lms-email-reset"
							data-target="art_lms_email_verification_subject"
							data-defaults-group="email_verification"
							data-reset-key="subject"
						>
							<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
						</button>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_email_verification_body"><?php esc_html_e( 'Текст письма', 'art-lms' ); ?></label>
				</th>
				<td>
					<textarea
						class="large-text code"
						id="art_lms_email_verification_body"
						name="<?php echo esc_attr( $option ); ?>[email_verification][body]"
						rows="12"
					><?php echo esc_textarea( $email_verification['body'] ); ?></textarea>
					<p>
						<button
							type="button"
							class="button art-lms-email-reset"
							data-target="art_lms_email_verification_body"
							data-defaults-group="email_verification"
							data-reset-key="body"
						>
							<?php esc_html_e( 'Сбросить', 'art-lms' ); ?>
						</button>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Подстановки', 'art-lms' ); ?></th>
				<td>
					<ul class="art-lms-email-placeholders">
						<?php foreach ( $verification_placeholders as $token => $description ) : ?>
							<li>
								<code><?php echo esc_html( $token ); ?></code>
								<?php echo esc_html( $description ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
		</table>
		</div>
	</details>

	<?php submit_button(); ?>
</form>
