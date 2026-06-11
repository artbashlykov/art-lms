<?php

/**

 * Payment button status sidebar meta box.

 *

 * @package Art_LMS

 *

 * @var WP_Post $post

 * @var bool    $is_enabled

 */



defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.



$is_enabled  = isset( $is_enabled ) ? (bool) $is_enabled : Art_LMS_Payment_Buttons::is_enabled( (int) $post->ID );

$is_archived = Art_LMS_Payment_Buttons::is_archived( (int) $post->ID );

?>

<div class="art-lms-payment-button-status">

	<?php if ( $is_archived ) : ?>

		<p class="art-lms-payment-button-status__archive-note">

			<span class="art-lms-button-status art-lms-button-status--archived"><?php esc_html_e( 'В архиве', 'art-lms' ); ?></span>

		</p>

		<p class="description">

			<?php esc_html_e( 'Архивная кнопка недоступна для checkout и не используется на сайте.', 'art-lms' ); ?>

		</p>

		<p>

			<a class="button button-secondary" href="<?php echo esc_url( Art_LMS_Payment_Buttons::get_unarchive_action_url( (int) $post->ID ) ); ?>">

				<?php esc_html_e( 'Вернуть из архива', 'art-lms' ); ?>

			</a>

		</p>

	<?php else : ?>

		<fieldset class="art-lms-payment-button-status__options">

			<legend class="screen-reader-text"><?php esc_html_e( 'Статус кнопки', 'art-lms' ); ?></legend>

			<label class="art-lms-payment-button-status__option">

				<input

					type="radio"

					name="art_lms_button_enabled"

					value="1"

					<?php checked( $is_enabled ); ?>

				>

				<?php esc_html_e( 'Активна', 'art-lms' ); ?>

			</label>

			<label class="art-lms-payment-button-status__option">

				<input

					type="radio"

					name="art_lms_button_enabled"

					value="0"

					<?php checked( ! $is_enabled ); ?>

				>

				<?php esc_html_e( 'Выключена', 'art-lms' ); ?>

			</label>

		</fieldset>

		<p class="description">

			<?php esc_html_e( 'Выключенная кнопка не показывается на сайте и недоступна для checkout.', 'art-lms' ); ?>

		</p>

		<?php if ( 'publish' === $post->post_status ) : ?>

			<p class="art-lms-payment-button-status__archive-action">

				<a class="button button-secondary" href="<?php echo esc_url( Art_LMS_Payment_Buttons::get_archive_action_url( (int) $post->ID ) ); ?>">

					<?php esc_html_e( 'Архивировать', 'art-lms' ); ?>

				</a>

			</p>

		<?php endif; ?>

	<?php endif; ?>

</div>


