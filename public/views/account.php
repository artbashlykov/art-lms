<?php
/**
 * Customer account view.
 *
 * @package Art_LMS
 *
 * @var array $settings Normalized account block settings.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$account_url   = Art_LMS_Account::get_page_url();
$wrapper_attrs = Art_LMS_Account::get_wrapper_attributes( $settings );
$button_style  = Art_LMS_Account::get_button_inline_style( $settings );

$user      = wp_get_current_user();
$materials = Art_LMS_Account::get_materials_for_user( $user->ID );
$logout_url  = wp_logout_url( $account_url );
$reset_url   = Art_LMS_Account::get_reset_password_url( $account_url, $user );
$show_footer = ! $settings['hide_logout_link'] || ! $settings['hide_reset_password'];
$materials_title      = trim( (string) ( $settings['materials_title'] ?? '' ) );
$show_materials_title = empty( $settings['hide_materials_title'] ) && '' !== $materials_title;
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $show_materials_title ) : ?>
		<h3 class="art-lms-account__section-title"><?php echo esc_html( $materials_title ); ?></h3>
	<?php endif; ?>

	<?php if ( empty( $materials ) ) : ?>
		<div class="art-lms-account__empty">
			<p><?php echo esc_html( $settings['empty_message'] ); ?></p>
		</div>
	<?php else : ?>
		<?php include ART_LMS_PLUGIN_DIR . 'public/views/account-materials-list.php'; ?>
	<?php endif; ?>

	<?php if ( $show_footer ) : ?>
		<div class="art-lms-account__footer">
			<?php if ( ! $settings['hide_reset_password'] ) : ?>
				<a href="<?php echo esc_url( $reset_url ); ?>"><?php echo esc_html( $settings['reset_password_link_text'] ); ?></a>
			<?php endif; ?>
			<?php if ( ! $settings['hide_reset_password'] && ! $settings['hide_logout_link'] ) : ?>
				<span class="art-lms-account__footer-sep" aria-hidden="true">|</span>
			<?php endif; ?>
			<?php if ( ! $settings['hide_logout_link'] ) : ?>
				<a href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html( $settings['logout_link_text'] ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
