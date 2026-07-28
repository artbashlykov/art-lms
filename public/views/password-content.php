<?php
/**
 * Custom password page content (lost password + reset).
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

$mode           = Art_LMS_Custom_Password::get_current_mode();
$settings       = Art_LMS_Settings::get_password();
$lost           = Art_LMS_Settings::get_password_lost_form();
$reset          = Art_LMS_Settings::get_password_reset_form();
$messages       = Art_LMS_Settings::get_password_messages();
$errors         = Art_LMS_Custom_Password::get_errors();
$redirect_to    = Art_LMS_Custom_Login::get_sanitized_redirect_to_from_get();
$login_url      = Art_LMS_Settings::get_login_page_url( $redirect_to );
$button_class   = Art_LMS_Settings::get_login_button_wrapper_class();
$user_login_val = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Display of posted value after validation error.
?>
<div class="art-lms-login-page-shell">
<div class="art-lms-login art-lms-password <?php echo esc_attr( $button_class ); ?>">

	<?php if ( is_wp_error( $errors ) && $errors->has_errors() ) : ?>
		<div class="art-lms-password__errors" role="alert">
			<?php foreach ( $errors->get_error_messages() as $message ) : ?>
				<p><?php echo esc_html( $message ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( 'checkemail' === $mode ) : ?>
		<?php if ( 'yes' === ( $lost['title_enabled'] ?? 'yes' ) ) : ?>
			<h1 class="art-lms-login__title"><?php echo esc_html( $lost['title_text'] ); ?></h1>
		<?php endif; ?>
		<p class="art-lms-login__subtitle"><?php echo esc_html( $messages['checkemail'] ); ?></p>
		<p class="art-lms-login__links">
			<a href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( $lost['login_link_text'] ); ?></a>
		</p>

	<?php elseif ( 'changed' === $mode ) : ?>
		<?php if ( 'yes' === ( $reset['title_enabled'] ?? 'yes' ) ) : ?>
			<h1 class="art-lms-login__title"><?php echo esc_html( $reset['title_text'] ); ?></h1>
		<?php endif; ?>
		<p class="art-lms-login__subtitle"><?php echo esc_html( $messages['password_changed'] ); ?></p>
		<p class="submit">
			<a class="button button-primary" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( $lost['login_link_text'] ); ?></a>
		</p>

	<?php elseif ( 'reset' === $mode ) : ?>
		<?php
		$rp = Art_LMS_Custom_Password::get_rp_key_and_login();
		$user = $rp ? check_password_reset_key( $rp['key'], $rp['login'] ) : new WP_Error( 'invalid_key', $messages['invalid_key'] );

		if ( is_wp_error( $user ) ) :
			?>
			<?php if ( 'yes' === ( $reset['title_enabled'] ?? 'yes' ) ) : ?>
				<h1 class="art-lms-login__title"><?php echo esc_html( $reset['title_text'] ); ?></h1>
			<?php endif; ?>
			<p class="art-lms-login__subtitle"><?php echo esc_html( $messages['invalid_key'] ); ?></p>
			<p class="art-lms-login__links">
				<a href="<?php echo esc_url( Art_LMS_Custom_Password::get_lostpassword_url( $redirect_to ) ); ?>">
					<?php echo esc_html( $lost['title_text'] ); ?>
				</a>
			</p>
		<?php else : ?>
			<?php if ( 'yes' === ( $reset['title_enabled'] ?? 'yes' ) ) : ?>
				<h1 class="art-lms-login__title"><?php echo esc_html( $reset['title_text'] ); ?></h1>
			<?php endif; ?>
			<?php if ( 'yes' === ( $reset['subtitle_enabled'] ?? 'no' ) && '' !== trim( (string) ( $reset['subtitle_text'] ?? '' ) ) ) : ?>
				<p class="art-lms-login__subtitle"><?php echo esc_html( $reset['subtitle_text'] ); ?></p>
			<?php endif; ?>

			<form name="resetpassform" id="art-lms-resetpassform" action="<?php echo esc_url( Art_LMS_Custom_Password::get_url( array( 'action' => 'rp' ), $redirect_to ) ); ?>" method="post" autocomplete="off">
				<p>
					<label for="pass1"><?php echo esc_html( $reset['password_label'] ); ?></label>
					<input type="password" name="pass1" id="pass1" class="input" size="20" autocomplete="new-password" required>
				</p>
				<p>
					<label for="pass2"><?php echo esc_html( $reset['password_confirm_label'] ); ?></label>
					<input type="password" name="pass2" id="pass2" class="input" size="20" autocomplete="new-password" required>
				</p>
				<input type="hidden" name="rp_key" value="<?php echo esc_attr( $rp['key'] ); ?>">
				<input type="hidden" name="rp_login" value="<?php echo esc_attr( $rp['login'] ); ?>">
				<?php if ( '' !== $redirect_to ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
				<?php endif; ?>
				<?php wp_nonce_field( 'resetpass' ); ?>
				<p class="submit">
					<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php echo esc_attr( $reset['button_text'] ); ?>">
				</p>
			</form>
		<?php endif; ?>

	<?php else : ?>
		<?php if ( 'yes' === ( $lost['title_enabled'] ?? 'yes' ) ) : ?>
			<h1 class="art-lms-login__title"><?php echo esc_html( $lost['title_text'] ); ?></h1>
		<?php endif; ?>
		<?php if ( 'yes' === ( $lost['subtitle_enabled'] ?? 'no' ) && '' !== trim( (string) ( $lost['subtitle_text'] ?? '' ) ) ) : ?>
			<p class="art-lms-login__subtitle"><?php echo esc_html( $lost['subtitle_text'] ); ?></p>
		<?php endif; ?>

		<form name="lostpasswordform" id="art-lms-lostpasswordform" action="<?php echo esc_url( Art_LMS_Custom_Password::get_lostpassword_url( $redirect_to ) ); ?>" method="post">
			<p>
				<label for="user_login"><?php echo esc_html( $lost['email_label'] ); ?></label>
				<input type="text" name="user_login" id="user_login" class="input" value="<?php echo esc_attr( $user_login_val ); ?>" size="20" autocapitalize="off" autocomplete="email" required>
			</p>
			<?php if ( '' !== $redirect_to ) : ?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
			<?php endif; ?>
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php echo esc_attr( $lost['button_text'] ); ?>">
			</p>
		</form>

		<p class="art-lms-login__links">
			<a href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( $lost['login_link_text'] ); ?></a>
		</p>
	<?php endif; ?>
</div>

<p class="art-lms-login__back">
	<a class="art-lms-login__back-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<span class="art-lms-login__back-icon" aria-hidden="true">&larr;</span>
		<?php esc_html_e( 'Вернуться на сайт', 'art-lms' ); ?>
	</a>
</p>
</div>
