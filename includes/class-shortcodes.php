<?php
/**
 * Plugin shortcodes.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public LMS URLs use shareable GET parameters (checkout, account, payment status).

/**
 * Class Art_LMS_Shortcodes
 */
class Art_LMS_Shortcodes {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_shortcode( 'art_lms_account', array( __CLASS__, 'render_account' ) );
		add_shortcode( 'art_lms_payment_status', array( __CLASS__, 'render_payment_status' ) );
		add_shortcode( 'art_lms_payment_button', array( __CLASS__, 'render_payment_button' ) );
	}

	/**
	 * Render customer account page.
	 *
	 * @return string
	 */
	public static function render_account() {
		return Art_LMS_Account::render();
	}

	/**
	 * Render payment button shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_payment_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'art_lms_payment_button'
		);

		return Art_LMS_Payment_Buttons::render( absint( $atts['id'] ) );
	}

	/**
	 * Render payment status block for success page.
	 *
	 * @return string
	 */
	public static function render_payment_status() {
		wp_enqueue_script( 'art-lms-payment-status' );
		Art_LMS_Payment_Status::localize_script();
		wp_enqueue_style( 'art-lms-public' );

		$has_order_key = ! empty( $_GET['art_lms_order'] );
		$messages      = Art_LMS_Settings::get_payment_status_messages();

		ob_start();
		?>
		<div class="art-lms-payment-status-wrap">
			<div
				id="art-lms-payment-status"
				class="art-lms-payment-status<?php echo esc_attr( $has_order_key ? ' is-pending is-loading' : ' is-error' ); ?>"
				role="status"
				aria-live="polite"
				<?php if ( ! $has_order_key ) : ?>
					data-missing-order="1"
				<?php endif; ?>
			>
				<?php if ( $has_order_key ) : ?>
					<p class="art-lms-payment-status__title"><?php echo esc_html( $messages['pending_title'] ); ?></p>
				<?php else : ?>
					<p class="art-lms-payment-status__title"><?php echo esc_html( $messages['missing_order_title'] ); ?></p>
					<p class="art-lms-payment-status__text"><?php echo esc_html( strtok( $messages['missing_order_description'], "\n" ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
