<?php
/**
 * Switch to another user (admin) and switch back.
 *
 * Threat model (must NOT happen):
 * - Buyer / same user in another browser becomes admin via "switch back".
 * - Opening a user profile creates switch state.
 * - Forged cookie or DB option alone escalates privileges.
 *
 * Required for switch-back (all of):
 * 1) Current user is logged in.
 * 2) Server option exists for this user ID.
 * 3) Option.session === wp_get_session_token() (this browser session only).
 * 4) Signed HttpOnly cookie present with matching tok (same browser that switched).
 * 5) Option HMAC valid (tamper-evident).
 * 6) Origin user still has manage_options.
 * 7) Valid nonce (CSRF).
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_User_Switching
 */
class Art_LMS_User_Switching {

	const OPTION_PREFIX     = 'art_lms_user_switch_';
	const COOKIE_NAME       = 'art_lms_switched_from';
	const NONCE_SWITCH_TO   = 'art_lms_switch_to_user';
	const NONCE_SWITCH_BACK = 'art_lms_switch_back_user';

	/**
	 * Skip clearing switch state while re-authenticating.
	 *
	 * @var bool
	 */
	private static $ignore_auth_cookie_clear = false;

	/**
	 * Early boot: register request handler before the init hook runs.
	 */
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'maybe_handle_request' ), 1 );
		add_action( 'login_init', array( __CLASS__, 'maybe_handle_request' ), 1 );
	}

	/**
	 * Register UI hooks.
	 */
	public static function init() {
		add_filter( 'user_row_actions', array( __CLASS__, 'filter_user_row_actions' ), 10, 2 );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_switch' ), 5 );
		add_action( 'show_user_profile', array( __CLASS__, 'render_profile_switch' ), 5 );

		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_switch_back' ), 999 );
		add_filter( 'body_class', array( __CLASS__, 'filter_body_class' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'filter_admin_body_class' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'render_switch_back_bar' ), 100 );
		add_action( 'wp_footer', array( __CLASS__, 'render_switch_back_bar' ), 100 );
		add_action( 'admin_footer', array( __CLASS__, 'render_switch_back_bar' ), 100 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'clear_auth_cookie', array( __CLASS__, 'on_clear_auth_cookie' ) );
		add_filter( 'login_message', array( __CLASS__, 'filter_login_message' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_filter( 'wp_die_handler', array( __CLASS__, 'filter_wp_die_handler' ) );
	}

	/**
	 * Whether the current user may switch to the target user.
	 *
	 * Only site admins (manage_options). Profile view alone never grants this.
	 *
	 * @param int $user_id Target user ID.
	 * @return bool
	 */
	public static function current_user_can_switch_to( $user_id ) {
		$user_id = absint( $user_id );

		if ( $user_id < 1 || ! is_user_logged_in() ) {
			return false;
		}

		if ( get_current_user_id() === $user_id ) {
			return false;
		}

		// Narrow capability: not every edit_users role (shop manager etc.).
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		return get_userdata( $user_id ) instanceof WP_User;
	}

	/**
	 * Handle switch requests.
	 */
	public static function maybe_handle_request() {
		static $handled = false;

		if ( $handled || empty( $_REQUEST['art_lms_user_switch'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$handled = true;
		$mode    = sanitize_key( wp_unslash( $_REQUEST['art_lms_user_switch'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'to' === $mode ) {
			self::handle_switch_to();
			return;
		}

		if ( 'back' === $mode ) {
			self::handle_switch_back();
		}
	}

	/**
	 * URL to switch to a user.
	 *
	 * @param int $user_id Target user ID.
	 * @return string
	 */
	public static function get_switch_to_url( $user_id ) {
		$user_id = absint( $user_id );

		return wp_nonce_url(
			add_query_arg(
				array(
					'art_lms_user_switch' => 'to',
					'user_id'             => $user_id,
				),
				home_url( '/' )
			),
			self::NONCE_SWITCH_TO . '_' . $user_id
		);
	}

	/**
	 * Original admin user ID if currently switched, else 0.
	 *
	 * @return int
	 */
	public static function get_old_user_id() {
		$state = self::get_switch_state();

		return $state ? absint( $state['uid'] ) : 0;
	}

	/**
	 * Whether the current browser session is a switched session.
	 *
	 * @return bool
	 */
	public static function is_switched() {
		return null !== self::get_switch_state();
	}

	/**
	 * Valid switch state for THIS browser session only, or null.
	 *
	 * @return array{uid:int,as:int,tok:string,exp:int,session:string}|null
	 */
	private static function get_switch_state() {
		if ( ! is_user_logged_in() ) {
			return null;
		}

		$as_user_id = get_current_user_id();
		$stored     = get_option( self::OPTION_PREFIX . $as_user_id, null );

		if ( ! is_array( $stored ) ) {
			return null;
		}

		$uid     = isset( $stored['uid'] ) ? absint( $stored['uid'] ) : 0;
		$exp     = isset( $stored['exp'] ) ? absint( $stored['exp'] ) : 0;
		$tok     = isset( $stored['tok'] ) ? (string) $stored['tok'] : '';
		$session = isset( $stored['session'] ) ? (string) $stored['session'] : '';
		$sig     = isset( $stored['sig'] ) ? (string) $stored['sig'] : '';

		// Reject legacy/incomplete records (including pre-session-binding options).
		if ( $uid < 1 || $exp < time() || '' === $tok || '' === $session || '' === $sig ) {
			self::delete_switch_state( $as_user_id );
			return null;
		}

		if ( ! hash_equals( self::sign_state( $uid, $as_user_id, $session, $tok, $exp ), $sig ) ) {
			self::delete_switch_state( $as_user_id );
			return null;
		}

		if ( ! get_userdata( $uid ) instanceof WP_User ) {
			self::delete_switch_state( $as_user_id );
			return null;
		}

		$current_session = wp_get_session_token();

		// Another login as the same user → different session → no switch-back.
		if ( '' === $current_session || ! hash_equals( $session, $current_session ) ) {
			return null;
		}

		// Same browser must also present the signed helper cookie (defense in depth).
		if ( ! self::cookie_matches_token( $tok, $uid, $as_user_id ) ) {
			return null;
		}

		return array(
			'uid'     => $uid,
			'as'      => $as_user_id,
			'tok'     => $tok,
			'exp'     => $exp,
			'session' => $session,
		);
	}

	/**
	 * HMAC for server-side state.
	 *
	 * @param int    $uid     Origin user.
	 * @param int    $as      Switched-to user.
	 * @param string $session Session token.
	 * @param string $tok     Random token.
	 * @param int    $exp     Expiry.
	 * @return string
	 */
	private static function sign_state( $uid, $as, $session, $tok, $exp ) {
		$payload = absint( $uid ) . '|' . absint( $as ) . '|' . (string) $session . '|' . (string) $tok . '|' . absint( $exp );

		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	/**
	 * Whether the helper cookie matches the server token for this switch.
	 *
	 * @param string $tok Expected token.
	 * @param int    $uid Origin user ID.
	 * @param int    $as  Switched-to user ID.
	 * @return bool
	 */
	private static function cookie_matches_token( $tok, $uid, $as ) {
		$raw = self::get_switch_cookie_raw();

		if ( '' === $raw ) {
			return false;
		}

		$parts = explode( '.', $raw, 2 );

		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$json = base64_decode( $parts[0], true );
		$sig  = $parts[1];

		if ( false === $json || '' === $sig ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $json, wp_salt( 'auth' ) );

		if ( ! hash_equals( $expected, $sig ) ) {
			return false;
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return false;
		}

		$c_uid = isset( $data['uid'] ) ? absint( $data['uid'] ) : 0;
		$c_as  = isset( $data['as'] ) ? absint( $data['as'] ) : 0;
		$c_tok = isset( $data['tok'] ) ? (string) $data['tok'] : '';
		$c_exp = isset( $data['exp'] ) ? absint( $data['exp'] ) : 0;

		if ( $c_exp < time() ) {
			return false;
		}

		return $c_uid === absint( $uid )
			&& $c_as === absint( $as )
			&& '' !== $c_tok
			&& hash_equals( (string) $tok, $c_tok );
	}

	/**
	 * Row action on Users list.
	 *
	 * @param array<string, string> $actions Actions.
	 * @param WP_User               $user    User.
	 * @return array<string, string>
	 */
	public static function filter_user_row_actions( $actions, $user ) {
		$actions = is_array( $actions ) ? $actions : array();

		if ( ! $user instanceof WP_User || ! self::current_user_can_switch_to( (int) $user->ID ) ) {
			return $actions;
		}

		$actions['art_lms_switch_to'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::get_switch_to_url( (int) $user->ID ) ),
			esc_html__( 'Переключиться', 'art-lms' )
		);

		return $actions;
	}

	/**
	 * Switch link on user profile screens (admin only; does not create state by itself).
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function render_profile_switch( $user ) {
		if ( ! $user instanceof WP_User || ! self::current_user_can_switch_to( (int) $user->ID ) ) {
			return;
		}

		$url = self::get_switch_to_url( (int) $user->ID );
		?>
		<h2><?php echo esc_html__( 'АРТ ЛМС: переключение пользователя', 'art-lms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Просмотр от имени', 'art-lms' ); ?></th>
				<td>
					<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: user login */
								__( 'Переключиться на %s', 'art-lms' ),
								$user->user_login
							)
						);
						?>
					</a>
					<p class="description">
						<?php echo esc_html__( 'Сайт откроется так, как его видит этот пользователь. Вернуться можно одной кнопкой.', 'art-lms' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Admin bar node.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar.
	 */
	public static function admin_bar_switch_back( $wp_admin_bar ) {
		if ( ! $wp_admin_bar instanceof WP_Admin_Bar || ! self::is_switched() ) {
			return;
		}

		$old_user = get_userdata( self::get_old_user_id() );

		if ( ! $old_user instanceof WP_User ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'art-lms-switch-back',
				'title' => sprintf(
					/* translators: %s: user login */
					__( 'Вернуться к %s', 'art-lms' ),
					$old_user->user_login
				),
				'href'  => '#art-lms-switch-back-form',
				'meta'  => array(
					'class' => 'art-lms-switch-back-ab',
				),
			)
		);
	}

	/**
	 * Visible switch-back bar.
	 */
	public static function render_switch_back_bar() {
		static $rendered = false;

		if ( $rendered || ! self::is_switched() ) {
			return;
		}

		$old_user = get_userdata( self::get_old_user_id() );

		if ( ! $old_user instanceof WP_User ) {
			return;
		}

		$rendered = true;
		$current  = wp_get_current_user();
		$redirect = admin_url( 'users.php' );
		$label    = sprintf(
			/* translators: %s: original user login */
			__( 'Вернуться к %s', 'art-lms' ),
			$old_user->user_login
		);

		echo '<div class="art-lms-switch-back-bar" id="art-lms-switch-back-bar" role="status">';
		printf(
			'<span class="art-lms-switch-back-bar__text">%s</span>',
			esc_html(
				sprintf(
					/* translators: %s: current user login */
					__( 'Вы вошли как %s.', 'art-lms' ),
					$current instanceof WP_User ? $current->user_login : ''
				)
			)
		);
		self::render_switch_back_form( $redirect, $label, 'art-lms-switch-back-bar__link' );
		echo '</div>';
	}

	/**
	 * POST form for switch-back.
	 *
	 * @param string $redirect Redirect after switch back.
	 * @param string $label    Button label.
	 * @param string $class    CSS class for the submit button.
	 */
	private static function render_switch_back_form( $redirect, $label, $class = '' ) {
		?>
		<form method="post" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="art-lms-switch-back-form" id="art-lms-switch-back-form">
			<input type="hidden" name="art_lms_user_switch" value="back" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>" />
			<?php wp_nonce_field( self::NONCE_SWITCH_BACK ); ?>
			<button type="submit" class="<?php echo esc_attr( $class ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
				<?php echo esc_html( $label ); ?>
			</button>
		</form>
		<script>
		(function(){
			var a=document.querySelector('#wp-admin-bar-art-lms-switch-back > a');
			var f=document.getElementById('art-lms-switch-back-form');
			if(a&&f){a.addEventListener('click',function(e){e.preventDefault();f.submit();});}
		})();
		</script>
		<?php
	}

	/**
	 * Frontend body class while switched.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function filter_body_class( $classes ) {
		if ( self::is_switched() ) {
			$classes[] = 'art-lms-is-switched';
		}

		return $classes;
	}

	/**
	 * Admin body class while switched.
	 *
	 * @param string $classes Space-separated classes.
	 * @return string
	 */
	public static function filter_admin_body_class( $classes ) {
		if ( self::is_switched() ) {
			$classes .= ' art-lms-is-switched';
		}

		return $classes;
	}

	/**
	 * Enqueue switch-back bar styles when needed.
	 */
	public static function enqueue_assets() {
		if ( ! self::is_switched() ) {
			return;
		}

		wp_enqueue_style(
			'art-lms-user-switching',
			ART_LMS_PLUGIN_URL . 'assets/css/user-switching.css',
			array(),
			ART_LMS_VERSION
		);
	}

	/**
	 * Admin notice while switched (like User Switching).
	 */
	public static function render_admin_notice() {
		if ( ! self::is_switched() ) {
			return;
		}

		$old_user = get_userdata( self::get_old_user_id() );
		$current  = wp_get_current_user();

		if ( ! $old_user instanceof WP_User || ! $current instanceof WP_User ) {
			return;
		}

		echo '<div class="notice notice-info"><p>';
		echo esc_html(
			sprintf(
				/* translators: 1: current user login, 2: original admin login */
				__( 'Вы вошли как %1$s (переключение от %2$s).', 'art-lms' ),
				$current->user_login,
				$old_user->user_login
			)
		);
		echo ' ';
		self::render_switch_back_form(
			admin_url( 'users.php' ),
			sprintf(
				/* translators: %s: original user login */
				__( 'Вернуться к %s', 'art-lms' ),
				$old_user->user_login
			),
			'button button-secondary'
		);
		echo '</p></div>';
	}

	/**
	 * Keep Switch Back available on wp_die screens (User Switching 1.11.0).
	 *
	 * @param callable $handler Existing handler.
	 * @return callable
	 */
	public static function filter_wp_die_handler( $handler ) {
		if ( ! self::is_switched() ) {
			return $handler;
		}

		return static function ( $message, $title, $args ) use ( $handler ) {
			$old_user = get_userdata( Art_LMS_User_Switching::get_old_user_id() );

			if ( $old_user instanceof WP_User ) {
				$label = sprintf(
					/* translators: %s: original user login */
					__( 'Вернуться к %s', 'art-lms' ),
					$old_user->user_login
				);

				ob_start();
				Art_LMS_User_Switching::render_switch_back_form_public( admin_url( 'users.php' ), $label, 'button' );
				$switch_html = ob_get_clean();

				if ( is_string( $message ) ) {
					$message .= '<div id="art-lms-switch-back-wp-die" style="margin-top:1.5em;padding-top:1em;border-top:1px solid #dadada;">' . $switch_html . '</div>';
				}
			}

			return call_user_func( $handler, $message, $title, $args );
		};
	}

	/**
	 * Public wrapper for switch-back form (wp_die closure).
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $label    Button label.
	 * @param string $class    CSS class.
	 */
	public static function render_switch_back_form_public( $redirect, $label, $class = '' ) {
		self::render_switch_back_form( $redirect, $label, $class );
	}

	/**
	 * Whether another admin session is already switched into this user.
	 *
	 * @param WP_User $target Target user.
	 * @param WP_User $ignore Current admin (ignored).
	 * @return array{login:int,user:WP_User}|null
	 */
	private static function get_duplicated_switch( WP_User $target, WP_User $ignore ) {
		$sessions = WP_Session_Tokens::get_instance( $target->ID )->get_all();

		foreach ( $sessions as $session ) {
			if ( empty( $session['art_lms_switched_from_id'] ) && empty( $session['switched_from_id'] ) ) {
				continue;
			}

			$from_id = ! empty( $session['art_lms_switched_from_id'] )
				? absint( $session['art_lms_switched_from_id'] )
				: absint( $session['switched_from_id'] );

			if ( $from_id < 1 || $from_id === (int) $ignore->ID ) {
				continue;
			}

			$from_user = get_userdata( $from_id );

			if ( ! $from_user instanceof WP_User ) {
				continue;
			}

			return array(
				'login' => isset( $session['login'] ) ? absint( $session['login'] ) : time(),
				'user'  => $from_user,
			);
		}

		return null;
	}

	/**
	 * Handle switch-to request.
	 */
	public static function handle_switch_to() {
		$user_id = isset( $_REQUEST['user_id'] ) ? absint( wp_unslash( $_REQUEST['user_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		check_admin_referer( self::NONCE_SWITCH_TO . '_' . $user_id );

		if ( ! self::current_user_can_switch_to( $user_id ) ) {
			wp_die( esc_html__( 'Недостаточно прав для переключения на этого пользователя.', 'art-lms' ), 403 );
		}

		$old_user_id = get_current_user_id();

		// Keep original admin if already inside a switched session.
		$existing = self::get_switch_state();
		if ( $existing ) {
			$old_user_id = absint( $existing['uid'] );
			self::delete_switch_state( (int) $existing['as'] );
		}

		$origin = get_userdata( $old_user_id );
		$target = get_userdata( $user_id );

		if ( ! $origin instanceof WP_User || ! user_can( $origin, 'manage_options' ) || ! $target instanceof WP_User ) {
			wp_die( esc_html__( 'Недостаточно прав для переключения.', 'art-lms' ), 403 );
		}

		// Warn if another agent is already switched into this account (User Switching 1.9.0).
		$force = ! empty( $_REQUEST['force_switch_user'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $force ) {
			$duplicate = self::get_duplicated_switch( $target, $origin );
			if ( $duplicate ) {
				$yes_url = wp_nonce_url(
					add_query_arg(
						array(
							'art_lms_user_switch' => 'to',
							'user_id'             => $user_id,
							'force_switch_user'   => '1',
						),
						home_url( '/' )
					),
					self::NONCE_SWITCH_TO . '_' . $user_id
				);

				$message = sprintf(
					/* translators: 1: other admin login, 2: target user login, 3: human time diff */
					__( '%1$s уже переключён в аккаунт %2$s (около %3$s назад). Продолжить?', 'art-lms' ),
					$duplicate['user']->user_login,
					$target->user_login,
					human_time_diff( $duplicate['login'] )
				);

				wp_die(
					sprintf(
						'<p>%1$s</p><p><a class="button button-primary" href="%2$s">%3$s</a> <a class="button" href="javascript:history.back()">%4$s</a></p>',
						esc_html( $message ),
						esc_url( $yes_url ),
						esc_html(
							sprintf(
								/* translators: %s: target user login */
								__( 'Да, переключиться на %s', 'art-lms' ),
								$target->user_login
							)
						),
						esc_html__( 'Нет, назад', 'art-lms' )
					),
					'',
					array(
						'response'  => 409,
						'back_link' => false,
					)
				);
			}
		}

		$session_token = self::authenticate_as( $user_id, $old_user_id );
		self::store_switch_state( $old_user_id, $user_id, $session_token );

		$account_url = Art_LMS_Settings::get_account_url();
		$redirect    = $account_url ? $account_url : home_url( '/' );

		/**
		 * Filter redirect URL after switching to a user.
		 *
		 * @param string $redirect    Redirect URL.
		 * @param int    $user_id     Target user ID.
		 * @param int    $old_user_id Original user ID.
		 */
		$redirect = apply_filters( 'art_lms_switch_to_redirect', $redirect, $user_id, $old_user_id );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle switch-back request.
	 */
	public static function handle_switch_back() {
		check_admin_referer( self::NONCE_SWITCH_BACK );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Нужна активная сессия для возврата.', 'art-lms' ), 403 );
		}

		$state = self::get_switch_state();

		if ( ! $state ) {
			wp_die( esc_html__( 'Нет пользователя для возврата.', 'art-lms' ), 400 );
		}

		$old_user_id      = absint( $state['uid'] );
		$switched_user_id = get_current_user_id();
		$old_user         = get_userdata( $old_user_id );

		// Origin must still be a full site admin — never escalate to a weaker stored uid.
		if ( ! $old_user instanceof WP_User || ! user_can( $old_user, 'manage_options' ) ) {
			self::delete_switch_state( $switched_user_id );
			self::clear_switch_cookie();
			wp_die( esc_html__( 'Возврат к этому пользователю запрещён.', 'art-lms' ), 403 );
		}

		// Destroy the switched-to session (same as johnbillion/user-switching on switch-back).
		$switched_session = wp_get_session_token();
		if ( '' !== $switched_session ) {
			WP_Session_Tokens::get_instance( $switched_user_id )->destroy( $switched_session );
		}

		self::delete_switch_state( $switched_user_id );
		self::clear_switch_cookie();
		self::authenticate_as( $old_user_id );

		$redirect = '';

		if ( isset( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$redirect = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( '' === $redirect ) {
			$redirect = admin_url( 'user-edit.php?user_id=' . $switched_user_id );
		}

		/**
		 * Filter redirect URL after switching back.
		 *
		 * @param string $redirect         Redirect URL.
		 * @param int    $old_user_id      User switched back to.
		 * @param int    $switched_user_id User we left.
		 */
		$redirect = apply_filters( 'art_lms_switch_back_redirect', $redirect, $old_user_id, $switched_user_id );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Log out current session and log in as another user.
	 *
	 * @param int $user_id         User ID.
	 * @param int $switched_from_id Origin user ID when switching to someone (0 = plain re-auth / switch-back).
	 * @return string New session token.
	 */
	private static function authenticate_as( $user_id, $switched_from_id = 0 ) {
		$user_id         = absint( $user_id );
		$switched_from_id = absint( $switched_from_id );
		$user            = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			wp_die( esc_html__( 'Пользователь не найден.', 'art-lms' ), 404 );
		}

		$expiration     = time() + ( 2 * DAY_IN_SECONDS );
		$manager        = WP_Session_Tokens::get_instance( $user_id );
		$session_filter = null;

		// Must attach before create() — that is when WP stores session meta.
		if ( $switched_from_id > 0 ) {
			$session_filter = static function ( array $session ) use ( $switched_from_id ) {
				$session['art_lms_switched_from_id'] = $switched_from_id;
				return $session;
			};
			add_filter( 'attach_session_information', $session_filter, 99 );
		}

		$token = $manager->create( $expiration );

		if ( null !== $session_filter ) {
			remove_filter( 'attach_session_information', $session_filter, 99 );
		}

		self::$ignore_auth_cookie_clear = true;
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id, $user->user_login );
		wp_set_auth_cookie( $user_id, false, '', $token );
		self::$ignore_auth_cookie_clear = false;

		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP hook.

		return is_string( $token ) ? $token : '';
	}

	/**
	 * Persist switch state (signed option + signed cookie).
	 *
	 * @param int    $old_user_id   Original admin user ID.
	 * @param int    $as_user_id    User we switch into.
	 * @param string $session_token WP session token of the switched browser session.
	 */
	private static function store_switch_state( $old_user_id, $as_user_id, $session_token ) {
		$old_user_id   = absint( $old_user_id );
		$as_user_id    = absint( $as_user_id );
		$session_token = (string) $session_token;

		if ( $old_user_id < 1 || $as_user_id < 1 || '' === $session_token ) {
			return;
		}

		$token      = wp_generate_password( 32, false, false );
		$expiration = time() + DAY_IN_SECONDS;
		$sig        = self::sign_state( $old_user_id, $as_user_id, $session_token, $token, $expiration );

		update_option(
			self::OPTION_PREFIX . $as_user_id,
			array(
				'uid'     => $old_user_id,
				'tok'     => $token,
				'exp'     => $expiration,
				'session' => $session_token,
				'sig'     => $sig,
			),
			false
		);

		$payload = wp_json_encode(
			array(
				'uid' => $old_user_id,
				'as'  => $as_user_id,
				'exp' => $expiration,
				'tok' => $token,
			)
		);

		if ( is_string( $payload ) && '' !== $payload ) {
			$value = base64_encode( $payload ) . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			self::set_cookie( self::cookie_name(), $value, $expiration );
			$_COOKIE[ self::cookie_name() ] = $value;
		}
	}

	/**
	 * Delete server-side switch state for a user.
	 *
	 * @param int $as_user_id Switched-to user ID.
	 */
	private static function delete_switch_state( $as_user_id ) {
		$as_user_id = absint( $as_user_id );

		if ( $as_user_id > 0 ) {
			delete_option( self::OPTION_PREFIX . $as_user_id );
		}
	}

	/**
	 * On logout — clear switch state only if THIS session owns it.
	 */
	public static function on_clear_auth_cookie() {
		if ( self::$ignore_auth_cookie_clear ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$as_user_id = get_current_user_id();
			$stored     = get_option( self::OPTION_PREFIX . $as_user_id, null );

			if ( is_array( $stored ) ) {
				$session         = isset( $stored['session'] ) ? (string) $stored['session'] : '';
				$current_session = wp_get_session_token();

				// Only the switched browser may wipe state — not the real user logging out elsewhere.
				if ( '' !== $session && '' !== $current_session && hash_equals( $session, $current_session ) ) {
					self::delete_switch_state( $as_user_id );
				}
			}
		}

		self::clear_switch_cookie();
	}

	/**
	 * Clear helper cookie.
	 */
	private static function clear_switch_cookie() {
		$name = self::cookie_name();

		self::set_cookie( $name, ' ', time() - YEAR_IN_SECONDS );
		unset( $_COOKIE[ $name ] );
	}

	/**
	 * Raw helper cookie value.
	 *
	 * @return string
	 */
	private static function get_switch_cookie_raw() {
		$name = self::cookie_name();

		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return '';
		}

		return preg_replace( '/[^A-Za-z0-9+\/=._-]/', '', wp_unslash( $_COOKIE[ $name ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Set cookie on common paths.
	 *
	 * @param string $name    Cookie name.
	 * @param string $value   Cookie value.
	 * @param int    $expires Expiration timestamp.
	 */
	private static function set_cookie( $name, $value, $expires ) {
		$secure = is_ssl();
		$paths  = array_unique(
			array_filter(
				array(
					COOKIEPATH ? COOKIEPATH : '/',
					defined( 'SITECOOKIEPATH' ) && SITECOOKIEPATH ? SITECOOKIEPATH : '',
					'/',
				)
			)
		);

		foreach ( $paths as $path ) {
			setcookie(
				$name,
				$value,
				array(
					'expires'  => $expires,
					'path'     => $path,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
	}

	/**
	 * Cookie name (scoped by site cookie hash).
	 *
	 * @return string
	 */
	private static function cookie_name() {
		return self::COOKIE_NAME . '_' . COOKIEHASH;
	}

	/**
	 * Notice on wp-login when switched.
	 *
	 * @param string $message Login message HTML.
	 * @return string
	 */
	public static function filter_login_message( $message ) {
		if ( ! self::is_switched() ) {
			return $message;
		}

		$old_user = get_userdata( self::get_old_user_id() );

		if ( ! $old_user instanceof WP_User ) {
			return $message;
		}

		ob_start();
		echo '<p class="message">' . esc_html__( 'Активно переключение пользователя.', 'art-lms' ) . ' ';
		self::render_switch_back_form(
			admin_url( 'users.php' ),
			sprintf(
				/* translators: %s: user login */
				__( 'Вернуться к %s', 'art-lms' ),
				$old_user->user_login
			)
		);
		echo '</p>';
		$notice = ob_get_clean();

		return $notice . $message;
	}
}
