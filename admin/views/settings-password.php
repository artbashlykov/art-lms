<?php
/**
 * Custom password page settings (texts + slug; design from login).
 *
 * @package Art_LMS
 *
 * @var array $settings Password settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$option            = Art_LMS_Settings::OPTION_PASSWORD;
$slug              = Art_LMS_Settings::get_password_slug();
$is_enabled        = Art_LMS_Settings::is_custom_login_enabled();
$home_url          = home_url( '/' );
$preview_url       = $is_enabled ? Art_LMS_Custom_Password::get_lostpassword_url() : '';
$lost              = Art_LMS_Settings::get_password_lost_form();
$reset             = Art_LMS_Settings::get_password_reset_form();
$messages          = Art_LMS_Settings::get_password_messages();
$lost_title_on     = 'yes' === ( $lost['title_enabled'] ?? 'yes' );
$lost_subtitle_on  = 'yes' === ( $lost['subtitle_enabled'] ?? 'yes' );
$reset_title_on    = 'yes' === ( $reset['title_enabled'] ?? 'yes' );
$reset_subtitle_on = 'yes' === ( $reset['subtitle_enabled'] ?? 'yes' );
$login_tab_url     = Art_LMS_Admin_Settings::get_tab_url(
	Art_LMS_Admin_Settings::PAGE_SETTINGS,
	Art_LMS_Admin_Settings::TAB_LOGIN
);
?>
<div class="art-lms-settings-password-page">
	<form method="post" action="options.php" class="art-lms-password-settings-form">
		<?php settings_fields( 'art_lms_password_group' ); ?>

		<div class="art-lms-panel">
			<h2><?php esc_html_e( 'О разделе', 'art-lms' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Своя страница сброса и установки пароля включается автоматически вместе со страницей входа. Оформление (цвета, шрифты, кнопка) берётся из настроек страницы входа. Доступна всем посетителям сайта.', 'art-lms' ); ?>
			</p>
			<?php if ( ! $is_enabled ) : ?>
				<p class="description">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: link to login settings tab */
							__( 'Сейчас страница входа выключена — кастомная страница пароля не используется. Включите её во вкладке %s.', 'art-lms' ),
							'<a href="' . esc_url( $login_tab_url ) . '">' . esc_html__( 'Страница входа', 'art-lms' ) . '</a>'
						),
						array(
							'a' => array(
								'href' => true,
							),
						)
					);
					?>
				</p>
			<?php else : ?>
				<p class="description">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: password page URL */
							__( 'Текущий адрес запроса сброса: %s', 'art-lms' ),
							'<a href="' . esc_url( $preview_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $preview_url ) . '</a>'
						),
						array(
							'a' => array(
								'href'   => true,
								'target' => true,
								'rel'    => true,
							),
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<div class="art-lms-panel">
			<h2><?php esc_html_e( 'Адрес страницы пароля', 'art-lms' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="art_lms_password_slug"><?php esc_html_e( 'URL страницы', 'art-lms' ); ?></label>
					</th>
					<td>
						<div class="art-lms-login-slug-field">
							<span class="art-lms-login-slug-field__prefix"><?php echo esc_html( untrailingslashit( $home_url ) ); ?>/</span>
							<input
								type="text"
								class="regular-text"
								id="art_lms_password_slug"
								name="<?php echo esc_attr( $option ); ?>[slug]"
								value="<?php echo esc_attr( $slug ); ?>"
								autocomplete="off"
								spellcheck="false"
								pattern="[a-z0-9\-]+"
							>
							<span class="art-lms-login-slug-field__suffix">/</span>
						</div>
						<p class="description">
							<?php esc_html_e( 'Латиница, цифры и дефис. Не должен совпадать с адресом страницы входа или оплаты.', 'art-lms' ); ?>
						</p>
						<p class="description">
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: link to login design settings */
									__( 'Цвета и шрифты — из вкладки %s (блок «Дизайн формы»).', 'art-lms' ),
									'<a href="' . esc_url( $login_tab_url ) . '">' . esc_html__( 'Страница входа', 'art-lms' ) . '</a>'
								),
								array(
									'a' => array(
										'href' => true,
									),
								)
							);
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<details class="art-lms-panel art-lms-collapsible-panel" open>
			<summary class="art-lms-collapsible-panel__summary"><?php esc_html_e( 'Форма «Забыли пароль»', 'art-lms' ); ?></summary>
			<div class="art-lms-collapsible-panel__content">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Заголовок', 'art-lms' ); ?></th>
						<td>
							<label>
								<input type="hidden" name="<?php echo esc_attr( $option ); ?>[lost][title_enabled]" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[lost][title_enabled]" value="1" <?php checked( $lost_title_on ); ?>>
								<?php esc_html_e( 'Показывать заголовок', 'art-lms' ); ?>
							</label>
							<p>
								<input type="text" class="large-text" name="<?php echo esc_attr( $option ); ?>[lost][title_text]" value="<?php echo esc_attr( $lost['title_text'] ); ?>">
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Подзаголовок', 'art-lms' ); ?></th>
						<td>
							<label>
								<input type="hidden" name="<?php echo esc_attr( $option ); ?>[lost][subtitle_enabled]" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[lost][subtitle_enabled]" value="1" <?php checked( $lost_subtitle_on ); ?>>
								<?php esc_html_e( 'Показывать подзаголовок', 'art-lms' ); ?>
							</label>
							<p>
								<input type="text" class="large-text" name="<?php echo esc_attr( $option ); ?>[lost][subtitle_text]" value="<?php echo esc_attr( $lost['subtitle_text'] ); ?>">
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="art_lms_password_lost_email_label"><?php esc_html_e( 'Подпись поля', 'art-lms' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="art_lms_password_lost_email_label" name="<?php echo esc_attr( $option ); ?>[lost][email_label]" value="<?php echo esc_attr( $lost['email_label'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="art_lms_password_lost_button"><?php esc_html_e( 'Текст кнопки', 'art-lms' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="art_lms_password_lost_button" name="<?php echo esc_attr( $option ); ?>[lost][button_text]" value="<?php echo esc_attr( $lost['button_text'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="art_lms_password_lost_login_link"><?php esc_html_e( 'Ссылка на вход', 'art-lms' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="art_lms_password_lost_login_link" name="<?php echo esc_attr( $option ); ?>[lost][login_link_text]" value="<?php echo esc_attr( $lost['login_link_text'] ); ?>">
						</td>
					</tr>
				</table>
			</div>
		</details>

		<details class="art-lms-panel art-lms-collapsible-panel">
			<summary class="art-lms-collapsible-panel__summary"><?php esc_html_e( 'Форма «Новый пароль»', 'art-lms' ); ?></summary>
			<div class="art-lms-collapsible-panel__content">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Заголовок', 'art-lms' ); ?></th>
						<td>
							<label>
								<input type="hidden" name="<?php echo esc_attr( $option ); ?>[reset][title_enabled]" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[reset][title_enabled]" value="1" <?php checked( $reset_title_on ); ?>>
								<?php esc_html_e( 'Показывать заголовок', 'art-lms' ); ?>
							</label>
							<p>
								<input type="text" class="large-text" name="<?php echo esc_attr( $option ); ?>[reset][title_text]" value="<?php echo esc_attr( $reset['title_text'] ); ?>">
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Подзаголовок', 'art-lms' ); ?></th>
						<td>
							<label>
								<input type="hidden" name="<?php echo esc_attr( $option ); ?>[reset][subtitle_enabled]" value="0">
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[reset][subtitle_enabled]" value="1" <?php checked( $reset_subtitle_on ); ?>>
								<?php esc_html_e( 'Показывать подзаголовок', 'art-lms' ); ?>
							</label>
							<p>
								<input type="text" class="large-text" name="<?php echo esc_attr( $option ); ?>[reset][subtitle_text]" value="<?php echo esc_attr( $reset['subtitle_text'] ); ?>">
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="art_lms_password_reset_pass_label"><?php esc_html_e( 'Подпись «Новый пароль»', 'art-lms' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="art_lms_password_reset_pass_label" name="<?php echo esc_attr( $option ); ?>[reset][password_label]" value="<?php echo esc_attr( $reset['password_label'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="art_lms_password_reset_confirm_label"><?php esc_html_e( 'Подпись «Повторите пароль»', 'art-lms' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="art_lms_password_reset_confirm_label" name="<?php echo esc_attr( $option ); ?>[reset][password_confirm_label]" value="<?php echo esc_attr( $reset['password_confirm_label'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="art_lms_password_reset_button"><?php esc_html_e( 'Текст кнопки', 'art-lms' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="art_lms_password_reset_button" name="<?php echo esc_attr( $option ); ?>[reset][button_text]" value="<?php echo esc_attr( $reset['button_text'] ); ?>">
						</td>
					</tr>
				</table>
			</div>
		</details>

		<details class="art-lms-panel art-lms-collapsible-panel">
			<summary class="art-lms-collapsible-panel__summary"><?php esc_html_e( 'Сообщения', 'art-lms' ); ?></summary>
			<div class="art-lms-collapsible-panel__content">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="art_lms_password_msg_checkemail"><?php esc_html_e( 'После запроса ссылки', 'art-lms' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" id="art_lms_password_msg_checkemail" name="<?php echo esc_attr( $option ); ?>[messages][checkemail]"><?php echo esc_textarea( $messages['checkemail'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="art_lms_password_msg_changed"><?php esc_html_e( 'Пароль изменён', 'art-lms' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" id="art_lms_password_msg_changed" name="<?php echo esc_attr( $option ); ?>[messages][password_changed]"><?php echo esc_textarea( $messages['password_changed'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="art_lms_password_msg_invalid"><?php esc_html_e( 'Недействительная ссылка', 'art-lms' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" id="art_lms_password_msg_invalid" name="<?php echo esc_attr( $option ); ?>[messages][invalid_key]"><?php echo esc_textarea( $messages['invalid_key'] ); ?></textarea>
						</td>
					</tr>
				</table>
			</div>
		</details>

		<?php submit_button( __( 'Сохранить изменения', 'art-lms' ) ); ?>
	</form>
</div>
