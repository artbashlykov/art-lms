<?php
/**
 * Payment button settings meta box view.
 *
 * @package Art_LMS
 *
 * @var WP_Post $post
 * @var array   $meta
 * @var array   $materials
 * @var array   $presets
 * @var int     $access_days
 * @var string  $access_mode
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$selected_material_ids = $meta['material_ids'] ?? array();
$selected_materials    = Art_LMS_Admin_Payment_Button_Editor::get_selected_materials( $selected_material_ids );
$materials_catalog     = $materials_catalog ?? array();
?>
<div class="art-lms-payment-button-meta-box">
	<p class="description">
		<?php esc_html_e( 'Заголовок записи выше — внутреннее название кнопки только для админки.', 'art-lms' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="art_lms_product_name"><?php esc_html_e( 'Название продукта', 'art-lms' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						class="regular-text"
						id="art_lms_product_name"
						name="art_lms_product_name"
						value="<?php echo esc_attr( $meta['product_name'] ?? '' ); ?>"
					>
					<p class="description"><?php esc_html_e( 'Показывается на странице сайта и на checkout.', 'art-lms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_compare_price"><?php esc_html_e( 'Зачёркнутая цена (₽)', 'art-lms' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						class="regular-text"
						id="art_lms_compare_price"
						name="art_lms_compare_price"
						value="<?php echo esc_attr( $meta['compare_price'] ?? '' ); ?>"
						inputmode="decimal"
					>
					<p class="description"><?php esc_html_e( 'Необязательно. Старая цена «до скидки».', 'art-lms' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_price"><?php esc_html_e( 'Цена (₽)', 'art-lms' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						class="regular-text"
						id="art_lms_price"
						name="art_lms_price"
						value="<?php echo esc_attr( $meta['price'] ?? '' ); ?>"
						min="0"
						step="0.01"
					>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="art_lms_access_mode"><?php esc_html_e( 'Срок доступа', 'art-lms' ); ?></label>
				</th>
				<td>
					<select id="art_lms_access_mode" name="art_lms_access_mode" class="art-lms-access-mode">
						<?php foreach ( $presets as $preset ) : ?>
							<option value="<?php echo esc_attr( (string) $preset['value'] ); ?>" <?php selected( $access_mode, (string) $preset['value'] ); ?>>
								<?php echo esc_html( $preset['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Срок считается с момента оплаты для всех материалов этой кнопки.', 'art-lms' ); ?></p>
					<p class="art-lms-access-days-custom-wrap"<?php if ( 'custom' !== $access_mode ) : ?> style="display:none;"<?php endif; ?>>
						<label for="art_lms_access_days_custom"><?php esc_html_e( 'Количество дней', 'art-lms' ); ?></label>
						<input
							type="number"
							class="small-text"
							id="art_lms_access_days_custom"
							name="art_lms_access_days_custom"
							value="<?php echo esc_attr( 'custom' === $access_mode ? (string) $access_days : '30' ); ?>"
							min="1"
						>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Материалы', 'art-lms' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Добавьте материалы, которые откроются покупателю после оплаты.', 'art-lms' ); ?></p>

	<?php if ( empty( $materials_catalog ) ) : ?>
		<p><?php esc_html_e( 'Сначала создайте материалы в разделе «Материалы».', 'art-lms' ); ?></p>
	<?php else : ?>
		<div
			class="art-lms-material-picker"
			data-empty-label="<?php echo esc_attr__( 'Материалы не добавлены.', 'art-lms' ); ?>"
		>
			<div class="art-lms-material-picker__controls">
				<select id="art_lms_material_picker_select" class="art-lms-material-picker__select">
					<option value=""><?php esc_html_e( '— Выберите материал —', 'art-lms' ); ?></option>
					<?php foreach ( $materials_catalog as $material_id => $material_title ) : ?>
						<?php if ( isset( $selected_materials[ $material_id ] ) ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<option value="<?php echo esc_attr( (string) $material_id ); ?>">
							<?php echo esc_html( $material_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button art-lms-material-picker__add">
					<?php esc_html_e( 'Добавить', 'art-lms' ); ?>
				</button>
			</div>

			<ul class="art-lms-material-picker__selected" id="art_lms_material_selected_list">
				<?php foreach ( $selected_materials as $material_id => $material_title ) : ?>
					<li class="art-lms-material-picker__item" data-material-id="<?php echo esc_attr( (string) $material_id ); ?>">
						<span class="art-lms-material-picker__title"><?php echo esc_html( $material_title ); ?></span>
						<button type="button" class="button-link-delete art-lms-material-picker__remove">
							<?php esc_html_e( 'Удалить', 'art-lms' ); ?>
						</button>
						<input type="hidden" name="art_lms_material_ids[]" value="<?php echo esc_attr( (string) $material_id ); ?>">
					</li>
				<?php endforeach; ?>
			</ul>

			<script type="application/json" id="art_lms_material_catalog">
				<?php
				echo wp_json_encode(
					$materials_catalog,
					JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
				);
				?>
			</script>
		</div>
	<?php endif; ?>

	<script type="application/json" id="art_lms_payment_button_initial_state">
		<?php
		echo wp_json_encode(
			array(
				'title'            => $post->post_title,
				'productName'      => $meta['product_name'] ?? '',
				'comparePrice'     => $meta['compare_price'] ?? '',
				'price'            => $meta['price'] ?? '',
				'accessMode'       => $access_mode,
				'accessDaysCustom' => 'custom' === $access_mode ? (string) $access_days : '30',
				'materialIds'      => array_values( $selected_material_ids ),
				'enabled'          => ! empty( $meta['enabled'] ) ? '1' : '0',
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		?>
	</script>
</div>
