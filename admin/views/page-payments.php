<?php
/**
 * Payment gateways admin page.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables scoped to this view.

?>
<div class="wrap art-lms-admin">
	<h1><?php esc_html_e( 'Платежные шлюзы', 'art-lms' ); ?></h1>

	<?php Art_LMS_Admin_Settings::render_settings_saved_notice(); ?>

	<?php Art_LMS_Admin_Settings::render_payments_partial(); ?>
</div>
