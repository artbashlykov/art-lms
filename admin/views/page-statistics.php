<?php
/**
 * Statistics admin page.
 *
 * @package Art_LMS
 *
 * @var array $period    Active period filter.
 * @var array $dashboard Dashboard payload.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$presets         = Art_LMS_Statistics::get_period_presets();
$active_period   = $period['period'] ?? Art_LMS_Statistics::PERIOD_30;
$period_label    = $period['label'] ?? '';
$kpis            = $dashboard['kpis'] ?? array();
$revenue_by_day  = $dashboard['revenue_by_day'] ?? array();
$products        = $dashboard['products'] ?? array();
$funnel          = $dashboard['funnel'] ?? array();
$funnel_steps    = $funnel['steps'] ?? array();
$gateways        = $dashboard['gateways'] ?? array();
$chart_max       = (float) ( $dashboard['chart_max'] ?? 0 );
$has_chart_data  = $chart_max > 0;
$chart_height_px = 220;
?>
<div class="wrap art-lms-admin art-lms-statistics-page">
	<h1><?php esc_html_e( 'ART LMS — Статистика', 'art-lms' ); ?></h1>

	<div class="art-lms-panel art-lms-statistics-filters">
		<div class="art-lms-statistics-filters__presets">
			<?php foreach ( $presets as $preset_key => $preset_label ) : ?>
				<a
					href="<?php echo esc_url( Art_LMS_Admin_Statistics::get_period_url( $preset_key ) ); ?>"
					class="button<?php echo esc_attr( $active_period === $preset_key ? ' button-primary' : '' ); ?>"
				>
					<?php echo esc_html( $preset_label ); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<form method="get" class="art-lms-statistics-filters__custom">
			<input type="hidden" name="page" value="<?php echo esc_attr( Art_LMS_Admin_Statistics::PAGE_LIST ); ?>">
			<input type="hidden" name="period" value="<?php echo esc_attr( Art_LMS_Statistics::PERIOD_CUSTOM ); ?>">

			<label for="art_lms_stats_date_from"><?php esc_html_e( 'С', 'art-lms' ); ?></label>
			<input
				type="text"
				name="date_from"
				id="art_lms_stats_date_from"
				class="art-lms-date-input"
				value="<?php echo esc_attr( $period['date_from'] ?? '' ); ?>"
				autocomplete="off"
				placeholder="<?php esc_attr_e( 'гггг-мм-дд', 'art-lms' ); ?>"
			>

			<label for="art_lms_stats_date_to"><?php esc_html_e( 'По', 'art-lms' ); ?></label>
			<input
				type="text"
				name="date_to"
				id="art_lms_stats_date_to"
				class="art-lms-date-input"
				value="<?php echo esc_attr( $period['date_to'] ?? '' ); ?>"
				autocomplete="off"
				placeholder="<?php esc_attr_e( 'гггг-мм-дд', 'art-lms' ); ?>"
			>

			<button type="submit" class="button"><?php esc_html_e( 'Применить', 'art-lms' ); ?></button>
		</form>

		<p class="description art-lms-statistics-filters__label">
			<?php
			printf(
				/* translators: %s: selected period label */
				esc_html__( 'Период: %s', 'art-lms' ),
				esc_html( $period_label )
			);
			?>
		</p>
	</div>

	<div class="art-lms-statistics-kpis">
		<?php foreach ( $kpis as $kpi ) : ?>
			<div class="art-lms-statistics-kpi art-lms-statistics-kpi--<?php echo esc_attr( $kpi['key'] ); ?>">
				<div class="art-lms-statistics-kpi__label"><?php echo esc_html( $kpi['label'] ); ?></div>
				<div class="art-lms-statistics-kpi__value"><?php echo esc_html( $kpi['value'] ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="art-lms-statistics-panels-grid">
		<div class="art-lms-panel art-lms-statistics-funnel-panel">
			<div class="art-lms-statistics-panel-heading">
				<h2><?php esc_html_e( 'Воронка заказов', 'art-lms' ); ?></h2>
				<?php if ( ! empty( $funnel['total'] ) ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: conversion percent */
							esc_html__( 'Конверсия в оплату: %s%%', 'art-lms' ),
							esc_html( number_format_i18n( (float) ( $funnel['conversion'] ?? 0 ), 1 ) )
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( empty( $funnel_steps ) || empty( $funnel['total'] ) ) : ?>
				<p class="description"><?php esc_html_e( 'За выбранный период заказов пока нет.', 'art-lms' ); ?></p>
			<?php else : ?>
				<div class="art-lms-statistics-funnel">
					<?php foreach ( $funnel_steps as $step ) : ?>
						<div class="art-lms-statistics-funnel__step art-lms-statistics-funnel__step--<?php echo esc_attr( $step['key'] ); ?>">
							<div class="art-lms-statistics-funnel__label">
								<?php if ( ! empty( $step['url'] ) ) : ?>
									<a href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['label'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $step['label'] ); ?>
								<?php endif; ?>
							</div>
							<div class="art-lms-statistics-funnel__bar" aria-hidden="true">
								<span style="width: <?php echo esc_attr( (string) min( 100, (float) $step['share'] ) ); ?>%;"></span>
							</div>
							<div class="art-lms-statistics-funnel__count"><?php echo esc_html( number_format_i18n( (int) $step['count'] ) ); ?></div>
							<div class="art-lms-statistics-funnel__share"><?php echo esc_html( number_format_i18n( (float) $step['share'], 1 ) ); ?>%</div>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Считается по дате создания заказа за выбранный период.', 'art-lms' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="art-lms-panel art-lms-statistics-gateways-panel">
			<h2><?php esc_html_e( 'Платёжные шлюзы', 'art-lms' ); ?></h2>

			<?php if ( empty( $gateways ) ) : ?>
				<p class="description"><?php esc_html_e( 'За выбранный период заказов по шлюзам пока нет.', 'art-lms' ); ?></p>
			<?php else : ?>
				<table class="widefat striped art-lms-statistics-gateways-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Шлюз', 'art-lms' ); ?></th>
							<th><?php esc_html_e( 'Заказов', 'art-lms' ); ?></th>
							<th><?php esc_html_e( 'Оплачено', 'art-lms' ); ?></th>
							<th><?php esc_html_e( 'Выручка', 'art-lms' ); ?></th>
							<th><?php esc_html_e( 'Конверсия', 'art-lms' ); ?></th>
							<th><?php esc_html_e( 'Доля', 'art-lms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $gateways as $gateway ) : ?>
							<tr>
								<td><?php echo esc_html( $gateway['title'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $gateway['orders_count'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $gateway['paid_count'] ) ); ?></td>
								<td><?php echo esc_html( $gateway['revenue_fmt'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $gateway['conversion'], 1 ) ); ?>%</td>
								<td><?php echo esc_html( number_format_i18n( (float) $gateway['revenue_share'], 1 ) ); ?>%</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Заказы — по дате создания, выручка — по оплаченным заказам из этого же набора.', 'art-lms' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<div class="art-lms-panel art-lms-statistics-chart-panel">
		<h2><?php esc_html_e( 'Выручка по дням', 'art-lms' ); ?></h2>

		<?php if ( empty( $revenue_by_day ) ) : ?>
			<p class="description"><?php esc_html_e( 'За выбранный период оплат пока нет.', 'art-lms' ); ?></p>
		<?php else : ?>
			<div class="art-lms-statistics-chart" style="--art-lms-chart-height: <?php echo esc_attr( (string) $chart_height_px ); ?>px;">
				<div class="art-lms-statistics-chart__bars" role="img" aria-label="<?php esc_attr_e( 'График выручки по дням', 'art-lms' ); ?>">
					<?php foreach ( $revenue_by_day as $day ) : ?>
						<?php
						$bar_height = $has_chart_data
							? max( 4, round( ( (float) $day['revenue'] / $chart_max ) * $chart_height_px ) )
							: 4;
						$revenue_fmt = Art_LMS_Statistics::format_money( (float) $day['revenue'] );
						?>
						<div class="art-lms-statistics-chart__bar-wrap">
							<div
								class="art-lms-statistics-chart__bar<?php echo esc_attr( (float) $day['revenue'] > 0 ? '' : ' is-empty' ); ?>"
								style="height: <?php echo esc_attr( (string) $bar_height ); ?>px;"
								aria-label="<?php echo esc_attr( sprintf( '%s: %s', $day['label'], $revenue_fmt ) ); ?>"
							>
								<div class="art-lms-statistics-chart__tooltip" role="tooltip">
									<?php echo esc_html( $revenue_fmt ); ?>
								</div>
							</div>
							<div class="art-lms-statistics-chart__bar-label"><?php echo esc_html( $day['label'] ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="art-lms-panel art-lms-statistics-products-panel">
		<h2><?php esc_html_e( 'Продажи по платёжным кнопкам', 'art-lms' ); ?></h2>

		<?php if ( empty( $products ) ) : ?>
			<p class="description"><?php esc_html_e( 'За выбранный период продаж по кнопкам пока нет.', 'art-lms' ); ?></p>
		<?php else : ?>
			<table class="widefat striped art-lms-statistics-products-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Платёжная кнопка', 'art-lms' ); ?></th>
						<th><?php esc_html_e( 'Продаж', 'art-lms' ); ?></th>
						<th><?php esc_html_e( 'Выручка', 'art-lms' ); ?></th>
						<th><?php esc_html_e( 'Доля', 'art-lms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $products as $product ) : ?>
						<tr>
							<td>
								<?php if ( ! empty( $product['edit_url'] ) ) : ?>
									<a href="<?php echo esc_url( $product['edit_url'] ); ?>"><?php echo esc_html( $product['title'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $product['title'] ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format_i18n( (int) $product['sales_count'] ) ); ?></td>
							<td><?php echo esc_html( $product['revenue_fmt'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (float) $product['share'], 1 ) ); ?>%</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
