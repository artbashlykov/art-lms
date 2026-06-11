<?php
/**
 * Checkout page content.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.NonceVerification.Recommended -- Template variables scoped to this view.

$button_id     = isset( $_GET[ Art_LMS_Payment_Buttons::CHECKOUT_QUERY_ARG ] )
	? absint( wp_unslash( $_GET[ Art_LMS_Payment_Buttons::CHECKOUT_QUERY_ARG ] ) )
	: 0;
$is_preview    = Art_LMS_Checkout::is_design_preview_request();
$form_fields   = Art_LMS_Settings::get_checkout_form_fields();
$consents      = Art_LMS_Settings::get_checkout_consents();
$button        = $button_id ? get_post( $button_id ) : null;
$button_error  = $is_preview ? '' : Art_LMS_Payment_Buttons::get_checkout_unavailable_reason( $button_id );
$button_valid  = $is_preview || '' === $button_error;
$button_meta   = $button_valid && ! $is_preview ? Art_LMS_Payment_Buttons::get_meta( $button_id ) : array();
$button_class  = 'art-lms-button ' . Art_LMS_Settings::get_checkout_button_size_class();
$actions_class = Art_LMS_Settings::get_checkout_button_actions_class();
?>
<div class="art-lms-checkout">
	<h1><?php echo esc_html( Art_LMS_Settings::get_checkout_form_title() ); ?></h1>

	<?php if ( ! $button_valid ) : ?>
		<p class="art-lms-notice art-lms-notice--warning">
			<?php
			switch ( $button_error ) {
				case 'disabled':
					esc_html_e( 'Платежная кнопка выключена. Оформление заказа временно недоступно.', 'art-lms' );
					break;
				case 'archived':
					esc_html_e( 'Платежная кнопка находится в архиве. Оформление заказа недоступно.', 'art-lms' );
					break;
				case 'not_published':
					esc_html_e( 'Платежная кнопка ещё не опубликована. Опубликуйте её в админке и попробуйте снова.', 'art-lms' );
					break;
				case 'not_found':
					esc_html_e( 'Платежная кнопка не найдена. Проверьте ссылку или выберите кнопку на сайте.', 'art-lms' );
					break;
				default:
					esc_html_e( 'Не выбрана платежная кнопка. Перейдите на эту страницу через кнопку оплаты на сайте.', 'art-lms' );
					break;
			}
			?>
		</p>
	<?php else : ?>
		<?php
		if ( $is_preview ) {
			$product_name = __( 'Пример платежной кнопки', 'art-lms' );
			$access_label = '';
			$has_summary  = true;
			$preview_price = __( '1 990 ₽', 'art-lms' );
		} else {
			$product_name = Art_LMS_Payment_Buttons::get_product_name( $button_id );
			$access_label = Art_LMS_Payment_Buttons::get_checkout_access_label( (int) ( $button_meta['access_days'] ?? 0 ) );
			$has_summary  = $product_name || ! empty( $button_meta['price'] ) || ! empty( $button_meta['compare_price'] ) || '' !== $access_label;
			$preview_price = '';
		}
		?>
		<?php if ( $has_summary ) : ?>
			<p class="art-lms-checkout__summary">
				<?php if ( $product_name ) : ?>
					<strong><?php echo esc_html( $product_name ); ?></strong>
				<?php endif; ?>
				<span class="art-lms-checkout__prices">
					<?php if ( ! $is_preview && ! empty( $button_meta['compare_price'] ) ) : ?>
						<span class="art-lms-checkout__compare"><?php echo esc_html( Art_LMS_Payment_Buttons::format_price( $button_meta['compare_price'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( $is_preview ) : ?>
						<span class="art-lms-checkout__price"><?php echo esc_html( $preview_price ); ?></span>
					<?php elseif ( ! empty( $button_meta['price'] ) ) : ?>
						<span class="art-lms-checkout__price"><?php echo esc_html( Art_LMS_Payment_Buttons::format_price( $button_meta['price'] ) ); ?></span>
					<?php endif; ?>
				</span>
				<?php if ( ! empty( $access_label ) ) : ?>
					<span class="art-lms-checkout__access"><?php echo esc_html( $access_label ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php
		$buyer_defaults = array();

		if ( ! $is_preview && is_user_logged_in() ) {
			$buyer_profile = Art_LMS_User_Registration::get_buyer_details_for_form( wp_get_current_user()->user_email );

			$buyer_defaults = array(
				'full_name' => sanitize_text_field( (string) ( $buyer_profile['name'] ?? '' ) ),
				'email'     => sanitize_email( (string) ( $buyer_profile['email'] ?? '' ) ),
				'phone'     => sanitize_text_field( (string) ( $buyer_profile['phone'] ?? '' ) ),
			);
		}
		?>

		<form class="art-lms-checkout-form" method="post" action="#" novalidate>
			<?php foreach ( $form_fields as $field ) : ?>
				<?php
				$input_name = Art_LMS_Settings::get_checkout_field_post_key( $field['key'] );
				$input_id   = 'art-lms-field-' . sanitize_html_class( $field['key'] );
				$input_value = $buyer_defaults[ $field['key'] ] ?? '';
				?>
				<p class="art-lms-checkout-form__field">
					<label for="<?php echo esc_attr( $input_id ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
						<?php if ( ! empty( $field['required'] ) ) : ?>
							<span class="art-lms-required">*</span>
						<?php endif; ?>
					</label>
					<input
						type="<?php echo esc_attr( $field['input'] ); ?>"
						id="<?php echo esc_attr( $input_id ); ?>"
						name="<?php echo esc_attr( $input_name ); ?>"
						value="<?php echo esc_attr( $input_value ); ?>"
						<?php if ( ! empty( $field['required'] ) ) : ?>
							required
						<?php endif; ?>
					>
				</p>
			<?php endforeach; ?>

			<?php if ( ! empty( $consents['items'] ) ) : ?>
				<div class="art-lms-checkout-form__consents">
					<?php if ( ! empty( $consents['title'] ) ) : ?>
						<p class="art-lms-checkout-form__consents-title"><?php echo esc_html( $consents['title'] ); ?></p>
					<?php endif; ?>
					<?php foreach ( $consents['items'] as $consent ) : ?>
						<?php
						$consent_id    = 'art-lms-consent-' . sanitize_html_class( $consent['key'] );
						$consent_label = Art_LMS_Settings::format_checkout_consent_label( $consent );
						?>
						<?php if ( $consent_label ) : ?>
							<p class="art-lms-checkout-form__consent">
								<label for="<?php echo esc_attr( $consent_id ); ?>">
									<input
										type="checkbox"
										id="<?php echo esc_attr( $consent_id ); ?>"
										name="<?php echo esc_attr( $consent['post_key'] ); ?>"
										value="1"
										<?php if ( ! empty( $consent['required'] ) ) : ?>
											required
										<?php endif; ?>
									>
									<span><?php echo wp_kses_post( $consent_label ); ?></span>
								</label>
							</p>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php
			$payment_methods   = Art_LMS_Settings::get_checkout_payment_methods();
			$default_gateway   = Art_LMS_Settings::get_default_checkout_gateway();
			$show_payment_pick = ! $is_preview && count( $payment_methods ) > 0;
			?>
			<?php if ( $show_payment_pick ) : ?>
				<p class="art-lms-checkout-form__field art-lms-checkout-form__field--payment">
					<label for="art-lms-payment-gateway">
						<?php esc_html_e( 'Способ оплаты', 'art-lms' ); ?>
						<span class="art-lms-required">*</span>
					</label>
					<select id="art-lms-payment-gateway" name="payment_gateway" required>
						<?php if ( '' === $default_gateway ) : ?>
							<option value="" selected disabled><?php esc_html_e( 'Выберите способ', 'art-lms' ); ?></option>
						<?php endif; ?>
						<?php foreach ( $payment_methods as $method ) : ?>
							<option value="<?php echo esc_attr( $method['id'] ); ?>" <?php selected( $default_gateway, $method['id'] ); ?>>
								<?php echo esc_html( $method['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>

			<p class="<?php echo esc_attr( $actions_class ); ?>">
				<button type="submit" class="<?php echo esc_attr( $button_class ); ?>"<?php if ( $is_preview ) : ?> disabled<?php endif; ?>>
					<?php echo esc_html( Art_LMS_Settings::get_checkout_button_text() ); ?>
				</button>
			</p>
			<div class="art-lms-checkout-form__feedback" role="alert" aria-live="polite" hidden></div>
		</form>
	<?php endif; ?>
</div>
