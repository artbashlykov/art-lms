<?php
/**
 * Optional MAX messenger attribution (mart_id) on checkout — additive only.
 *
 * Does not change payment gateways or grant-access logic. Stores digits-only
 * mart_id in order form_data when present in request / cookie.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Mart_Attribution
 */
class Art_LMS_Mart_Attribution {

	const PARAM       = 'mart_id';
	const COOKIE      = 'art_lms_mart_id';
	const COOKIE_DAYS = 14;
	const FIELD_KEY   = 'mart_id';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'art_lms_checkout_url', array( __CLASS__, 'filter_checkout_url' ), 20, 2 );
		add_filter( 'art_lms_order_form_snapshot', array( __CLASS__, 'filter_order_form_snapshot' ), 10, 2 );
		add_action( 'art_lms_checkout_form_after_fields', array( __CLASS__, 'render_hidden_field' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_persist_cookie' ), 5 );
	}

	/**
	 * Digits-only MAX user id (empty if invalid).
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	public static function sanitize( $raw ) {
		$raw = is_scalar( $raw ) ? (string) $raw : '';
		$raw = preg_replace( '/\D+/', '', $raw );
		if ( ! is_string( $raw ) || strlen( $raw ) < 3 || strlen( $raw ) > 32 ) {
			return '';
		}

		return $raw;
	}

	/**
	 * Resolve from GET, POST, cookie (priority order).
	 *
	 * @return string
	 */
	public static function resolve_from_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public attribution param.
		if ( isset( $_GET[ self::PARAM ] ) ) {
			$from_get = self::sanitize( wp_unslash( $_GET[ self::PARAM ] ) );
			if ( '' !== $from_get ) {
				return $from_get;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checkout REST/form already verifies elsewhere.
		if ( isset( $_POST[ self::PARAM ] ) ) {
			$from_post = self::sanitize( wp_unslash( $_POST[ self::PARAM ] ) );
			if ( '' !== $from_post ) {
				return $from_post;
			}
		}

		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			return self::sanitize( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		}

		return '';
	}

	/**
	 * Persist cookie when URL carries mart_id (best-effort; URL remains primary).
	 */
	public static function maybe_persist_cookie() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public query attribution.
		if ( ! isset( $_GET[ self::PARAM ] ) ) {
			return;
		}

		$id = self::sanitize( wp_unslash( $_GET[ self::PARAM ] ) );
		if ( '' === $id ) {
			return;
		}

		self::set_cookie( $id );
	}

	/**
	 * @param string $mart_id Sanitized id.
	 */
	public static function set_cookie( $mart_id ) {
		$mart_id = self::sanitize( $mart_id );
		if ( '' === $mart_id || headers_sent() ) {
			return;
		}

		$expire = time() + ( self::COOKIE_DAYS * DAY_IN_SECONDS );
		$secure = is_ssl();

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				self::COOKIE,
				$mart_id,
				array(
					'expires'  => $expire,
					'path'     => '/',
					'secure'   => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		} else {
			setcookie( self::COOKIE, $mart_id, $expire, '/; samesite=Lax', '', $secure, true );
		}

		$_COOKIE[ self::COOKIE ] = $mart_id;
	}

	/**
	 * Append mart_id to checkout URLs when known.
	 *
	 * Skipped in wp-admin so «Вставка» / copy links stay clean (no admin's own
	 * mart_id from cookie after testing). Front-end buttons and MAX funnel
	 * links still get mart_id from the visitor URL / cookie / {user_id}.
	 *
	 * @param string $url       Checkout URL.
	 * @param int    $button_id Button ID.
	 * @return string
	 */
	public static function filter_checkout_url( $url, $button_id = 0 ) {
		unset( $button_id );
		$url = (string) $url;
		if ( '' === $url ) {
			return $url;
		}

		/* Admin screens (incl. payment-button «Вставка») — never inject mart_id. */
		if ( is_admin() ) {
			return $url;
		}

		$id = self::resolve_from_request();
		if ( '' === $id ) {
			return $url;
		}

		return add_query_arg( self::PARAM, $id, $url );
	}

	/**
	 * Hidden field on checkout form.
	 */
	public static function render_hidden_field() {
		$id = self::resolve_from_request();
		if ( '' !== $id ) {
			self::set_cookie( $id );
		}
		?>
		<input
			type="hidden"
			name="<?php echo esc_attr( self::PARAM ); ?>"
			id="art-lms-field-mart-id"
			value="<?php echo esc_attr( $id ); ?>"
			autocomplete="off"
		/>
		<?php
	}

	/**
	 * Inject mart_id into form_data snapshot (non-required, never blocks checkout).
	 *
	 * @param array<string, mixed> $snapshot Snapshot with fields.
	 * @param array<string, mixed> $input    Raw input.
	 * @return array<string, mixed>
	 */
	public static function filter_order_form_snapshot( $snapshot, $input ) {
		$snapshot = is_array( $snapshot ) ? $snapshot : array( 'fields' => array() );
		$input    = is_array( $input ) ? $input : array();

		$id = '';
		if ( isset( $input[ self::PARAM ] ) ) {
			$id = self::sanitize( $input[ self::PARAM ] );
		}
		if ( '' === $id ) {
			$id = self::resolve_from_request();
		}
		if ( '' === $id ) {
			return $snapshot;
		}

		$fields = isset( $snapshot['fields'] ) && is_array( $snapshot['fields'] ) ? $snapshot['fields'] : array();
		$found  = false;

		foreach ( $fields as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( self::FIELD_KEY === (string) ( $row['key'] ?? '' ) ) {
				$fields[ $i ]['value'] = $id;
				$fields[ $i ]['label'] = 'MAX ID';
				$fields[ $i ]['type']  = 'field';
				$found                 = true;
				break;
			}
		}

		if ( ! $found ) {
			$fields[] = array(
				'key'   => self::FIELD_KEY,
				'label' => 'MAX ID',
				'value' => $id,
				'type'  => 'field',
			);
		}

		$snapshot['fields'] = $fields;

		return $snapshot;
	}

	/**
	 * Read mart_id from stored order form_data.
	 *
	 * @param object|array<string, mixed>|null $order Order row.
	 * @return string
	 */
	public static function get_from_order( $order ) {
		if ( is_object( $order ) ) {
			$raw = $order->form_data ?? '';
		} elseif ( is_array( $order ) ) {
			$raw = $order['form_data'] ?? '';
		} else {
			return '';
		}

		$snapshot = Art_LMS_Order_Form_Data::decode( is_string( $raw ) ? $raw : '' );
		foreach ( $snapshot['fields'] as $row ) {
			if ( self::FIELD_KEY === (string) ( $row['key'] ?? '' ) ) {
				return self::sanitize( $row['value'] ?? '' );
			}
		}

		return '';
	}
}
