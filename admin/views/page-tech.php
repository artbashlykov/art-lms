<?php
/**
 * Technical settings hub page.
 *
 * @package Art_LMS
 *
 * @var string $active_tab Active tab slug.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$wrap_class = 'wrap art-lms-admin';

if ( Art_LMS_Admin_Settings::TAB_EMAIL === $active_tab ) {
	$wrap_class .= ' art-lms-settings-email-page';
} elseif ( Art_LMS_Admin_Settings::TAB_CHECKOUT === $active_tab ) {
	$wrap_class .= ' art-lms-settings-checkout-page';
} elseif ( Art_LMS_Admin_Settings::TAB_DESIGN === $active_tab ) {
	$wrap_class .= ' art-lms-settings-checkout-design-page';
}
?>
<div class="<?php echo esc_attr( $wrap_class ); ?>">
	<h1><?php esc_html_e( 'ART LMS — Настройки формы', 'art-lms' ); ?></h1>

	<?php
	Art_LMS_Admin_Settings::render_tabs(
		Art_LMS_Admin_Settings::PAGE_TECH,
		array(
			Art_LMS_Admin_Settings::TAB_CHECKOUT     => __( 'Настройки полей', 'art-lms' ),
			Art_LMS_Admin_Settings::TAB_DESIGN       => __( 'Дизайн формы', 'art-lms' ),
			Art_LMS_Admin_Settings::TAB_CONFIRMATION => __( 'Страница подтверждения', 'art-lms' ),
			Art_LMS_Admin_Settings::TAB_EMAIL        => __( 'Сообщения на почту', 'art-lms' ),
		),
		$active_tab
	);
	?>

	<?php if ( Art_LMS_Admin_Settings::TAB_EMAIL === $active_tab ) : ?>
		<?php Art_LMS_Admin_Settings::render_email_partial(); ?>
	<?php elseif ( Art_LMS_Admin_Settings::TAB_DESIGN === $active_tab ) : ?>
		<?php Art_LMS_Admin_Settings::render_design_partial(); ?>
	<?php elseif ( Art_LMS_Admin_Settings::TAB_CONFIRMATION === $active_tab ) : ?>
		<?php Art_LMS_Admin_Settings::render_confirmation_partial(); ?>
	<?php else : ?>
		<?php Art_LMS_Admin_Settings::render_checkout_partial(); ?>
	<?php endif; ?>
</div>
