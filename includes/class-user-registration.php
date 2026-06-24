<?php

/**

 * User registration before payment.

 *

 * @package Art_LMS

 */



defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public LMS URLs use shareable GET parameters (checkout, account, payment status).



/**

 * Class Art_LMS_User_Registration

 */

class Art_LMS_User_Registration {



	const QUERY_VERIFY_CHECKOUT = 'art_lms_verify_checkout';



	const PENDING_CHECKOUT_PREFIX = 'art_lms_pending_checkout_';



	const PENDING_CHECKOUT_TTL = 172800;



	/**

	 * Register hooks.

	 */

	public static function init() {

		add_action( 'template_redirect', array( __CLASS__, 'maybe_complete_email_verification' ), 1 );

	}



	/**

	 * Resolve checkout buyer account according to plugin settings.

	 *

	 * @param array $data Checkout context.

	 * @return array|WP_Error

	 */

	public static function resolve_checkout_user( array $data ) {

		$email     = sanitize_email( $data['email'] ?? '' );

		$name      = sanitize_text_field( $data['name'] ?? '' );

		$phone     = sanitize_text_field( $data['phone'] ?? '' );

		$button_id = absint( $data['button_id'] ?? 0 );

		$input     = is_array( $data['input'] ?? null ) ? $data['input'] : array();

		$snapshot  = is_array( $data['snapshot'] ?? null ) ? $data['snapshot'] : array();



		if ( ! Art_LMS_Settings::should_create_user_before_payment() ) {

			return array(

				'user_id' => 0,

				'is_new'  => false,

			);

		}



		$existing_user = get_user_by( 'email', $email );



		if ( $existing_user ) {

			if ( $phone ) {

				update_user_meta( $existing_user->ID, 'art_lms_phone', $phone );

			}



			return array(

				'user_id' => (int) $existing_user->ID,

				'is_new'  => false,

			);

		}



		if ( Art_LMS_Settings::should_require_email_verification() ) {

			return self::start_email_verification_checkout(

				array(

					'email'     => $email,

					'name'      => $name,

					'phone'     => $phone,

					'button_id' => $button_id,

					'input'     => $input,

					'snapshot'  => $snapshot,

				)

			);

		}



		$user_result = self::get_or_create_user(

			array(

				'email' => $email,

				'name'  => $name,

				'phone' => $phone,

			)

		);



		if ( is_wp_error( $user_result ) ) {

			return $user_result;

		}



		self::maybe_auto_login( (int) $user_result['user_id'], ! empty( $user_result['is_new'] ) );



		return $user_result;

	}



	/**

	 * Store pending checkout and send verification email.

	 *

	 * @param array $data Checkout payload.

	 * @return WP_Error

	 */

	private static function start_email_verification_checkout( array $data ) {

		$token = wp_generate_password( 32, false, false );



		set_transient(

			self::PENDING_CHECKOUT_PREFIX . $token,

			array(

				'email'     => sanitize_email( $data['email'] ?? '' ),

				'name'      => sanitize_text_field( $data['name'] ?? '' ),

				'phone'     => sanitize_text_field( $data['phone'] ?? '' ),

				'button_id' => absint( $data['button_id'] ?? 0 ),

				'input'     => is_array( $data['input'] ?? null ) ? $data['input'] : array(),

				'snapshot'  => is_array( $data['snapshot'] ?? null ) ? $data['snapshot'] : array(),

				'created_at' => time(),

			),

			self::PENDING_CHECKOUT_TTL

		);



		$sent = Art_LMS_Email::send_checkout_verification_email(

			sanitize_email( $data['email'] ?? '' ),

			sanitize_text_field( $data['name'] ?? '' ),

			self::get_verification_url( $token ),

			absint( $data['button_id'] ?? 0 )

		);



		if ( is_wp_error( $sent ) ) {

			delete_transient( self::PENDING_CHECKOUT_PREFIX . $token );



			return $sent;

		}



		return new WP_Error(

			'verification_pending',

			Art_LMS_Settings::format_checkout_form_message( 'email_verification_sent' ),

			array(

				'status' => 200,

			)

		);

	}



	/**

	 * Complete checkout after email verification link is opened.

	 */

