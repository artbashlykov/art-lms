<?php
/**
 * Main settings hub page.
 *
 * @package Art_LMS
 *
 * @var string $active_tab Active tab slug.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$wrap_class = 'wrap art-lms-admin';

if ( Art_LMS_Admin_Settings::TAB_LOGIN === $active_tab ) {
	$wrap_class .= ' art-lms-settings-login-page';
}
?>
<div class="<?php echo esc_attr( $wrap_class ); ?>">
	<h1><?php esc_html_e( 'ART LMS — Настройки', 'art-lms' ); ?></h1>

	<?php Art_LMS_Admin_Settings::render_settings_saved_notice(); ?>

	<?php
	Art_LMS_Admin_Settings::render_tabs(
		Art_LMS_Admin_Settings::PAGE_SETTINGS,
		array(
			Art_LMS_Admin_Settings::TAB_GENERAL  => __( 'Основные', 'art-lms' ),
			Art_LMS_Admin_Settings::TAB_LOGIN    => __( 'Страница входа', 'art-lms' ),
			Art_LMS_Admin_Settings::TAB_PAYMENTS => __( 'Прием платежей', 'art-lms' ),
		),
		$active_tab
	);
	?>

	<?php if ( Art_LMS_Admin_Settings::TAB_PAYMENTS === $active_tab ) : ?>
		<?php Art_LMS_Admin_Settings::render_payments_partial(); ?>
	<?php elseif ( Art_LMS_Admin_Settings::TAB_LOGIN === $active_tab ) : ?>
		<?php Art_LMS_Admin_Settings::render_login_partial(); ?>
	<?php else : ?>
		<?php Art_LMS_Admin_Settings::render_general_partial(); ?>
	<?php endif; ?>
</div>
