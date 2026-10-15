<?php
/**
 * Mini-course lessons inside a single material.
 *
 * @package Art_LMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_LMS_Material_Lessons
 */
class Art_LMS_Material_Lessons {

	const BLOCK_NAME = 'art-lms/lesson';

	/**
	 * Whether post content contains lesson markers.
	 *
	 * @param int|WP_Post|null $post Post ID or object.
	 * @return bool
	 */
	public static function has_lessons( $post = null ) {
		$course = self::parse_course( $post );

		return count( $course['lessons'] ) > 0;
	}

	/**
	 * Parse material content into mini-course lessons.
	 *
	 * @param int|WP_Post|null $post Post ID or object.
	 * @return array<int, array{index:int, number:int, title:string, content:string, anchor:string}>
	 */
	public static function parse( $post = null ) {
		$course = self::parse_course( $post );

		return $course['lessons'];
	}

	/**
	 * Parse material content into intro (before first lesson) + lessons.
	 *
	 * Blocks before the first «Урок» become the intro shown above «Содержание».
	 *
	 * @param int|WP_Post|null $post Post ID or object.
	 * @return array{intro:string, lessons:array<int, array{index:int, number:int, title:string, content:string, anchor:string}>}
	 */
	public static function parse_course( $post = null ) {
		$empty = array(
			'intro'   => '',
			'lessons' => array(),
		);

		$post = get_post( $post );

		if ( ! $post instanceof WP_Post || Art_LMS_Materials::POST_TYPE !== $post->post_type ) {
			return $empty;
		}

		if ( ! has_blocks( $post->post_content ) ) {
			return $empty;
		}

		$blocks      = parse_blocks( $post->post_content );
		$intro_blocks = array();
		$lessons      = array();
		$current      = null;

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$block_name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

			if ( self::BLOCK_NAME === $block_name ) {
				if ( null !== $current ) {
					$lessons[] = $current;
				}

				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				$title = isset( $attrs['title'] ) ? sanitize_text_field( (string) $attrs['title'] ) : '';

				$current = array(
					'title'  => $title,
					'blocks' => array(),
				);
				continue;
			}

			// Skip empty freeform/null blocks.
			if ( null === $block['blockName'] && '' === trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
				continue;
			}

			if ( null === $current ) {
				$intro_blocks[] = $block;
				continue;
			}

			$current['blocks'][] = $block;
		}

		if ( null !== $current ) {
			$lessons[] = $current;
		}

		if ( array() === $lessons ) {
			return $empty;
		}

		$parsed = array();

		foreach ( $lessons as $index => $lesson ) {
			$number = $index + 1;
			$title  = trim( (string) $lesson['title'] );

			if ( '' === $title ) {
				$title = sprintf(
					/* translators: %d: lesson number */
					__( 'Урок %d', 'art-lms' ),
					$number
				);
			}

			$content = self::render_blocks( $lesson['blocks'] );

			$parsed[] = array(
				'index'   => $index,
				'number'  => $number,
				'title'   => $title,
				'content' => $content,
				'anchor'  => 'lesson-' . $number,
			);
		}

