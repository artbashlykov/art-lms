<?php
/**
 * Checkout page shell (uses active theme header/footer).
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

get_header();
?>
<main id="primary" class="site-main art-lms-checkout-page art-lms-checkout-page--with-theme">
	<?php Art_LMS_Checkout::render_content(); ?>
</main>
<?php
get_footer();
