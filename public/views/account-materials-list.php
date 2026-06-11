<?php
/**
 * Account materials list partial.
 *
 * @package Art_LMS
 *
 * @var array<int, array<string, mixed>> $materials Materials to render.
 * @var array                            $settings  Normalized account settings.
 * @var string                           $button_style Inline button CSS declarations.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

if ( empty( $materials ) ) {
	return;
}
?>
<ul class="art-lms-account__list">
	<?php foreach ( $materials as $material ) : ?>
		<?php
		$status_class = 'art-lms-account__status';

		if ( ! empty( $material['expiring_soon'] ) ) {
			$status_class .= ' art-lms-account__status--soon';
		}
		?>
		<li class="art-lms-account__item">
			<div class="art-lms-account__item-body">
				<h4 class="art-lms-account__item-title">
					<a href="<?php echo esc_url( $material['url'] ); ?>">
						<?php echo esc_html( $material['title'] ); ?>
					</a>
				</h4>

				<?php if ( ! $settings['hide_access_label'] ) : ?>
					<p class="<?php echo esc_attr( $status_class ); ?>">
						<?php echo esc_html( $material['access_label'] ); ?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( ! $settings['hide_open_button'] ) : ?>
				<div class="art-lms-account__item-actions">
					<a class="art-lms-button art-lms-button--small" href="<?php echo esc_url( $material['url'] ); ?>"<?php if ( $button_style ) : ?> style="<?php echo esc_attr( $button_style ); ?>"<?php endif; ?>>
						<?php echo esc_html( $settings['open_button_text'] ); ?>
					</a>
				</div>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
