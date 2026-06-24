<?php
/**
 * Protect media files embedded in LMS materials.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Protected_Media
 */
class Art_LMS_Protected_Media {

	const QUERY_VAR = 'art_lms_media';

	const MATERIAL_ATTACHMENT_META = '_art_lms_attachment_ids';

	const ATTACHMENT_MATERIALS_META = '_art_lms_linked_material_ids';

	const REWRITE_VERSION_OPTION = 'art_lms_protected_media_rewrite_version';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 10 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 99 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'save_post_' . Art_LMS_Materials::POST_TYPE, array( __CLASS__, 'sync_material_attachments' ), 20, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_deleted_material' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_protected_media' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_protect_attachment_page' ), 1 );
		add_action( 'init', array( __CLASS__, 'maybe_block_direct_upload_request' ), 0 );
		add_filter( 'the_content', array( __CLASS__, 'filter_material_content_urls' ), 20 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest_guard_attachment' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_backfill_material_attachments' ), 20 );
	}

	/**
	 * Register rewrite rule for protected downloads.
	 */
	public static function register_rewrite() {
		add_rewrite_rule(
			'^art-lms-media/([0-9]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Flush rewrite rules when plugin version changes.
	 */
	public static function maybe_flush_rewrites() {
		if ( get_option( self::REWRITE_VERSION_OPTION ) === ART_LMS_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_VERSION_OPTION, ART_LMS_VERSION, false );
	}

	/**
	 * Register public query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Sync attachment registry when a material is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function sync_material_attachments( $post_id, $post ) {
		if ( ! $post instanceof WP_Post || Art_LMS_Materials::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$new_ids = self::collect_attachment_ids_from_content( (string) $post->post_content );
		$old_ids = get_post_meta( $post_id, self::MATERIAL_ATTACHMENT_META, true );

		if ( ! is_array( $old_ids ) ) {
			$old_ids = array();
		}

		$old_ids = array_map( 'absint', $old_ids );
		$new_ids = array_map( 'absint', $new_ids );

		foreach ( array_diff( $old_ids, $new_ids ) as $attachment_id ) {
			self::unlink_attachment_from_material( $attachment_id, $post_id );
		}

		foreach ( array_diff( $new_ids, $old_ids ) as $attachment_id ) {
			self::link_attachment_to_material( $attachment_id, $post_id );
		}

		update_post_meta( $post_id, self::MATERIAL_ATTACHMENT_META, $new_ids );
	}

	/**
	 * Remove material links when a material post is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function cleanup_deleted_material( $post_id ) {
		if ( Art_LMS_Materials::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$attachment_ids = get_post_meta( $post_id, self::MATERIAL_ATTACHMENT_META, true );

		if ( ! is_array( $attachment_ids ) ) {
			return;
		}

		foreach ( $attachment_ids as $attachment_id ) {
			self::unlink_attachment_from_material( absint( $attachment_id ), $post_id );
		}

		delete_post_meta( $post_id, self::MATERIAL_ATTACHMENT_META );
	}

	/**
	 * Backfill attachment registry for materials created before file protection.
	 */
	public static function maybe_backfill_material_attachments() {
		if ( get_option( 'art_lms_protected_media_backfilled' ) === ART_LMS_VERSION ) {
			return;
		}

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$material_ids = get_posts(
			array(
				'post_type'      => Art_LMS_Materials::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $material_ids as $material_id ) {
			$post = get_post( $material_id );

			if ( $post instanceof WP_Post ) {
				self::sync_material_attachments( (int) $material_id, $post );
			}
		}

		update_option( 'art_lms_protected_media_backfilled', ART_LMS_VERSION, false );
	}

	/**
	 * Whether an attachment is linked to LMS materials.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_protected_attachment( $attachment_id ) {
		return ! empty( self::get_linked_material_ids( $attachment_id ) );
	}

	/**
	 * Get material IDs linked to an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, int>
	 */
	public static function get_linked_material_ids( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return array();
		}

		$material_ids = get_post_meta( $attachment_id, self::ATTACHMENT_MATERIALS_META, true );

		if ( ! is_array( $material_ids ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'absint', $material_ids ) ) );
	}

	/**
	 * Check whether the current user may access a protected attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $user_id         Optional user ID.
	 * @return bool
	 */
	public static function user_can_access_attachment( $attachment_id, $user_id = 0 ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return true;
		}

		$material_ids = self::get_linked_material_ids( $attachment_id );

		if ( empty( $material_ids ) ) {
			return true;
		}

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		foreach ( $material_ids as $material_id ) {
			if ( $user_id && user_can( $user_id, 'edit_post', $material_id ) ) {
				return true;
			}

			if ( Art_LMS_Materials::user_can_view_material( $material_id, $user_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a protected download URL for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function get_download_url( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return '';
		}

		return home_url( user_trailingslashit( 'art-lms-media/' . $attachment_id ) );
	}

	/**
	 * Serve protected media through WordPress.
	 */
	public static function maybe_serve_protected_media() {
		$attachment_id = absint( get_query_var( self::QUERY_VAR ) );

		if ( ! $attachment_id ) {
			return;
		}

		if ( ! self::is_protected_attachment( $attachment_id ) ) {
			wp_safe_redirect( wp_get_attachment_url( $attachment_id ) );
			exit;
		}

		if ( ! self::user_can_access_attachment( $attachment_id ) ) {
			$material_ids = self::get_linked_material_ids( $attachment_id );
			$return_url   = ! empty( $material_ids ) ? Art_LMS_Materials::get_url( (int) $material_ids[0] ) : '';

			wp_safe_redirect( Art_LMS_Materials::get_account_gate_url( $return_url, is_user_logged_in() ) );
			exit;
		}

		self::serve_attachment_file( $attachment_id );
	}

	/**
	 * Protect attachment permalink pages for LMS files.
	 */
	public static function maybe_protect_attachment_page() {
		if ( is_admin() || ! is_attachment() ) {
			return;
		}

		$attachment_id = get_queried_object_id();

		if ( ! self::is_protected_attachment( $attachment_id ) || self::user_can_access_attachment( $attachment_id ) ) {
			return;
		}

		wp_safe_redirect( Art_LMS_Materials::get_account_gate_url( '', is_user_logged_in() ) );
		exit;
	}

	/**
	 * Block direct /wp-content/uploads/ requests when PHP handles them.
	 */
	public static function maybe_block_direct_upload_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! empty( get_query_var( self::QUERY_VAR ) ) || is_attachment() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( '' === $request_uri || false === strpos( $request_uri, '/wp-content/uploads/' ) ) {
			return;
		}

		$attachment_id = self::resolve_attachment_id_from_url( home_url( wp_parse_url( $request_uri, PHP_URL_PATH ) ) );

		if ( ! $attachment_id || ! self::is_protected_attachment( $attachment_id ) || self::user_can_access_attachment( $attachment_id ) ) {
			return;
		}

		status_header( 403 );
		nocache_headers();
		wp_die(
			esc_html__( 'Доступ к файлу ограничен.', 'art-lms' ),
			esc_html__( 'Доступ запрещён', 'art-lms' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Replace direct upload URLs with protected download URLs in material content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_material_content_urls( $content ) {
		if ( ! is_singular( Art_LMS_Materials::POST_TYPE ) || ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$material_id = get_queried_object_id();

		if ( ! Art_LMS_Materials::user_can_view_material( $material_id ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'#https?://[^"\'\s>]+/wp-content/uploads/[^"\'\s>]+#i',
			array( __CLASS__, 'replace_upload_url_callback' ),
			$content
		);
	}

	/**
	 * Replace a single upload URL when it belongs to a protected attachment.
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @return string
	 */
	public static function replace_upload_url_callback( $matches ) {
		$url = html_entity_decode( (string) ( $matches[0] ?? '' ), ENT_QUOTES, get_bloginfo( 'charset' ) );

		if ( '' === $url ) {
			return (string) ( $matches[0] ?? '' );
		}

		$attachment_id = self::resolve_attachment_id_from_url( $url );

		if ( ! $attachment_id || ! self::is_protected_attachment( $attachment_id ) ) {
			return (string) ( $matches[0] ?? '' );
		}

		return esc_url( self::get_download_url( $attachment_id ) );
	}

	/**
	 * Block REST access to protected attachments without purchase.
	 *
	 * @param mixed           $result  Response to replace.
	 * @param WP_REST_Server  $server  REST server instance.
	 * @param WP_REST_Request $request REST request.
	 * @return mixed
	 */
	public static function rest_guard_attachment( $result, $server, $request ) {
		unset( $server );

		if ( null !== $result || ! ( $request instanceof WP_REST_Request ) ) {
			return $result;
		}

		if ( ! preg_match( '#^/wp/v2/media/(\d+)$#', (string) $request->get_route(), $matches ) ) {
			return $result;
		}

		$attachment_id = absint( $matches[1] );

		if ( ! $attachment_id || ! self::is_protected_attachment( $attachment_id ) || self::user_can_access_attachment( $attachment_id ) ) {
			return $result;
		}

		return new WP_Error(
			'art_lms_rest_forbidden',
			__( 'Доступ к файлу ограничен.', 'art-lms' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Collect attachment IDs used inside material content.
	 *
	 * @param string $content Material post content.
	 * @return array<int, int>
	 */
	public static function collect_attachment_ids_from_content( $content ) {
		$content = (string) $content;

		if ( '' === $content ) {
			return array();
		}

		$attachment_ids = array();

		if ( function_exists( 'parse_blocks' ) && has_blocks( $content ) ) {
			$attachment_ids = array_merge( $attachment_ids, self::collect_attachment_ids_from_blocks( parse_blocks( $content ) ) );
		}

		if ( preg_match_all( '/\bwp-image-(\d+)\b/', $content, $matches ) ) {
			foreach ( $matches[1] as $attachment_id ) {
				$attachment_ids[] = absint( $attachment_id );
			}
		}

		if ( preg_match_all( '#https?://[^"\'\s>]+/wp-content/uploads/[^"\'\s>]+#i', $content, $url_matches ) ) {
			foreach ( $url_matches[0] as $url ) {
				$attachment_id = self::resolve_attachment_id_from_url( html_entity_decode( $url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );

				if ( $attachment_id ) {
					$attachment_ids[] = $attachment_id;
				}
			}
		}

		$attachment_ids = array_values( array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) ) );

		return array_values(
			array_filter(
				$attachment_ids,
				static function ( $attachment_id ) {
					return 'attachment' === get_post_type( $attachment_id );
				}
			)
		);
	}

	/**
	 * Walk block tree and collect attachment IDs.
	 *
	 * @param array<int, array<string, mixed>> $blocks Block list.
	 * @return array<int, int>
	 */
	private static function collect_attachment_ids_from_blocks( array $blocks ) {
		$attachment_ids = array();

		foreach ( $blocks as $block ) {
			if ( ! empty( $block['attrs']['id'] ) ) {
				$attachment_ids[] = absint( $block['attrs']['id'] );
			}

			if ( ! empty( $block['attrs']['ids'] ) && is_array( $block['attrs']['ids'] ) ) {
				foreach ( $block['attrs']['ids'] as $attachment_id ) {
					$attachment_ids[] = absint( $attachment_id );
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$attachment_ids = array_merge( $attachment_ids, self::collect_attachment_ids_from_blocks( $block['innerBlocks'] ) );
			}
		}

		return $attachment_ids;
	}

	/**
	 * Resolve attachment ID from a file URL, including resized variants.
	 *
	 * @param string $url File URL.
	 * @return int
	 */
	private static function resolve_attachment_id_from_url( $url ) {
		$url = esc_url_raw( (string) $url );

		if ( '' === $url ) {
			return 0;
		}

		$attachment_id = attachment_url_to_postid( $url );

		if ( $attachment_id ) {
			return (int) $attachment_id;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return 0;
		}

		$file          = wp_basename( $path );
		$original_file = preg_replace( '/-\d+x\d+(?=\.[^.]+$)/', '', $file );

		if ( ! is_string( $original_file ) || $original_file === $file ) {
			return 0;
		}

		$original_url = home_url( trailingslashit( dirname( $path ) ) . $original_file );

		return (int) attachment_url_to_postid( $original_url );
	}

	/**
	 * Link attachment to a material.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $material_id   Material ID.
	 */
	private static function link_attachment_to_material( $attachment_id, $material_id ) {
		$attachment_id = absint( $attachment_id );
		$material_id   = absint( $material_id );

		if ( ! $attachment_id || ! $material_id ) {
			return;
		}

		$material_ids = self::get_linked_material_ids( $attachment_id );

		if ( in_array( $material_id, $material_ids, true ) ) {
			return;
		}

		$material_ids[] = $material_id;
		update_post_meta( $attachment_id, self::ATTACHMENT_MATERIALS_META, $material_ids );
	}

	/**
	 * Unlink attachment from a material.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $material_id   Material ID.
	 */
	private static function unlink_attachment_from_material( $attachment_id, $material_id ) {
		$attachment_id = absint( $attachment_id );
		$material_id   = absint( $material_id );

		if ( ! $attachment_id || ! $material_id ) {
			return;
		}

		$material_ids = array_values(
			array_diff(
				self::get_linked_material_ids( $attachment_id ),
				array( $material_id )
			)
		);

		if ( empty( $material_ids ) ) {
			delete_post_meta( $attachment_id, self::ATTACHMENT_MATERIALS_META );
			return;
		}

		update_post_meta( $attachment_id, self::ATTACHMENT_MATERIALS_META, $material_ids );
	}

	/**
	 * Stream an attachment file to the browser.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private static function serve_attachment_file( $attachment_id ) {
		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_readable( $path ) ) {
			wp_die(
				esc_html__( 'Файл не найден.', 'art-lms' ),
				esc_html__( 'Ошибка', 'art-lms' ),
				array( 'response' => 404 )
			);
		}

		$mime = get_post_mime_type( $attachment_id );

		nocache_headers();

		if ( $mime ) {
			header( 'Content-Type: ' . $mime );
		}

		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( wp_basename( $path ) ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Binary file streaming.
		readfile( $path );
		exit;
	}
}
