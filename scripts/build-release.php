<?php
/**
 * Build GitHub Release zip for ART LMS.
 *
 * Usage: php scripts/build-release.php
 *
 * Writes art-lms.zip to the system temp directory and prints the full path to stdout.
 *
 * @package Art_LMS
 */

if ( 'cli' === PHP_SAPI && ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

defined( 'ABSPATH' ) || exit;

/**
 * Write a message to STDERR in CLI mode.
 *
 * @param string $art_lms_message Message text.
 */
function art_lms_build_release_stderr( $art_lms_message ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI build script only.
	fwrite( STDERR, $art_lms_message );
}

/**
 * Build release zip archive.
 *
 * @param array<int, string> $art_lms_argv CLI arguments.
 * @return int Exit code.
 */
function art_lms_build_release( array $art_lms_argv ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		art_lms_build_release_stderr( "ZipArchive is required.\n" );
		return 1;
	}

	$art_lms_plugin_dir = dirname( __DIR__ );
	$art_lms_slug       = basename( $art_lms_plugin_dir );
	$art_lms_output     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $art_lms_slug . '.zip';

	$art_lms_exclude_dirs          = array( '.git', '.cursor', '.idea', '.vscode', 'node_modules', 'scripts' );
	$art_lms_exclude_file_patterns = array(
		'*.zip',
		'*.log',
		'tmp-*.php',
		'local-*.php',
	);

	/**
	 * Whether a path should be excluded from the release archive.
	 *
	 * @param string $art_lms_relative_path Path relative to plugin root.
	 */
	$art_lms_should_exclude = static function ( $art_lms_relative_path ) use ( $art_lms_exclude_dirs, $art_lms_exclude_file_patterns ) {
		$art_lms_relative_path = str_replace( '\\', '/', $art_lms_relative_path );
		$art_lms_parts         = explode( '/', $art_lms_relative_path );

		foreach ( $art_lms_parts as $art_lms_part ) {
			if ( in_array( $art_lms_part, $art_lms_exclude_dirs, true ) ) {
				return true;
			}
		}

		$art_lms_basename = basename( $art_lms_relative_path );
		foreach ( $art_lms_exclude_file_patterns as $art_lms_pattern ) {
			if ( fnmatch( $art_lms_pattern, $art_lms_basename ) ) {
				return true;
			}
		}

		return false;
	};

	$art_lms_zip    = new ZipArchive();
	$art_lms_opened = $art_lms_zip->open( $art_lms_output, ZipArchive::OVERWRITE | ZipArchive::CREATE );

	if ( true !== $art_lms_opened ) {
		art_lms_build_release_stderr( 'Cannot create zip: ' . $art_lms_output . "\n" );
		return 1;
	}

	$art_lms_iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $art_lms_plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS )
	);

	foreach ( $art_lms_iterator as $art_lms_file_info ) {
		/**
		 * SplFileInfo instance for the current archive entry.
		 *
		 * @var SplFileInfo $art_lms_file_info
		 */
		$art_lms_absolute_path   = $art_lms_file_info->getPathname();
		$art_lms_relative_path   = substr( $art_lms_absolute_path, strlen( $art_lms_plugin_dir ) + 1 );

		if ( $art_lms_should_exclude( $art_lms_relative_path ) ) {
			continue;
		}

		$art_lms_zip_path = $art_lms_slug . '/' . str_replace( '\\', '/', $art_lms_relative_path );

		if ( $art_lms_file_info->isDir() ) {
			$art_lms_zip->addEmptyDir( rtrim( $art_lms_zip_path, '/' ) );
			continue;
		}

		$art_lms_zip->addFile( $art_lms_absolute_path, $art_lms_zip_path );
	}

	$art_lms_zip->close();

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI outputs a local filesystem path.
	echo $art_lms_output, PHP_EOL;

	return 0;
}

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI exit code, not rendered output.
exit( art_lms_build_release( $argv ) );
