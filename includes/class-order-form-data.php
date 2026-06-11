<?php
/**
 * Order checkout form data helpers.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Order_Form_Data
 */
class Art_LMS_Order_Form_Data {

	/**
	 * Parse checkout submission into storable snapshot.
	 *
	 * @param array $input Raw request data (POST keys => values).
	 * @return array{fields: array<int, array<string, string>>}
	 */
	public static function parse_submission( array $input ) {
		$rows = array();

		foreach ( Art_LMS_Settings::get_checkout_form_fields() as $field ) {
			$post_key = Art_LMS_Settings::get_checkout_field_post_key( $field['key'] );
			$value    = isset( $input[ $post_key ] ) ? sanitize_text_field( wp_unslash( $input[ $post_key ] ) ) : '';

			if ( 'email' === $field['input'] ) {
				$value = sanitize_email( $value );
			}

			$rows[] = array(
				'key'   => (string) $field['key'],
				'label' => (string) $field['label'],
				'value' => $value,
				'type'  => 'field',
			);
		}

		foreach ( Art_LMS_Settings::get_checkout_consents()['items'] as $consent ) {
			$post_key = (string) ( $consent['post_key'] ?? '' );
			$checked  = ! empty( $input[ $post_key ] );
			$label    = wp_strip_all_tags( Art_LMS_Settings::format_checkout_consent_label( $consent ) );

			if ( '' === $label ) {
				$label = (string) ( $consent['admin_label'] ?? $consent['key'] );
			}

			$rows[] = array(
				'key'   => (string) ( $consent['key'] ?? '' ),
				'label' => $label,
				'value' => $checked ? __( 'Да', 'art-lms' ) : __( 'Нет', 'art-lms' ),
				'type'  => 'consent',
			);
		}

		return array(
			'fields' => $rows,
		);
	}

	/**
	 * Build snapshot from standard order columns when form_data is empty.
	 *
	 * @param string $name  Buyer name.
	 * @param string $email Buyer email.
	 * @param string $phone Buyer phone.
	 * @return array{fields: array<int, array<string, string>>}
	 */
	public static function build_snapshot_from_columns( $name, $email, $phone ) {
		$rows    = array();
		$values  = array(
			'full_name' => (string) $name,
			'email'     => (string) $email,
			'phone'     => (string) $phone,
		);
		$checkout = Art_LMS_Settings::get_checkout();

		foreach ( Art_LMS_Settings::get_checkout_builtin_field_keys() as $key ) {
			$field = $checkout['fields'][ $key ] ?? array();

			if ( ( $field['enabled'] ?? 'no' ) !== 'yes' ) {
				continue;
			}

			$rows[] = array(
				'key'   => $key,
				'label' => (string) ( $field['label'] ?? Art_LMS_Settings::get_checkout_field_catalog()[ $key ] ?? $key ),
				'value' => $values[ $key ] ?? '',
				'type'  => 'field',
			);
		}

		return array(
			'fields' => $rows,
		);
	}

	/**
	 * Encode snapshot for DB storage.
	 *
	 * @param array $snapshot Parsed snapshot.
	 * @return string
	 */
	public static function encode( array $snapshot ) {
		return wp_json_encode( $snapshot, JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Decode stored form data.
	 *
	 * @param string|null $raw Raw JSON.
	 * @return array{fields: array<int, array<string, string>>}
	 */
	public static function decode( $raw ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array( 'fields' => array() );
		}

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || ! is_array( $data['fields'] ?? null ) ) {
			return array( 'fields' => array() );
		}

		$fields = array();

		foreach ( $data['fields'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$fields[] = array(
				'key'   => sanitize_key( (string) ( $row['key'] ?? '' ) ),
				'label' => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
				'value' => sanitize_text_field( (string) ( $row['value'] ?? '' ) ),
				'type'  => in_array( $row['type'] ?? '', array( 'field', 'consent' ), true ) ? $row['type'] : 'field',
			);
		}

		return array(
			'fields' => $fields,
		);
	}

	/**
	 * Get display rows for an order (stored snapshot or standard columns).
	 *
	 * @param object $order Order object.
	 * @return array{fields: array<int, array<string, string>>, consents: array<int, array<string, string>>}
	 */
	public static function get_display_groups( $order ) {
		$snapshot = self::decode( $order->form_data ?? '' );

		if ( empty( $snapshot['fields'] ) ) {
			$snapshot = self::build_snapshot_from_columns(
				$order->name ?? '',
				$order->email ?? '',
				$order->phone ?? ''
			);
		}

		$fields   = array();
		$consents = array();

		foreach ( $snapshot['fields'] as $row ) {
			if ( 'consent' === ( $row['type'] ?? 'field' ) ) {
				$consents[] = $row;
			} else {
				$fields[] = $row;
			}
		}

		return array(
			'fields'   => $fields,
			'consents' => $consents,
		);
	}

	/**
	 * Format all form rows for admin email placeholder.
	 *
	 * @param object $order Order object.
	 * @return string
	 */
	public static function format_for_email( $order ) {
		$groups = self::get_display_groups( $order );
		$lines  = array();

		foreach ( $groups['fields'] as $row ) {
			if ( '' === trim( (string) ( $row['value'] ?? '' ) ) ) {
				continue;
			}

			$lines[] = ( $row['label'] ?? '' ) . ': ' . ( $row['value'] ?? '' );
		}

		foreach ( $groups['consents'] as $row ) {
			$lines[] = ( $row['label'] ?? '' ) . ': ' . ( $row['value'] ?? '' );
		}

		if ( empty( $lines ) ) {
			return '—';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Refresh stored snapshot when admin updates standard buyer columns.
	 *
	 * @param string $stored_raw Stored JSON.
	 * @param string $name       Buyer name.
	 * @param string $email      Buyer email.
	 * @param string $phone      Buyer phone.
	 * @return string
	 */
	public static function refresh_snapshot_from_columns( $stored_raw, $name, $email, $phone ) {
		$snapshot   = self::decode( $stored_raw );
		$has_custom = false;

		foreach ( $snapshot['fields'] as $row ) {
			if ( 0 === strpos( (string) ( $row['key'] ?? '' ), 'custom_' ) || 'consent' === ( $row['type'] ?? '' ) ) {
				$has_custom = true;
				break;
			}
		}

		if ( ! $has_custom ) {
			return self::encode(
				self::build_snapshot_from_columns( $name, $email, $phone )
			);
		}

		foreach ( $snapshot['fields'] as $index => $row ) {
			if ( 'full_name' === ( $row['key'] ?? '' ) ) {
				$snapshot['fields'][ $index ]['value'] = (string) $name;
			}

			if ( 'email' === ( $row['key'] ?? '' ) ) {
				$snapshot['fields'][ $index ]['value'] = (string) $email;
			}

			if ( 'phone' === ( $row['key'] ?? '' ) ) {
				$snapshot['fields'][ $index ]['value'] = (string) $phone;
			}
		}

		return self::encode( $snapshot );
	}
}
