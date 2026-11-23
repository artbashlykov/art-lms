<?php
/**
 * Payment button links sidebar meta box.
 *
 * @package Art_LMS
 *
 * @var WP_Post $post
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$button_id    = (int) $post->ID;
$shortcode    = Art_LMS_Payment_Buttons::get_shortcode( $button_id );
$is_published = Art_LMS_Payment_Buttons::is_published( $button_id );
$checkout_url = $is_published ? Art_LMS_Payment_Buttons::get_checkout_link( $button_id ) : '';
$max_bot_url  = $is_published ? Art_LMS_Payment_Buttons::get_max_bot_checkout_link( $button_id ) : '';
?>
<div class="art-lms-payment-button-links">
	<?php if ( ! $button_id ) : ?>
		<p class="description">
			<?php esc_html_e( 'Сохраните кнопку, чтобы получить шорткод и ссылку на checkout.', 'art-lms' ); ?>
		</p>
	<?php else : ?>
		<div class="art-lms-copy-field">
			<p class="art-lms-copy-field__label"><?php esc_html_e( 'Шорткод', 'art-lms' ); ?></p>
			<div class="art-lms-copy-field__row">
				<code
					class="art-lms-copy-field__value art-lms-shortcode-select"
					id="art-lms-payment-button-shortcode"
					tabindex="0"
					role="textbox"
				><?php echo esc_html( $shortcode ); ?></code>
				<button
					type="button"
					class="button art-lms-copy-button"
					data-copy-target="#art-lms-payment-button-shortcode"
					data-copy-value="<?php echo esc_attr( $shortcode ); ?>"
					aria-label="<?php esc_attr_e( 'Скопировать шорткод', 'art-lms' ); ?>"
					title="<?php esc_attr_e( 'Скопировать', 'art-lms' ); ?>"
				>
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
				</button>
			</div>
		</div>

		<?php if ( $is_published ) : ?>
			<div class="art-lms-copy-field">
				<p class="art-lms-copy-field__label"><?php esc_html_e( 'Ссылка на checkout', 'art-lms' ); ?></p>
				<div class="art-lms-copy-field__row">
					<?php if ( $checkout_url ) : ?>
						<a
							class="art-lms-copy-field__value art-lms-copy-field__link"
							id="art-lms-payment-button-checkout-url"
							href="<?php echo esc_url( $checkout_url ); ?>"
							target="_blank"
							rel="noopener noreferrer"
						><?php echo esc_html( $checkout_url ); ?></a>
					<?php else : ?>
						<span class="art-lms-copy-field__value" id="art-lms-payment-button-checkout-url">
							<?php esc_html_e( 'Checkout не настроен в разделе «Настройки формы».', 'art-lms' ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $checkout_url ) : ?>
						<button
							type="button"
							class="button art-lms-copy-button"
							data-copy-target="#art-lms-payment-button-checkout-url"
							data-copy-value="<?php echo esc_attr( $checkout_url ); ?>"
							data-copy-mode="text"
							aria-label="<?php esc_attr_e( 'Скопировать ссылку', 'art-lms' ); ?>"
							title="<?php esc_attr_e( 'Скопировать', 'art-lms' ); ?>"
						>
							<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $max_bot_url ) : ?>
				<div class="art-lms-copy-field">
					<p class="art-lms-copy-field__label"><?php esc_html_e( 'Max Bot', 'art-lms' ); ?></p>
					<div class="art-lms-copy-field__row">
						<code
							class="art-lms-copy-field__value art-lms-shortcode-select"
							id="art-lms-payment-button-max-bot-url"
							tabindex="0"
							role="textbox"
						><?php echo esc_html( $max_bot_url ); ?></code>
						<button
							type="button"
							class="button art-lms-copy-button"
							data-copy-target="#art-lms-payment-button-max-bot-url"
							data-copy-value="<?php echo esc_attr( $max_bot_url ); ?>"
							aria-label="<?php esc_attr_e( 'Скопировать ссылку Max Bot', 'art-lms' ); ?>"
							title="<?php esc_attr_e( 'Скопировать', 'art-lms' ); ?>"
						>
							<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
						</button>
					</div>
					<p class="description art-lms-payment-button-links__mart-hint">
						<?php esc_html_e( 'Готовая ссылка для воронки MAX: {user_id} подставится ID клиента.', 'art-lms' ); ?>
					</p>
				</div>
			<?php elseif ( $checkout_url ) : ?>
				<p class="description art-lms-payment-button-links__mart-hint">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: query fragment with placeholder */
							__( 'Для MAX-бота добавьте в конец ссылки: %s — подставится ID клиента. Без этого покупка не привяжется к чату.', 'art-lms' ),
							'<code>&amp;mart_id={user_id}</code>'
						),
						array(
							'code' => array(),
						)
					);
					?>
				</p>
			<?php endif; ?>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'Ссылки на оплату появятся после публикации кнопки.', 'art-lms' ); ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
