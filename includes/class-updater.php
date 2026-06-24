<?php
/**
 * GitHub update checker for ART LMS.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Updater
 */
class Art_LMS_Updater {

	const GITHUB_REPO = 'artbashlykov/art-lms';

	/**
	 * Register update checker.
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		$library = ART_LMS_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

		if ( ! file_exists( $library ) ) {
			return;
		}

		require_once $library;

		$checker = \YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
			'https://github.com/' . self::GITHUB_REPO . '/',
			ART_LMS_PLUGIN_FILE,
			ART_LMS_ADMIN_MENU_SLUG
		);

		$checker->addFilter( 'view_details_link', '__return_empty_string' );

		$checker->getVcsApi()->enableReleaseAssets( '/^art-lms\.zip$/i' );

		$token = self::get_github_token();

		if ( '' !== $token ) {
			$checker->setAuthentication( $token );
		}
	}

	/**
	 * GitHub token for private repository access.
	 *
	 * Public repo does not require a token. For private forks add to wp-config.php:
	 * define( 'ART_LMS_GITHUB_TOKEN', 'your-github-token' );
	 *
	 * @return string
	 */
	private static function get_github_token() {
		$token = '';

		if ( defined( 'ART_LMS_GITHUB_TOKEN' ) ) {
			$token = (string) ART_LMS_GITHUB_TOKEN;
		}

		/**
		 * Filters GitHub token used to check ART LMS updates.
		 *
		 * @param string $token GitHub personal access token.
		 */
		$token = (string) apply_filters( 'art_lms_github_token', $token );

		return sanitize_text_field( $token );
	}
}
