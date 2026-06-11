<?php
/**
 * Standalone checkout page without theme header/footer.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'art-lms-checkout-standalone-body' ); ?>>
<?php wp_body_open(); ?>
<div class="art-lms-checkout-page art-lms-checkout-page--standalone">
	<?php Art_LMS_Checkout::render_content(); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
