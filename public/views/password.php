<?php
/**
 * Standalone password page without theme header/footer.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php Art_LMS_Custom_Password::print_template_styles(); ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'art-lms-login-standalone-body art-lms-password-standalone-body' ); ?>>
<?php wp_body_open(); ?>
<div class="art-lms-login-page art-lms-login-page--standalone art-lms-password-page">
	<?php Art_LMS_Custom_Password::render_content(); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
