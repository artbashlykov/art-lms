<?php

/**

 * Single payment gateway settings page.

 *

 * @package Art_LMS

 *

 * @var array                       $settings   Payment settings.

 * @var string                      $gateway_id Gateway ID.

 * @var Art_LMS_Payment_Gateway     $gateway    Gateway instance.

 */



defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.



$option           = Art_LMS_Settings::OPTION_PAYMENT;

$meta             = $gateway->get_meta();

$gateway_settings = $settings['gateways'][ $gateway_id ] ?? $gateway->get_default_settings();

$back_url = Art_LMS_Admin_Settings::get_tab_url( Art_LMS_Admin_Settings::PAGE_SETTINGS, Art_LMS_Admin_Settings::TAB_PAYMENTS );

?>

<p class="art-lms-payment-gateway-back">

	<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Вернуться ко всем способам оплаты', 'art-lms' ); ?></a>

</p>

<form method="post" action="options.php" class="art-lms-payment-gateway-settings-form">

	<?php settings_fields( 'art_lms_payment_group' ); ?>



	<div class="art-lms-panel">

		<h2><?php esc_html_e( 'Способ оплаты', 'art-lms' ); ?></h2>

		<table class="form-table" role="presentation">

			<tr>

				<th scope="row"><?php esc_html_e( 'Внутреннее название', 'art-lms' ); ?></th>

				<td>

					<strong><?php echo esc_html( $meta['title'] ); ?></strong>

					<p class="description"><?php echo esc_html( $gateway->get_admin_description() ); ?></p>
					<?php $gateway->render_partner_signup_prompt( $gateway_settings ); ?>

				</td>

			</tr>

			<?php $gateway->render_gateway_status_control( $option, $gateway_settings, 'table' ); ?>

			<tr>

				<th scope="row">

					<label for="art-lms-gateway-display-name"><?php esc_html_e( 'Внешнее название', 'art-lms' ); ?></label>

				</th>

				<td>

					<input

						type="text"

						id="art-lms-gateway-display-name"

						name="<?php echo esc_attr( $option ); ?>[gateways][<?php echo esc_attr( $gateway_id ); ?>][display_name]"

						value="<?php echo esc_attr( $gateway_settings['display_name'] ?? '' ); ?>"

						class="regular-text"

						placeholder="<?php echo esc_attr( $meta['title'] ); ?>"

					>

					<p class="description">

						<?php esc_html_e( 'Отображается покупателю в выпадающем списке «Способ оплаты» на странице checkout.', 'art-lms' ); ?>

					</p>

				</td>

			</tr>

		</table>

	</div>

	<?php $gateway->render_documentation_panel(); ?>

	<div class="art-lms-panel">

		<h2><?php esc_html_e( 'Настройки шлюза', 'art-lms' ); ?></h2>

		<?php $gateway->render_admin_settings( $option, $gateway_settings ); ?>

	</div>

	<?php if ( method_exists( $gateway, 'render_receipt_admin_settings' ) ) : ?>
		<?php $gateway->render_receipt_admin_settings( $option, $gateway_settings ); ?>
	<?php endif; ?>

	<?php submit_button(); ?>

</form>