		return array(
			'intro'   => self::render_blocks( $intro_blocks ),
			'lessons' => $parsed,
		);
	}

	/**
	 * Render a list of parsed blocks to HTML.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @return string
	 */
	private static function render_blocks( array $blocks ) {
		$html = '';

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$html .= render_block( $block );
		}

		// Ensure oEmbed URLs (Vimeo/YouTube) become iframes when rendered outside the_content.
		global $wp_embed;

		if ( $wp_embed instanceof WP_Embed ) {
			$html = $wp_embed->autoembed( $html );
		}

		return $html;
	}

	/**
	 * Render mini-course shell HTML.
	 *
	 * @param array<int, array{index:int, number:int, title:string, content:string, anchor:string}> $lessons Lessons.
	 * @param string                                                                                 $intro   Optional HTML before TOC.
	 * @return string
	 */
	public static function render( array $lessons, $intro = '' ) {
		if ( array() === $lessons ) {
			return '';
		}

		$total = count( $lessons );
		$intro = is_string( $intro ) ? trim( $intro ) : '';

		ob_start();
		?>
		<div
			class="art-lms-course"
			data-art-lms-course="1"
			data-view="toc"
			data-lesson="0"
			data-total="<?php echo esc_attr( (string) $total ); ?>"
		>
			<section class="art-lms-course__screen art-lms-course__toc" data-screen="toc">
				<?php if ( '' !== $intro ) : ?>
					<div class="art-lms-course__intro">
						<?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block HTML from render_block(). ?>
					</div>
				<?php endif; ?>
				<h2 class="art-lms-course__toc-title"><?php esc_html_e( 'Содержание', 'art-lms' ); ?></h2>
				<div class="art-lms-course__toc-list" role="list">
					<?php foreach ( $lessons as $lesson ) : ?>
						<div class="art-lms-course__toc-item" role="listitem">
							<button
								type="button"
								class="art-lms-course__toc-link"
								data-art-lms-open-lesson="<?php echo esc_attr( (string) $lesson['index'] ); ?>"
							>
								<span class="art-lms-course__toc-number" aria-hidden="true"><?php echo esc_html( (string) $lesson['number'] ); ?></span>
								<span class="art-lms-course__toc-label"><?php echo esc_html( $lesson['title'] ); ?></span>
							</button>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<?php foreach ( $lessons as $lesson ) : ?>
				<section
					class="art-lms-course__screen art-lms-course__lesson"
					data-screen="lesson"
					data-lesson-index="<?php echo esc_attr( (string) $lesson['index'] ); ?>"
					id="<?php echo esc_attr( $lesson['anchor'] ); ?>"
					hidden
				>
					<?php echo self::render_nav( $lesson['index'], $total, 'top' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with escaping inside helper. ?>

					<header class="art-lms-course__lesson-header">
						<p class="art-lms-course__lesson-meta">
							<?php
							printf(
								/* translators: 1: current lesson number, 2: total lessons */
								esc_html__( 'Урок %1$d из %2$d', 'art-lms' ),
								(int) $lesson['number'],
								(int) $total
							);
							?>
						</p>
						<h2 class="art-lms-course__lesson-title"><?php echo esc_html( $lesson['title'] ); ?></h2>
					</header>

					<div class="art-lms-course__lesson-content">
						<?php echo $lesson['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block HTML from render_block(). ?>
					</div>

					<?php echo self::render_nav( $lesson['index'], $total, 'bottom' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with escaping inside helper. ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render lesson navigation bar.
	 *
	 * @param int    $index Lesson index (0-based).
	 * @param int    $total Total lessons.
	 * @param string $place top|bottom.
	 * @return string
	 */
	private static function render_nav( $index, $total, $place ) {
		$index = (int) $index;
		$total = (int) $total;
		$place = sanitize_key( $place );

		ob_start();
		?>
		<nav
			class="art-lms-course__nav art-lms-course__nav--<?php echo esc_attr( $place ); ?>"
			aria-label="<?php esc_attr_e( 'Навигация по урокам', 'art-lms' ); ?>"
		>
			<button type="button" class="art-lms-course__nav-btn art-lms-course__nav-prev" data-art-lms-course-action="prev"><?php esc_html_e( 'Назад', 'art-lms' ); ?></button>
			<button type="button" class="art-lms-course__nav-btn art-lms-course__nav-toc" data-art-lms-course-action="toc"><?php esc_html_e( 'Навигация', 'art-lms' ); ?></button>
			<button type="button" class="art-lms-course__nav-btn art-lms-course__nav-next" data-art-lms-course-action="next" <?php disabled( $index >= $total - 1 ); ?>><?php esc_html_e( 'Вперёд', 'art-lms' ); ?></button>
		</nav>
		<?php
		return (string) ob_get_clean();
	}
}
