<?php
/**
 * Custom login page content.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$redirect_to      = Art_LMS_Custom_Login::get_sanitized_redirect_to_from_get();
$form_settings    = Art_LMS_Settings::get_login_form();
$button_settings  = Art_LMS_Settings::get_login_button();
$subtitle_enabled = 'yes' === ( $form_settings['subtitle_enabled'] ?? 'no' );
$lost_pw_enabled  = 'yes' === ( $form_settings['lost_password_enabled'] ?? 'yes' );
?>
<div class="art-lms-login <?php echo esc_attr( Art_LMS_Settings::get_login_button_wrapper_class() ); ?>">
	<?php if ( 'yes' === ( $form_settings['title_enabled'] ?? 'yes' ) ) : ?>
		<h1 class="art-lms-login__title"><?php echo esc_html( $form_settings['title_text'] ); ?></h1>
	<?php endif; ?>

	<?php if ( $subtitle_enabled && '' !== trim( (string) ( $form_settings['subtitle_text'] ?? '' ) ) ) : ?>
		<p class="art-lms-login__subtitle"><?php echo esc_html( $form_settings['subtitle_text'] ); ?></p>
	<?php endif; ?>

	<?php
	$form_args = array(
		'form_id'        => 'art-lms-loginform',
		'label_username' => $form_settings['username_label'],
		'label_password' => $form_settings['password_label'],
		'label_remember' => $form_settings['remember_label'],
		'label_log_in'   => $button_settings['text'],
		'remember'       => 'yes' === ( $form_settings['remember_enabled'] ?? 'yes' ),
	);

	if ( '' !== $redirect_to ) {
		$form_args['redirect'] = $redirect_to;
	}

	wp_login_form( $form_args );
	?>

	<?php if ( $lost_pw_enabled ) : ?>
		<p class="art-lms-login__links">
			<a href="<?php echo esc_url( wp_lostpassword_url( $redirect_to ) ); ?>">
				<?php echo esc_html( $form_settings['lost_password_text'] ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
