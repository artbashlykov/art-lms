<?php

/**

 * Payment settings list page.

 *

 * @package Art_LMS

 *

 * @var array $settings Payment settings.

 */



defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.



$option          = Art_LMS_Settings::OPTION_PAYMENT;

$default_gateway = $settings['default_gateway'] ?? $settings['active_gateway'] ?? '';

$ordered_ids     = Art_LMS_Settings::get_ordered_gateway_ids();

?>

<form method="post" action="options.php" class="art-lms-payment-settings-form">

	<?php settings_fields( 'art_lms_payment_group' ); ?>



	<div class="art-lms-panel">

		<h2><?php esc_html_e( 'Способы приёма платежей', 'art-lms' ); ?></h2>

		<p class="description">

			<?php esc_html_e( 'Перетащите строки, чтобы изменить порядок способов оплаты в форме checkout. Настройки каждого способа — по кнопке «Изменить».', 'art-lms' ); ?>

		</p>



		<ul class="art-lms-payment-gateway-list" id="art-lms-payment-gateway-list">

			<?php foreach ( $ordered_ids as $gateway_id ) : ?>

				<?php

				$gateway_instance = Art_LMS_Payment_Gateway_Registry::get( $gateway_id );



				if ( ! $gateway_instance ) {

					continue;

				}



				$meta             = $gateway_instance->get_meta();

				$gateway_settings = $settings['gateways'][ $gateway_id ] ?? $gateway_instance->get_default_settings();

				$edit_url         = Art_LMS_Admin_Settings::get_gateway_settings_url( $gateway_id );

				?>

				<li class="art-lms-payment-gateway-list__item" data-gateway-id="<?php echo esc_attr( $gateway_id ); ?>">

					<input type="hidden" name="<?php echo esc_attr( $option ); ?>[gateway_order][]" value="<?php echo esc_attr( $gateway_id ); ?>">

					<span class="art-lms-payment-gateway-list__handle" aria-hidden="true" title="<?php esc_attr_e( 'Перетащите для изменения порядка', 'art-lms' ); ?>">⋮⋮</span>

					<div class="art-lms-payment-gateway-list__main">

						<strong class="art-lms-payment-gateway-list__title"><?php echo esc_html( $meta['title'] ); ?></strong>

						<p class="art-lms-payment-gateway-list__description description">

							<?php echo esc_html( $gateway_instance->get_admin_description() ); ?>

						</p>

						<?php $gateway_instance->render_partner_signup_prompt( $gateway_settings, 'list' ); ?>

					</div>

					<div class="art-lms-payment-gateway-list__status">

						<?php $gateway_instance->render_gateway_status_control( $option, $gateway_settings, 'list' ); ?>

					</div>

					<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-secondary">

						<?php esc_html_e( 'Изменить', 'art-lms' ); ?>

					</a>

				</li>

			<?php endforeach; ?>

		</ul>

	</div>



	<div class="art-lms-panel">

		<h2><?php esc_html_e( 'Шлюз по умолчанию', 'art-lms' ); ?></h2>

		<p class="description">

			<?php esc_html_e( 'Выбранный способ будет автоматически подставлен в выпадающий список на странице checkout. Покупатель сможет выбрать другой способ, если нужно. Если не выбрать — в списке будет подсказка «Выберите способ».', 'art-lms' ); ?>

		</p>

		<fieldset class="art-lms-payment-default-gateway">

			<label class="art-lms-payment-default-gateway__option">

				<input

					type="radio"

					name="<?php echo esc_attr( $option ); ?>[default_gateway]"

					value=""

					<?php checked( $default_gateway, '' ); ?>

				>

				<?php esc_html_e( 'Не выбран', 'art-lms' ); ?>

			</label>

			<?php foreach ( $ordered_ids as $gateway_id ) : ?>

				<?php

				$gateway_instance = Art_LMS_Payment_Gateway_Registry::get( $gateway_id );



				if ( ! $gateway_instance ) {

					continue;

				}



				$meta = $gateway_instance->get_meta();

				?>

				<label class="art-lms-payment-default-gateway__option">

					<input

						type="radio"

						name="<?php echo esc_attr( $option ); ?>[default_gateway]"

						value="<?php echo esc_attr( $gateway_id ); ?>"

						<?php checked( $default_gateway, $gateway_id ); ?>

					>

					<?php echo esc_html( $meta['title'] ); ?>

				</label>

			<?php endforeach; ?>

		</fieldset>

	</div>



	<?php submit_button(); ?>

</form>


