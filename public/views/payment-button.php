<?php

/**

 * Payment button frontend markup.

 *

 * @package Art_LMS

 *

 * @var int    $button_id

 * @var string $product_name

 * @var array  $meta

 * @var string $checkout_url

 * @var array  $display
 * @var array  $design

 */



defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.



$show_product_name = ! $display['hide_product_name'] && $product_name;

$show_prices       = ! $display['hide_price'];

$show_compare      = $show_prices && ! $display['hide_compare_price'] && ! empty( $meta['compare_price'] );

$show_price        = $show_prices && ! empty( $meta['price'] );

$has_prices        = $show_compare || $show_price;

$wrapper_classes = array(
	'art-lms-payment-button',
	'art-lms-payment-button--align-' . sanitize_html_class( $design['button_align'] ),
);

$button_style = Art_LMS_Payment_Buttons::build_button_inline_style( $design );

?>

<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>">

	<?php if ( $show_product_name ) : ?>

		<p class="art-lms-payment-button__title"><?php echo esc_html( $product_name ); ?></p>

	<?php endif; ?>



	<?php if ( $has_prices ) : ?>

		<p class="art-lms-payment-button__prices">

			<?php if ( $show_compare ) : ?>

				<span class="art-lms-payment-button__compare"><?php echo esc_html( Art_LMS_Payment_Buttons::format_price( $meta['compare_price'] ) ); ?></span>

			<?php endif; ?>

			<?php if ( $show_price ) : ?>

				<span class="art-lms-payment-button__price"><?php echo esc_html( Art_LMS_Payment_Buttons::format_price( $meta['price'] ) ); ?></span>

			<?php endif; ?>

		</p>

	<?php endif; ?>



	<a class="art-lms-button art-lms-payment-button__cta" href="<?php echo esc_url( $checkout_url ); ?>"<?php if ( $button_style ) : ?> style="<?php echo esc_attr( $button_style ); ?>"<?php endif; ?>>

		<?php echo esc_html( $display['button_text'] ); ?>

	</a>

</div>

