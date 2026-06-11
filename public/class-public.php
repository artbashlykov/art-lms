<?php

/**

 * Public-facing functionality.

 *

 * @package Art_LMS

 */



defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public LMS URLs use shareable GET parameters (checkout, account, payment status).



/**

 * Class Art_LMS_Public

 */

class Art_LMS_Public {



	/**

	 * Register hooks.

	 */

	public static function init() {

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_payment_status' ) );

	}



	/**

	 * Enqueue frontend assets.

	 */

	public static function enqueue_assets() {

		wp_register_style(

			'art-lms-public',

			ART_LMS_PLUGIN_URL . 'assets/css/public.css',

			array(),

			ART_LMS_VERSION

		);



		wp_register_script(

			'art-lms-payment-status',

			ART_LMS_PLUGIN_URL . 'assets/js/payment-status.js',

			array(),

			ART_LMS_VERSION,

			true

		);



		wp_register_script(

			'art-lms-checkout',

			ART_LMS_PLUGIN_URL . 'assets/js/checkout.js',

			array( 'jquery' ),

			ART_LMS_VERSION,

			true

		);

	}



	/**

	 * Enqueue assets on success page when order key is present.

	 */

	public static function maybe_enqueue_payment_status() {

		if ( ! isset( $_GET['art_lms_order'] ) ) {

			return;

		}



		wp_enqueue_script( 'art-lms-payment-status' );

		Art_LMS_Payment_Status::localize_script();

		wp_enqueue_style( 'art-lms-public' );

	}

}


