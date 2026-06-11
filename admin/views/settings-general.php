<?php

/**

 * General settings page.

 *

 * @package Art_LMS

 *

 * @var array $settings General settings.

 */



defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.



$general_option   = Art_LMS_Settings::OPTION_GENERAL;

$verification_mode = Art_LMS_Settings::get_user_registration_verification();

$page_pickers     = array(

	'account' => array(

		'label'       => __( 'Страница личного кабинета', 'art-lms' ),

		'field'       => 'account_page_id',

		'selected'    => (int) ( $settings['account_page_id'] ?? 0 ),

		'description' => Art_LMS_Pages::get_manual_setup_hint( Art_LMS_Pages::TYPE_ACCOUNT ),

	),

	'success' => array(

		'label'       => __( 'Страница успешной оплаты', 'art-lms' ),

		'field'       => 'success_page_id',

		'selected'    => (int) ( $settings['success_page_id'] ?? 0 ),

		'description' => Art_LMS_Pages::get_manual_setup_hint( Art_LMS_Pages::TYPE_SUCCESS ),

	),

);

?>

<form method="post" action="options.php" class="art-lms-settings-general-page">

	<?php settings_fields( 'art_lms_general_group' ); ?>



	<div class="art-lms-panel">

		<h2><?php esc_html_e( 'Страницы', 'art-lms' ); ?></h2>

		<p class="description"><?php esc_html_e( 'Выберите существующие страницы или создайте их из шаблона ART LMS в один клик.', 'art-lms' ); ?></p>

		<table class="form-table" role="presentation">

			<?php foreach ( $page_pickers as $page_type => $picker ) : ?>

				<?php

				$view_url = $picker['selected'] ? get_permalink( $picker['selected'] ) : '';

				?>

				<tr>

					<th scope="row">

						<span class="art-lms-page-picker__label-wrap">

							<label for="<?php echo esc_attr( $picker['field'] ); ?>">

								<?php echo esc_html( $picker['label'] ); ?>

							</label>

							<a

								href="<?php echo esc_url( $view_url ? $view_url : '' ); ?>"

								class="art-lms-page-picker__view<?php echo esc_attr( $view_url ? '' : ' hidden' ); ?>"

								id="<?php echo esc_attr( $picker['field'] ); ?>_view"

								target="_blank"

								rel="noopener noreferrer"

							>

								<?php esc_html_e( 'Перейти', 'art-lms' ); ?>

							</a>

						</span>

					</th>

					<td>

						<div class="art-lms-page-picker" data-page-type="<?php echo esc_attr( $page_type ); ?>">

							<?php

							wp_dropdown_pages(

								array(

									'name'              => esc_attr( $general_option . '[' . $picker['field'] . ']' ),

									'id'                => esc_attr( $picker['field'] ),

									'class'             => 'art-lms-page-picker__select',

									'selected'          => (int) $picker['selected'],

									'show_option_none'  => esc_html__( '— Выберите страницу —', 'art-lms' ),

									'option_none_value' => 0,

								)

							);

							?>

							<button

								type="button"

								class="button art-lms-page-picker__create"

								data-page-type="<?php echo esc_attr( $page_type ); ?>"

							>

								<?php esc_html_e( 'Быстрое создание', 'art-lms' ); ?>

							</button>

						</div>

						<p class="description"><?php echo esc_html( $picker['description'] ); ?></p>

						<p class="art-lms-page-picker__status" id="<?php echo esc_attr( $picker['field'] ); ?>_status" aria-live="polite"></p>

					</td>

				</tr>

			<?php endforeach; ?>

		</table>

	</div>



	<div class="art-lms-panel">

		<h2><?php esc_html_e( 'Поддержка', 'art-lms' ); ?></h2>

		<table class="form-table" role="presentation">

			<tr>

				<th scope="row">

					<label for="support_email"><?php esc_html_e( 'Email поддержки', 'art-lms' ); ?></label>

				</th>

				<td>

					<input

						type="email"

						class="regular-text"

						name="<?php echo esc_attr( $general_option ); ?>[support_email]"

						id="support_email"

						value="<?php echo esc_attr( $settings['support_email'] ?? '' ); ?>"

						placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"

					>

					<p class="description">

						<?php esc_html_e( 'Укажите email, на который покупатели смогут написать, если при оплате возникнут трудности. Адрес показывается на странице подтверждения оплаты. Если поле пустое, используется email администратора сайта.', 'art-lms' ); ?>

					</p>

				</td>

			</tr>

		</table>

	</div>



	<div class="art-lms-panel">

		<h2><?php esc_html_e( 'Поведение при покупке', 'art-lms' ); ?></h2>

		<table class="form-table" role="presentation">

			<tr>

				<th scope="row"><?php esc_html_e( 'Аккаунт покупателя', 'art-lms' ); ?></th>

				<td>

					<label>

						<input type="checkbox" name="<?php echo esc_attr( $general_option ); ?>[create_user_before_payment]" value="1" <?php checked( $settings['create_user_before_payment'], 'yes' ); ?>>

						<?php esc_html_e( 'Создавать пользователя до оплаты', 'art-lms' ); ?>

					</label>

					<p class="description">

						<?php esc_html_e( 'Если отключено, WordPress-пользователь будет создан только после успешной оплаты.', 'art-lms' ); ?>

					</p>

					<p class="description">

						<?php esc_html_e( 'Новым покупателям назначается роль «Покупатель ART LMS» (art_lms_customer) без доступа в wp-admin. Не назначайте покупателям роли Автор, Редактор или Администратор.', 'art-lms' ); ?>

					</p>

				</td>

			</tr>

			<tr>

				<th scope="row"><?php esc_html_e( 'Подтверждение email', 'art-lms' ); ?></th>

				<td>

					<fieldset>

						<label>

							<input

								type="radio"

								name="<?php echo esc_attr( $general_option ); ?>[user_registration_verification]"

								value="none"

								<?php checked( $verification_mode, 'none' ); ?>

							>

							<?php esc_html_e( 'Без подтверждения — сразу создавать аккаунт и переходить к оплате', 'art-lms' ); ?>

						</label><br>

						<label>

							<input

								type="radio"

								name="<?php echo esc_attr( $general_option ); ?>[user_registration_verification]"

								value="email"

								<?php checked( $verification_mode, 'email' ); ?>

							>

							<?php esc_html_e( 'С подтверждением email — отправлять письмо со ссылкой перед оплатой', 'art-lms' ); ?>

						</label>

					</fieldset>

					<p class="description">

						<?php esc_html_e( 'Применяется только к новым email, которых ещё нет в WordPress. Текст письма настраивается в разделе «Письма».', 'art-lms' ); ?>

					</p>

				</td>

			</tr>

			<tr>

				<th scope="row"><?php esc_html_e( 'Автовход', 'art-lms' ); ?></th>

				<td>

					<label>

						<input type="checkbox" name="<?php echo esc_attr( $general_option ); ?>[auto_login_after_register]" value="1" <?php checked( $settings['auto_login_after_register'], 'yes' ); ?>>

						<?php esc_html_e( 'Автоматически входить после регистрации', 'art-lms' ); ?>

					</label>

					<p class="description">

						<?php esc_html_e( 'Работает при создании аккаунта на checkout или после подтверждения email.', 'art-lms' ); ?>

					</p>

				</td>

			</tr>

		</table>

	</div>



	<div class="art-lms-panel">

		<h2><?php esc_html_e( 'Удаление плагина', 'art-lms' ); ?></h2>

		<table class="form-table" role="presentation">

			<tr>

				<th scope="row"><?php esc_html_e( 'Данные при удалении', 'art-lms' ); ?></th>

				<td>

					<label>

						<input

							type="checkbox"

							name="<?php echo esc_attr( $general_option ); ?>[delete_data_on_uninstall]"

							value="1"

							<?php checked( Art_LMS_Settings::delete_data_on_uninstall_enabled() ); ?>

						>

						<?php esc_html_e( 'Удалить все данные плагина при удалении ART LMS', 'art-lms' ); ?>

					</label>

					<p class="description">

						<?php esc_html_e( 'Если включено, при удалении плагина через экран «Плагины» будут безвозвратно удалены заказы, доступы, материалы, кнопки оплаты, настройки и служебные данные ART LMS. Страницы WordPress, выбранные в настройках, не удаляются.', 'art-lms' ); ?>

					</p>

				</td>

			</tr>

		</table>

	</div>



	<?php submit_button(); ?>

</form>


