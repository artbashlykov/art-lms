<?php
/**
 * YooKassa 54-FZ receipt builder.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Yookassa_Receipt
 */
class Art_LMS_Yookassa_Receipt {

	const ITEM_DESCRIPTION_MAX = 128;

	/**
	 * Whether receipt sending is enabled in gateway settings.
	 *
	 * @param array $settings Gateway settings.
	 * @return bool
	 */
	public static function is_enabled( array $settings ) {
		return ( $settings['receipts_enabled'] ?? 'no' ) === 'yes';
	}

	/**
	 * Build receipt payload for YooKassa payment creation.
	 *
	 * @param object $order    Order object.
	 * @param array  $settings Gateway settings.
	 * @param string $item_name Receipt line item title.
	 * @return array|WP_Error|null Null when receipts are disabled.
	 */
	public static function build_from_order( $order, array $settings, $item_name = '' ) {
		if ( ! self::is_enabled( $settings ) ) {
			return null;
		}

		$email = sanitize_email( (string) ( $order->email ?? '' ) );

		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'yookassa_receipt_email',
				__( 'Для отправки чека 54-ФЗ нужен корректный email покупателя.', 'art-lms' )
			);
		}

		$amount_value = number_format( (float) ( $order->amount ?? 0 ), 2, '.', '' );

		if ( (float) $amount_value <= 0 ) {
			return new WP_Error(
				'yookassa_receipt_amount',
				__( 'Сумма заказа должна быть больше нуля для формирования чека.', 'art-lms' )
			);
		}

		$item_name = self::normalize_item_name( $item_name );

		$receipt = array(
			'customer' => array(
				'email' => $email,
			),
			'items'    => array(
				array(
					'description'     => $item_name,
					'quantity'        => '1.00',
					'amount'          => array(
						'value'    => $amount_value,
						'currency' => 'RUB',
					),
					'vat_code'        => self::sanitize_vat_code( $settings['receipt_vat_code'] ?? 1 ),
					'payment_mode'    => self::sanitize_payment_mode( $settings['receipt_payment_mode'] ?? 'full_payment' ),
					'payment_subject' => self::sanitize_payment_subject( $settings['receipt_payment_subject'] ?? 'service' ),
				),
			),
		);

		$phone = self::normalize_phone( (string) ( $order->phone ?? '' ) );

		if ( '' !== $phone ) {
			$receipt['customer']['phone'] = $phone;
		}

		$tax_system_code = self::sanitize_tax_system_code( $settings['receipt_tax_system_code'] ?? '' );

		if ( $tax_system_code > 0 ) {
			$receipt['tax_system_code'] = $tax_system_code;
		}

		return $receipt;
	}

	/**
	 * @param string $name Item name.
	 * @return string
	 */
	public static function normalize_item_name( $name ) {
		$name = trim( wp_strip_all_tags( (string) $name ) );

		if ( '' === $name ) {
			$name = __( 'Цифровой продукт', 'art-lms' );
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $name, 0, self::ITEM_DESCRIPTION_MAX );
		}

		return substr( $name, 0, self::ITEM_DESCRIPTION_MAX );
	}

	/**
	 * @param string $phone Raw phone.
	 * @return string
	 */
	public static function normalize_phone( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );

		if ( '' === $digits ) {
			return '';
		}

		if ( 11 === strlen( $digits ) && '8' === $digits[0] ) {
			$digits = '7' . substr( $digits, 1 );
		}

		if ( 10 === strlen( $digits ) ) {
			$digits = '7' . $digits;
		}

		if ( 11 !== strlen( $digits ) || '7' !== $digits[0] ) {
			return '';
		}

		return '+' . $digits;
	}

	/**
	 * @param mixed $value VAT code.
	 * @return int
	 */
	public static function sanitize_vat_code( $value ) {
		$allowed = array_keys( self::get_vat_code_options() );
		$code    = (int) $value;

		return in_array( $code, $allowed, true ) ? $code : 1;
	}

	/**
	 * @param string $value Payment subject.
	 * @return string
	 */
	public static function sanitize_payment_subject( $value ) {
		$value   = sanitize_key( (string) $value );
		$allowed = array_keys( self::get_payment_subject_options() );

		return in_array( $value, $allowed, true ) ? $value : 'service';
	}

	/**
	 * @param string $value Payment mode.
	 * @return string
	 */
	public static function sanitize_payment_mode( $value ) {
		$value   = sanitize_key( (string) $value );
		$allowed = array_keys( self::get_payment_mode_options() );

		return in_array( $value, $allowed, true ) ? $value : 'full_payment';
	}

	/**
	 * @param mixed $value Tax system code.
	 * @return int
	 */
	public static function sanitize_tax_system_code( $value ) {
		if ( '' === (string) $value || '0' === (string) $value ) {
			return 0;
		}

		$code    = (int) $value;
		$allowed = array_keys( self::get_tax_system_options() );

		return in_array( $code, $allowed, true ) ? $code : 0;
	}

	/**
	 * @return array<int, string>
	 */
	public static function get_vat_code_options() {
		return array(
			1  => __( 'Без НДС', 'art-lms' ),
			2  => __( 'НДС 0%', 'art-lms' ),
			3  => __( 'НДС 10%', 'art-lms' ),
			4  => __( 'НДС 20%', 'art-lms' ),
			5  => __( 'НДС 10/110', 'art-lms' ),
			6  => __( 'НДС 20/120', 'art-lms' ),
			7  => __( 'НДС 5%', 'art-lms' ),
			8  => __( 'НДС 7%', 'art-lms' ),
			11 => __( 'НДС 5/105', 'art-lms' ),
			12 => __( 'НДС 7/107', 'art-lms' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_payment_subject_options() {
		return array(
			'service'              => __( 'Услуга', 'art-lms' ),
			'intellectual_activity' => __( 'Результаты интеллектуальной деятельности', 'art-lms' ),
			'commodity'            => __( 'Товар', 'art-lms' ),
			'payment'              => __( 'Платёж', 'art-lms' ),
			'another'              => __( 'Иной предмет расчёта', 'art-lms' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_payment_mode_options() {
		return array(
			'full_payment' => __( 'Полный расчёт', 'art-lms' ),
			'full_prepayment' => __( 'Полная предоплата', 'art-lms' ),
			'partial_prepayment' => __( 'Частичная предоплата', 'art-lms' ),
			'advance'      => __( 'Аванс', 'art-lms' ),
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function get_tax_system_options() {
		return array(
			1 => __( 'ОСН', 'art-lms' ),
			2 => __( 'УСН (доход)', 'art-lms' ),
			3 => __( 'УСН (доход минус расход)', 'art-lms' ),
			4 => __( 'ЕНВД', 'art-lms' ),
			5 => __( 'ЕСХН', 'art-lms' ),
			6 => __( 'Патент', 'art-lms' ),
		);
	}
}
