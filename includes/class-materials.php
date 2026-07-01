<?php
/**
 * Materials custom post type (protected content).
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public LMS URLs use shareable GET parameters (checkout, account, payment status).

/**
 * Class Art_LMS_Materials
 */
class Art_LMS_Materials {

	const POST_TYPE = 'art_lms_material';

	const QUERY_RETURN        = 'art_lms_return';
	const QUERY_ACCESS_DENIED = 'art_lms_access_denied';

	/**
	 * Whether the material page header was already prepended to the content.
	 *
	 * @var bool
	 */
	private static $material_page_header_prepended = false;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 99 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'force_latin_post_slug' ), 10, 2 );
		add_filter( 'wp_unique_post_slug', array( __CLASS__, 'filter_unique_material_slug' ), 10, 6 );
		add_filter( 'rest_pre_insert_' . self::POST_TYPE, array( __CLASS__, 'rest_force_latin_slug' ), 10, 2 );
		add_filter( 'rest_pre_update_' . self::POST_TYPE, array( __CLASS__, 'rest_force_latin_slug' ), 10, 2 );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'ensure_saved_material_latin_slug' ), 100, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_block_material_feeds' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_protect_single_material' ), 9 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_from_account_gate' ), 10 );
		add_filter( 'the_content_feed', array( __CLASS__, 'filter_material_feed_content' ), 10, 1 );
		add_filter( 'the_excerpt_rss', array( __CLASS__, 'filter_material_feed_content' ), 10, 1 );
		add_action( 'wp', array( __CLASS__, 'setup_single_material_page' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_single_material_assets' ) );
		add_filter( 'body_class', array( __CLASS__, 'filter_material_body_class' ) );
		add_filter( 'render_block', array( __CLASS__, 'filter_single_material_blocks' ), 10, 2 );
		add_filter( 'the_content', array( __CLASS__, 'prepend_material_page_header' ), 1 );
		add_filter( 'hello_elementor_page_title', array( __CLASS__, 'filter_hello_elementor_page_title' ), 20 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest_guard_material' ), 10, 3 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'exclude_from_sitemaps' ) );
	}

	/**
	 * Flush rewrite rules after slug changes.
	 */
	public static function maybe_flush_rewrites() {
		if ( get_option( 'art_lms_materials_rewrite_version' ) === ART_LMS_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'art_lms_materials_rewrite_version', ART_LMS_VERSION );
	}

	/**
	 * Force Latin slugs for materials on save.
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Raw post data.
	 * @return array
	 */
	public static function force_latin_post_slug( $data, $postarr ) {
		if ( empty( $data['post_type'] ) || self::POST_TYPE !== $data['post_type'] ) {
			return $data;
		}

		if ( ! empty( $postarr['post_type'] ) && 'revision' === $postarr['post_type'] ) {
			return $data;
		}

		if ( 'inherit' === ( $data['post_status'] ?? '' ) ) {
			return $data;
		}

		$slug = self::build_latin_slug_from_post_data( $data, $postarr );

		if ( '' !== $slug ) {
			$data['post_name'] = $slug;
		}

		return $data;
	}

	/**
	 * Transliterate material slug during WordPress uniqueness checks.
	 *
	 * @param string $slug         Candidate slug.
	 * @param int    $post_id      Post ID.
	 * @param string $post_status  Post status.
	 * @param string $post_type    Post type.
	 * @param int    $post_parent  Post parent ID.
	 * @param string $original_slug Original slug.
	 * @return string
	 */
	public static function filter_unique_material_slug( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
		unset( $post_id, $post_status, $post_parent, $original_slug );

		if ( self::POST_TYPE !== $post_type ) {
			return $slug;
		}

		$latin = Art_LMS_Transliteration::to_slug( (string) $slug );

		return $latin ?: $slug;
	}

	/**
	 * Force Latin slug for REST create/update requests.
	 *
	 * @param stdClass|WP_Post $prepared_post Prepared post object.
	 * @param WP_REST_Request  $request       REST request.
	 * @return stdClass|WP_Post
	 */
	public static function rest_force_latin_slug( $prepared_post, $request ) {
		if ( ! is_object( $prepared_post ) ) {
			return $prepared_post;
		}

		$data = array(
			'post_name'  => isset( $prepared_post->post_name ) ? (string) $prepared_post->post_name : '',
			'post_title' => isset( $prepared_post->post_title ) ? (string) $prepared_post->post_title : '',
		);

		$postarr = $data;

		if ( $request instanceof WP_REST_Request && null !== $request->get_param( 'slug' ) ) {
			$postarr['slug'] = (string) $request->get_param( 'slug' );
		}

		$slug = self::build_latin_slug_from_post_data( $data, $postarr );

		if ( '' !== $slug ) {
			$prepared_post->post_name = $slug;
		}

		return $prepared_post;
	}

	/**
	 * Fix material slug after save if Gutenberg/WordPress bypassed earlier hooks.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function ensure_saved_material_latin_slug( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'inherit' ), true ) ) {
			return;
		}

		if ( Art_LMS_Transliteration::needs_latin_slug_fix( $post->post_name ) ) {
			self::update_material_slug( (int) $post_id );
		}
	}

	/**
	 * Resolve source text for material slug generation.
	 *
	 * @param string $post_name  Current slug.
	 * @param string $post_title Post title.
	 * @return string
	 */
	private static function resolve_slug_source( $post_name, $post_title ) {
		$post_name  = (string) $post_name;
		$post_title = (string) $post_title;

		if ( '' !== trim( $post_title ) && Art_LMS_Transliteration::needs_latin_slug_fix( $post_name ) ) {
			return Art_LMS_Transliteration::normalize_slug_source( $post_title );
		}

		$source = '' !== trim( $post_name ) ? $post_name : $post_title;

		return Art_LMS_Transliteration::normalize_slug_source( $source );
	}

	/**
	 * Build Latin slug from incoming post data.
	 *
	 * @param array $data    Sanitized post data.
	 * @param array $postarr Raw post data.
	 * @return string
	 */
	private static function build_latin_slug_from_post_data( $data, $postarr ) {
		$name  = isset( $data['post_name'] ) ? (string) $data['post_name'] : '';
		$title = isset( $data['post_title'] ) ? (string) $data['post_title'] : '';

		if ( '' === trim( $name ) && isset( $postarr['slug'] ) ) {
			$name = (string) $postarr['slug'];
		}

		return Art_LMS_Transliteration::to_slug( self::resolve_slug_source( $name, $title ) );
	}

	/**
	 * Update a material slug to Latin if needed.
	 *
	 * @param int $post_id Material post ID.
	 */
	private static function update_material_slug( $post_id ) {
		static $updating = array();

		if ( ! empty( $updating[ $post_id ] ) ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'inherit' ), true ) ) {
			return;
		}

		$source = self::resolve_slug_source( (string) $post->post_name, (string) $post->post_title );
		$slug   = Art_LMS_Transliteration::to_slug( $source );

		if ( '' === $slug || $slug === $post->post_name ) {
			return;
		}

		$unique = wp_unique_post_slug(
			$slug,
			$post_id,
			$post->post_status,
			$post->post_type,
			(int) $post->post_parent
		);

		$updating[ $post_id ] = true;

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Core posts table slug update for material permalinks.
		$wpdb->update(
			$wpdb->posts,
			array( 'post_name' => $unique ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);

		clean_post_cache( $post_id );
		unset( $updating[ $post_id ] );
	}

	/**
	 * Register materials post type.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Материалы', 'art-lms' ),
			'singular_name'      => __( 'Материал', 'art-lms' ),
			'add_new'            => __( 'Добавить', 'art-lms' ),
			'add_new_item'       => __( 'Добавить материал', 'art-lms' ),
			'edit_item'          => __( 'Редактировать материал', 'art-lms' ),
			'new_item'           => __( 'Новый материал', 'art-lms' ),
			'view_item'          => __( 'Просмотреть материал', 'art-lms' ),
			'search_items'       => __( 'Искать материалы', 'art-lms' ),
			'not_found'          => __( 'Материалы не найдены', 'art-lms' ),
			'not_found_in_trash' => __( 'В корзине материалов нет', 'art-lms' ),
			'menu_name'          => __( 'Материалы', 'art-lms' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'query_var'           => true,
				'rewrite'             => array( 'slug' => 'materials' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'              => 'manage_options',
					'read_post'              => 'manage_options',
					'delete_post'            => 'manage_options',
					'edit_posts'             => 'manage_options',
					'edit_others_posts'      => 'manage_options',
					'publish_posts'          => 'manage_options',
					'read_private_posts'     => 'manage_options',
					'delete_posts'           => 'manage_options',
					'delete_private_posts'   => 'manage_options',
					'delete_published_posts' => 'manage_options',
					'delete_others_posts'    => 'manage_options',
					'edit_private_posts'     => 'manage_options',
					'edit_published_posts'   => 'manage_options',
					'create_posts'           => 'manage_options',
				),
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'show_in_rest'        => true,
				'template'            => array(
					array(
						'core/paragraph',
						array(
							'placeholder' => __( 'Содержимое материала — доступно покупателям после оплаты…', 'art-lms' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Get material permalink.
	 *
	 * @param int $material_id Material ID.
	 * @return string
	 */
	public static function get_url( $material_id ) {
		$url = get_permalink( $material_id );

		return $url ? (string) $url : '';
	}

	/**
	 * Sanitize return URL for redirects.
	 *
	 * @param string $url Return URL.
	 * @return string
	 */
	public static function sanitize_return_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		$url = wp_sanitize_redirect( $url );

		return wp_validate_redirect( $url, '' );
	}

	/**
	 * Read return URL from the current request.
	 *
	 * @return string
	 */
	public static function get_requested_return_url() {
		if ( empty( $_GET[ self::QUERY_RETURN ] ) ) {
			return '';
		}

		$raw = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_RETURN ] ) );

		return self::sanitize_return_url( $raw );
	}

	/**
	 * Whether the current request indicates missing material access.
	 *
	 * @return bool
	 */
	public static function is_access_denied_request() {
		return ! empty( $_GET[ self::QUERY_ACCESS_DENIED ] );
	}

	/**
	 * Build account gate URL with optional return target.
	 *
	 * @param string $return_url    URL to open after login.
	 * @param bool   $access_denied Whether user is logged in but has no access.
	 * @return string
	 */
	public static function get_account_gate_url( $return_url = '', $access_denied = false ) {
		$return_url = self::sanitize_return_url( $return_url );

		if ( ! $access_denied ) {
			$url = $return_url ? Art_LMS_Settings::get_login_page_url( $return_url ) : Art_LMS_Settings::get_login_page_url();
		} else {
			$account_url = Art_LMS_Settings::get_account_url();
			$args        = array();

			if ( $return_url ) {
				$args[ self::QUERY_RETURN ] = $return_url;
			}

			$args[ self::QUERY_ACCESS_DENIED ] = '1';

			$url = ! empty( $args ) ? add_query_arg( $args, $account_url ) : $account_url;
		}

		/**
		 * Filter redirect URL for protected materials.
		 *
		 * @param string $url           Redirect URL.
		 * @param string $return_url    Material URL to return to.
		 * @param bool   $access_denied Whether access was denied for a logged-in user.
		 */
		return (string) apply_filters( 'art_lms_material_account_gate_url', $url, $return_url, $access_denied );
	}

	/**
	 * Check whether the user may view material on the frontend.
	 *
	 * @param int $material_id Material ID.
	 * @param int $user_id     Optional user ID.
	 * @return bool
	 */
	public static function user_can_view_material( $material_id, $user_id = 0 ) {
		$material_id = absint( $material_id );

		if ( ! $material_id ) {
			return false;
		}

		$post = get_post( $material_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		if ( ! $user_id ) {
			return false;
		}

		return Art_LMS_Access::user_has_access( $user_id, $material_id );
	}

	/**
	 * Exclude paid materials from WordPress core sitemaps.
	 *
	 * @param array<string, WP_Post_Type> $post_types Post types included in sitemaps.
	 * @return array<string, WP_Post_Type>
	 */
	public static function exclude_from_sitemaps( $post_types ) {
		unset( $post_types[ self::POST_TYPE ] );

		return $post_types;
	}

	/**
	 * Block RSS/Atom feeds for paid materials.
	 */
	public static function maybe_block_material_feeds() {
		if ( ! is_feed() || ! self::is_material_feed_request() ) {
			return;
		}

		wp_die(
			esc_html__( 'Доступ к материалам ограничен.', 'art-lms' ),
			esc_html__( 'Доступ запрещён', 'art-lms' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Strip feed content for materials unless the viewer has access.
	 *
	 * @param string $content Feed content.
	 * @return string
	 */
	public static function filter_material_feed_content( $content ) {
		if ( ! is_feed() || self::POST_TYPE !== get_post_type() ) {
			return $content;
		}

		$material_id = get_the_ID();

		if ( $material_id && self::user_can_view_material( $material_id ) ) {
			return $content;
		}

		return '';
	}

	/**
	 * Whether the current feed request exposes LMS materials.
	 *
	 * @return bool
	 */
	private static function is_material_feed_request() {
		global $wp_query;

		if ( ! $wp_query instanceof WP_Query ) {
			return false;
		}

		$post_type = $wp_query->get( 'post_type' );

		if ( self::POST_TYPE === $post_type ) {
			return true;
		}

		if ( is_array( $post_type ) && in_array( self::POST_TYPE, $post_type, true ) ) {
			return true;
		}

		if ( is_singular( self::POST_TYPE ) ) {
			return true;
		}

		$queried = get_queried_object();

		return $queried instanceof WP_Post && self::POST_TYPE === $queried->post_type;
	}

	/**
	 * Redirect guests and users without access away from material pages.
	 */
	public static function maybe_protect_single_material() {
		if ( is_admin() || ! is_singular( self::POST_TYPE ) ) {
			return;
		}

		$material_id = get_queried_object_id();

		if ( self::user_can_view_material( $material_id ) ) {
			return;
		}

		$material_url  = self::get_url( $material_id );
		$access_denied = is_user_logged_in();

		wp_safe_redirect( self::get_account_gate_url( $material_url, $access_denied ) );
		exit;
	}

	/**
	 * Send logged-in buyers back to the material after account gate login.
	 */
	public static function maybe_redirect_from_account_gate() {
		if ( is_admin() || ! is_user_logged_in() || ! Art_LMS_Settings::is_account_page() ) {
			return;
		}

		$return_url = self::get_requested_return_url();

		if ( ! $return_url || self::is_access_denied_request() ) {
			return;
		}

		$material_id = url_to_postid( $return_url );

		if ( ! $material_id || self::POST_TYPE !== get_post_type( $material_id ) ) {
			return;
		}

		if ( ! self::user_can_view_material( $material_id ) ) {
			return;
		}

		wp_safe_redirect( $return_url );
		exit;
	}

	/**
	 * Configure single material pages on the frontend.
	 */
	public static function setup_single_material_page() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}

		add_filter( 'previous_post_link', '__return_empty_string' );
		add_filter( 'next_post_link', '__return_empty_string' );
	}

	/**
	 * Add a body class for material-specific frontend styles.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function filter_material_body_class( $classes ) {
		if ( is_singular( self::POST_TYPE ) ) {
			$classes[] = 'art-lms-material-single';
		}

		return $classes;
	}

	/**
	 * Enqueue styles on material pages.
	 */
	public static function enqueue_single_material_assets() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}

		wp_enqueue_style( 'art-lms-public' );
	}

	/**
	 * Block names hidden on material pages.
	 *
	 * @return array<int, string>
	 */
	private static function get_hidden_material_blocks() {
		return array(
			'core/post-meta',
			'core/post-author',
			'core/post-author-name',
			'core/post-author-biography',
			'core/post-date',
			'core/post-terms',
			'core/post-time-to-read',
			'core/post-comments-count',
			'core/post-navigation-link',
			'core/post-navigation',
			'core/comments',
			'core/post-comments-form',
			'core/latest-comments',
		);
	}

	/**
	 * Whether a block should be hidden on material pages.
	 *
	 * @param string $block_name Block name.
	 * @return bool
	 */
	private static function is_hidden_material_block( $block_name ) {
		if ( in_array( $block_name, self::get_hidden_material_blocks(), true ) ) {
			return true;
		}

		$prefixes = array(
			'core/post-author',
			'core/post-date',
			'core/post-terms',
		);

		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $block_name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove metadata and navigation blocks from material templates.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string
	 */
	public static function filter_single_material_blocks( $block_content, $block ) {
		if ( ! is_singular( self::POST_TYPE ) || empty( $block['blockName'] ) ) {
			return $block_content;
		}

		if ( self::is_hidden_material_block( $block['blockName'] ) ) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Hide the theme page title on material pages — we render our own header above content.
	 *
	 * @param bool $show Whether Hello Elementor should render the page title.
	 * @return bool
	 */
	public static function filter_hello_elementor_page_title( $show ) {
		if ( is_singular( self::POST_TYPE ) ) {
			return false;
		}

		return $show;
	}

	/**
	 * Prepend material title and back link above the material content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function prepend_material_page_header( $content ) {
		if ( ! is_singular( self::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( self::$material_page_header_prepended ) {
			return $content;
		}

		self::$material_page_header_prepended = true;

		$header  = '<div class="art-lms-material-page-header">';
		$back    = self::get_back_to_account_link_html();
		$title   = get_the_title();
		$title   = is_string( $title ) ? $title : '';

		if ( '' !== $title ) {
			$header .= '<h1 class="art-lms-material-page-header__title entry-title">';
			$header .= esc_html( $title );
			$header .= '</h1>';
		}

		if ( '' !== $back ) {
			$header .= $back;
		}

		$header .= '</div>';

		return $header . $content;
	}

	/**
	 * Build HTML for the back-to-account link below the material title.
	 *
	 * @return string
	 */
	public static function get_back_to_account_link_html() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return '';
		}

		if ( ! Art_LMS_Settings::get_account_page_id() ) {
			return '';
		}

		$account_url = Art_LMS_Settings::get_account_url();

		if ( '' === $account_url ) {
			return '';
		}

		ob_start();
		?>
		<div class="art-lms-material-back">
			<a class="art-lms-material-back__link" href="<?php echo esc_url( $account_url ); ?>">
				<span class="art-lms-material-back__icon" aria-hidden="true">&larr;</span>
				<?php esc_html_e( 'Вернуться в личный кабинет', 'art-lms' ); ?>
			</a>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Block REST access to materials without purchase.
	 *
	 * @param mixed           $result  Response to replace.
	 * @param WP_REST_Server  $server  REST server instance.
	 * @param WP_REST_Request $request Request object.
	 * @return mixed
	 */
	public static function rest_guard_material( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}

		if ( ! $request instanceof WP_REST_Request ) {
			return $result;
		}

		$route = (string) $request->get_route();
		$base  = '/wp/v2/' . self::POST_TYPE;

		if ( 0 !== strpos( $route, $base ) ) {
			return $result;
		}

		if ( $route === $base && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Доступ к материалам ограничен.', 'art-lms' ),
				array( 'status' => 403 )
			);
		}

		if ( preg_match( '#^' . preg_quote( $base, '#' ) . '/(?P<id>\d+)(?:/|$)#', $route, $matches ) ) {
			$material_id = absint( $matches['id'] );

			if ( ! self::user_can_view_material( $material_id ) ) {
				return new WP_Error(
					'rest_forbidden',
					__( 'Доступ к материалу ограничен.', 'art-lms' ),
					array( 'status' => 403 )
				);
			}
		}

		return $result;
	}
}