	public static function maybe_complete_email_verification() {

		if ( empty( $_GET[ self::QUERY_VERIFY_CHECKOUT ] ) ) {

			return;

		}



		$token  = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VERIFY_CHECKOUT ] ) );

		$result = self::complete_verified_checkout( $token );



		if ( is_wp_error( $result ) ) {

			wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Подтверждение email', 'art-lms' ), array( 'response' => 400 ) );

		}



		$order = Art_LMS_Orders::get( (int) $result );



		if ( ! $order ) {

			wp_die( esc_html__( 'Заказ не найден.', 'art-lms' ), '', array( 'response' => 404 ) );

		}



		wp_safe_redirect( Art_LMS_Checkout::get_order_payment_redirect_url( $order ) );

		exit;

	}



	/**

	 * Create user and order from a verified checkout token.

	 *

	 * @param string $token Verification token.

	 * @return int|WP_Error Order ID.

	 */

	public static function complete_verified_checkout( $token ) {

		$token = sanitize_text_field( (string) $token );



		if ( '' === $token ) {

			return new WP_Error( 'invalid_token', __( 'Ссылка подтверждения недействительна.', 'art-lms' ) );

		}



		$pending = get_transient( self::PENDING_CHECKOUT_PREFIX . $token );



		if ( ! is_array( $pending ) ) {

			return new WP_Error( 'expired_token', __( 'Ссылка подтверждения устарела. Оформите заказ заново.', 'art-lms' ) );

		}



		delete_transient( self::PENDING_CHECKOUT_PREFIX . $token );



		$email = sanitize_email( $pending['email'] ?? '' );



		if ( ! is_email( $email ) ) {

			return new WP_Error( 'invalid_email', __( 'Некорректный email в заявке.', 'art-lms' ) );

		}



		$existing_user = get_user_by( 'email', $email );

		$is_new        = false;



		if ( $existing_user ) {

			$user_id = (int) $existing_user->ID;

		} else {

			$user_result = self::get_or_create_user(

				array(

					'email' => $email,

					'name'  => sanitize_text_field( $pending['name'] ?? '' ),

					'phone' => sanitize_text_field( $pending['phone'] ?? '' ),

				)

			);



			if ( is_wp_error( $user_result ) ) {

				return $user_result;

			}



			$user_id = (int) $user_result['user_id'];

			$is_new  = ! empty( $user_result['is_new'] );

		}



		self::maybe_auto_login( $user_id, $is_new );



		$snapshot = is_array( $pending['snapshot'] ?? null ) ? $pending['snapshot'] : array();
		$pending_input = is_array( $pending['input'] ?? null ) ? $pending['input'] : array();
		$button_id = absint( $pending['button_id'] ?? 0 );

		$button_check = Art_LMS_Payment_Buttons::validate_order_form_button( $button_id );

		if ( is_wp_error( $button_check ) ) {
			return $button_check;
		}

		$payment_gateway = sanitize_key( (string) ( $pending_input['payment_gateway'] ?? '' ) );

		if ( '' === $payment_gateway ) {
			$payment_gateway = Art_LMS_Settings::get_default_checkout_gateway();
		}

		if ( '' === $payment_gateway || ! Art_LMS_Settings::is_checkout_gateway_available( $payment_gateway ) ) {
			return new WP_Error(
				'payment_method_required',
				Art_LMS_Settings::format_checkout_form_message( 'payment_method_required' )
			);
		}

		return Art_LMS_Orders::create_checkout_order(
			$button_id,
			array(
				'user_id'         => $user_id,
				'email'           => $email,
				'name'            => sanitize_text_field( $pending['name'] ?? '' ),
				'phone'           => sanitize_text_field( $pending['phone'] ?? '' ),
				'form_data'       => Art_LMS_Order_Form_Data::encode( $snapshot ),
				'payment_gateway' => $payment_gateway,
			)
		);

	}



	/**

	 * Build email verification URL.

	 *

	 * @param string $token Verification token.

	 * @return string

	 */

	public static function get_verification_url( $token ) {

		return add_query_arg(

			array(

				self::QUERY_VERIFY_CHECKOUT => sanitize_text_field( (string) $token ),

			),

			home_url( '/' )

		);

	}



	/**

	 * Log in a newly registered buyer when the setting is enabled.

	 *

	 * @param int  $user_id User ID.

	 * @param bool $is_new  Whether the account was just created.

	 */

	public static function maybe_auto_login( $user_id, $is_new ) {

		if ( ! $is_new || ! Art_LMS_Settings::should_auto_login_after_register() ) {

			return;

		}



		$user_id = absint( $user_id );



		if ( ! $user_id || is_user_logged_in() ) {

			return;

		}



		wp_set_current_user( $user_id );

		wp_set_auth_cookie( $user_id, true );

	}



	/**

	 * Find an existing user by login or email.

	 *

	 * @param array $data User data (identity, phone).

	 * @return array|WP_Error

	 */

	public static function get_existing_user( $data ) {

		$identity = isset( $data['identity'] ) ? sanitize_text_field( $data['identity'] ) : '';

		$phone    = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';



		if ( '' === $identity ) {

			return new WP_Error(

				'missing_identity',

				__( 'Укажите логин или email.', 'art-lms' )

			);

		}



		$user = self::find_user_by_identity( $identity );



		if ( ! $user ) {

			return new WP_Error(

				'user_not_found',

				__( 'Пользователь с таким логином или email не найден.', 'art-lms' )

			);

		}



		if ( $phone ) {

			update_user_meta( $user->ID, 'art_lms_phone', $phone );

		}



		return array(

			'user_id' => (int) $user->ID,

			'is_new'  => false,

		);

	}



	/**

	 * Find existing user or create a new one.

	 *

	 * @param array $data User data (email, name, phone).

	 * @return array|WP_Error

	 */

	public static function get_or_create_user( $data ) {

		$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';

		$name  = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';

		$phone = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';



		if ( ! is_email( $email ) ) {

			return new WP_Error( 'invalid_email', __( 'Некорректный email.', 'art-lms' ) );

		}



		$user = get_user_by( 'email', $email );



		if ( $user ) {

			if ( $phone ) {

				update_user_meta( $user->ID, 'art_lms_phone', $phone );

			}



			return array(

				'user_id' => (int) $user->ID,

				'is_new'  => false,

			);

		}



		$username = self::generate_username( $email );

		$password = wp_generate_password( 16, true, true );



		$user_id = wp_insert_user(

			array(

				'user_login'   => $username,

				'user_email'   => $email,

				'user_pass'    => $password,

				'display_name' => $name ? $name : $email,

				'first_name'   => $name,

				'role'         => Art_LMS_Roles::ROLE_CUSTOMER,

			)

		);



		if ( is_wp_error( $user_id ) ) {

			return $user_id;

		}



		if ( $phone ) {

			update_user_meta( $user_id, 'art_lms_phone', $phone );

		}



		update_user_meta( $user_id, 'art_lms_created_via_plugin', 'yes' );



		do_action( 'art_lms_user_registered', $user_id, $email );



		return array(

			'user_id' => (int) $user_id,

			'is_new'  => true,

		);

	}



	/**

	 * Generate unique username from email.

	 *

	 * @param string $email Email address.

	 * @return string

	 */

	private static function generate_username( $email ) {

		$base = sanitize_user( current( explode( '@', $email ) ), true );



		if ( empty( $base ) ) {

			$base = 'buyer';

		}



		$username = $base;

		$suffix   = 1;



		while ( username_exists( $username ) ) {

			$username = $base . $suffix;

			++$suffix;

		}



		return $username;

	}



	/**

	 * Resolve user by email or login.

	 *

	 * @param string $identity Email or login.

	 * @return WP_User|false

	 */

	public static function find_user_by_identity( $identity ) {

		$identity = trim( (string) $identity );



		if ( '' === $identity ) {

			return false;

		}



		if ( is_email( $identity ) ) {

			$user = get_user_by( 'email', $identity );

			if ( $user ) {

				return $user;

			}

		}



		$login = sanitize_user( $identity, true );



		if ( $login ) {

			$user = get_user_by( 'login', $login );

			if ( $user ) {

				return $user;

			}

		}



		if ( ! is_email( $identity ) ) {

			return get_user_by( 'email', $identity );

		}



		return false;

	}



	/**

	 * Get buyer profile data for admin order form lookup.

	 *

	 * @param string $identity Email or login.

	 * @return array

	 */

	public static function get_buyer_details_for_form( $identity ) {

		$identity = trim( (string) $identity );

		$user     = self::find_user_by_identity( $identity );



		if ( $user ) {

			$name = $user->display_name ? $user->display_name : $user->first_name;



			if ( ! $name ) {

				$name = $user->user_login;

			}



			return array(

				'found'   => true,

				'user_id' => (int) $user->ID,

				'login'   => $user->user_login,

				'email'   => $user->user_email,

				'name'    => $name,

				'phone'   => (string) get_user_meta( $user->ID, 'art_lms_phone', true ),

			);

		}



		return array(

			'found'   => false,

			'user_id' => 0,

			'login'   => '',

			'email'   => is_email( $identity ) ? sanitize_email( $identity ) : '',

			'name'    => '',

			'phone'   => '',

		);

	}



	/**

	 * Resolve buyer email from identity for admin order save.

	 *

	 * @param string $identity Email or login.

	 * @param string $fallback_email Fallback email from form.

	 * @return string|WP_Error

	 */

	public static function resolve_buyer_email( $identity, $fallback_email = '' ) {

		$identity       = trim( (string) $identity );

		$fallback_email = sanitize_email( $fallback_email );



		if ( '' === $identity && $fallback_email ) {

			return $fallback_email;

		}



		if ( '' === $identity ) {

			return new WP_Error( 'missing_identity', __( 'Укажите email или логин покупателя.', 'art-lms' ) );

		}



		$user = self::find_user_by_identity( $identity );



		if ( $user ) {

			return $user->user_email;

		}



		if ( is_email( $identity ) ) {

			return sanitize_email( $identity );

		}



		if ( $fallback_email ) {

			return $fallback_email;

		}



		return new WP_Error( 'user_not_found', __( 'Пользователь с таким логином или email не найден.', 'art-lms' ) );

	}

}


